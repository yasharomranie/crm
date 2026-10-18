<?php

namespace FarsiWP\SeoConnector;

/**
 * Keeps the plugin up to date from the CRM instead of wordpress.org: WordPress
 * asks its normal update question, and this answers it with the version the
 * CRM serves. With many sites, this is what saves reinstalling a zip on each.
 */
class Updater
{
    const TRANSIENT = 'fswc_update_info';

    const SLUG = 'farsiwp-seo-connector';

    public static function register()
    {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'inject']);
        add_filter('plugins_api', [self::class, 'info'], 20, 3);
        add_filter('auto_update_plugin', [self::class, 'autoUpdate'], 10, 2);
    }

    /**
     * @param  object  $transient
     * @return object
     */
    public static function inject($transient)
    {
        if (! is_object($transient) || ! Settings::isConnected()) {
            return $transient;
        }

        $info = self::latest();

        if ($info && ! empty($info['update_available']) && version_compare($info['version'], FSWC_VERSION, '>')) {
            $basename = plugin_basename(FSWC_FILE);

            $transient->response[$basename] = (object) [
                'id' => $basename,
                'slug' => self::SLUG,
                'plugin' => $basename,
                'new_version' => $info['version'],
                'package' => $info['package'],
                'url' => '',
                'requires_php' => isset($info['requires_php']) ? $info['requires_php'] : '7.4',
            ];
        }

        return $transient;
    }

    /**
     * The "view details" window of the update notice.
     *
     * @param  false|object|array  $result
     * @param  string  $action
     * @param  object  $args
     * @return false|object|array
     */
    public static function info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ! isset($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $info = self::latest();

        return (object) [
            'name' => 'اتصال CRM سئو',
            'slug' => self::SLUG,
            'version' => $info ? $info['version'] : FSWC_VERSION,
            'author' => 'FarsiWP',
            'sections' => ['description' => 'فهرست محتوای سایت را با اجازه‌ی شما به مرکز سئوی CRM می‌فرستد. فقط می‌خواند.'],
            'download_link' => $info && isset($info['package']) ? $info['package'] : '',
        ];
    }

    /**
     * @param  bool|null  $update
     * @param  object  $item
     * @return bool|null
     */
    public static function autoUpdate($update, $item)
    {
        if (isset($item->plugin) && $item->plugin === plugin_basename(FSWC_FILE)) {
            return (bool) Settings::get()['auto_update'];
        }

        return $update;
    }

    /**
     * What the CRM says is newest, asked at most every six hours (and not
     * again for half an hour after a failure).
     *
     * @return array<string, mixed>|null
     */
    private static function latest()
    {
        $cached = get_transient(self::TRANSIENT);

        if ($cached !== false) {
            return is_array($cached) ? $cached : null;
        }

        $response = Client::send('/update-check', ['current_version' => FSWC_VERSION]);

        if ($response['code'] === 200 && isset($response['body']['version'], $response['body']['package'])) {
            set_transient(self::TRANSIENT, $response['body'], 6 * HOUR_IN_SECONDS);

            return $response['body'];
        }

        set_transient(self::TRANSIENT, 'unavailable', 30 * MINUTE_IN_SECONDS);

        return null;
    }
}
