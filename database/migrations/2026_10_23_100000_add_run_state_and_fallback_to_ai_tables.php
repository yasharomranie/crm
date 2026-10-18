<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('run_label', 120)->nullable()->after('pending_actions');
            $table->boolean('cancel_requested')->default(false)->after('run_label');
        });

        Schema::table('ai_providers', function (Blueprint $table) {
            $table->boolean('is_fallback')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn('is_fallback');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['run_label', 'cancel_requested']);
        });
    }
};
