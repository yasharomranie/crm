<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_missions', function (Blueprint $table) {
            $table->unsignedInteger('thread_token_cap')->nullable();
            $table->json('quick_prompts')->nullable();
        });

        Schema::table('ai_mission_messages', function (Blueprint $table) {
            $table->text('attachment_text')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('ai_mission_messages', function (Blueprint $table) {
            $table->dropColumn('attachment_text');
        });

        Schema::table('ai_missions', function (Blueprint $table) {
            $table->dropColumn(['thread_token_cap', 'quick_prompts']);
        });
    }
};
