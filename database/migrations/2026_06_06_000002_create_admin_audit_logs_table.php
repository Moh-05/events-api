<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('action');                 // e.g. vendor.approve, user.ban, payment.view
            $table->string('target_type')->nullable(); // e.g. vendor, user, booking
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('meta')->nullable();          // extra context (reason, old/new values...)
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
