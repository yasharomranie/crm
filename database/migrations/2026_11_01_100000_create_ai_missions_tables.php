<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('goal');
            $table->text('end_condition')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('max_rounds')->default(3);
            $table->unsignedTinyInteger('rounds_run')->default(0);
            $table->json('report')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_mission_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained('ai_missions', 'id', 'aimr_mission_fk')->cascadeOnDelete();
            $table->foreignId('ai_assistant_profile_id')->constrained('ai_assistant_profiles', 'id', 'aimr_profile_fk')->cascadeOnDelete();
            $table->string('role');
            $table->text('directive')->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('ai_mission_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained('ai_missions', 'id', 'aimc_mission_fk')->cascadeOnDelete();
            $table->foreignId('ai_assistant_profile_id')->nullable()->constrained('ai_assistant_profiles', 'id', 'aimc_profile_fk')->nullOnDelete();
            $table->string('role');
            $table->unsignedTinyInteger('round');
            $table->string('kind');
            $table->unsignedBigInteger('target_card_id')->nullable();
            $table->text('claim');
            $table->string('source')->nullable();
            $table->string('confidence')->default('medium');
            $table->boolean('unsourced')->default(false);
            $table->timestamps();

            $table->index(['ai_mission_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mission_cards');
        Schema::dropIfExists('ai_mission_roles');
        Schema::dropIfExists('ai_missions');
    }
};
