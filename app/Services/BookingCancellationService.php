<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\VendorProduct;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

// Cancels a booking on the PLATFORM's behalf (admin ban or admin dispute
// resolution) and settles the money fairly. Because the customer did nothing
// wrong here, they are always due a full (100%) refund — unlike a customer
// self-cancellation, which follows the timed deposit tiers in BookingController.
//
// One place for this logic so ban + dispute handling can never drift apart.
class BookingCancellationService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    // Returns a small summary. Safe to call on any booking — a booking that is
    // already finished (completed/cancelled/declined) is left untouched.
    public function cancelByPlatform(Booking $booking, string $reason, bool $notify = true): array
    {
        // Idempotency guard: only in-flight bookings can be cancelled.
        if (! in_array($booking->status, ['awaiting_payment', 'pending', 'approved'], true)) {
            return ['cancelled' => false, 'skipped' => true, 'status' => $booking->status];
        }

        $wasStatus     = $booking->status;
        $refundPercent = $wasStatus === 'awaiting_payment' ? 0 : 100;

        // The customer is owed back everything they paid (full refund). Recorded
        // on the booking so admins have a "refunds due" list to pay out manually.
        $paid         = (float) Payment::where('booking_id', $booking->id)
            ->where('status', 'verified')->value('amount_paid');
        $refundAmount = $refundPercent > 0 ? round($paid, 2) : 0.0;

        DB::transaction(function () use ($booking, $wasStatus, $refundAmount) {
            // Only an APPROVED booking has moved money/stock: the vendor was
            // credited and one unit of stock was taken. Reverse both.
            if ($wasStatus === 'approved') {
                $this->reverseVendorCredit($booking);
                $this->restoreStock($booking);
            }
            // awaiting_payment → nothing paid. pending → paid but the vendor was
            // never credited (credit happens on approve), so the platform still
            // holds the money; the refund is handled off-platform (payout API).

            $booking->update([
                'status'        => 'cancelled',
                'refund_amount' => $refundAmount > 0 ? $refundAmount : null,
            ]);
        });

        if ($notify) {
            $this->notifications->notifyUser(
                $booking->user,
                'Booking cancelled',
                $refundPercent > 0
                    ? 'Your booking was cancelled by the platform. A full refund is due to you.'
                    : 'Your booking was cancelled by the platform.',
                ['booking_id' => (string) $booking->id, 'reason' => $reason]
            );
        }

        return [
            'cancelled'      => true,
            'from_status'    => $wasStatus,
            'refund_percent' => $refundPercent,
            'refund_amount'  => $refundAmount,
            'booking_id'     => $booking->id,
        ];
    }

    // Net the vendor's credit for this booking fully back out. The refund row
    // shares the booking_id so it cancels the credit in the wallet ledger.
    private function reverseVendorCredit(Booking $booking): void
    {
        $credit = WalletTransaction::where('booking_id', $booking->id)
            ->where('type', 'credit')
            ->first();

        if (! $credit) {
            return;
        }

        // Account for any refund already applied to this booking, so we never
        // reverse more than what's still credited.
        $alreadyRefunded = (float) WalletTransaction::where('booking_id', $booking->id)
            ->where('type', 'refund')
            ->sum('amount'); // <= 0

        $remaining = (float) $credit->amount + $alreadyRefunded;

        if ($remaining > 0) {
            WalletTransaction::create([
                'vendor_id'  => $booking->vendor_id,
                'booking_id' => $booking->id,
                'type'       => 'refund',
                'amount'     => -1 * round($remaining, 2),
            ]);
        }
    }

    // Give one unit of stock back (mirror of BookingController::decrementStock).
    // No-op for untracked products (stock = null, e.g. appointment services).
    private function restoreStock(Booking $booking): void
    {
        $product = $booking->product;

        if (! $product || $product->stock === null) {
            return;
        }

        VendorProduct::where('id', $product->id)->increment('stock');

        VendorProduct::where('id', $product->id)
            ->where('stock', '>', 0)
            ->update(['is_available' => true]);
    }
}
