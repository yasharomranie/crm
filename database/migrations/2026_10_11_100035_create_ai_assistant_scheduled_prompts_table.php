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
        Schema::create('ai_assistant_scheduled_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_profile_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('run_at_time', 5);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_scheduled_prompts');
    }
};
