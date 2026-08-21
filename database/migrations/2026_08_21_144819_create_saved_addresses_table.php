<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer's address book. The single latitude/longitude/address on
        // the users table stays as-is (it is the profile's "where I am now",
        // used for Explore's default sort); this is the separate list of places
        // they save and reuse — "Home", "Work", "My sister's place".
        Schema::create('saved_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // What the customer calls it, e.g. "Home". Their own words.
            $table->string('label');

            // The map pin is the real source of truth — the customer picks the
            // spot, and Flutter reverse-geocodes it into readable text. Both
            // are stored: lat/lng powers distance sorting on Explore, and
            // `address` is what a vendor actually reads in order to deliver.
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address');

            // Free-text extras the map can never know: floor, apartment
            // number, "the blue gate", a landmark.
            $table->string('details')->nullable();

            // Exactly one address may be the default, enforced in the
            // controller (setting a new default clears the previous one).
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_addresses');
    }
};
