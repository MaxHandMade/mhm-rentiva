<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Vendor;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryProvider;
use MHMRentiva\Admin\Vendor\Directory\VendorDirectorySchema;
use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryUrlBase;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * `[rentiva_vendor_directory]` canonical render entry.
 *
 * Pro-gated (render-time). Block + Elementor widget delegate here via
 * `do_shortcode()` per render-parity rule.
 *
 * Attributes (all optional):
 *   - per_page         : int 1..50, default 12
 *   - default_sort     : 'rating'|'newest'|'alpha', default 'rating'
 *   - show_filter_bar  : 'yes'|'no', default 'yes'
 *   - show_breadcrumb  : 'yes'|'no', default 'yes'
 *   - show_pagination  : 'yes'|'no', default 'yes'
 *   - empty_message    : string override
 *   - class            : extra CSS class(es) on outer wrapper
 *   - id               : DOM id on outer wrapper
 *
 * @since 4.38.0
 */
final class VendorDirectory
{
    public const TAG = 'rentiva_vendor_directory';

    public static function register(): void
    {
        add_shortcode(self::TAG, [self::class, 'render']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public static function render($atts = []): string
    {
        if (!Mode::canUseVendorMarketplace()) {
            return '';
        }

        $atts = self::normalize_atts($atts);
        $query_args = self::read_query_args($atts);

        $data = VendorDirectoryProvider::query($query_args);

        ob_start();
        $template = MHM_RENTIVA_PLUGIN_PATH . 'templates/frontend/vendor-directory.php';
        if (!file_exists($template)) {
            ob_end_clean();
            return '';
        }
        require $template;
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed>|string $atts
     * @return array<string, string|int>
     */
    private static function normalize_atts($atts): array
    {
        $defaults = [
            'per_page'        => 12,
            'default_sort'    => 'rating',
            'show_filter_bar' => 'yes',
            'show_breadcrumb' => 'yes',
            'show_pagination' => 'yes',
            'empty_message'   => '',
            'class'           => '',
            'id'              => '',
        ];
        $merged = shortcode_atts($defaults, is_array($atts) ? $atts : []);

        // Clamp per_page (Phase 6 reviewer Y-5 of v4.37.0)
        $merged['per_page'] = max(1, min(50, (int) $merged['per_page']));

        // Bool flag normalization (Phase 7 of v4.37.0 lesson — CAM 'yes'→'1' bypass)
        foreach (['show_filter_bar', 'show_breadcrumb', 'show_pagination'] as $flag) {
            $val = (string) $merged[$flag];
            $merged[$flag] = in_array($val, ['yes', '1', 'true', 'on'], true) ? 'yes' : 'no';
        }

        // Sort enum guard
        if (!in_array($merged['default_sort'], ['rating', 'newest', 'alpha'], true)) {
            $merged['default_sort'] = 'rating';
        }

        // Multi-class sanitize per token (Phase 6 ORTA-1)
        if ($merged['class'] !== '') {
            $tokens = preg_split('/\s+/', trim((string) $merged['class'])) ?: [];
            $merged['class'] = implode(' ', array_filter(array_map('sanitize_html_class', $tokens)));
        }

        $merged['id'] = sanitize_html_class((string) $merged['id']);

        return $merged;
    }

    /**
     * @param array<string, string|int> $atts
     * @return array<string, mixed>
     */
    private static function read_query_args(array $atts): array
    {
        return [
            'city'       => isset($_GET['city']) ? sanitize_text_field(wp_unslash((string) $_GET['city'])) : '',
            'badge'      => isset($_GET['badge']) ? sanitize_text_field(wp_unslash((string) $_GET['badge'])) : '',
            'min_rating' => isset($_GET['min_rating']) ? absint(wp_unslash($_GET['min_rating'])) : 0,
            'sort'       => isset($_GET['sort']) ? sanitize_text_field(wp_unslash((string) $_GET['sort'])) : (string) $atts['default_sort'],
            'paged'      => max(1, (int) ($_GET['paged'] ?? 1)),
            'per_page'   => (int) $atts['per_page'],
        ];
    }
}
