<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('vendors', function (Blueprint $table) {
        $table->id();
        $table->string('shop_name'); // اسم المحل أو المزوّد
        $table->string('phone')->unique(); // رقم التواصل الأساسي
        $table->string('category')->nullable(); // (Venue, DJ, Photographer...)
        $table->string('password')->nullable(); // اختياري حسب رغبتك
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
