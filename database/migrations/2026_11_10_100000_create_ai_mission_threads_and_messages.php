<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_mission_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('idle');
            $table->string('stage')->nullable();
            $table->json('plan')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_mission_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_assistant_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable();
            $table->string('kind');
            $table->text('body');
            $table->unsignedInteger('tokens')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mission_messages');
        Schema::dropIfExists('ai_mission_threads');
    }
};
