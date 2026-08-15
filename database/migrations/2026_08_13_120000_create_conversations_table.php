<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One WhatsApp-style conversation per (user, vendor) pair. Unlike the
        // support threads (whose other side is always the admin, hence the
        // owner_type trick), here the two sides are fixed roles — a user and a
        // vendor — so we store them as two explicit columns.
        //
        // The conversation is only ever created AFTER the user has a PAID booking
        // with that vendor (the gate lives in the controller). Once created it
        // stays for good, so the two can keep talking across future bookings.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            // drives the WhatsApp-style list order (most recent chat on top);
            // null while a conversation exists but has no message yet
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // exactly ONE conversation per pair — saving twice can never duplicate
            $table->unique(['user_id', 'vendor_id']);
            // list a user's / a vendor's conversations, newest activity first
            $table->index(['user_id', 'last_message_at']);
            $table->index(['vendor_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
