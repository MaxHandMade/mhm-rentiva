<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Transfer\Engine\TransferRouteProvider;

/**
 * Popular Transfer Routes Shortcode
 *
 * Renders a grid of A → B transfer route cards (origin → destination, duration,
 * starting price). Block + Elementor widget delegate to this shortcode via
 * do_shortcode() so the canonical renderer lives here.
 *
 * Usage:
 *   [rentiva_popular_routes limit="6" columns="3" order="featured"]
 *
 * @since 4.34.0
 */
final class PopularRoutesShortcode {

    private const SHORTCODE_TAG = 'rentiva_popular_routes';

    private const ALLOWED_COLUMNS = [ 2, 3, 4 ];

    /**
     * Register the shortcode + asset enqueue side-effect.
     */
    public static function register(): void
    {
        add_shortcode(self::SHORTCODE_TAG, [ self::class, 'render' ]);
    }

    /**
     * @param array<string,mixed>|string $atts
     */
    public static function render($atts = [], ?string $content = null): string
    {
        $atts = shortcode_atts(self::default_attributes(), is_array($atts) ? $atts : [], self::SHORTCODE_TAG);

        $limit = self::resolve_limit( (int) $atts['limit']);
        if ($limit < 1) {
            return '';
        }

        $routes = TransferRouteProvider::get_popular_routes([
            'limit'              => $limit,
            'order'              => (string) $atts['order'],
            'featured_only'      => self::to_bool($atts['featured_only']),
            'filter_origin_city' => (string) $atts['filter_origin_city'],
            'filter_origin_type' => (string) $atts['filter_origin_type'],
        ]);

        if (empty($routes)) {
            // Silent no-op: section never renders if no eligible routes exist.
            return '';
        }

        self::enqueue_styles();

        return self::render_html($atts, $routes);
    }

    /**
     * @return array<string,string>
     */
    private static function default_attributes(): array
    {
        return [
            'limit'              => '6',
            'columns'            => '3',
            'order'              => 'featured',
            'heading'            => __('Popular Routes', 'mhm-rentiva'),
            'subheading'         => __('Most preferred VIP transfer routes', 'mhm-rentiva'),
            'show_view_all'      => 'true',
            'view_all_url'       => '',
            'show_duration'      => 'true',
            'show_distance'      => 'true',
            'show_traffic_note'  => 'true',
            'show_price'         => 'true',
            'currency_symbol'    => '₺',
            'filter_origin_city' => '',
            'filter_origin_type' => '',
            'featured_only'      => 'false',
            'theme'              => 'light',
        ];
    }

    /**
     * Apply Lite quota: never render more cards than the active license tier permits.
     */
    private static function resolve_limit(int $requested): int
    {
        $requested = max(1, $requested);

        if (! class_exists(Mode::class) || ! method_exists(Mode::class, 'maxTransferRoutes')) {
            return $requested;
        }

        $tier_max = (int) Mode::maxTransferRoutes();
        if ($tier_max <= 0) {
            return $requested;
        }
        return min($requested, $tier_max);
    }

    /**
     * @param mixed $value
     */
    private static function to_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower( (string) $value);
        return in_array($normalized, [ 'true', '1', 'yes', 'on' ], true);
    }

    private static function enqueue_styles(): void
    {
        if (! function_exists('wp_enqueue_style')) {
            return;
        }
        $handle = 'mhm-popular-routes-css';
        if (wp_style_is($handle, 'enqueued') || wp_style_is($handle, 'registered')) {
            wp_enqueue_style($handle);
            return;
        }

        $rel  = 'assets/css/frontend/popular-routes.css';
        $base = defined('MHM_RENTIVA_PLUGIN_URL') ? rtrim( (string) constant('MHM_RENTIVA_PLUGIN_URL'), '/') . '/' : plugin_dir_url(dirname(__DIR__, 3) . '/mhm-rentiva.php');
        $path = defined('MHM_RENTIVA_PLUGIN_DIR') ? rtrim( (string) constant('MHM_RENTIVA_PLUGIN_DIR'), '/\\') . '/' . $rel : '';

        $version = defined('MHM_RENTIVA_VERSION') ? (string) constant('MHM_RENTIVA_VERSION') : '4.34.0';
        if ($path !== '' && file_exists($path)) {
            $version .= '.' . (string) filemtime($path);
        }

        wp_enqueue_style($handle, $base . $rel, [], $version);
    }

    /**
     * @param array<string,mixed> $atts
     * @param array<int,object>   $routes
     */
    private static function render_html(array $atts, array $routes): string
    {
        $columns = (int) $atts['columns'];
        if (! in_array($columns, self::ALLOWED_COLUMNS, true)) {
            $columns = 3;
        }

        $theme = in_array( (string) $atts['theme'], [ 'light', 'dark' ], true) ? (string) $atts['theme'] : 'light';

        $heading            = (string) $atts['heading'];
        $subheading         = (string) $atts['subheading'];
        $show_duration      = self::to_bool($atts['show_duration']);
        $show_distance      = self::to_bool($atts['show_distance']);
        $show_traffic_note  = self::to_bool($atts['show_traffic_note']);
        $show_price         = self::to_bool($atts['show_price']);
        $currency_symbol    = (string) $atts['currency_symbol'];
        $show_view_all_attr = self::to_bool($atts['show_view_all']);

        $view_all_url  = self::resolve_view_all_url( (string) $atts['view_all_url']);
        $show_view_all = $show_view_all_attr && $view_all_url !== '' && self::has_more_routes_than_rendered($atts, count($routes));

        $card_opts = [
            'show_duration'     => $show_duration,
            'show_distance'     => $show_distance,
            'show_traffic_note' => $show_traffic_note,
            'show_price'        => $show_price,
            'currency_symbol'   => $currency_symbol,
        ];

        ob_start();
        ?>
        <section class="mhm-popular-routes mhm-popular-routes--theme-<?php echo esc_attr($theme); ?> mhm-popular-routes--cols-<?php echo esc_attr( (string) $columns); ?>">
            <header class="mhm-popular-routes__header">
                <div class="mhm-popular-routes__heading-wrap">
                    <?php if ($heading !== '') : ?>
                        <h2 class="mhm-popular-routes__heading"><?php echo esc_html($heading); ?></h2>
                    <?php endif; ?>
                    <?php if ($subheading !== '') : ?>
                        <p class="mhm-popular-routes__subheading"><?php echo esc_html($subheading); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($show_view_all) : ?>
                    <a class="mhm-popular-routes__view-all" href="<?php echo esc_url($view_all_url); ?>">
                        <?php echo esc_html__('View all', 'mhm-rentiva'); ?> &rarr;
                    </a>
                <?php endif; ?>
            </header>

            <div class="mhm-popular-routes__grid" role="list">
                <?php
                foreach ($routes as $route) {
                    $card_html = self::render_card($route, $card_opts);
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_card emits already-escaped HTML.
                    echo $card_html;
                }
                ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array{show_duration:bool,show_distance:bool,show_traffic_note:bool,show_price:bool,currency_symbol:string} $opts
     */
    private static function render_card(object $route, array $opts): string
    {
        $origin_name      = isset($route->origin_name) ? (string) $route->origin_name : '';
        $destination_name = isset($route->destination_name) ? (string) $route->destination_name : '';
        $origin_city      = isset($route->origin_city) ? (string) $route->origin_city : '';
        $origin_type      = isset($route->origin_type) ? (string) $route->origin_type : '';
        $duration_min     = isset($route->duration_min) ? (int) $route->duration_min : 0;
        $distance_km      = isset($route->distance_km) ? (float) $route->distance_km : 0.0;
        $is_featured      = isset($route->is_featured) ? (int) $route->is_featured : 0;
        $price            = self::resolve_card_price($route);

        $type_icon = self::location_type_icon($origin_type);

        ob_start();
        ?>
        <article class="mhm-popular-route-card<?php echo $is_featured ? ' mhm-popular-route-card--featured' : ''; ?>" role="listitem">
            <header class="mhm-popular-route-card__top">
                <?php if ($origin_city !== '') : ?>
                    <span class="mhm-popular-route-card__city"><?php echo esc_html(mb_strtoupper($origin_city, 'UTF-8')); ?></span>
                <?php endif; ?>
                <?php if ($type_icon !== '') : ?>
                    <span class="mhm-popular-route-card__icon" aria-hidden="true"><?php echo esc_html($type_icon); ?></span>
                <?php endif; ?>
            </header>
            <h3 class="mhm-popular-route-card__title">
                <span class="mhm-popular-route-card__from"><?php echo esc_html($origin_name); ?></span>
                <span class="mhm-popular-route-card__sep" aria-hidden="true"> &rarr; </span>
                <span class="mhm-popular-route-card__to"><?php echo esc_html($destination_name); ?></span>
            </h3>
            <ul class="mhm-popular-route-card__meta">
                <?php if ($opts['show_duration'] && $duration_min > 0) : ?>
                    <li class="mhm-popular-route-duration">
                        <span class="mhm-popular-route-card__meta-icon" aria-hidden="true">⏱</span>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %d: average travel duration in minutes */
                            __('Approx. %d min', 'mhm-rentiva'),
                            $duration_min
                        ));
                        ?>
                    </li>
                <?php endif; ?>
                <?php if ($opts['show_distance'] && $distance_km > 0) : ?>
                    <li class="mhm-popular-route-distance">
                        <span class="mhm-popular-route-card__meta-icon" aria-hidden="true">📍</span>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: route distance in kilometers */
                            __('%s km', 'mhm-rentiva'),
                            number_format_i18n($distance_km, $distance_km < 10 ? 1 : 0)
                        ));
                        ?>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if ($opts['show_traffic_note']) : ?>
                <p class="mhm-popular-route-card__traffic-note">
                    <?php echo esc_html__('May vary with traffic', 'mhm-rentiva'); ?>
                </p>
            <?php endif; ?>
            <?php if ($opts['show_price'] && $price > 0) : ?>
                <footer class="mhm-popular-route-card__footer">
                    <span class="mhm-popular-route-card__price">
                        <?php echo esc_html($opts['currency_symbol']); ?><?php echo esc_html(number_format_i18n($price)); ?>
                    </span>
                    <span class="mhm-popular-route-card__price-label">
                        <?php echo esc_html__('Starting from', 'mhm-rentiva'); ?>
                    </span>
                </footer>
            <?php endif; ?>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    private static function resolve_card_price(object $route): float
    {
        $method = isset($route->pricing_method) ? (string) $route->pricing_method : 'fixed';
        $base   = isset($route->base_price) ? (float) $route->base_price : 0.0;
        $min    = isset($route->min_price) ? (float) $route->min_price : 0.0;

        if ($method === 'calculated' && $min > 0) {
            return $min;
        }
        if ($base > 0) {
            return $base;
        }
        return $min;
    }

    private static function location_type_icon(string $type): string
    {
        $map = [
            'airport'     => '✈️',
            'train'       => '🚆',
            'hotel'       => '🏨',
            'marina'      => '⛵',
            'city_center' => '🏙️',
        ];
        /**
         * Filter the icon shown for a given transfer-location type on popular-route cards.
         *
         * @param string $icon Default icon emoji (may be empty for unknown types).
         * @param string $type Location type slug.
         */
        return (string) apply_filters('mhm_rentiva_popular_routes_type_icon', $map[ $type ] ?? '↗', $type);
    }

    private static function resolve_view_all_url(string $supplied): string
    {
        $supplied = trim($supplied);
        if ($supplied !== '') {
            return esc_url_raw($supplied);
        }
        /**
         * Filter the destination of the "View all" link when no explicit URL is supplied.
         *
         * @param string $url Default empty (link hidden) — themes/integrations should return a transfer-search URL.
         */
        $url = (string) apply_filters('mhm_rentiva_popular_routes_view_all_url', '');
        return $url === '' ? '' : esc_url_raw($url);
    }

    /**
     * @param array<string,mixed> $atts
     */
    private static function has_more_routes_than_rendered(array $atts, int $rendered_count): bool
    {
        $total = TransferRouteProvider::get_popular_routes([
            'limit'              => 50,
            'order'              => (string) $atts['order'],
            'featured_only'      => self::to_bool($atts['featured_only']),
            'filter_origin_city' => (string) $atts['filter_origin_city'],
            'filter_origin_type' => (string) $atts['filter_origin_type'],
        ]);
        return count($total) > $rendered_count;
    }
}
