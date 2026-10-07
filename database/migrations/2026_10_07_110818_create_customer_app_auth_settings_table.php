<?php

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
        Schema::create('customer_app_auth_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('otp_enabled')->default(true);
            $table->boolean('password_enabled')->default(true);
            $table->boolean('google_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_app_auth_settings');
    }
};
