<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every booking holds exactly one course instance, found by its own token instead of a cart.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('hold_token_hash', 64)->nullable()->index();
            $table->dropConstrainedForeignId('cart_id');
        });

        Schema::dropIfExists('carts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['hold_token_hash']);
            $table->dropColumn('hold_token_hash');
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
