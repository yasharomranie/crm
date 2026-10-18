<?php

use App\Domain\Seo\WpConnector\WpUrl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_wp_sites', function (Blueprint $table) {
            $table->boolean('search_engines_discouraged')->nullable()->after('has_woocommerce');
            $table->dateTime('analyzed_at')->nullable()->after('last_sync_at');
        });

        Schema::table('seo_wp_posts', function (Blueprint $table) {
            $table->unsignedInteger('inbound_links')->nullable()->after('content_hash');
            $table->unsignedInteger('gsc_clicks')->nullable()->after('inbound_links');
            $table->unsignedInteger('gsc_impressions')->nullable()->after('gsc_clicks');
            $table->decimal('gsc_ctr', 8, 4)->nullable()->after('gsc_impressions');
            $table->decimal('gsc_position', 8, 2)->nullable()->after('gsc_ctr');
            $table->unsignedInteger('gsc_prev_clicks')->nullable()->after('gsc_position');
            $table->json('issues')->nullable()->after('gsc_prev_clicks');
        });

        // url_hash now identifies a page the way Search Console rows are
        // matched to it (address without scheme, www, query or trailing slash).
        DB::table('seo_wp_posts')->orderBy('id')->select(['id', 'url'])->chunkById(500, function ($posts) {
            foreach ($posts as $post) {
                DB::table('seo_wp_posts')->where('id', $post->id)->update(['url_hash' => WpUrl::key($post->url)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_wp_posts', function (Blueprint $table) {
            $table->dropColumn(['inbound_links', 'gsc_clicks', 'gsc_impressions', 'gsc_ctr', 'gsc_position', 'gsc_prev_clicks', 'issues']);
        });

        Schema::table('seo_wp_sites', function (Blueprint $table) {
            $table->dropColumn(['search_engines_discouraged', 'analyzed_at']);
        });
    }
};
