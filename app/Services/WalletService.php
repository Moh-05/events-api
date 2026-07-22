<?php

namespace App\Services;

use Illuminate\Support\Collection;

// The one place that turns a vendor's wallet transactions into balances, so the
// vendor's own wallet and an admin viewing that wallet always agree.
class WalletService
{
    // A booking's earnings (credit minus any refund) stay in escrow (pending)
    // while the booking is still in progress (pending/approved). They clear —
    // become withdrawable — only once the booking is settled: completed (service
    // delivered) or cancelled (final). Withdrawals reduce available immediately.
    //
    // Pass transactions that already have `booking:id,status` loaded.
    public function balances(Collection $transactions): array
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
