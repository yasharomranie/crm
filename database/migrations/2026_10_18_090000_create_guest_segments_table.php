<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('match_type')->default('all');
            $table->json('conditions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_segments');
    }
};
