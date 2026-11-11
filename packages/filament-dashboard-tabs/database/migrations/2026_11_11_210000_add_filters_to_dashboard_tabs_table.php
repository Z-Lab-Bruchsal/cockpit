<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each tab keeps the state of the dashboard filter form, so every tab can show its own selection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_tabs', function (Blueprint $table): void {
            $table->json('filters')->nullable()->after('sort');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_tabs', function (Blueprint $table): void {
            $table->dropColumn('filters');
        });
    }
};
