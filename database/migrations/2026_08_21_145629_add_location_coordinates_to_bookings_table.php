<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Bookings stored the service/delivery location as TEXT only
            // (event_location / delivery_address), so a vendor had an address
            // to read but no point to navigate to.
            //
            // These are a SNAPSHOT taken at booking time — copied from the
            // customer's saved address (or from a one-off pin they dropped),
            // never a reference to it. Editing or deleting a saved address
            // later must not rewrite what a past booking agreed to, the same
            // principle already used for item price snapshots.
            //
            // Nullable because both existing bookings and text-only bookings
            // (a customer who typed an address without picking a point) are
            // still valid.
            $table->decimal('location_latitude', 10, 8)->nullable()->after('delivery_address');
            $table->decimal('location_longitude', 11, 8)->nullable()->after('location_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['location_latitude', 'location_longitude']);
        });
    }
};
