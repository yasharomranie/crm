<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_missions', function (Blueprint $table) {
            $table->boolean('cancel_requested')->default(false)->after('last_error');
            $table->string('current_step')->nullable()->after('cancel_requested');
            $table->unsignedBigInteger('current_profile_id')->nullable()->after('current_step');
            $table->unsignedTinyInteger('step_index')->default(0)->after('current_profile_id');
            $table->unsignedTinyInteger('step_total')->default(0)->after('step_index');
            $table->unsignedInteger('tokens_used')->default(0)->after('step_total');
            $table->unsignedInteger('token_budget')->nullable()->after('tokens_used');
        });
    }

    public function down(): void
    {
        Schema::table('ai_missions', function (Blueprint $table) {
            $table->dropColumn(['cancel_requested', 'current_step', 'current_profile_id', 'step_index', 'step_total', 'tokens_used', 'token_budget']);
        });
    }
};
