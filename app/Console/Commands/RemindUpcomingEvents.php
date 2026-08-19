<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Console\Command;

// Reminds an APPOINTMENT vendor that an approved event is coming up in 3 days,
// so they can prepare (equipment, staff, travel) instead of being surprised.
// Order vendors are excluded on purpose: they take many orders a day and are
// driven by delivery_date, not by a single event they must show up to.
//
// Runs daily. Each booking is reminded ONCE — reminded_at is stamped so a
// re-run (or a second daily run) can never double-notify the same booking.
class RemindUpcomingEvents extends Command
{
    protected $signature = 'bookings:remind-upcoming {--days=3 : How many days ahead to remind}';

    protected $description = 'Notify appointment vendors about approved events happening in N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        // The whole calendar day N days out, so a 09:00 run catches an event at
        // any hour of that day.
        $windowStart = now()->addDays($days)->startOfDay();
        $windowEnd   = now()->addDays($days)->endOfDay();

        $bookings = Booking::where('status', 'approved')
            ->where('booking_style', 'appointment')
            ->whereNotNull('event_date')
            ->whereBetween('event_date', [$windowStart, $windowEnd])
            ->whereNull('reminded_at')
            ->with(['vendor', 'user'])
            ->get();

        $notifier = new NotificationService();
        $sent     = 0;

        foreach ($bookings as $booking) {
            if (! $booking->vendor) {
                continue;
            }

            $notifier->notifyVendorTrans(
                $booking->vendor,
                'messages.notif_event_reminder_title',
                'messages.notif_event_reminder_body',
                [
                    'name' => trim(($booking->user->first_name ?? '') . ' ' . ($booking->user->last_name ?? '')) ?: '-',
                    'days' => $days,
                ],
                ['type' => 'event_reminder', 'booking_id' => $booking->id]
            );

            // Stamped so this booking is never reminded twice.
            $booking->update(['reminded_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} upcoming-event reminder(s).");

        return self::SUCCESS;
    }
}
