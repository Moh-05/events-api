<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A light "this content is wrong" flag (review / product / portfolio).
        // Feeds the FLAGGED / REPORTED badges on the admin moderation page.
        // No thread, no lifecycle — the admin either deletes the content
        // (existing moderation actions) or dismisses the flag.
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('reporter_type', ['user', 'vendor']);
            $table->unsignedBigInteger('reporter_id');
            $table->enum('reportable_type', ['review', 'product', 'portfolio_item']);
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 1000)->nullable();
            $table->enum('status', ['pending', 'dismissed'])->default('pending');
            $table->timestamps();

            // One report per person per item (re-reporting is a no-op)
            $table->unique(
                ['reporter_type', 'reporter_id', 'reportable_type', 'reportable_id'],
                'content_reports_unique_reporter_item'
            );
            $table->index(['reportable_type', 'reportable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
