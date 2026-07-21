<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    // Vendor wallet — balances + transaction history
    public function show(Request $request)
    {
        $vendor = $request->user();

        // One query: the transactions with their booking (customer + product,
        // selected fields only). Balances are derived from this same result.
        $transactions = WalletTransaction::where('vendor_id', $vendor->id)
            ->with([
                'booking:id,user_id,vendor_product_id,booking_style,status',
                'booking.user:id,first_name,last_name',
                'booking.product:id,name,price',
            ])
            ->latest()
            ->get();

        $balances = $this->balances($transactions);

        return response()->json([
            'status' => 'success',
            'wallet' => [
                'available_balance' => $balances['available'],
                'pending_clearance' => $balances['pending'],
                'total_earned'      => $balances['total_earned'],
                'currency'          => 'SYP',
                'pending_note'      => 'Earnings stay pending until the booking is completed (service delivered), then become withdrawable.',
            ],
            'transactions' => $transactions,
        ]);
    }

    // Withdraw the full available balance (resets available to 0).
    // Real payout to the vendor's bank waits on the ShamCash payout API.
    public function withdraw(Request $request)
    {
        $vendor    = $request->user();
        $available = $this->balances(
            WalletTransaction::where('vendor_id', $vendor->id)
                ->with('booking:id,status')
                ->get()
        )['available'];

        if ($available <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No balance available to withdraw yet',
            ], 422);
        }

        $withdrawal = WalletTransaction::create([
            'vendor_id'  => $vendor->id,
            'booking_id' => null,
            'type'       => 'withdrawal',
            'amount'     => -1 * $available,
        ]);

        return response()->json([
            'status'            => 'success',
            'message'           => 'Withdrawal successful (real payout pending ShamCash payout API)',
            'amount_withdrawn'  => $available,
            'available_balance' => 0.0,
            'withdrawal'        => $withdrawal,
        ]);
    }

    // Derive the three balances from a vendor's transactions.
    // A booking's earnings (credit minus any refund) stay in escrow (pending)
    // while the booking is still in progress (pending/approved). They clear —
    // become withdrawable — only once the booking is settled: completed (service
    // delivered) or cancelled (final). This keeps the money refundable right up
    // until the service actually happens. Withdrawals reduce available immediately.
    private function balances(Collection $transactions): array
    {
        $withdrawn = (float) $transactions->where('type', 'withdrawal')->sum('amount'); // negative

        $cleared = 0;
        $pending = 0;

        foreach ($transactions->whereIn('type', ['credit', 'refund'])->groupBy('booking_id') as $rows) {
            $net    = (float) $rows->sum('amount');
            $status = $rows->first()->booking?->status;

            if (in_array($status, ['completed', 'cancelled'], true)) {
                $cleared += $net;
            } else {
                $pending += $net;
            }
        }

        return [
            'available'    => round($cleared + $withdrawn, 2),
            'pending'      => round($pending, 2),
            'total_earned' => round($cleared, 2),
        ];
    }
}
