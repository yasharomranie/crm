<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_mission_cards', function (Blueprint $table) {
            $table->string('status')->default('open')->after('unsourced');
        });

        Schema::table('ai_missions', function (Blueprint $table) {
            $table->json('notes')->nullable()->after('report');
        });
    }

    public function down(): void
    {
        Schema::table('ai_mission_cards', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('ai_missions', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
