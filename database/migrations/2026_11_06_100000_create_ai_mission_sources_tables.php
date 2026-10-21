<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_mission_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('extension', 10);
            $table->string('kind')->default('policy');
            $table->string('path')->nullable();
            $table->string('content_hash', 40)->nullable();
            $table->unsignedInteger('char_count')->default(0);
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->longText('content')->nullable();
            $table->json('digest')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_mission_source_mission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_mission_source_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ai_mission_id', 'ai_mission_source_id'], 'ai_mission_source_unique');
        });

        Schema::table('ai_missions', function (Blueprint $table) {
            $table->json('brief')->nullable()->after('notes');
            $table->string('brief_status')->default('none')->after('brief');
            $table->string('brief_sources_hash', 40)->nullable()->after('brief_status');
            $table->text('brief_error')->nullable()->after('brief_sources_hash');
            $table->timestamp('brief_approved_at')->nullable()->after('brief_error');
        });
    }

    public function down(): void
    {
        Schema::table('ai_missions', function (Blueprint $table) {
            $table->dropColumn(['brief', 'brief_status', 'brief_sources_hash', 'brief_error', 'brief_approved_at']);
        });

        Schema::dropIfExists('ai_mission_source_mission');
        Schema::dropIfExists('ai_mission_sources');
    }
};
