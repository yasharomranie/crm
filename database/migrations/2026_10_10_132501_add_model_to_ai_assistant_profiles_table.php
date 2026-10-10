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
        Schema::table('ai_assistant_profiles', function (Blueprint $table) {
            // Null = use the active provider's own default_model.
            $table->string('model')->nullable()->after('avatar_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_assistant_profiles', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
