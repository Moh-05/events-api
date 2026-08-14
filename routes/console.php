<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-complete approved bookings one day after their event/delivery date.
// Requires the scheduler to be running (see Railway cron setup).
Schedule::command('bookings:auto-complete')->dailyAt('01:00');

// Revert products whose discount period has ended (and start the cooldown).
// Pricing is already correct via the is_on_offer accessor; this cleans up fields.
Schedule::command('offers:expire')->hourly();
