<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_phases')->cascadeOnDelete();
            $table->foreignId('predecessor_id')->nullable()->constrained('project_phases')->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('activity');
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phases');
    }
};
