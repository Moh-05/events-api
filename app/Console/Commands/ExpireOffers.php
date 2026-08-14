<?php

namespace App\Console\Commands;

use App\Models\VendorProduct;
use Illuminate\Console\Command;

class ExpireOffers extends Command
{
    protected $signature = 'offers:expire';

    protected $description = 'Revert products whose discount has ended back to their original price and start the cooldown';

    public function handle(): int
    {
        // Note: the is_on_offer accessor already treats a past discount_ends_at as
        // "no offer", so pricing is correct even before this runs. This just cleans
        // up the fields and stamps discount_last_ended_at so the 1-week cooldown
        // starts. Bulk update — no model events needed.
        $count = VendorProduct::whereNotNull('discount_percent')
            ->whereNotNull('discount_ends_at')
            ->where('discount_ends_at', '<=', now())
            ->update([
                'discount_percent'       => null,
                'discount_ends_at'       => null,
                'discount_last_ended_at' => now(),
            ]);

        $this->info("Expired {$count} offer(s).");

        return self::SUCCESS;
    }
}
