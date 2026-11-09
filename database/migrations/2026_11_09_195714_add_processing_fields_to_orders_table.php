<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Processing orders: who placed them where, with which order number and at what cost,
 * plus the weekly report for accounting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('merchant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->after('url')->constrained()->nullOnDelete();
            $table->string('order_number')->nullable()->after('merchant_id');
            $table->decimal('total_price', 10, 2)->nullable()->after('order_number');
            $table->foreignId('processed_by_user_id')->nullable()->after('orderdatetime')->constrained('users')->nullOnDelete();
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('order_report_enabled')->default(false);
            $table->unsignedTinyInteger('order_report_weekday')->default(1);
            $table->string('order_report_time', 5)->default('07:00');
            $table->json('order_report_recipients')->nullable();
            $table->timestamp('order_report_last_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['order_report_enabled', 'order_report_weekday', 'order_report_time', 'order_report_recipients', 'order_report_last_sent_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by_user_id');
            $table->dropColumn(['total_price', 'order_number']);
            $table->dropConstrainedForeignId('merchant_id');
        });

        Schema::dropIfExists('merchant_domains');
        Schema::dropIfExists('merchants');
    }
};
