<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\ShamCashService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'booking_id'     => 'required|exists:bookings,id',
            'transaction_id' => 'required|string',
        ]);

        $user    = $request->user();
        // Pay-first: only an unpaid draft can be paid for.
        $booking = Booking::where('id', $request->booking_id)
            ->where('user_id', $user->id)
            ->where('status', 'awaiting_payment')
            ->firstOrFail();

        if (Payment::where('booking_id', $booking->id)
            ->where('status', 'verified')
            ->exists()
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.booking_already_paid'),
            ], 409);
        }

        // TEST bypass: transaction_id "0000" skips ShamCash verification and can
        // be reused across bookings, so we can test the full pay flow without a
        // real transfer. Remove before production.
        $isTest = $request->transaction_id === '0000';

        // prevent reuse of the same transaction (the test id is exempt)
        if (!$isTest && Payment::where('transaction_id', $request->transaction_id)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.transaction_used'),
            ], 409);
        }

        $product = $booking->product;
        $vendor  = $booking->vendor;

        // Two amounts matter for offers:
        //   $expectedAmount = what the CUSTOMER pays (discounted, if on offer)
        //   $originalAmount = the SAME calc on the ORIGINAL price (no discount)
        // Haflati's commission is always 15% of $originalAmount — the vendor
        // carries the whole discount, the platform never loses commission.
        if ($vendor->booking_style === 'appointment') {
            // Appointment: deposit % of the package price. discounted_price already
            // reflects a live offer (falls back to price when there's none).
            $depositFactor   = $product->deposit_percent / 100;
            $expectedAmount  = round((float) $product->discounted_price * $depositFactor, 2);
            $originalAmount  = round((float) $product->price * $depositFactor, 2);
        } else {
            // Order: full cart total from the price snapshots taken at booking time.
            // unit_price = the DISCOUNTED price the customer was charged; the item
            // also stored the original so commission bills the pre-discount total.
            $expectedAmount = round($booking->items->sum(fn ($item) => $item->unit_price * $item->quantity), 2);
            $originalAmount = round($booking->items->sum(fn ($item) => ($item->original_unit_price ?? $item->unit_price) * $item->quantity), 2);
        }

        if ($isTest) {
            $result = ['verified' => true, 'sender_name' => 'TEST'];
        } else {
            $shamCash = new ShamCashService();
            $result   = $shamCash->verifyTransaction(
                $request->transaction_id,
                $expectedAmount
            );
        }

        if (!$result['verified']) {
            return response()->json([
                'status'  => 'error',
                'message' => match ($result['reason']) {
                    'NOT_FOUND'          => 'Transaction not found',
                    'AMOUNT_MISMATCH'    => 'Amount does not match. Expected: ' . $expectedAmount,
                    'CURRENCY_MISMATCH'  => 'Wrong currency',
                    'TRANSACTION_EXPIRED' => 'Transaction is too old',
                    default              => 'Payment verification failed',
                },
            ], 422);
        }

        // Commission on the ORIGINAL amount; vendor gets whatever is left of what
        // the customer actually paid after that commission.
        $commission   = round($originalAmount * 0.15, 2);
        $vendorPayout = round($expectedAmount - $commission, 2);

        $payment = Payment::create([
            'booking_id'     => $booking->id,
            'amount_paid'    => $expectedAmount,
            'commission'     => $commission,
            'vendor_payout'  => $vendorPayout,
            'currency'       => 'SYP',
            'transaction_id' => $isTest ? '0000-' . $booking->id : $request->transaction_id,
            'sender_name'    => $result['sender_name'],
            'status'         => 'verified',
        ]);

        // payment confirmed — move from awaiting_payment to pending (vendor can now see it)
        $booking->update(['status' => 'pending']);
        $booking->refresh();

        $notification = new NotificationService();

        $notifyUser   = $notification->notifyUserTrans(
            $user,
            'messages.notif_payment_received_title',
            'messages.notif_payment_received_body'
        );

        $notifyVendor = $notification->notifyVendorTrans(
            $vendor,
            'messages.notif_new_booking_title',
            'messages.notif_new_booking_body',
            ['id' => $booking->id]
        );

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.payment_verified'),
            'booking' => $booking->load(['vendor', 'product', 'items.product']),
            'payment' => $payment,
            'debug_notifications' => [
                'user'   => $notifyUser,
                'vendor' => $notifyVendor,
            ],
        ]);
    }
}
