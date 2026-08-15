<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Third fate for a recorded refund: WAIVED — the admin officially decided
    // to keep the money (e.g. fraud). null = still due (or paid, see
    // refund_paid_at). Waived refunds leave the refunds-due list; the
    // decision + reason live in the audit log.
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('refund_waived_at')->nullable()->after('refund_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('refund_waived_at');
        });
    }
};
