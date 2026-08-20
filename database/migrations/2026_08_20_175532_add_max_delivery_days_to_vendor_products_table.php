<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_products', function (Blueprint $table) {
            // Optional, seller-set delivery promise: "I deliver this within N
            // days." Nullable — a vendor with no policy set leaves this empty,
            // and delivery_date on an order simply stays null, same as before
            // this feature existed. When set, BookingController::storeOrder()
            // computes the order's delivery_date as
            // (order time + the LONGEST max_delivery_days among the cart's
            // products) — the order isn't "late" until its slowest item is.
            $table->unsignedSmallInteger('max_delivery_days')->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_products', function (Blueprint $table) {
            $table->dropColumn('max_delivery_days');
        });
    }
};
