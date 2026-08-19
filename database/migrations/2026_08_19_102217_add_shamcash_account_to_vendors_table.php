<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // The vendor's OWN ShamCash account — where the platform sends a
            // withdrawal payout. Separate from the platform's ShamCash account
            // (SHAMCASH_ACCOUNT_ID env var), which is where CUSTOMERS pay IN.
            // Set once by the vendor via POST /vendor/shamcash-account; nullable
            // because a vendor can register and work for a while before ever
            // withdrawing. POST /vendor/withdraw requires it to be set (422
            // otherwise) so the admin never gets a payout request with nowhere
            // to send the money.
            $table->string('shamcash_account')->nullable()->after('is_accepting_bookings');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('shamcash_account');
        });
    }
};
