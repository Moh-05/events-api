<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Line items for bookings (cart-style): one row per product, so a single
    // order can hold different products from the same vendor, each with its
    // own quantity. Appointment bookings get a single row (quantity 1).
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_product_id')->nullable()->constrained('vendor_products')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            // Price snapshot at booking time — a later price change by the
            // vendor must not change what an existing order owes.
            // unit_price          = what the customer was charged (discounted if on offer)
            // original_unit_price = the pre-discount price, so commission bills the
            //                       original (the vendor carries the discount).
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('original_unit_price', 10, 2)->nullable();
            // The options the customer picked for THIS item (e.g. color, size),
            // chosen from the vendor's product meta. Free-form JSON — the app
            // decides what's selectable; the backend just stores and relays it so
            // the vendor sees what to prepare.
            $table->json('selected_options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
