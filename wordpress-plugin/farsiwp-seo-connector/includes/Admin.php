<?php

namespace FarsiWP\SeoConnector;

/**
 * The settings screen (تنظیمات ← اتصال CRM سئو): connect with a code, choose
 * exactly what may be sent, see what was sent. Only administrators can open
 * it, and every action is protected by a nonce.
 */
class Admin
{
    const PAGE = 'fswc-settings';

    public static function register()
    {
        add_action('admin_menu', [self::class, 'menu']);

        foreach (['save', 'connect', 'disconnect', 'sync_now', 'resync'] as $action) {
            add_action("admin_post_fswc_{$action}", [self::class, "handle_{$action}"]);
        }
    }

    public static function menu()
    {
        add_options_page('اتصال CRM سئو', 'اتصال CRM سئو', 'manage_options', self::PAGE, [self::class, 'render']);
    }

    private static function guard()
    {
        if (! current_user_can('manage_options')) {
            wp_die('دسترسی ندارید.', 403);
        }

        check_admin_referer('fswc_action');
    }

    private static function back($notice, $type = 'success')
    {
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'fswc_notice' => rawurlencode($notice), 'fswc_type' => $type], admin_url('options-general.php')));
        exit;
    }

    public static function handle_connect()
    {
        self::guard();

        $crmUrl = esc_url_raw(trim(wp_unslash($_POST['crm_url'] ?? '')));
        $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));

        if ($crmUrl === '' || $code === '') {
            self::back('آدرس CRM و کد اتصال را وارد کنید.', 'error');
        }

        Settings::save(['crm_url' => $crmUrl]);

        $response = Client::send('/pair', ['code' => $code] + Collector::siteInfo(), false);

        if ($response['code'] !== 200 || empty($response['body']['secret'])) {
            self::back($response['error'] ?: 'اتصال برقرار نشد؛ آدرس CRM را بررسی کنید.', 'error');
        }

        Settings::saveState([
            'site_id' => (int) $response['body']['site_id'],
            'secret' => (string) $response['body']['secret'],
            'unauthorized' => false,
            'last_error' => '',
            'failures' => 0,
            'next_allowed' => 0,
        ]);
        Sync::resetCursor();
        Settings::log('connect', 'اتصال برقرار شد');

        self::back('اتصال برقرار شد. '.Sync::run(true));
    }

    public static function handle_disconnect()
    {
        self::guard();

        if (Settings::isConnected()) {
            Client::send('/disconnect', []);
        }

        Settings::saveState(['site_id' => 0, 'secret' => '', 'unauthorized' => false]);
        Settings::log('disconnect', 'اتصال قطع شد');
        self::back('اتصال قطع شد.');
    }

    public static function handle_sync_now()
    {
        self::guard();
        self::back(Sync::run(true));
    }

    public static function handle_resync()
    {
        self::guard();
        Sync::resetCursor();
        self::back('از ابتدا شروع شد. '.Sync::run(true));
    }

    public static function handle_save()
    {
        self::guard();

        $before = Settings::get();
        $scopes = array_values(array_intersect(array_keys(Settings::SCOPES), array_map('sanitize_key', (array) ($_POST['scopes'] ?? []))));
        $scopes[] = 'content';

        $settings = [
            'scopes' => array_values(array_unique($scopes)),
            'post_types' => array_values(array_filter(array_map('sanitize_key', (array) ($_POST['post_types'] ?? [])), 'post_type_exists')),
            'include_unpublished' => ! empty($_POST['include_unpublished']),
            'excluded_post_ids' => self::parseIds(wp_unslash($_POST['excluded_post_ids'] ?? '')),
            'excluded_term_ids' => array_values(array_filter(array_map('intval', (array) ($_POST['excluded_term_ids'] ?? [])))),
            'window_start' => max(0, min(23, (int) ($_POST['window_start'] ?? 0))),
            'window_end' => max(1, min(24, (int) ($_POST['window_end'] ?? 24))),
            'max_batches_per_run' => max(1, min(20, (int) ($_POST['max_batches_per_run'] ?? 5))),
        ];

        Settings::save($settings);
        self::dropNewlyExcluded($before, $settings);
        self::back('تنظیمات ذخیره شد.');
    }

    private static function parseIds($raw)
    {
        return array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,،]+/', (string) $raw)))));
    }

    /**
     * Posts the owner just excluded may already be in the CRM; ask it to
     * forget them.
     */
    private static function dropNewlyExcluded(array $before, array $after)
    {
        foreach (array_diff($after['excluded_post_ids'], array_map('intval', $before['excluded_post_ids'])) as $id) {
            Sync::queueDeletion($id);
        }

        $newTerms = array_diff($after['excluded_term_ids'], array_map('intval', $before['excluded_term_ids']));

        if ($newTerms === [] || $after['post_types'] === []) {
            return;
        }

        $posts = get_posts([
            'post_type' => $after['post_types'],
            'post_status' => 'any',
            'numberposts' => 5000,
            'fields' => 'ids',
            'tax_query' => [['taxonomy' => 'category', 'field' => 'term_id', 'terms' => array_values($newTerms)]],
        ]);

        foreach ($posts as $id) {
            Sync::queueDeletion($id);
        }
    }

    public static function render()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = Settings::get();
        $state = Settings::state();
        $connected = Settings::isConnected();
        $action = function ($name) {
            return esc_url(admin_url("admin-post.php?action=fswc_{$name}"));
        };

        echo '<div class="wrap" dir="rtl"><h1>اتصال CRM سئو</h1>';

        if (! empty($_GET['fswc_notice'])) {
            $type = ($_GET['fswc_type'] ?? '') === 'error' ? 'error' : 'success';
            echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html(rawurldecode(wp_unslash($_GET['fswc_notice']))).'</p></div>';
        }

        self::renderConnection($settings, $state, $connected, $action);
        self::renderSettings($settings, $action);
        self::renderTransparency($state);

        echo '</div>';
    }

    private static function renderConnection(array $settings, array $state, $connected, $action)
    {
        echo '<h2>اتصال</h2>';

        if ($state['unauthorized']) {
            echo '<div class="notice notice-error inline"><p>اتصال از سمت CRM قطع یا نامعتبر شده است. یک کد تازه از CRM بگیرید و دوباره وصل کنید.</p></div>';
        }

        if ($connected) {
            echo '<p>وضعیت: <strong>متصل</strong> به '.esc_html($settings['crm_url']).' — آخرین ارسال: '.esc_html($state['last_sync'] ? wp_date('Y/m/d H:i', $state['last_sync']) : 'هنوز نشده').'</p>';

            if ($state['last_error'] !== '') {
                echo '<p style="color:#b32d2e">آخرین خطا: '.esc_html($state['last_error']).'</p>';
            }

            $buttons = [['sync_now', 'همگام‌سازی همین حالا', 'primary'], ['resync', 'ارسال دوباره‌ی همه از ابتدا', 'secondary'], ['disconnect', 'قطع اتصال', 'delete']];

            foreach ($buttons as $button) {
                echo '<form method="post" action="'.$action($button[0]).'" style="display:inline-block;margin-inline-end:8px">';
                wp_nonce_field('fswc_action');
                submit_button($button[1], $button[2], 'submit', false);
                echo '</form>';
            }

            return;
        }

        echo '<p>در CRM به صفحه‌ی «پروژه‌ی سئو» همین سایت بروید و روی «اتصال وردپرس» بزنید تا یک کد بگیرید.</p>';
        echo '<form method="post" action="'.$action('connect').'">';
        wp_nonce_field('fswc_action');
        echo '<table class="form-table"><tr><th>آدرس CRM</th><td><input type="url" name="crm_url" class="regular-text" dir="ltr" placeholder="https://crm.example.com" value="'.esc_attr($settings['crm_url']).'" required></td></tr>';
        echo '<tr><th>کد اتصال</th><td><input type="text" name="code" class="regular-text" dir="ltr" placeholder="XXXX-XXXX-XXXX" required></td></tr></table>';
        submit_button('اتصال');
        echo '</form>';
    }

    private static function renderSettings(array $settings, $action)
    {
        echo '<h2>چه چیزی به CRM فرستاده شود؟</h2>';
        echo '<p>CRM هیچ‌وقت بیش از آنچه اینجا روشن می‌کنید دریافت نمی‌کند. متن کامل نوشته‌ها هرگز فرستاده نمی‌شود.</p>';
        echo '<form method="post" action="'.$action('save').'">';
        wp_nonce_field('fswc_action');

        echo '<h3>نوع اطلاعات</h3>';

        foreach (Settings::SCOPES as $key => $label) {
            $locked = $key === 'content';
            $disabled = $key === 'commerce' && ! class_exists('WooCommerce');
            printf(
                '<p><label><input type="checkbox" name="scopes[]" value="%s" %s %s> %s</label></p>',
                esc_attr($key),
                checked(in_array($key, $settings['scopes'], true) || $locked, true, false),
                disabled($locked || $disabled, true, false),
                esc_html($label)
            );
        }

        echo '<h3>نوع محتوا</h3>';

        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if ($type->name === 'attachment') {
                continue;
            }

            printf(
                '<label style="margin-inline-end:16px"><input type="checkbox" name="post_types[]" value="%s" %s> %s</label>',
                esc_attr($type->name),
                checked(in_array($type->name, $settings['post_types'], true), true, false),
                esc_html($type->labels->name)
            );
        }

        printf(
            '<p><label><input type="checkbox" name="include_unpublished" value="1" %s> پیش‌نویس، در انتظار بررسی، زمان‌بندی‌شده و خصوصی هم فرستاده شود (فقط عنوان و آدرس، برای جلوگیری از موضوع تکراری)</label></p>',
            checked($settings['include_unpublished'], true, false)
        );

        echo '<h3>استثناها</h3>';
        printf('<p><label>شناسه‌ی نوشته‌هایی که هرگز فرستاده نشوند (با ویرگول یا فاصله جدا کنید):<br><input type="text" name="excluded_post_ids" class="regular-text" dir="ltr" value="%s"></label></p>', esc_attr(implode(', ', $settings['excluded_post_ids'])));
        echo '<p>دسته‌هایی که نوشته‌هایشان فرستاده نشود:<br>';

        foreach (get_categories(['hide_empty' => false, 'number' => 200]) as $category) {
            printf(
                '<label style="margin-inline-end:12px"><input type="checkbox" name="excluded_term_ids[]" value="%d" %s> %s</label>',
                (int) $category->term_id,
                checked(in_array((int) $category->term_id, array_map('intval', $settings['excluded_term_ids']), true), true, false),
                esc_html($category->name)
            );
        }

        echo '</p>';

        echo '<h3>زمان و فشار</h3>';
        printf(
            '<p>همگام‌سازی خودکار فقط بین ساعت <input type="number" name="window_start" min="0" max="23" value="%d" style="width:60px"> تا <input type="number" name="window_end" min="1" max="24" value="%d" style="width:60px"> (به وقت سایت) انجام شود.</p>',
            (int) $settings['window_start'],
            (int) $settings['window_end']
        );
        printf(
            '<p>حداکثر تعداد دسته (هر دسته ۵۰ مورد) در هر نوبت: <input type="number" name="max_batches_per_run" min="1" max="20" value="%d" style="width:60px"></p>',
            (int) $settings['max_batches_per_run']
        );

        echo '<h3>نوشتن در سایت</h3><p><em>در این نسخه CRM فقط می‌خواند و هیچ تغییری در سایت شما نمی‌دهد. اگر در نسخه‌های بعد قابلیت نوشتن اضافه شود، با کلید جداگانه و پیش‌فرض خاموش خواهد بود.</em></p>';

        submit_button('ذخیره‌ی تنظیمات');
        echo '</form>';
    }

    private static function renderTransparency(array $state)
    {
        echo '<h2>شفافیت</h2>';

        $types = Settings::get()['post_types'] ?: ['post'];
        $sample = get_posts(['numberposts' => 1, 'post_type' => $types, 'post_status' => 'publish', 'orderby' => 'modified', 'order' => 'DESC']);

        if ($sample) {
            echo '<details><summary>پیش‌نمایش دقیق آنچه برای آخرین نوشته‌ی ویرایش‌شده فرستاده می‌شود</summary><pre dir="ltr" style="max-height:360px;overflow:auto;background:#f6f7f7;padding:12px">'
                .esc_html(wp_json_encode(Collector::collect($sample[0]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre></details>';
        }

        echo '<h3>آخرین ارتباط‌ها با CRM</h3>';

        if (! $state['log']) {
            echo '<p>هنوز ارتباطی نبوده است.</p>';

            return;
        }

        echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>زمان</th><th>نوع</th><th>جزئیات</th></tr></thead><tbody>';

        foreach ($state['log'] as $entry) {
            printf('<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html(wp_date('Y/m/d H:i', $entry['time'])), esc_html($entry['kind']), esc_html($entry['detail']));
        }

        echo '</tbody></table>';
    }
}
