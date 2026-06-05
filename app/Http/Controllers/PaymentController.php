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

        // تأكد إن الحجز ما اتدفع قبل
        if (Payment::where('booking_id', $booking->id)
            ->where('status', 'verified')
            ->exists()
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This booking is already paid',
            ], 409);
        }

        // تأكد إن الـ transaction_id ما استخدم قبل
        if (Payment::where('transaction_id', $request->transaction_id)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This transaction has already been used',
            ], 409);
        }

        // احسب المبلغ المطلوب
        $product       = $booking->vendor_product;
        $vendor        = $booking->vendor;
        $expectedAmount = $vendor->booking_style === 'appointment'
            ? round($product->price * ($product->deposit_percent / 100), 2)
            : $product->price;

        // تحقق من ShamCash
        $shamCash = new ShamCashService();
        $result   = $shamCash->verifyTransaction(
            $request->transaction_id,
            $expectedAmount
        );

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

        // سجل الـ payment
        $commission   = round($expectedAmount * 0.15, 2);
        $vendorPayout = round($expectedAmount * 0.85, 2);

        $payment = Payment::create([
            'booking_id'     => $booking->id,
            'amount_paid'    => $expectedAmount,
            'commission'     => $commission,
            'vendor_payout'  => $vendorPayout,
            'currency'       => 'SYP',
            'transaction_id' => $request->transaction_id,
            'sender_name'    => $result['sender_name'],
            'status'         => 'verified',
        ]);

        // payment confirmed — move from awaiting_payment to pending (vendor can now see it)
        $booking->update(['status' => 'pending']);
        $booking->refresh();

        $notification = new NotificationService();

        $notifyUser   = $notification->notifyUser(
            $user,
            'Payment Received ✅',
            'Your payment was confirmed. Your booking is now waiting for vendor approval.'
        );

        $notifyVendor = $notification->notifyVendor(
            $vendor,
            'New Paid Booking 🔔',
            'You have a new paid booking #' . $booking->id . '. Please accept or decline.'
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment verified successfully',
            'booking' => $booking->load(['vendor', 'vendor_product']),
            'payment' => $payment,
            'debug_notifications' => [
                'user'   => $notifyUser,
                'vendor' => $notifyVendor,
            ],
        ]);
    }
}
