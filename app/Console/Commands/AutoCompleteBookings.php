<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Vendor;
use Illuminate\Console\Command;

class AutoCompleteBookings extends Command
{
    protected $signature = 'bookings:auto-complete';

    protected $description = 'Mark approved bookings as completed one day after the event (appointment) or delivery (order) date';

    public function handle(): int
    {
        // One day after the service date. The buffer gives a little room in case
        // the event ran late; after it, the service is considered delivered and
        // the vendor's escrowed money clears.
        $cutoff = now()->subDay();

        $due = Booking::where('status', 'approved')
            ->where(function ($q) use ($cutoff) {
                // Appointment vendors → judged by event_date
                $q->where(fn ($s) => $s->where('booking_style', 'appointment')
                    ->whereNotNull('event_date')
                    ->where('event_date', '<=', $cutoff))
                    // Order vendors → judged by delivery_date
                    ->orWhere(fn ($s) => $s->where('booking_style', 'order')
                        ->whereNotNull('delivery_date')
                        ->where('delivery_date', '<=', $cutoff));
            });

        // Vendors touched by this run — captured before the bulk update so we can
        // finalize winding-down bans (bulk updates bypass the Booking model event).
        $vendorIds = (clone $due)->pluck('vendor_id')->unique();

        $count = $due->update(['status' => 'completed']);

        Vendor::whereIn('id', $vendorIds)
            ->where('winding_down', true)
            ->get()
            ->each->finalizeBanIfCleared();

        $this->info("Auto-completed {$count} booking(s).");

        return self::SUCCESS;
    }
}
