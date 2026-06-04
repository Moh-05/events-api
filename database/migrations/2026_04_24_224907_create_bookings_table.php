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
                $table->timestamps();
            }


        );
    }
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
