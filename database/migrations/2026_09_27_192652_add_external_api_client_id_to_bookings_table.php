<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors orders.external_api_client_id - without it, any external API
     * client could read back a booking any other channel created (no
     * ownership boundary at all), unlike tickets which already have this.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('external_api_client_id')->nullable()->after('sales_channel_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_api_client_id');
        });
    }
};
