<?php

declare(strict_types=1);

namespace MHMRentiva\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Centralized Block Registry for MHM Rentiva
 * 
 * Manages registration and rendering of all Gutenberg blocks.
 * Blocks are mapped to corresponding shortcodes to ensure consistency.
 * 
 * @package MHMRentiva\Blocks
 * @since 4.6.6
 */
class BlockRegistry
{
    /**
     * List of blocks to register
     * 
     * Key: block-slug (used in assets/blocks/slug/)
     * Value: Configuration array
     */
    private static array $blocks = [
        'search' => [
            'tag'   => 'rentiva_search',
            'title' => 'Search Form',
            'css'   => 'vehicle-search-compact.css',
        ],
        'search-results' => [
            'tag'   => 'rentiva_search_results',
            'title' => 'Search Results',
            'css'   => 'search-results.css',
        ],
        'vehicle-comparison' => [
            'tag'   => 'rentiva_vehicle_comparison',
            'title' => 'Vehicle Comparison',
            'css'   => 'vehicle-comparison.css',
        ],
        'testimonials' => [
            'tag'   => 'rentiva_testimonials',
            'title' => 'Testimonials',
            'css'   => 'testimonials.css',
        ],
        'availability-calendar' => [
            'tag'   => 'rentiva_availability_calendar',
            'title' => 'Availability Calendar',
            'css'   => 'availability-calendar.css',
        ],
        'booking-confirmation' => [
            'tag'   => 'rentiva_booking_confirmation',
            'title' => 'Booking Confirmation',
            'css'   => 'booking-form.css',
        ],
        'vehicle-details' => [
            'tag'   => 'rentiva_vehicle_details',
            'title' => 'Vehicle Details',
            'css'   => 'vehicle-details.css',
        ],
        'vehicles-grid' => [
            'tag'   => 'rentiva_vehicles_grid',
            'title' => 'Vehicles Grid',
            'css'   => 'vehicles-grid.css',
        ],
        'vehicles-list' => [
            'tag'   => 'rentiva_vehicles_list',
            'title' => 'Vehicles List',
            'css'   => 'vehicles-list.css',
        ],
        'contact' => [
            'tag'   => 'rentiva_contact',
            'title' => 'Contact Form',
            'css'   => 'contact-form.css',
        ],
        'vehicle-rating-form' => [
            'tag'   => 'rentiva_vehicle_rating_form',
            'title' => 'Vehicle Rating Form',
            'css'   => 'vehicle-rating-form.css',
        ],
        'my-bookings' => [
            'tag'   => 'rentiva_my_bookings',
            'title' => 'My Bookings',
            'css'   => 'my-account.css',
        ],
        'my-favorites' => [
            'tag'   => 'rentiva_my_favorites',
            'title' => 'My Favorites',
            'css'   => 'my-account.css',
        ],
        'payment-history' => [
            'tag'   => 'rentiva_payment_history',
            'title' => 'Payment History',
            'css'   => 'my-account.css',
        ],
        'booking-form' => [
            'tag'   => 'rentiva_booking_form',
            'title' => 'Booking Form',
            'css'   => 'booking-form.css',
        ],
    ];

    /**
     * Initialize the registry
     * 
     * @return void
     */
    public static function init(): void
    {
        add_action('init', [self::class, 'register_blocks']);
    }

    /**
     * Register all defined blocks with their dependencies
     * 
     * @return void
     */
    public static function register_blocks(): void
    {
        // Register Core Variables (Design Tokens) as a shared dependency
        wp_register_style(
            'mhm-rentiva-core-variables',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css',
            array(),
            MHM_RENTIVA_VERSION
        );

        foreach (self::$blocks as $slug => $config) {
            $script_handle = 'mhm-rentiva-block-' . $slug . '-editor';
            $style_handle  = 'mhm-rentiva-block-' . $slug . '-style';

            // 1. Register Editor Script (Shared requirements)
            wp_register_script(
                $script_handle,
                MHM_RENTIVA_PLUGIN_URL . 'assets/blocks/' . $slug . '/index.js',
                ['wp-blocks', 'wp-element', 'wp-components', 'wp-server-side-render', 'wp-block-editor'],
                MHM_RENTIVA_VERSION,
                true
            );

            // 2. Register Block Styles (With Core Variables as dependency)
            wp_register_style(
                $style_handle,
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/' . $config['css'],
                ['mhm-rentiva-core-variables'],
                MHM_RENTIVA_VERSION
            );

            // 3. Register Block Type via block.json
            register_block_type(
                MHM_RENTIVA_PLUGIN_DIR . 'assets/blocks/' . $slug . '/block.json',
                [
                    'render_callback' => [self::class, 'render_callback'],
                    'editor_script'   => $script_handle,
                    'style'           => $style_handle,
                ]
            );
        }
    }

    /**
     * Master Render Callback for all MHM Rentiva dynamic blocks
     * 
     * This method automatically maps the block attributes to the corresponding shortcode,
     * ensuring that Gutenberg blocks and shortcodes always share the same logic.
     * 
     * @param array $attributes Block attributes from editor.
     * @param string $content Inner block content (if any).
     * @param \WP_Block $block The block instance.
     * @return string Rendered HTML.
     */
    public static function render_callback(array $attributes, string $content, \WP_Block $block): string
    {
        // Extract the slug from the block name (e.g., mhm-rentiva/search -> search)
        $slug = str_replace('mhm-rentiva/', '', $block->name);

        if (! isset(self::$blocks[$slug])) {
            return '';
        }

        $tag = self::$blocks[$slug]['tag'];

        // Convert block attributes to shortcode attributes string
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr((string) $value));
        }

        /**
         * We use do_shortcode() instead of calling classes directly because:
         * 1. ShortcodeServiceProvider already handles access control (authentication).
         * 2. It handles dependency loading and asset synchronization.
         * 3. It ensures third-party filters on shortcodes are respected.
         */
        $shortcode_content = do_shortcode(sprintf('[%s%s]', $tag, $attr_string));

        // Return wrapped content with proper block wrapper attributes (FSE support)
        return sprintf(
            '<div %s>%s</div>',
            get_block_wrapper_attributes(),
            $shortcode_content
        );
    }
}
