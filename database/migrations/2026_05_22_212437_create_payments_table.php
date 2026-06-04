<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('commission', 10, 2);    // 15% for the pltform
            $table->decimal('vendor_payout', 10, 2); // 85% for the vendor
            $table->string('currency')->default('SYP');
            $table->string('transaction_id')->unique(); // from the payment gateway
            $table->string('sender_name')->nullable();  // from the api
            $table->enum('status', [
                'pending',
                'verified',
                'failed'
            ])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
