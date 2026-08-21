<?php

namespace App\Console\Commands;

use App\Models\VendorProduct;
use Illuminate\Console\Command;

// One-time repair for products left invisible by the old one-way availability
// logic (2026-08-21).
//
// is_available used to be switched OFF when stock hit zero, but nothing ever
// switched it back ON when the vendor restocked — so a product that sold out
// once stayed hidden from customers AND from its own vendor forever, no matter
// how many units were added back.
//
// The rule is now enforced on the model (VendorProduct::booted), but rows that
// were already stuck need correcting once. Idempotent: it only touches rows
// whose flag actually disagrees with their stock.
class FixStuckAvailability extends Command
{
    protected $signature = 'products:fix-availability {--force : Write the changes (default is a dry run)}';

    protected $description = 'Repair products whose is_available disagrees with their stock';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes written. Re-run with --force to apply.');
        }

        // Stuck hidden: has stock but is marked unavailable (the reported bug).
        $stuckHidden = VendorProduct::whereNotNull('stock')
            ->where('stock', '>', 0)
            ->where('is_available', false)
            ->get();

        // Stuck visible: no stock but still marked available (the inverse).
        $stuckVisible = VendorProduct::whereNotNull('stock')
            ->where('stock', '<=', 0)
            ->where('is_available', true)
            ->get();

        foreach ($stuckHidden as $p) {
            $this->line("  #{$p->id} '{$p->name}' stock={$p->stock} — hidden despite stock, making AVAILABLE");
            if (! $dryRun) {
                $p->update(['is_available' => true]);
            }
        }

        foreach ($stuckVisible as $p) {
            $this->line("  #{$p->id} '{$p->name}' stock={$p->stock} — visible despite no stock, making UNAVAILABLE");
            if (! $dryRun) {
                $p->update(['is_available' => false]);
            }
        }

        $total = $stuckHidden->count() + $stuckVisible->count();

        if ($total === 0) {
            $this->info('  nothing to fix — every product already matches its stock.');
        } else {
            $this->info(($dryRun ? "  would fix {$total}" : "  fixed {$total}") . ' product(s).');
        }

        return self::SUCCESS;
    }
}
