<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // Who the notification belongs to: 'user' or 'vendor' + their id
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();   // optional extra payload (e.g. booking_id)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
