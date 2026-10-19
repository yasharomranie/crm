<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('fswc_cron_sync');
wp_clear_scheduled_hook('fswc_sync_soon');
delete_option('fswc_settings');
delete_option('fswc_state');
delete_transient('fswc_sync_lock');
delete_transient('fswc_admin_tick');
delete_option('fswc_not_found');
delete_option('fswc_searches');
