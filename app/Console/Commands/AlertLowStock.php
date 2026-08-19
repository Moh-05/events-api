<?php

namespace App\Console\Commands;

use App\Models\VendorProduct;
use App\Services\NotificationService;
use Illuminate\Console\Command;

// Warns a SELLER (order vendor) that a product is nearly sold out, so they can
// restock before it auto-hides at zero. Appointment packages have stock = null
// (untracked) and are skipped entirely.
//
// Fires once per "low episode": low_stock_alerted_at is stamped when we warn,
// and cleared again as soon as the vendor restocks above the threshold — so a
// product that dips low, gets restocked, then dips low again is warned both
// times, but a product sitting at 2 units is not nagged every single day.
class AlertLowStock extends Command
{
    protected $signature = 'products:alert-low-stock {--threshold=3 : Warn at or below this quantity}';

    protected $description = 'Notify seller vendors when a product is nearly out of stock';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        // Restocked above the threshold -> clear the flag so the NEXT dip warns again.
        VendorProduct::whereNotNull('low_stock_alerted_at')
            ->whereNotNull('stock')
            ->where('stock', '>', $threshold)
            ->update(['low_stock_alerted_at' => null]);

        // Low, still sellable, not already warned for this episode.
        $products = VendorProduct::whereNotNull('stock')
            ->where('stock', '>', 0)              // zero = already sold out + auto-hidden
            ->where('stock', '<=', $threshold)
            ->where('is_hidden', false)
            ->whereNull('low_stock_alerted_at')
            ->with('vendor')
            ->get();

        $notifier = new NotificationService();
        $sent     = 0;

        foreach ($products as $product) {
            // Only sellers track stock; skip anything else defensively.
            if (! $product->vendor || $product->vendor->booking_style !== 'order') {
                continue;
            }

            $notifier->notifyVendorTrans(
                $product->vendor,
                'messages.notif_low_stock_title',
                'messages.notif_low_stock_body',
                ['name' => $product->name, 'count' => $product->stock],
                ['type' => 'low_stock', 'product_id' => $product->id]
            );

            $product->update(['low_stock_alerted_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} low-stock alert(s).");

        return self::SUCCESS;
    }
}
