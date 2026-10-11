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
        Schema::create('whats_app_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_profile_id')
                ->constrained('ai_assistant_profiles', 'id', 'wac_profile_fk')
                ->cascadeOnDelete();
            $table->string('wa_id');
            $table->string('contact_name')->nullable();
            $table->timestamps();

            $table->unique(['ai_assistant_profile_id', 'wa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whats_app_conversations');
    }
};
