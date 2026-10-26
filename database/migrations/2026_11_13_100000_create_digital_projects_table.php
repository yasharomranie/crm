<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_projects', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->index();
            $table->string('name', 150);
            $table->string('handle', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('planning')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_review_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_projects');
    }
};
