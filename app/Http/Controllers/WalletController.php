<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function wallet(Request $request)
    {
        $vendor = $request->user();

        // Credits older than 3 days are fully cleared.
        // After 3 days the refund % = 0%, so cleared credits can never have a refund row.
        $clearedCredits = (float) WalletTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'credit')
            ->where('created_at', '<=', now()->subDays(3))
            ->sum('amount');

        // Credits still inside the 3-day hold window (user can still cancel)
        $pendingCredits = (float) WalletTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'credit')
            ->where('created_at', '>', now()->subDays(3))
            ->sum('amount');

        // All refunds always land on pending bookings (see above — no refunds after 3 days)
        $refunds = (float) WalletTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'refund')
            ->sum('amount'); // negative

        // All withdrawals the vendor has already swept
        $withdrawals = (float) WalletTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'withdrawal')
            ->sum('amount'); // negative

        $totalEarned      = round($clearedCredits, 2);
        $availableBalance = max(0.0, round($clearedCredits + $withdrawals, 2));
        $pendingClearance = max(0.0, round($pendingCredits + $refunds, 2));

        $transactions = WalletTransaction::where('vendor_id', $vendor->id)
            ->with([
                'booking:id,user_id,vendor_product_id,booking_style',
                'booking.user:id,first_name,last_name',
                'booking.vendor_product:id,name,price',
            ])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'wallet' => [
                'available_balance' => $availableBalance,
                'pending_clearance'  => $pendingClearance,
                'total_earned'       => $totalEarned,
            ],
            'transactions' => $transactions,
        ]);
    }

    public function withdraw(Request $request)
    {
        $vendor = $request->user();

        $clearedCredits = (float) WalletTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'credit')
            ->where('created_at', '<=', now()->subDays(3))
            ->sum('amount');

        $withdrawals = (float) WalletTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'withdrawal')
            ->sum('amount');

        $available = max(0.0, round($clearedCredits + $withdrawals, 2));

        if ($available <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No available balance to withdraw.',
            ], 422);
        }

        // Sweep the entire available balance to zero — no partial amounts
        WalletTransaction::create([
            'vendor_id'  => $vendor->id,
            'booking_id' => null,
            'type'       => 'withdrawal',
            'amount'     => -$available,
        ]);

        return response()->json([
            'status'            => 'success',
            'message'           => 'Withdrawal of ' . $available . ' SYP recorded. Transfer pending ShamCash payout API.',
            'amount_withdrawn'  => $available,
            'available_balance' => 0.0,
        ]);
    }
}
