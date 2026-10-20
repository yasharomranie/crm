<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->json('snapshot');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_closings');
    }
};
