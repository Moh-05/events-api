<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A withdrawal request the admin REJECTED (e.g. fraud / suspicious, or the
    // vendor's wallet went negative after a commission clawback). A rejected
    // withdrawal no longer counts against the wallet — the held amount returns
    // to the vendor's available balance — and it leaves the payout queue.
    // Only meaningful for type = 'withdrawal'. Mutually exclusive with paid_at.
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
