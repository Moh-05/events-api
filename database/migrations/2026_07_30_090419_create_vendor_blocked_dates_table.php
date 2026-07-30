<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dates a vendor manually marks as unavailable — e.g. an offline booking
        // (walk-in / phone) the app doesn't know about, or a personal day off.
        // Availability = booked dates (from bookings) + these blocked dates.
        // Appointment (service) vendors only; order vendors take many orders a day.
        Schema::create('vendor_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable(); // optional label the vendor sees
            $table->timestamps();

            // A vendor can't block the same day twice.
            $table->unique(['vendor_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_blocked_dates');
    }
};
