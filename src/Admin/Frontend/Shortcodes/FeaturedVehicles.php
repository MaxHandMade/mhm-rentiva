<?php

/**
 * Featured Vehicles Shortcode
 *
 * [rentiva_featured_vehicles limit="6" layout="slider"]
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use WP_Query;

final class FeaturedVehicles extends AbstractShortcode
{
    public const SHORTCODE = 'rentiva_featured_vehicles';

    protected static function get_shortcode_tag(): string
    {
        return self::SHORTCODE;
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/featured-vehicles';
    }

    protected static function get_default_attributes(): array
    {
        return array(
            'title'    => __('Featured Vehicles', 'mhm-rentiva'),
            'ids'      => '',      // Comma separated IDs
            'category' => '',      // Category slug
            'limit'    => '6',
            'layout'   => 'slider', // slider, grid
            'columns'  => '3',
            'autoplay' => '1',
            'interval' => '5000',
            'orderby'  => 'date',  // date, price, title, rand
            'order'    => 'DESC',
        );
    }

    protected static function prepare_template_data(array $atts): array
    {
        $args = array(
            'post_type'      => PT_Vehicle::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => (int) ($atts['limit'] ?? 6),
            'orderby'        => sanitize_key((string) ($atts['orderby'] ?? 'date')),
            'order'          => sanitize_key((string) ($atts['order'] ?? 'DESC')),
        );

        // Filter by IDs
        if (! empty($atts['ids'])) {
            $args['post__in'] = array_map('intval', explode(',', (string) $atts['ids']));
            $args['orderby']  = 'post__in'; // Preserve order
        }
        // Filter by Category
        elseif (! empty($atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'vehicle_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field((string) $atts['category']),
                ),
            );
        }

        $cache_key = 'featured_' . md5(wp_json_encode($atts));
        $vehicles  = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache('vehicle_list', $cache_key);

        if (empty($vehicles)) {
            $query = new WP_Query($args);
            $vehicles = array();

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $id = get_the_ID();

                    // Basic data required for the card
                    $vehicles[] = array(
                        'id'            => $id,
                        'title'         => get_the_title(),
                        'permalink'     => get_permalink(),
                        'thumbnail_url' => get_the_post_thumbnail_url($id, 'medium_large'),
                        'price'         => get_post_meta($id, '_mhm_rentiva_price_per_day', true),
                        'seats'         => get_post_meta($id, '_mhm_rentiva_seats', true),
                        'transmission'  => get_post_meta($id, '_mhm_rentiva_transmission', true),
                        'fuel'          => get_post_meta($id, '_mhm_rentiva_fuel_type', true),
                    );
                }
                wp_reset_postdata();

                // Cache Optimization: Set cache for 1 hour
                if (! empty($vehicles)) {
                    \MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache('vehicle_list', $cache_key, $vehicles, 3600);
                }
            }
        }

        return array(
            'atts'      => $atts,
            'vehicles'  => $vehicles,
            'has_posts' => ! empty($vehicles),
        );
    }

    protected static function get_css_files(array $atts = []): array
    {
        $files = [];

        // If using slider layout, enqueue Swiper CSS from vendor
        $layout = $atts['layout'] ?? 'slider';

        if ($layout === 'slider') {
            wp_enqueue_style('mhm-swiper-css');
        }

        // Module specific styles
        $files[] = 'assets/css/frontend/featured-vehicles.css';

        return $files;
    }

    protected static function get_js_files(array $atts = []): array
    {
        $files = [];
        $layout = $atts['layout'] ?? 'slider';

        if ($layout === 'slider') {
            $files[] = 'assets/js/frontend/featured-vehicles.js';
        }
        return $files;
    }

    protected static function get_js_dependencies(): array
    {
        return array('jquery', 'mhm-swiper');
    }

    protected static function get_js_config(): array
    {
        return [];
    }
}
