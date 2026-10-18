<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The app sets Schema::defaultStringLength(191), so "slug" and "site_url"
 * were created as VARCHAR(191) - but a WordPress slug can be 200 characters
 * and the plugin's address up to 255, which made a whole batch of pages fail
 * with "Data too long for column 'slug'" on MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('seo_wp_posts', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->change();
        });

        Schema::table('seo_wp_sites', function (Blueprint $table) {
            $table->string('site_url', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        //
    }
};
