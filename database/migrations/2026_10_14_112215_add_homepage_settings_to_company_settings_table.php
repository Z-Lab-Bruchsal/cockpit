<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('app_title')->nullable();
            $table->string('homepage_title')->nullable();
            $table->string('homepage_tagline')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('cockpit_primary_color')->nullable();
            $table->string('cockpit_success_color')->nullable();
            $table->string('cockpit_warning_color')->nullable();
            $table->string('cockpit_danger_color')->nullable();
            $table->string('cockpit_info_color')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('footer_text')->nullable();
            $table->json('footer_links')->nullable();
            $table->json('social_links')->nullable();
            $table->unsignedInteger('hold_minutes')->default(15);
            $table->unsignedInteger('confirmation_hours')->default(48);
        });

        $defaults = [
            'name' => 'Z-Lab',
            'city' => 'Bruchsal',
            'app_title' => 'Z-Lab Cockpit',
            'homepage_title' => 'Z-Lab Bruchsal',
            'primary_color' => '#f59e0b',
            'secondary_color' => '#fff7ed',
            'cockpit_primary_color' => '#f59e0b',
        ];

        if (DB::table('company_settings')->doesntExist()) {
            DB::table('company_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach ($defaults as $column => $value) {
            DB::table('company_settings')->whereNull($column)->update([$column => $value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'app_title',
                'homepage_title',
                'homepage_tagline',
                'primary_color',
                'secondary_color',
                'cockpit_primary_color',
                'cockpit_success_color',
                'cockpit_warning_color',
                'cockpit_danger_color',
                'cockpit_info_color',
                'contact_email',
                'contact_phone',
                'footer_text',
                'footer_links',
                'social_links',
                'hold_minutes',
                'confirmation_hours',
            ]);
        });
    }
};
