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
        Schema::create('web_app_domain_settings', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('dns_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_app_domain_settings');
    }
};
