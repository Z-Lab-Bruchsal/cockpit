<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string(config('filament-oauth2.subjectColumn', 'oauth_subject'))->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $column = config('filament-oauth2.subjectColumn', 'oauth_subject');

            $table->dropUnique([$column]);
            $table->dropColumn($column);
        });
    }
};
