<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public vendor profile SEO defaults — page title + meta description.
 *
 * Provides a sensible default `<title>` and `<meta name="description">`
 * for the rewrite-routed vendor profile pages **only when no real SEO
 * plugin is active**. Yoast SEO, Rank Math, and AIOSEO own this contract
 * on the sites that have them installed; we deliberately defer to them.
 *
 * Site owners can disable our emission entirely with:
 *
 *   add_filter('mhm_rentiva_vendor_profile_seo_disable', '__return_true');
 *
 * This class registers two filters:
 *
 *   - `document_title_parts` (priority 10) — replaces the default theme
 *     title with `{vendor name} | {site name}` so browser tabs and
 *     social share previews carry the vendor identity.
 *
 *   - `wp_head` (priority 1) — emits a single `<meta name="description">`
 *     line carrying the vendor's bio (first 155 characters, trimmed at a
 *     word boundary). Priority 1 places the tag early in `<head>`,
 *     ahead of OpenGraph image emissions and other late-priority hooks.
 *
 * Schema.org `LocalBusiness` JSON-LD is owned by {@see VendorProfileSchema}
 * and runs independently — both classes are safe to coexist.
 *
 * @since 4.37.2
 */
final class VendorProfileSeo
{
    public static function register(): void
    {
        if (self::is_seo_plugin_active()) {
            return;
        }
        if ((bool) apply_filters('mhm_rentiva_vendor_profile_seo_disable', false)) {
            return;
        }

        add_filter('document_title_parts', [self::class, 'filter_title'], 10, 1);
        add_action('wp_head', [self::class, 'emit_meta_description'], 1);
    }

    /**
     * @param array<string,string> $parts
     * @return array<string,string>
     */
    public static function filter_title(array $parts): array
    {
        $user_id = self::current_vendor_user_id();
        if ($user_id <= 0) {
            return $parts;
        }
        $user = get_userdata($user_id);
        if (!$user) {
            return $parts;
        }

        $parts['title']   = (string) $user->display_name;
        // Leave $parts['site'] / $parts['tagline'] alone so themes that show
        // the site name in the browser tab keep their convention. Themes
        // that override `wp_title` directly may bypass this filter — Yoast
        // / RankMath would intercept them anyway when present.

        return $parts;
    }

    public static function emit_meta_description(): void
    {
        $user_id = self::current_vendor_user_id();
        if ($user_id <= 0) {
            return;
        }
        $bio = (string) get_user_meta($user_id, '_rentiva_vendor_bio', true);
        $bio = trim(wp_strip_all_tags($bio));
        if ($bio === '') {
            return;
        }

        $excerpt = self::trim_at_word_boundary($bio, 155);

        echo "\n" . '<meta name="description" content="' . esc_attr($excerpt) . '" />' . "\n";
    }

    /**
     * Trim a string at a word boundary so the description never cuts mid-word.
     */
    public static function trim_at_word_boundary(string $text, int $limit): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }
        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
        $last_space = function_exists('mb_strrpos') ? mb_strrpos($cut, ' ', 0, 'UTF-8') : strrpos($cut, ' ');
        if ($last_space !== false && $last_space > 0) {
            $cut = function_exists('mb_substr') ? mb_substr($cut, 0, $last_space, 'UTF-8') : substr($cut, 0, $last_space);
        }
        return rtrim($cut, " \t\n\r\0\x0B,;:.") . '…';
    }

    /**
     * Identify whether the current request is rendering a vendor profile,
     * and return the matching user ID if so.
     */
    public static function current_vendor_user_id(): int
    {
        if (!class_exists(VendorProfileRewrite::class)) {
            return 0;
        }
        $slug = (string) get_query_var(VendorProfileRewrite::QUERY_VAR);
        if ($slug === '') {
            return 0;
        }
        if (!class_exists(VendorProfileProvider::class)) {
            return 0;
        }
        return VendorProfileProvider::lookup_by_slug($slug);
    }

    /**
     * Whether a real SEO plugin owns title / description on this site.
     * If yes, we yield — those plugins have richer settings and already
     * intercept document_title_parts at higher priorities.
     */
    public static function is_seo_plugin_active(): bool
    {
        return class_exists('WPSEO_Frontend')                 // Yoast SEO (free + premium)
            || defined('WPSEO_VERSION')                        // Yoast SEO (alt detection)
            || class_exists('RankMath')                        // Rank Math
            || defined('RANK_MATH_VERSION')                    // Rank Math (alt)
            || defined('AIOSEO_VERSION')                       // All-in-One SEO
            || class_exists('AIOSEO\\Plugin\\AIOSEO')          // AIOSEO Pro namespaced
            || class_exists('Smartcrawl_Init')                 // SmartCrawl (WPMU DEV)
            || defined('SEOPRESS_VERSION')                     // SEOPress
            || defined('THE_SEO_FRAMEWORK_VERSION');           // The SEO Framework
    }
}
