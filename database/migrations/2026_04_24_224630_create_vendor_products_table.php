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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_products');
    }
};
