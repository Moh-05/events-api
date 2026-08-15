<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            // who wrote it — same 'user'|'vendor' convention as notifications/support
            $table->enum('sender_type', ['user', 'vendor']);
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            // set when the OTHER side has seen it — drives unread badges + "seen"
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // fetch a conversation's messages in chronological order, fast
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
