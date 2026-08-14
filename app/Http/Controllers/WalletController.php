<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private WalletService $wallet)
    {
    }

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

        $balances = $this->wallet->balances($transactions);

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
        $available = $this->wallet->balances(
            WalletTransaction::where('vendor_id', $vendor->id)
                ->with('booking:id,status')
                ->get()
        )['available'];

        if ($available <= 0) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.no_balance'),
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
            'message'           => __('messages.withdrawal_successful'),
            'amount_withdrawn'  => $available,
            'available_balance' => 0.0,
            'withdrawal'        => $withdrawal,
        ]);
    }

}
