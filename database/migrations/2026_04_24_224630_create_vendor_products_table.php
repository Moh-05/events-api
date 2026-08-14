<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('deposit_percent', 5, 2)->default(20)->nullable(); // 20% deposit by default (only for appointments products)
            $table->json('meta')->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('stock')->nullable(); // stock count for order vendors (cake_shop, store). null = not tracked

            // Discount / offer. The vendor sets a % off an EXISTING item; the item
            // then shows in "Best Offers" until discount_ends_at, when it auto-reverts.
            // Haflati's commission is ALWAYS taken on the ORIGINAL price — the vendor
            // fully carries the discount. discount_last_ended_at powers the 1-week
            // cooldown before the same item can be discounted again.
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->timestamp('discount_ends_at')->nullable();
            $table->timestamp('discount_last_ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_products');
    }
};
