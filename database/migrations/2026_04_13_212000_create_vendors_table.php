<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->unique();
            $table->string('city')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('business_name')->nullable();
            $table->enum('vendor_type', [
                // Service categories (appointment-based)
                'photographer',
                'makeupArtist',
                'dj',
                'weddingHall',
                // Seller categories (order-based)
                'flowers',
                'gifts',
                'dresses',
                'accessories',
                'candles',
                'cakes',
            ])->nullable();
            $table->enum('booking_style', [
                'appointment',
                'order'
            ])->nullable();
            // Helper for Flutter only (vendor_type drives the workflow).
            $table->enum('vendor_style', [
                'service_provider',
                'seller',
            ])->nullable();
            $table->string('profile_image')->nullable();
            $table->string('cover_image')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();
            $table->text('bio')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_active')->default(true);          // ADMIN ban switch — false = banned (blocks login). NOT vendor-controlled.
            $table->boolean('winding_down')->default(false);      // banned but still allowed to finish existing bookings
            // Vendor-controlled "open for bookings" toggle. false = the vendor set
            // themselves offline: hidden from browse + can't receive new bookings,
            // but stays logged in and manages existing ones. Separate from is_active.
            $table->boolean('is_accepting_bookings')->default(true);
            $table->text('rejection_reason')->nullable(); // why KYC was rejected (shown to vendor)
            $table->string('language', 2)->default('ar'); // ar | en — the app's language, used for notifications
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
