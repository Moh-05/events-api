<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Average minutes between a booking being PAID and this vendor
            // responding (approve/decline). Previously this was computed live
            // by GET /vendor/response-time, which was fine for one vendor
            // looking at themselves — but customers need to see it on every
            // vendor card, and computing it per vendor would mean an extra
            // query for every row of every browse list.
            //
            // So it is stored here and recalculated when the vendor responds
            // (BookingController::touchResponseTime), the same way rating_avg
            // is maintained. Null = has never responded to a paid booking yet,
            // which the app shows as "New".
            $table->unsignedInteger('avg_response_minutes')->nullable()->after('rating_avg');

            // How many responses that average is based on — lets the app say
            // "based on 12 bookings" and avoids trusting an average of one.
            $table->unsignedInteger('response_count')->default(0)->after('avg_response_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['avg_response_minutes', 'response_count']);
        });
    }
};
