<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_mission_source_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('heading', 200)->nullable();
            $table->text('content');
            $table->timestamps();
            $table->unique(['ai_mission_source_id', 'position']);
        });

        Schema::create('ai_mission_role_source', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_mission_source_id')->constrained()->cascadeOnDelete();
            $table->unique(['ai_mission_role_id', 'ai_mission_source_id'], 'ai_mission_role_source_unique');
        });

        Schema::table('ai_mission_cards', function (Blueprint $table) {
            $table->foreignId('source_chunk_id')->nullable()->after('source')->constrained('ai_mission_source_chunks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_mission_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_chunk_id');
        });

        Schema::dropIfExists('ai_mission_role_source');
        Schema::dropIfExists('ai_mission_source_chunks');
    }
};
