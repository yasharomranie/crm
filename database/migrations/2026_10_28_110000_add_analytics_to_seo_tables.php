<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_google_connections', function (Blueprint $table) {
            $table->json('ga_summary')->nullable();
            $table->timestamp('ga_synced_at')->nullable();
            $table->string('ga_last_error', 255)->nullable();
        });

        Schema::table('seo_wp_posts', function (Blueprint $table) {
            $table->unsignedInteger('ga_sessions')->nullable();
            $table->unsignedInteger('ga_key_events')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_wp_posts', function (Blueprint $table) {
            $table->dropColumn(['ga_sessions', 'ga_key_events']);
        });

        Schema::table('seo_google_connections', function (Blueprint $table) {
            $table->dropColumn(['ga_summary', 'ga_synced_at', 'ga_last_error']);
        });
    }
};
