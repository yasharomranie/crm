<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->text('merchant_id')->nullable();
            $table->boolean('is_sandbox')->default(false);
            $table->unsignedBigInteger('min_topup_amount')->default(10000);
            $table->unsignedBigInteger('max_topup_amount')->default(50000000);
            $table->timestamps();
        });

        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('authority')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->string('ref_id')->nullable();
            $table->string('card_pan')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topups');
        Schema::dropIfExists('payment_gateway_settings');
    }
};
