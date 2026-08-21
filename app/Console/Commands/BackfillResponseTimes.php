<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Vendor;
use Illuminate\Console\Command;

// One-time backfill for the stored response-time columns (2026-08-21).
// Response time used to be computed live per request; it is now stored on the
// vendor and updated whenever they answer a booking. Vendors who already
// responded BEFORE that change have null columns and would wrongly show as
// "New" to customers, so this recomputes them once from existing history.
//
// Idempotent — safe to re-run; it simply recalculates the same averages.
class BackfillResponseTimes extends Command
{
    protected $signature = 'vendors:backfill-response-times {--force : Write the changes (default is a dry run)}';

    protected $description = 'Recompute stored avg_response_minutes for vendors who already answered bookings';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes written. Re-run with --force to apply.');
        }

        $updated = 0;

        Vendor::query()->chunkById(100, function ($vendors) use ($dryRun, &$updated) {
            foreach ($vendors as $vendor) {
                $bookings = Booking::where('vendor_id', $vendor->id)
                    ->whereNotNull('responded_at')
                    ->whereHas('payment', fn ($q) => $q->where('status', 'verified'))
                    ->with('payment:id,booking_id,created_at')
                    ->get();

                if ($bookings->isEmpty()) {
                    continue;
                }

                $avg = $bookings->avg(
                    fn (Booking $b) => $b->payment->created_at->diffInMinutes($b->responded_at)
                );

                $this->line(sprintf(
                    '  #%d %s -> %d min (based on %d)',
                    $vendor->id,
                    $vendor->business_name ?: '(no name)',
                    (int) round($avg),
                    $bookings->count()
                ));

                if (! $dryRun) {
                    $vendor->update([
                        'avg_response_minutes' => (int) round($avg),
                        'response_count'       => $bookings->count(),
                    ]);
                }

                $updated++;
            }
        });

        $this->info(($dryRun ? 'Would update ' : 'Updated ') . "{$updated} vendor(s).");

        return self::SUCCESS;
    }
}
