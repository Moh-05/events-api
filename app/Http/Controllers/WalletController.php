<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    // Money is held for 3 days after approval (the refund window) before it
    // becomes withdrawable.
    private const HOLD_DAYS = 3;

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
                'pending_note'      => 'Pending money becomes available to withdraw 3 days after the booking is approved.',
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
            WalletTransaction::where('vendor_id', $vendor->id)->get()
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
    // Each booking's earnings (credit minus any refund) clear 3 days after the
    // booking was approved — i.e. 3 days after the credit's created_at.
    // Withdrawals reduce the available balance immediately.
    private function balances(Collection $transactions): array
    {
        $cutoff    = now()->subDays(self::HOLD_DAYS);
        $withdrawn = (float) $transactions->where('type', 'withdrawal')->sum('amount'); // negative

        $cleared = 0;
        $pending = 0;

        foreach ($transactions->whereIn('type', ['credit', 'refund'])->groupBy('booking_id') as $rows) {
            $net        = (float) $rows->sum('amount');
            $approvedAt = $rows->firstWhere('type', 'credit')?->created_at;

            if ($approvedAt && $approvedAt->lte($cutoff)) {
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
