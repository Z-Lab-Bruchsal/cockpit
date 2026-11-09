<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Short description for an order request. Also clears the order date of requests that were
 * never placed – it used to be prefilled when the request was recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('description')->nullable()->after('url');
        });

        $recordedStatusId = DB::table('orderstatuses')->where('name', 'erfasst')->value('id');

        if ($recordedStatusId !== null) {
            DB::table('orders')->where('orderstatus_id', $recordedStatusId)->update(['orderdatetime' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
