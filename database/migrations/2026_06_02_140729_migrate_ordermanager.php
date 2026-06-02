<?php

use App\Models\Role;
use App\Models\User;
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
        $ordermanager = Role::firstOrCreate(["name" => "ordermanager", 'title' => 'Ordermanager', 'description' => 'Verwaltet Bestellungen'])->save();
        $users = User::all();
        foreach($users as $user) {
            if($user->ordermanager) $user->roles()->attach($ordermanager);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ordermanager');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean("ordermanager")->default(false);
        });
        $users = User::all();
        foreach($users as $user) {
            if($user->hasRole("ordermanager")) {
                $user->ordermanager = 1;
                $user->save();
            }
        }

    }
};
