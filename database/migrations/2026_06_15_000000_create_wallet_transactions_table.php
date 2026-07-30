<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            // null for withdrawals (not tied to a booking)
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->enum('type', ['credit', 'refund', 'withdrawal']);
            // signed: credit > 0, refund < 0, withdrawal < 0
            $table->decimal('amount', 10, 2);
            // For withdrawals: when the admin actually paid the vendor out
            // (null = requested but not yet paid). Meaningless for credit/refund.
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
