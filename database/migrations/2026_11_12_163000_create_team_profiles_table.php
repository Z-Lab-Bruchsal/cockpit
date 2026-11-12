<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->boolean('team_visible')->default(false);
            $table->string('team_area')->nullable();
            $table->string('team_role', 100)->nullable();
            $table->text('team_bio')->nullable();
            $table->string('team_photo_path')->nullable();
            $table->string('team_photo_alt')->nullable();
            $table->unsignedInteger('team_sort')->default(0);
            $table->boolean('team_contactable')->default(true);
            $table->timestamps();
        });

        Schema::create('interest_team_profile', function (Blueprint $table): void {
            $table->foreignId('team_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
            $table->primary(['team_profile_id', 'interest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_team_profile');
        Schema::dropIfExists('team_profiles');
    }
};
