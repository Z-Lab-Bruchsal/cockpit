<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->json('school_types')->nullable();
            $table->unsignedTinyInteger('min_grade')->nullable();
            $table->unsignedTinyInteger('max_grade')->nullable();
            $table->unsignedSmallInteger('max_class_size')->nullable();
        });

        Schema::table('course_instances', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_class_size')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['school_types', 'min_grade', 'max_grade', 'max_class_size']);
        });

        Schema::table('course_instances', function (Blueprint $table) {
            $table->dropColumn('max_class_size');
        });
    }
};
