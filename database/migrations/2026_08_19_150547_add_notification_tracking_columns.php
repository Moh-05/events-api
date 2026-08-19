<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stamped when the vendor was reminded their event is coming up, so the
        // daily reminder command can never notify the same booking twice.
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('responded_at');
        });

        // Stamped when a low-stock warning was sent. Cleared again once the
        // vendor restocks above the threshold, so each new "low episode" warns
        // once instead of nagging daily.
        Schema::table('vendor_products', function (Blueprint $table) {
            $table->timestamp('low_stock_alerted_at')->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });

        Schema::table('vendor_products', function (Blueprint $table) {
            $table->dropColumn('low_stock_alerted_at');
        });
    }
};
