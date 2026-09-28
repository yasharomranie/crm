<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real Search Console page URL (long query strings, encoded
     * parameters) can exceed the 255-char VARCHAR these were created
     * with, and the raw URL is copied unchanged into landing_page/
     * subject_page_url downstream - so a single long URL from Google
     * breaks the sync with a "Data too long" error. TEXT has no such
     * practical limit.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite has no fixed-length varchar/text distinction (TEXT
            // and VARCHAR are both stored as unlimited-length TEXT
            // internally), so there's nothing to actually widen here -
            // this only matters for MySQL/PostgreSQL in production.
            return;
        }

        DB::statement('ALTER TABLE seo_page_metrics MODIFY page_url TEXT NOT NULL');
        DB::statement('ALTER TABLE seo_query_page_metrics MODIFY page_url TEXT NOT NULL');
        DB::statement('ALTER TABLE seo_keyword_profiles MODIFY landing_page TEXT NULL');
        DB::statement('ALTER TABLE seo_opportunities MODIFY subject_page_url TEXT NULL');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE seo_page_metrics MODIFY page_url VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE seo_query_page_metrics MODIFY page_url VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE seo_keyword_profiles MODIFY landing_page VARCHAR(255) NULL');
        DB::statement('ALTER TABLE seo_opportunities MODIFY subject_page_url VARCHAR(255) NULL');
    }
};
