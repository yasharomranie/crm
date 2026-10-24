<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_mission_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('ai_mission_messages')->nullOnDelete();
            $table->text('text');
            $table->timestamps();
        });

        Schema::table('ai_mission_messages', function (Blueprint $table) {
            $table->tinyInteger('rating')->nullable()->after('tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_mission_messages', function (Blueprint $table) {
            $table->dropColumn('rating');
        });

        Schema::dropIfExists('ai_mission_pins');
    }
};
