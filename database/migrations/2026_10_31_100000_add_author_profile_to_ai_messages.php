<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->foreignId('ai_assistant_profile_id')->nullable()->after('ai_conversation_id')->constrained('ai_assistant_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_assistant_profile_id');
        });
    }
};
