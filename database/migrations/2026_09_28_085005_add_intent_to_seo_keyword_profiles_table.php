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
        Schema::table('seo_keyword_profiles', function (Blueprint $table) {
            $table->string('intent')->nullable()->after('worst_position');
            $table->dateTime('classified_at')->nullable()->after('last_analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_keyword_profiles', function (Blueprint $table) {
            $table->dropColumn(['intent', 'classified_at']);
        });
    }
};
