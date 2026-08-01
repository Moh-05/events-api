<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // New ledger row type: 'commission' — the platform's commission charged
    // to the vendor when a booking is cancelled AT THE VENDOR'S REQUEST
    // (he backed out of a committed booking, so he bears the platform's cut).
    // Amount is negative, tied to the booking. Can push the wallet negative —
    // the vendor then owes the platform and can't withdraw until covered.
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->enum('type', ['credit', 'refund', 'withdrawal', 'commission'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->enum('type', ['credit', 'refund', 'withdrawal'])->change();
        });
    }
};
