<?php

namespace FarsiWP\SeoConnector;

use RankMath\Helper;

/**
 * Turns one WordPress post into what the CRM is allowed to receive. Nothing
 * outside the owner's scopes is ever added, and an unpublished post only
 * ever contributes its title, address and dates.
 */
class Collector
{
    /**
     * @param  \WP_Post  $post
     * @param  bool  $minimal  true for posts that are not public yet
     * @return array<string, mixed>
     */
    public static function collect($post, $minimal = false)
    {
        $payload = [
            'wp_id' => (int) $post->ID,
            'post_type' => $post->post_type,
            'status' => $post->post_status,
            'url' => get_permalink($post),
            'title' => html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES, 'UTF-8'),
            'slug' => $post->post_name,
            'published_at' => $post->post_date_gmt && $post->post_date_gmt !== '0000-00-00 00:00:00' ? mysql2date('c', $post->post_date_gmt, false) : null,
            'modified_at' => mysql2date('c', $post->post_modified_gmt, false),
        ];

        if ($minimal) {
            $payload['word_count'] = 0;

            return $payload;
        }

        $html = self::contentHtml($post);
        $analysis = HtmlAnalyzer::analyze($html, home_url());

        $payload['word_count'] = $analysis['word_count'];
        $payload['content_hash'] = sha1($html);

        if (Settings::allows('structure')) {
            $payload['headings'] = $analysis['headings'];
            $payload['internal_links'] = $analysis['internal_links'];
            $payload['external_links'] = $analysis['external_links'];
        }

        if (Settings::allows('images')) {
            $payload['images'] = $analysis['images'];
        }

        if (Settings::allows('terms')) {
            $payload['terms'] = self::terms($post);
        }

        if (Settings::allows('authors')) {
            $author = get_userdata($post->post_author);
            $payload['author_name'] = $author ? $author->display_name : null;
        }

        if (Settings::allows('seo_meta')) {
            $payload['seo'] = self::seo($post);
        }

        if (Settings::allows('commerce') && $post->post_type === 'product' && function_exists('wc_get_product')) {
            $payload['commerce'] = self::commerce($post);
        }

        return $payload;
    }

    /**
     * The post's HTML. Elementor keeps its content outside post_content, so
     * when post_content is empty its text and headings are rebuilt from there.
     */
    public static function contentHtml($post)
    {
        $html = strip_shortcodes((string) $post->post_content);

        if (trim(wp_strip_all_tags($html)) === '') {
            $elementor = get_post_meta($post->ID, '_elementor_data', true);

            if (is_string($elementor) && $elementor !== '') {
                $html = self::elementorHtml(json_decode($elementor, true));
            }
        }

        return $html;
    }

    private static function elementorHtml($elements)
    {
        $html = '';

        foreach ((array) $elements as $element) {
            if (! is_array($element)) {
                continue;
            }

            $settings = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : [];

            if (($element['widgetType'] ?? '') === 'heading' && ! empty($settings['title'])) {
                $tag = in_array($settings['header_size'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $settings['header_size'] : 'h2';
                $html .= "<{$tag}>".esc_html(wp_strip_all_tags($settings['title']))."</{$tag}>";
            } elseif (! empty($settings['editor'])) {
                $html .= '<div>'.$settings['editor'].'</div>';
            } elseif (! empty($settings['text']) && is_string($settings['text'])) {
                $html .= '<p>'.esc_html($settings['text']).'</p>';
            }

            if (! empty($element['elements'])) {
                $html .= self::elementorHtml($element['elements']);
            }
        }

        return $html;
    }

    private static function terms($post)
    {
        $isProduct = $post->post_type === 'product';
        $names = function ($taxonomy) use ($post) {
            $terms = taxonomy_exists($taxonomy) ? wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'names']) : [];

            return is_wp_error($terms) ? [] : array_values($terms);
        };

        return [
            'categories' => $names($isProduct ? 'product_cat' : 'category'),
            'tags' => $names($isProduct ? 'product_tag' : 'post_tag'),
        ];
    }

    /**
     * Which SEO plugin is active, as the CRM names it.
     */
    public static function seoPlugin()
    {
        if (defined('WPSEO_VERSION')) {
            return 'yoast';
        }

        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            return 'rank_math';
        }

        return 'none';
    }

    /**
     * Focus keyword, titles, canonical and noindex. Both plugins' fields are
     * read (the active one first) so a site that switched plugins keeps its
     * old data.
     */
    private static function seo($post)
    {
        $plugin = self::seoPlugin();
        $meta = function ($key) use ($post) {
            $value = get_post_meta($post->ID, $key, true);

            return is_string($value) ? trim($value) : '';
        };

        $yoast = [
            'focus_keyword' => $meta('_yoast_wpseo_focuskw'),
            'title' => $meta('_yoast_wpseo_title'),
            'description' => $meta('_yoast_wpseo_metadesc'),
            'canonical' => $meta('_yoast_wpseo_canonical'),
        ];
        $rankMath = [
            'focus_keyword' => $meta('rank_math_focus_keyword'),
            'title' => $meta('rank_math_title'),
            'description' => $meta('rank_math_description'),
            'canonical' => $meta('rank_math_canonical_url'),
        ];

        $first = $plugin === 'rank_math' ? [$rankMath, $yoast] : [$yoast, $rankMath];
        $seo = [];

        foreach (array_keys($yoast) as $field) {
            $seo[$field] = $first[0][$field] !== '' ? $first[0][$field] : $first[1][$field];
        }

        // Rank Math keeps several keywords comma-separated; the first is the main one.
        $seo['focus_keyword'] = trim(explode(',', $seo['focus_keyword'])[0]);
        $seo['title'] = self::resolveVariables($seo['title'], $post, $plugin);
        $seo['description'] = self::resolveVariables($seo['description'], $post, $plugin);

        $robots = get_post_meta($post->ID, 'rank_math_robots', true);
        $seo['noindex'] = $meta('_yoast_wpseo_meta-robots-noindex') === '1'
            || (is_array($robots) && in_array('noindex', $robots, true));
        $seo['plugin'] = $plugin;

        return $seo;
    }

    /**
     * SEO plugins store titles with placeholders such as %%title%%; the CRM
     * needs the real text, so ask the plugin to fill them in.
     */
    private static function resolveVariables($text, $post, $plugin)
    {
        if ($text === '' || strpos($text, '%') === false) {
            return $text;
        }

        try {
            if ($plugin === 'yoast' && function_exists('wpseo_replace_vars')) {
                return trim(wpseo_replace_vars($text, $post));
            }

            if ($plugin === 'rank_math' && class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'replace_vars')) {
                return trim(Helper::replace_vars($text, $post));
            }
        } catch (\Throwable $exception) {
            return $text;
        }

        return $text;
    }

    private static function commerce($post)
    {
        $product = wc_get_product($post->ID);

        if (! $product) {
            return null;
        }

        return [
            'product_type' => $product->get_type(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_status' => $product->get_stock_status(),
            'sku' => $product->get_sku(),
            'rating_average' => (float) $product->get_average_rating(),
            'review_count' => (int) $product->get_review_count(),
        ];
    }

    /**
     * What the plugin tells the CRM about the site itself.
     */
    public static function siteInfo()
    {
        global $wp_version;

        $info = [
            'url' => home_url(),
            'plugin_version' => FSWC_VERSION,
            'wp_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'seo_plugin' => self::seoPlugin(),
            'woocommerce' => class_exists('WooCommerce'),
            'scopes' => Settings::get()['scopes'],
        ];

        if (Settings::allows('health')) {
            $info['search_engines_discouraged'] = ! (bool) get_option('blog_public', 1);
        }

        return $info;
    }
}
