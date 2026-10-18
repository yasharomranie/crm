<?php

namespace FarsiWP\SeoConnector;

/**
 * Everything the site owner controls (what may be sent) and the connection
 * state (who we are talking to, how far we got). Two options: settings the
 * owner edits, state the plugin maintains.
 */
class Settings
{
    const OPTION = 'fswc_settings';

    const STATE = 'fswc_state';

    /** What each scope lets the plugin send. 'content' is the basic inventory and is required. */
    const SCOPES = [
        'content' => 'فهرست محتوا (عنوان، آدرس، وضعیت، تاریخ‌ها، تعداد کلمات)',
        'seo_meta' => 'متای سئو (کلمه کلیدی کانونی، عنوان و توضیح سئو، canonical، noindex) از Yoast و Rank Math',
        'structure' => 'تیترها و لینک‌های داخل متن (داخلی و خارجی)',
        'images' => 'تعداد تصاویر و تصاویر بدون متن جایگزین (alt)',
        'terms' => 'دسته‌ها و برچسب‌ها',
        'authors' => 'نام نویسنده',
        'commerce' => 'اطلاعات محصول ووکامرس (قیمت، موجودی، امتیاز) — فقط اگر ووکامرس نصب باشد',
        'health' => 'وضعیت سلامت سایت (مثلاً روشن بودن «جلوگیری از ایندکس‌شدن توسط موتورهای جست‌وجو»)',
    ];

    public static function defaults()
    {
        return [
            'crm_url' => '',
            'scopes' => ['content', 'seo_meta', 'structure', 'images', 'terms', 'health'],
            'post_types' => ['post', 'page'],
            'include_unpublished' => false,
            'excluded_post_ids' => [],
            'excluded_term_ids' => [],
            'window_start' => 0,
            'window_end' => 24,
            'max_batches_per_run' => 5,
        ];
    }

    public static function get()
    {
        $stored = get_option(self::OPTION, []);

        return array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    public static function save(array $settings)
    {
        update_option(self::OPTION, array_merge(self::get(), $settings), false);
    }

    public static function state()
    {
        $state = get_option(self::STATE, []);

        return array_merge([
            'site_id' => 0,
            'secret' => '',
            'cursor_modified' => '1970-01-01 00:00:00',
            'cursor_id' => 0,
            'deleted_queue' => [],
            'next_allowed' => 0,
            'failures' => 0,
            'last_sync' => 0,
            'last_heartbeat' => 0,
            'last_error' => '',
            'unauthorized' => false,
            'log' => [],
        ], is_array($state) ? $state : []);
    }

    public static function saveState(array $changes)
    {
        update_option(self::STATE, array_merge(self::state(), $changes), false);
    }

    public static function isConnected()
    {
        $state = self::state();

        return $state['site_id'] > 0 && $state['secret'] !== '' && ! $state['unauthorized'];
    }

    public static function allows($scope)
    {
        return in_array($scope, self::get()['scopes'], true);
    }

    /**
     * Adds a line to the transparency log (the last 25 contacts with the CRM).
     */
    public static function log($kind, $detail)
    {
        $log = self::state()['log'];
        array_unshift($log, ['time' => time(), 'kind' => $kind, 'detail' => $detail]);
        self::saveState(['log' => array_slice($log, 0, 25)]);
    }
}
