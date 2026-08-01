<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One support conversation with the admin team.
        //   vendor thread: ONE persistent chat per vendor (SUPPORT button),
        //                  subject/booking/category stay null.
        //   user thread:   a ticket — subject + optional booking + category.
        //                  The admin decides whether to reply (open a chat)
        //                  or just act; resolving closes it for good.
        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->enum('owner_type', ['user', 'vendor']);
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('subject')->nullable();
            // user tickets only: what kind of complaint (routes the admin to
            // the right tool — money vs vendor behaviour)
            $table->string('category')->nullable(); // no_show | payment | behavior | other
            $table->enum('status', ['open', 'in_review', 'resolved'])->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_threads');
    }
};
