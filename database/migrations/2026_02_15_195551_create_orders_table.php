<?php

use App\Models\User;
use App\Orderstatus;
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
        $orderstatusRegistered = Orderstatus::find("name", "erfasst")->first();
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string("name");
            $table->string("url")->nullable();
            $table->integer("count")->nullable();
            $table->foreignidfor(Orderstatus::class)->default($orderstatusRegistered->id);
            $table->datetime("orderdatetime")->nullable();
            $table->foreignIdFor(User::class);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
