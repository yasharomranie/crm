<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_mission_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_assistant_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('round')->default(1);
            $table->string('tool');
            $table->json('arguments');
            $table->string('summary', 300);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mission_actions');
    }
};
