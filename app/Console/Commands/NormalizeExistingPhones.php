<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PhoneNumberService;
use Illuminate\Console\Command;

// One-time backfill for the phone-normalization fix (2026-08-19). New signups
// already store phone as +963{local} via PhoneNumberService::normalize(), but
// every account created BEFORE that fix still has its ORIGINAL raw value
// (0949101231, 935983121, ...). Login always normalizes the typed number to
// +963{local} before the lookup, so an un-backfilled old row can never match
// ANY login attempt, no matter what the person types. This is not a new
// mismatch bug — it is every pre-existing account being locked out.
//
// This command rewrites phone -> PhoneNumberService::normalize(phone) for
// every row that isn't already in that form, on both users and vendors. No
// migration, no schema change, no data loss — an UPDATE on one existing
// column. Idempotent: a row already stored as +963... normalizes to itself,
// so running this twice is a no-op the second time.
class NormalizeExistingPhones extends Command
{
    protected $signature = 'phones:normalize {--force : Actually write the changes (default is dry-run)}';

    protected $description = 'Backfill existing user/vendor phone numbers to the canonical +963 format';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written. Re-run with --force to apply.');
        }

        $this->backfill(User::class, 'users', $dryRun);
        $this->backfill(Vendor::class, 'vendors', $dryRun);

        return self::SUCCESS;
    }

    private function backfill(string $model, string $label, bool $dryRun): void
    {
        $rows = $model::select('id', 'phone')->get();
        $changed = 0;

        $this->line("--- {$label} ({$rows->count()} rows) ---");

        foreach ($rows as $row) {
            $normalized = PhoneNumberService::normalize($row->phone);

            if ($normalized === $row->phone) {
                continue; // already canonical, nothing to do
            }

            $this->line("  #{$row->id}: {$row->phone}  ->  {$normalized}");
            $changed++;

            if (! $dryRun) {
                $row->update(['phone' => $normalized]);
            }
        }

        if ($changed === 0) {
            $this->info("  nothing to change — all {$label} already canonical.");
        } else {
            $this->info(($dryRun ? '  would update ' : '  updated ') . "{$changed} {$label} row(s).");
        }
    }
}
