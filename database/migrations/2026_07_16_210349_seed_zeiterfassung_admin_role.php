<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::firstOrCreate(
            ['name' => 'zeiterfassung-admin'],
            ['title' => 'Zeiterfassung-Admin', 'description' => 'Verwaltet Pausenregeln und Zeiterfassungs-Einstellungen'],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::where('name', 'zeiterfassung-admin')->delete();
    }
};
