<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_missions', function (Blueprint $table) {
            $table->string('schedule')->nullable()->after('token_budget');
            $table->string('schedule_time', 5)->default('08:00')->after('schedule');
            $table->unsignedTinyInteger('schedule_weekday')->nullable()->after('schedule_time');
            $table->unsignedTinyInteger('schedule_day')->nullable()->after('schedule_weekday');
            $table->timestamp('next_run_at')->nullable()->after('schedule_day');
            $table->timestamp('last_scheduled_at')->nullable()->after('next_run_at');
            $table->string('run_trigger')->default('manual')->after('last_scheduled_at');
            $table->boolean('budget_alerted')->default(false)->after('run_trigger');
        });

        Schema::create('ai_mission_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained('ai_missions', 'id', 'aimrun_mission_fk')->cascadeOnDelete();
            $table->string('trigger')->default('manual');
            $table->unsignedTinyInteger('round')->default(1);
            $table->string('status');
            $table->text('summary')->nullable();
            $table->json('report')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['ai_mission_id', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mission_runs');

        Schema::table('ai_missions', function (Blueprint $table) {
            $table->dropColumn(['schedule', 'schedule_time', 'schedule_weekday', 'schedule_day', 'next_run_at', 'last_scheduled_at', 'run_trigger', 'budget_alerted']);
        });
    }
};
