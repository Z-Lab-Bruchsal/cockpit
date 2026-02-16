<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('orderstatuses')->insert(['name' => 'erfasst']);
        DB::table('orderstatuses')->insert(['name' => 'bestellt']);
        DB::table('orderstatuses')->insert(['name' => 'angekommen']);
        DB::table('orderstatuses')->insert(['name' => 'genommen']);
    }
}
