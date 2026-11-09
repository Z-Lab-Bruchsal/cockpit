<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_tabs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort']);
        });

        Schema::create('dashboard_tab_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dashboard_tab_id')->constrained()->cascadeOnDelete();
            $table->string('widget');
            $table->string('width', 10)->default('full');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['dashboard_tab_id', 'widget']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_tab_widgets');
        Schema::dropIfExists('dashboard_tabs');
    }
};
