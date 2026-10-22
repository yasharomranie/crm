<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_mission_actions', function (Blueprint $table) {
            $table->json('compliance')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('ai_mission_actions', function (Blueprint $table) {
            $table->dropColumn('compliance');
        });
    }
};
