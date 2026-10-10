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
        Schema::table('ai_assistant_profiles', function (Blueprint $table) {
            // When false (default), a knowledge entry the assistant saves
            // during a conversation lands as "pending" for the creator to
            // review before it's ever used in a future chat.
            $table->boolean('knowledge_auto_save')->default(false)->after('model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_assistant_profiles', function (Blueprint $table) {
            $table->dropColumn('knowledge_auto_save');
        });
    }
};
