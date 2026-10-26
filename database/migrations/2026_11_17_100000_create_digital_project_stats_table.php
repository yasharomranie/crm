<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_project_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_project_id')->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->unsignedBigInteger('value');
            $table->date('recorded_on');
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['digital_project_id', 'metric', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_project_stats');
    }
};
