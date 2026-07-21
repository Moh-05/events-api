<?php

namespace App\Console\Commands;

use App\Models\Booking;
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

        $count = Booking::where('status', 'approved')
            ->where(function ($q) use ($cutoff) {
                // Appointment vendors → judged by event_date
                $q->where(fn ($s) => $s->where('booking_style', 'appointment')
                    ->whereNotNull('event_date')
                    ->where('event_date', '<=', $cutoff))
                    // Order vendors → judged by delivery_date
                    ->orWhere(fn ($s) => $s->where('booking_style', 'order')
                        ->whereNotNull('delivery_date')
                        ->where('delivery_date', '<=', $cutoff));
            })
            ->update(['status' => 'completed']);

        $this->info("Auto-completed {$count} booking(s).");

        return self::SUCCESS;
    }
}
