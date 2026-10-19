<?php

namespace FarsiWP\SeoConnector;

/**
 * Sends the content inventory to the CRM in small batches: posts changed
 * since the last acknowledged one (oldest first), plus the ids of posts that
 * disappeared. The cursor only moves when the CRM confirms a batch, so a
 * failed or interrupted run simply continues from the same place.
 */
class Sync
{
    const BATCH_SIZE = 50;

    const HEARTBEAT_EVERY = 1800;

    const LOCK = 'fswc_sync_lock';

    const PUBLIC_STATUSES = ['publish'];

    const UNPUBLISHED_STATUSES = ['draft', 'pending', 'future', 'private'];

    /**
     * @param  bool  $force  ignore the time window and any back-off (the "sync now" button)
     * @return string a short Persian result for the screen
     */
    public static function run($force = false)
    {
        if (! Settings::isConnected()) {
            return 'سایت به CRM وصل نیست.';
        }

        $state = Settings::state();

        if (! $force && (time() < $state['next_allowed'] || ! self::insideWindow())) {
            return 'در بازه‌ی مجاز یا زمان مناسب نیست.';
        }

        if (get_transient(self::LOCK)) {
            return 'یک همگام‌سازی دیگر در حال اجراست.';
        }

        set_transient(self::LOCK, 1, 120);
        $started = time();
        $sent = 0;
        $message = 'انجام شد.';

        try {
            $maxBatches = max(1, (int) Settings::get()['max_batches_per_run']);

            for ($batch = 0; $batch < $maxBatches && time() - $started < 30; $batch++) {
                $result = self::sendBatch();

                if ($result['status'] === 'error') {
                    $message = $result['message'];
                    break;
                }

                $sent += $result['posts'];

                if ($result['status'] === 'done') {
                    break;
                }
            }
        } finally {
            delete_transient(self::LOCK);
        }

        return $sent > 0 ? "{$sent} مورد برای CRM فرستاده شد. {$message}" : $message;
    }

    /**
     * @return array{status: string, posts: int, message: string} status is more|done|error
     */
    private static function sendBatch()
    {
        $state = Settings::state();
        $ids = self::nextPostIds($state);
        $deleted = array_slice($state['deleted_queue'], 0, 200);
        $posts = [];

        foreach ($ids as $id) {
            $post = get_post($id);

            if ($post && ! self::isExcluded($post)) {
                $posts[] = Collector::collect($post, ! in_array($post->post_status, Sync::PUBLIC_STATUSES, true));
            }
        }

        $heartbeatDue = time() - $state['last_heartbeat'] >= self::HEARTBEAT_EVERY;

        if ($posts === [] && $deleted === [] && $ids === [] && ! $heartbeatDue) {
            return ['status' => 'done', 'posts' => 0, 'message' => 'چیز تازه‌ای نبود.'];
        }

        $payload = [
            'site' => Collector::siteInfo(),
            'posts' => $posts,
            'deleted' => $deleted,
        ];

        if ($heartbeatDue) {
            $payload['report'] = Collector::siteReport();
        }

        $response = Client::send('/sync', $payload);

        if ($response['code'] === 200) {
            self::advance($ids, $deleted, count($posts));

            return [
                'status' => count($ids) >= self::BATCH_SIZE || count($deleted) >= 200 ? 'more' : 'done',
                'posts' => count($posts),
                'message' => '',
            ];
        }

        return self::fail($response);
    }

    /**
     * @return array<int, int> ids of posts modified after the cursor, oldest first
     */
    private static function nextPostIds(array $state)
    {
        global $wpdb;

        $settings = Settings::get();
        $types = array_values(array_filter($settings['post_types'], 'post_type_exists'));

        if ($types === []) {
            return [];
        }

        $statuses = $settings['include_unpublished']
            ? array_merge(self::PUBLIC_STATUSES, self::UNPUBLISHED_STATUSES)
            : self::PUBLIC_STATUSES;

        $typePlaceholders = implode(',', array_fill(0, count($types), '%s'));
        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '%s'));

        $sql = "SELECT ID FROM {$wpdb->posts}
                WHERE post_type IN ({$typePlaceholders}) AND post_status IN ({$statusPlaceholders})
                AND (post_modified_gmt > %s OR (post_modified_gmt = %s AND ID > %d))
                ORDER BY post_modified_gmt ASC, ID ASC LIMIT %d";

        $args = array_merge($types, $statuses, [$state['cursor_modified'], $state['cursor_modified'], $state['cursor_id'], self::BATCH_SIZE]);

        return array_map('intval', $wpdb->get_col($wpdb->prepare($sql, $args)));
    }

    private static function advance(array $ids, array $deleted, $postsSent)
    {
        global $wpdb;

        $changes = [
            'deleted_queue' => array_values(array_diff(Settings::state()['deleted_queue'], $deleted)),
            'last_sync' => time(),
            'last_heartbeat' => time(),
            'last_error' => '',
            'next_allowed' => 0,
            'failures' => 0,
        ];

        if ($ids !== []) {
            $lastId = end($ids);
            $changes['cursor_id'] = $lastId;
            $changes['cursor_modified'] = (string) $wpdb->get_var($wpdb->prepare("SELECT post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $lastId));
        }

        Settings::saveState($changes);
        Settings::log('sync', "{$postsSent} مورد، ".count($deleted).' حذف');
    }

    /**
     * @param  array{code: int, body: ?array, error: ?string, retry_after: ?int}  $response
     * @return array{status: string, posts: int, message: string}
     */
    private static function fail(array $response)
    {
        $code = isset($response['body']['error']['code']) ? $response['body']['error']['code'] : '';
        $message = $response['error'] ?: 'ارسال به CRM ناموفق بود.';

        if ($response['code'] === 401 && in_array($code, ['UNAUTHORIZED', 'BAD_SIGNATURE'], true)) {
            Settings::saveState(['unauthorized' => true, 'last_error' => 'اتصال از سمت CRM قطع یا نامعتبر شده؛ دوباره وصل کنید.']);
        } else {
            $failures = Settings::state()['failures'] + 1;
            $wait = $response['retry_after'] ?: min(3600, 300 * (2 ** min($failures, 4)));
            Settings::saveState(['next_allowed' => time() + $wait, 'last_error' => $message, 'failures' => $failures]);
        }

        Settings::log('error', $message);

        return ['status' => 'error', 'posts' => 0, 'message' => $message];
    }

    private static function insideWindow()
    {
        $settings = Settings::get();
        $hour = (int) wp_date('G');

        return $hour >= (int) $settings['window_start'] && $hour < (int) $settings['window_end'];
    }

    public static function isExcluded($post)
    {
        $settings = Settings::get();

        if (in_array((int) $post->ID, array_map('intval', $settings['excluded_post_ids']), true)) {
            return true;
        }

        if ($settings['excluded_term_ids']) {
            foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']);

                if (! is_wp_error($terms) && array_intersect(array_map('intval', $settings['excluded_term_ids']), $terms)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Remembers a post that left the inventory (deleted, trashed, or no
     * longer an allowed status) so the CRM can drop it.
     */
    public static function queueDeletion($postId)
    {
        $queue = Settings::state()['deleted_queue'];

        if (! in_array((int) $postId, $queue, true)) {
            $queue[] = (int) $postId;
            Settings::saveState(['deleted_queue' => $queue]);
        }
    }

    public static function resetCursor()
    {
        Settings::saveState(['cursor_modified' => '1970-01-01 00:00:00', 'cursor_id' => 0, 'next_allowed' => 0]);
    }
}
