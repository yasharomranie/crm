<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same ownership boundary as orders.external_api_client_id and
     * bookings.external_api_client_id - a lead captured through one
     * integration's API credentials should not be readable back through
     * another's.
     */
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('external_api_client_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_api_client_id');
        });
    }
};
