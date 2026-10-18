<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('prompt');
            $table->string('run_at_time', 5)->default('08:00');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('last_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'last_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scheduled_reports');
    }
};
