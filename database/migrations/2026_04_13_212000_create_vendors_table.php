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
            $table->string('name');
            $table->string('phone')->unique();
            $table->date('birth_date')->nullable();
            $table->string('business_name')->nullable();
            $table->enum('vendor_type', [
                'wedding_venue',
                'photographer',
                'cake_shop',
                'dj',
                'catering',
                'beauty',
                'decor',
                'accessories'
            ])->nullable();
            $table->enum('booking_style', [
                'appointment',
                'order'
            ])->nullable();
            $table->string('profile_image')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();
            $table->text('bio')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};