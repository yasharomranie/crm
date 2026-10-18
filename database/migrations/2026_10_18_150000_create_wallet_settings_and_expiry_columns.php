<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('cashback_bronze_percent', 5, 2)->default(2);
            $table->decimal('cashback_silver_percent', 5, 2)->default(2);
            $table->decimal('cashback_gold_percent', 5, 2)->default(2);
            $table->decimal('cashback_vip_percent', 5, 2)->default(2);
            $table->unsignedSmallInteger('cashback_expiry_days')->nullable();
            $table->unsignedSmallInteger('expiry_reminder_days')->default(7);
            $table->timestamps();
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->after('reason');
            $table->dateTime('expiry_reminded_at')->nullable()->after('expires_at');
            $table->dateTime('expiry_processed_at')->nullable()->after('expiry_reminded_at');
            $table->unsignedBigInteger('source_transaction_id')->nullable()->after('expiry_processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'expiry_reminded_at', 'expiry_processed_at', 'source_transaction_id']);
        });

        Schema::dropIfExists('wallet_settings');
    }
};
