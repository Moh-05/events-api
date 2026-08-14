<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'bookings',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('vendor_product_id')->nullable()->constrained('vendor_products')->nullOnDelete();
                $table->enum('booking_style', ['appointment', 'order']);
                $table->enum('status', [
                    'awaiting_payment', // draft — hidden from vendor until payment
                    'pending',          // paid — vendor can accept or decline
                    'approved',
                    'declined',
                    'completed',
                    'cancelled'
                ])->default('awaiting_payment');

                // Appointment fields
                $table->dateTime('event_date')->nullable();
                // Keeps the date for display after a booking is cancelled/declined:
                // event_date is nulled then (to free the slot for rebooking + drop
                // out of the unique index), old_event_date preserves it for history.
                $table->dateTime('old_event_date')->nullable();
                $table->string('event_type')->nullable();
                $table->string('event_location')->nullable();
                $table->integer('duration_hours')->nullable();

                // Order fields
                $table->json('details')->nullable();
                $table->dateTime('delivery_date')->nullable();
                $table->string('delivery_address')->nullable();

                // Shared
                $table->text('notes')->nullable();
                $table->decimal('price_agreed', 10, 2)->nullable();
                // Appointment only: options the customer picked for the single
                // service (e.g. outfit, package tier), chosen from the product meta.
                // Orders carry their selections per item (booking_items instead).
                $table->json('selected_options')->nullable();

                // When the vendor first responded (approved or declined) to a paid
                // booking. Used to compute the vendor's average response time.
                $table->timestamp('responded_at')->nullable();

                // Customer refund tracking (set when a paid booking is cancelled).
                // refund_amount = money owed back to the customer; refund_paid_at =
                // when an admin actually sent it (null = still due). Real payout is
                // manual until the ShamCash payout API exists.
                $table->decimal('refund_amount', 12, 2)->nullable();
                $table->timestamp('refund_paid_at')->nullable();

                $table->timestamps();

                // Day-level slot lock: the DB itself forbids two bookings for the
                // same vendor on the same day. event_day is the date part of
                // event_date (null when event_date is null — so orders and
                // cancelled/declined appointments are exempt and never collide,
                // since MySQL unique indexes ignore NULLs).
                $table->date('event_day')
                    ->storedAs('CASE WHEN event_date IS NULL THEN NULL ELSE DATE(event_date) END')
                    ->nullable();
                $table->unique(['vendor_id', 'event_day']);
            }


        );
    }
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
