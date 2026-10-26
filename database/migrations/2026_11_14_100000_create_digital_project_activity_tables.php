<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_project_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('details')->nullable();
            $table->string('status')->default('open')->index();
            $table->date('due_on')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('digital_project_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_project_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('done');
            $table->text('body');
            $table->date('logged_on');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['digital_project_id', 'logged_on']);
        });

        Schema::create('digital_project_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_project_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('note', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_project_status_changes');
        Schema::dropIfExists('digital_project_logs');
        Schema::dropIfExists('digital_project_actions');
    }
};
