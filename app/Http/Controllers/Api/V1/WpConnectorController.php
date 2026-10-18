<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Seo\WpConnector\WpConnectorException;
use App\Domain\Seo\WpConnector\WpPairingService;
use App\Domain\Seo\WpConnector\WpPluginPackage;
use App\Domain\Seo\WpConnector\WpPostIngestor;
use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeWpSiteJob;
use App\Models\SeoWpSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The CRM end of the WordPress connector plugin: pair() trades the one-time
 * code for a signing secret, sync() takes batches of content (signed, see
 * AuthenticateWpConnector), disconnect() lets the site owner cut the link.
 */
class WpConnectorController extends Controller
{
    /** Seconds the plugin should wait before its next regular sync. */
    private const SYNC_INTERVAL_SECONDS = 600;

    public function pair(Request $request, WpPairingService $pairing): JsonResponse
    {
        // Plugin 1.0.0 sends the address as "url" (as it does in every sync).
        if (! $request->filled('site_url') && $request->filled('url')) {
            $request->merge(['site_url' => $request->input('url')]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'site_url' => ['required', 'string', 'max:255', 'regex:#^https?://#'],
            'plugin_version' => ['nullable', 'string', 'max:20'],
            'wp_version' => ['nullable', 'string', 'max:20'],
            'php_version' => ['nullable', 'string', 'max:20'],
            'seo_plugin' => ['nullable', Rule::in(['yoast', 'rank_math', 'none'])],
            'woocommerce' => ['nullable', 'boolean'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in(WpPostIngestor::SCOPES)],
        ]);

        try {
            $result = $pairing->pair($data['code'], $data);
        } catch (WpConnectorException $exception) {
            return response()->json(['error' => ['code' => 'INVALID_CODE', 'message' => $exception->getMessage()]], 422);
        }

        return response()->json([
            'site_id' => $result['site']->id,
            'secret' => $result['secret'],
            'sync_interval_seconds' => self::SYNC_INTERVAL_SECONDS,
        ]);
    }

    public function sync(Request $request, WpPostIngestor $ingestor): JsonResponse
    {
        /** @var SeoWpSite $site */
        $site = $request->attributes->get('seo_wp_site');

        $data = $request->validate([
            'site' => ['nullable', 'array'],
            'site.url' => ['nullable', 'string', 'max:255', 'regex:#^https?://#'],
            'site.plugin_version' => ['nullable', 'string', 'max:20'],
            'site.wp_version' => ['nullable', 'string', 'max:20'],
            'site.php_version' => ['nullable', 'string', 'max:20'],
            'site.seo_plugin' => ['nullable', Rule::in(['yoast', 'rank_math', 'none'])],
            'site.woocommerce' => ['nullable', 'boolean'],
            'site.search_engines_discouraged' => ['nullable', 'boolean'],
            'site.scopes' => ['nullable', 'array'],
            'site.scopes.*' => ['string', Rule::in(WpPostIngestor::SCOPES)],
            'posts' => ['nullable', 'array', 'max:100'],
            'posts.*.wp_id' => ['required', 'integer', 'min:1'],
            'posts.*.post_type' => ['required', 'string', 'max:40'],
            'posts.*.status' => ['required', 'string', 'max:20'],
            'posts.*.url' => ['required', 'string', 'max:2000', 'regex:#^https?://#'],
            'posts.*.title' => ['nullable', 'string'],
            'posts.*.slug' => ['nullable', 'string', 'max:255'],
            'posts.*.published_at' => ['nullable', 'string'],
            'posts.*.modified_at' => ['nullable', 'string'],
            'posts.*.word_count' => ['nullable', 'integer', 'min:0'],
            'deleted' => ['nullable', 'array', 'max:500'],
            'deleted.*' => ['integer', 'min:1'],
            'cursor' => ['nullable', 'string', 'max:100'],
        ]);

        $scopes = $data['site']['scopes'] ?? $site->scopes ?? [];

        if (! in_array('content', $scopes, true)) {
            return response()->json(['error' => ['code' => 'SCOPE_REQUIRED', 'message' => 'دسترسی «فهرست محتوا» در تنظیمات پلاگین باید روشن باشد.']], 422);
        }

        $info = $data['site'] ?? [];
        $site->update(array_filter([
            'site_url' => $info['url'] ?? null,
            'plugin_version' => $info['plugin_version'] ?? null,
            'wp_version' => $info['wp_version'] ?? null,
            'php_version' => $info['php_version'] ?? null,
            'seo_plugin' => $info['seo_plugin'] ?? null,
            'search_engines_discouraged' => isset($info['search_engines_discouraged']) ? (bool) $info['search_engines_discouraged'] : null,
        ], fn ($value) => $value !== null) + [
            'scopes' => $scopes,
            'has_woocommerce' => (bool) ($info['woocommerce'] ?? $site->has_woocommerce),
            'last_seen_at' => now(),
        ]);

        $result = $ingestor->ingest($site, $request->input('posts', []), array_map('intval', $data['deleted'] ?? []));

        $site->update(['last_sync_at' => now(), 'last_error' => null]);

        if ($result['accepted'] > 0 || $result['deleted'] > 0) {
            AnalyzeWpSiteJob::dispatch($site->id)->delay(now()->addMinutes(2));
        }

        return response()->json($result + [
            'cursor' => $data['cursor'] ?? null,
            'next_sync_after_seconds' => self::SYNC_INTERVAL_SECONDS,
        ]);
    }

    /**
     * Tells a site whether a newer plugin exists, with a link WordPress can
     * download it from. The link is signed rather than secret: the zip holds
     * only the plugin's own code.
     */
    public function updateCheck(Request $request, WpPluginPackage $package): JsonResponse
    {
        $current = (string) $request->input('current_version', '0.0.0');
        $latest = $package->version();

        return response()->json([
            'version' => $latest,
            'update_available' => version_compare($latest, $current, '>'),
            'package' => URL::temporarySignedRoute('wp-connector.package', now()->addHours(48)),
            'requires_php' => '7.4',
        ]);
    }

    public function package(WpPluginPackage $package): BinaryFileResponse
    {
        return response()->download($package->zipPath(), WpPluginPackage::SLUG.'.zip', ['Content-Type' => 'application/zip']);
    }

    public function disconnect(Request $request, WpPairingService $pairing): JsonResponse
    {
        $pairing->disconnect($request->attributes->get('seo_wp_site'));

        return response()->json(['disconnected' => true]);
    }
}
