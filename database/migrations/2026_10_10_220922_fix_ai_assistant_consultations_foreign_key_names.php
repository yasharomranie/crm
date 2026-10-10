<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * The previous migration's auto-generated constraint names
     * (ai_assistant_consultations_consulting_ai_assistant_profile_id_foreign,
     * 69 chars, and the matching "consulted" one at 68) exceed MySQL's
     * 64-char identifier limit. On MySQL, that failed exactly one
     * "alter table add constraint" statement into the CREATE TABLE
     * already having committed (MySQL DDL isn't transactional) - so the
     * table exists there with no foreign keys at all, and the previous
     * migration was never recorded as run. Dropping and recreating here
     * is safe everywhere: on SQLite (where the 64-char limit doesn't
     * apply) the table already has its three foreign keys and no rows
     * yet, since this feature only just shipped; on MySQL it replaces
     * the broken, FK-less table left behind.
     */
    public function up(): void
    {
        Schema::dropIfExists('ai_assistant_consultations');

        Schema::create('ai_assistant_consultations', function (Blueprint $table) {
            $table->id();
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
