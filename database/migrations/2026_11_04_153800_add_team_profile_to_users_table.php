<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public team profile shown on the homepage.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('team_visible')->default(false);
            $table->string('team_area')->nullable();
            $table->string('team_role', 100)->nullable();
            $table->text('team_bio')->nullable();
            $table->string('team_photo_path')->nullable();
            $table->string('team_photo_alt')->nullable();
            $table->unsignedInteger('team_sort')->default(0);
            $table->boolean('team_contactable')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'team_visible', 'team_area', 'team_role', 'team_bio',
                'team_photo_path', 'team_photo_alt', 'team_sort', 'team_contactable',
            ]);
        });
    }
};
