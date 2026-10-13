<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_sitemap_entries', function (Blueprint $table) {
            $table->foreignId('topic_cluster_id')->nullable()->after('title')->constrained('seo_topic_clusters')->nullOnDelete();
            $table->timestamp('classified_at')->nullable()->after('topic_cluster_id');
        });
    }

    public function down(): void
    {
        Schema::table('seo_sitemap_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_cluster_id');
            $table->dropColumn('classified_at');
        });
    }
};
