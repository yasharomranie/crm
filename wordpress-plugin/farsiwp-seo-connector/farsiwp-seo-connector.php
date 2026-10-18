<?php

/**
 * Plugin Name:       اتصال CRM سئو (FarsiWP SEO Connector)
 * Description:       فهرست محتوای سایت را با اجازه‌ی شما به مرکز سئوی CRM می‌فرستد تا کلمات کلیدی و محتوا را هوشمندتر پیشنهاد دهد. فقط می‌خواند و متن کامل نوشته‌ها را نمی‌فرستد.
 * Version:           1.0.1
 * Requires PHP:      7.4
 * Requires at least: 5.3
 * Author:            FarsiWP
 * Text Domain:       farsiwp-seo-connector
 */

use FarsiWP\SeoConnector\Admin;
use FarsiWP\SeoConnector\Settings;
use FarsiWP\SeoConnector\Sync;
use FarsiWP\SeoConnector\Updater;

if (! defined('ABSPATH')) {
    exit;
}

define('FSWC_VERSION', '1.0.1');
define('FSWC_FILE', __FILE__);

foreach (['Signer', 'HtmlAnalyzer', 'Settings', 'Collector', 'Client', 'Sync', 'Updater', 'Admin'] as $class) {
    require_once __DIR__.'/includes/'.$class.'.php';
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['fswc_ten_minutes'] = ['interval' => 600, 'display' => 'هر ۱۰ دقیقه'];

    return $schedules;
});

register_activation_hook(__FILE__, function () {
    if (! wp_next_scheduled('fswc_cron_sync')) {
        wp_schedule_event(time() + 120, 'fswc_ten_minutes', 'fswc_cron_sync');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('fswc_cron_sync');
    wp_clear_scheduled_hook('fswc_sync_soon');
});

add_action('fswc_cron_sync', function () {
    Sync::run(false);
});

add_action('fswc_sync_soon', function () {
    Sync::run(false);
});

// A saved post is sent within about half a minute instead of waiting for the
// next regular run.
add_action('save_post', function ($postId, $post) {
    if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) || ! Settings::isConnected()) {
        return;
    }

    if (in_array($post->post_type, Settings::get()['post_types'], true) && ! wp_next_scheduled('fswc_sync_soon')) {
        wp_schedule_single_event(time() + 30, 'fswc_sync_soon');
    }
}, 20, 2);

add_action('before_delete_post', function ($postId) {
    if (Settings::isConnected()) {
        Sync::queueDeletion($postId);

        if (! wp_next_scheduled('fswc_sync_soon')) {
            wp_schedule_single_event(time() + 30, 'fswc_sync_soon');
        }
    }
});

// A post that stops being an allowed status (trashed, back to draft when
// drafts are not shared) leaves the CRM's inventory too.
add_action('transition_post_status', function ($new, $old, $post) {
    if (! Settings::isConnected() || ! in_array($post->post_type, Settings::get()['post_types'], true)) {
        return;
    }

    $allowed = Settings::get()['include_unpublished']
        ? array_merge(Sync::PUBLIC_STATUSES, Sync::UNPUBLISHED_STATUSES)
        : Sync::PUBLIC_STATUSES;

    if (! in_array($new, $allowed, true) && in_array($old, $allowed, true)) {
        Sync::queueDeletion($post->ID);

        if (! wp_next_scheduled('fswc_sync_soon')) {
            wp_schedule_single_event(time() + 30, 'fswc_sync_soon');
        }
    }
}, 10, 3);

// Where WP-Cron rarely runs (little traffic), an administrator opening the
// dashboard catches the plugin up.
add_action('admin_init', function () {
    if (Settings::isConnected() && time() - (int) Settings::state()['last_heartbeat'] > 1800 && ! get_transient('fswc_admin_tick')) {
        set_transient('fswc_admin_tick', 1, 900);
        add_action('shutdown', function () {
            Sync::run(false);
        });
    }
});

Updater::register();

if (is_admin()) {
    Admin::register();
}
