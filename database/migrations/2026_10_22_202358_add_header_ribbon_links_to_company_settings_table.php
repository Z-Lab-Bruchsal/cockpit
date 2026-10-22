<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links of the ribbons hanging below the homepage header, prefilled with the pages of auerbach-stiftung.de.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('newsletter_url', 500)->nullable();
            $table->string('donation_url', 500)->nullable();
        });

        DB::table('company_settings')->update([
            'newsletter_url' => 'https://www.auerbach-stiftung.de/newsletter-12-de',
            'donation_url' => 'https://www.auerbach-stiftung.de/spenden-11-de',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['newsletter_url', 'donation_url']);
        });
    }
};
