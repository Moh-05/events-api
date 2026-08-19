<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // The notification USED to be stored only as rendered text, which
            // froze it in whatever language the recipient had at the time. If
            // they later switched the app to Arabic, an old English row stayed
            // English forever - there was nothing left to retranslate from.
            //
            // Storing the key + its placeholder params instead lets the API
            // render the row at READ time, in whatever language the request
            // asks for. title/body stay as a fallback for rows written before
            // this change (and for anything with no key, like chat pushes).
            $table->string('title_key')->nullable()->after('body');
            $table->string('body_key')->nullable()->after('title_key');
            $table->json('params')->nullable()->after('body_key');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['title_key', 'body_key', 'params']);
        });
    }
};
