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
        Schema::table('ai_messages', function (Blueprint $table) {
            // Set only on a final assistant answer (never on a tool-call-
            // requesting round) that was built from a chartable reporting
            // tool's result - see HasAiAssistantEngine::buildChartForTool().
            $table->json('chart')->nullable()->after('tool_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('chart');
        });
    }
};
