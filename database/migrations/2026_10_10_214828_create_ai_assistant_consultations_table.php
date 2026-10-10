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
        // Idempotent: on a host where an earlier deploy attempt already
        // got this far before failing on the (now-fixed) foreign key
        // names below, the table exists without ever being recorded as
        // migrated - drop it first so this always starts clean. Safe
        // everywhere, since nothing is ever written here before this
        // migration itself finishes successfully.
        Schema::dropIfExists('ai_assistant_consultations');

        Schema::create('ai_assistant_consultations', function (Blueprint $table) {
            $table->id();
            // Explicit short names: the auto-generated ones for these two
            // columns (e.g. ai_assistant_consultations_consulting_ai_
            // assistant_profile_id_foreign) exceed MySQL's 64-char
            // identifier limit - this doesn't show up on SQLite (no such
            // limit), which is why it only failed in production.
            $table->foreignId('consulting_ai_assistant_profile_id')
                ->constrained('ai_assistant_profiles', 'id', 'aac_consulting_profile_fk')
                ->cascadeOnDelete();
            $table->foreignId('consulted_ai_assistant_profile_id')
                ->constrained('ai_assistant_profiles', 'id', 'aac_consulted_profile_fk')
                ->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()
                ->constrained('ai_conversations', 'id', 'aac_conversation_fk')
                ->nullOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_consultations');
    }
};
