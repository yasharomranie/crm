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
        Schema::create('ai_assistant_ocr_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('status')->default('pending');
            $table->unsignedInteger('note_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_ocr_uploads');
    }
};
