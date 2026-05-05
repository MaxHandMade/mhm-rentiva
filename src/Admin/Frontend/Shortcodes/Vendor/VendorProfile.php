<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Vendor;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileProvider;

/**
 * [rentiva_vendor_profile slug="akif-otomotiv"] shortcode.
 *
 * Canonical renderer — block + Elementor widget delegate via do_shortcode().
 * Pro-gated (vendor_marketplace feature flag).
 *
 * @since 4.37.0
 */
final class VendorProfile
{
    private const SHORTCODE_TAG = 'rentiva_vendor_profile';

    public static function register(): void
    {
        add_shortcode(self::SHORTCODE_TAG, [self::class, 'render']);
    }

    /**
     * @param array<string,mixed>|string $atts
     */
    public static function render($atts = [], ?string $content = null): string
    {
        if (!Mode::canUseVendorMarketplace()) {
            return '';
        }

        $atts = shortcode_atts(self::default_attributes(), is_array($atts) ? $atts : [], self::SHORTCODE_TAG);
        $atts = self::normalize_bool_flags($atts);
        $slug = (string) $atts['slug'];
        if ($slug === '') {
            return '';
        }
        $user_id = VendorProfileProvider::lookup_by_slug($slug);
        if ($user_id <= 0) {
            return '';
        }
        $max_vehicles = (int) $atts['max_vehicles'];
        $max_reviews  = (int) $atts['max_reviews'];
        $data         = VendorProfileProvider::get_profile_data($user_id, $max_vehicles, $max_reviews);
        if (empty($data)) {
            return '';
        }

        self::enqueue_styles();

        return self::render_template($data, $atts);
    }

    /**
     * Render five-star ASCII representation of an average rating.
     *
     * Public so partials (which are PHP includes, not class methods) can
     * call it via FQN.
     */
    public static function stars_html(float $avg): string
    {
        $full  = (int) floor($avg);
        $half  = ($avg - $full) >= 0.5 ? 1 : 0;
        $empty = 5 - $full - $half;
        return str_repeat('★', $full) . ($half ? '½' : '') . str_repeat('☆', $empty);
    }

    /**
     * Normalize boolean-like flag attributes back to canonical 'yes'/'no'.
     *
     * Block + Elementor widget paths can deliver flags as '1'/'0' (CAM bool
     * normalization, get_prepared_atts switcher), while templates use strict
     * `=== 'yes'` comparison. This single seam keeps the canonical contract
     * intact regardless of caller.
     *
     * @param array<string,mixed> $atts
     * @return array<string,string>
     */
    private static function normalize_bool_flags(array $atts): array
    {
        $flags = ['show_badge', 'show_rating', 'show_about', 'show_vehicles', 'show_reviews', 'show_location'];
        foreach ($flags as $flag) {
            if (!isset($atts[$flag])) {
                continue;
            }
            $value         = (string) $atts[$flag];
            $atts[$flag]   = in_array($value, ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no';
        }
        return $atts;
    }

    /** @return array<string,string> */
    private static function default_attributes(): array
    {
        return [
            'slug'                   => '',
            'show_badge'             => 'yes',
            'show_rating'            => 'yes',
            'show_about'             => 'yes',
            'show_vehicles'          => 'yes',
            'max_vehicles'           => '6',
            'vehicle_sort'           => 'rating-newest',
            'show_reviews'           => 'yes',
            'max_reviews'            => '10',
            // v4.37.2: default 'no' — hero already shows the city. The dedicated
            // section is reserved for the v4.40.0+ Transfer Map enrichment;
            // until then it duplicates the hero meta. Layouts that want to show
            // the section explicitly can pass show_location="yes".
            'show_location'          => 'no',
            'empty_vehicles_message' => '',
            'empty_reviews_message'  => '',
            'class'                  => '',
        ];
    }

    private static function enqueue_styles(): void
    {
        $handle = 'mhm-rentiva-vendor-profile';
        if (wp_style_is($handle, 'enqueued')) {
            return;
        }
        $rel     = 'assets/css/frontend/vendor-profile.css';
        $url     = plugins_url($rel, MHM_RENTIVA_PLUGIN_FILE);
        $path    = plugin_dir_path(MHM_RENTIVA_PLUGIN_FILE) . $rel;
        $version = defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : '4.37.0';
        if (file_exists($path)) {
            $version .= '.' . filemtime($path);
        }
        wp_enqueue_style($handle, $url, [], $version);
    }

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $atts
     */
    private static function render_template(array $data, array $atts): string
    {
        $template = plugin_dir_path(MHM_RENTIVA_PLUGIN_FILE) . 'templates/frontend/vendor-profile.php';
        if (!file_exists($template)) {
            return '';
        }
        ob_start();
        // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
        include $template;
        return (string) ob_get_clean();
    }
}
