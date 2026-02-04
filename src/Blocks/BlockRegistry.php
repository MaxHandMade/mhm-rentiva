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
            'css'   => ['datepicker-custom.css', 'vehicle-search-compact.css', 'vehicle-search.css'],
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
            'css'   => 'booking-confirmation.css',
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
        'featured-vehicles' => [
            'tag'   => 'rentiva_featured_vehicles',
            'title' => 'Featured Vehicles',
            'css'   => 'featured-vehicles.css',
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
        'transfer-search' => [
            'tag'   => 'rentiva_transfer_search',
            'title' => 'Transfer Search',
            'css'   => 'transfer.css',
        ],
        'messages' => [
            'tag'   => 'rentiva_messages',
            'title' => 'Customer Messages',
            'css'   => 'customer-messages.css',
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

        // Correct way to load assets specifically for the block editor (parent and iframe)
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_editor_assets']);
    }

    /**
     * Enqueue block editor specific assets
     */
    public static function enqueue_editor_assets(): void
    {
        // Ensure core variables are available inside the editor iframe
        wp_enqueue_style('mhm-rentiva-core-variables');

        // This will load in BOTH the parent and the iframe if registered correctly
        wp_enqueue_style(
            'mhm-rentiva-block-editor-fixes',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/editor/block-editor-fixes.css',
            [],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_script(
            'mhm-rentiva-block-editor-fixes-js',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/editor/block-editor-fixes.js',
            ['jquery', 'jquery-ui-datepicker', 'wp-blocks', 'wp-element', 'wp-data', 'wp-editor'],
            MHM_RENTIVA_VERSION . '.' . time(),
            true
        );

        // Localize for the editor fixes
        wp_localize_script('mhm-rentiva-block-editor-fixes-js', 'mhmRentivaSearch', [
            'ajax_url'           => admin_url('admin-ajax.php'),
            'datepicker_options' => self::get_datepicker_localization(),
        ]);
    }

    /**
     * Get datepicker localization data (Duplicated from VehicleSearch for editor access)
     */
    private static function get_datepicker_localization(): array
    {
        return [
            'dateFormat'      => 'yy-mm-dd', // Basic fallback
            'minDate'         => 0,
            'showButtonPanel' => true,
            'closeText'       => __('Close', 'mhm-rentiva'),
            'currentText'     => __('Today', 'mhm-rentiva'),
            'monthNames'      => [__('January', 'mhm-rentiva'), __('February', 'mhm-rentiva'), __('March', 'mhm-rentiva'), __('April', 'mhm-rentiva'), __('May', 'mhm-rentiva'), __('June', 'mhm-rentiva'), __('July', 'mhm-rentiva'), __('August', 'mhm-rentiva'), __('September', 'mhm-rentiva'), __('October', 'mhm-rentiva'), __('November', 'mhm-rentiva'), __('December', 'mhm-rentiva')],
            'dayNamesMin'     => [__('Su', 'mhm-rentiva'), __('Mo', 'mhm-rentiva'), __('Tu', 'mhm-rentiva'), __('We', 'mhm-rentiva'), __('Th', 'mhm-rentiva'), __('Fr', 'mhm-rentiva'), __('Sa', 'mhm-rentiva')],
        ];
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

            // 1. Register Editor Script (Shared requirements)
            wp_register_script(
                $script_handle,
                MHM_RENTIVA_PLUGIN_URL . 'assets/blocks/' . $slug . '/index.js',
                ['wp-blocks', 'wp-element', 'wp-components', 'wp-server-side-render', 'wp-block-editor'],
                MHM_RENTIVA_VERSION . '.' . time(),
                true
            );

            // 2. Register Block Styles (Support String or Array)
            $style_handles = [];
            $css_files     = is_array($config['css']) ? $config['css'] : [$config['css']];

            foreach ($css_files as $index => $css_file) {
                // Use simplified handle if single file, indexed handle if multiple
                $style_handle = (count($css_files) === 1)
                    ? 'mhm-rentiva-block-' . $slug . '-style'
                    : 'mhm-rentiva-block-' . $slug . '-style-' . $index;

                wp_register_style(
                    $style_handle,
                    MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/' . $css_file,
                    ['mhm-rentiva-core-variables'],
                    MHM_RENTIVA_VERSION
                );

                $style_handles[] = $style_handle;
            }

            // 3. Register Block Type via block.json
            register_block_type(
                MHM_RENTIVA_PLUGIN_DIR . 'assets/blocks/' . $slug . '/block.json',
                [
                    'render_callback' => [self::class, 'render_callback'],
                    'editor_script'   => $script_handle,
                    'style'           => $style_handles,
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

        $config = self::$blocks[$slug];
        $tag    = $config['tag'];

        // Consolidate dimensions into a single style attribute for the shortcode
        // We also pass them individually in lowercase to avoid case-sensitivity issues with WP shortcode parser
        $style_parts = [];
        if (!empty($attributes['minWidth'])) {
            $val = $attributes['minWidth'];
            if (is_numeric($val)) $val .= 'px';
            $style_parts[] = "min-width:$val";
            $attributes['minwidth'] = $val;
        }
        if (!empty($attributes['maxWidth'])) {
            $val = $attributes['maxWidth'];
            if (is_numeric($val)) $val .= 'px';
            $style_parts[] = "max-width:$val";
            $attributes['maxwidth'] = $val;
        }
        if (!empty($attributes['height'])) {
            $val = $attributes['height'];
            if (is_numeric($val)) $val .= 'px';
            $style_parts[] = "height:$val";
            $attributes['height'] = $val; // Overwrite with PX if needed
        }

        if (!empty($style_parts)) {
            $attributes['style'] = implode(';', $style_parts) . ';';
        }

        // Ensure className support is passed to inner templates
        if (! empty($attributes['className'])) {
            $attributes['class'] = $attributes['className'];
        }

        /**
         * We use do_shortcode() instead of calling classes directly because:
         * 1. ShortcodeServiceProvider already handles access control (authentication).
         * 2. It handles dependency loading and asset synchronization.
         * 3. It ensures third-party filters on shortcodes are respected.
         */
        $shortcode_content = do_shortcode('[' . $tag . ' ' . self::attributes_to_string($attributes) . ']');

        // Prepare wrapper attributes to ensure dimensions are applied to the container
        $wrapper_args = [];
        $wrapper_styles = [];

        if (!empty($attributes['maxWidth'])) {
            $val = $attributes['maxWidth'];
            if (is_numeric($val)) $val .= 'px';
            $wrapper_styles[] = "max-width:$val";
            $wrapper_styles[] = "width:100%";
            $wrapper_styles[] = "margin-left:auto";
            $wrapper_styles[] = "margin-right:auto";
        }

        if (!empty($attributes['minWidth'])) {
            $val = $attributes['minWidth'];
            if (is_numeric($val)) $val .= 'px';
            $wrapper_styles[] = "min-width:$val";
        }

        if (!empty($attributes['height'])) {
            $val = $attributes['height'];
            if (is_numeric($val)) $val .= 'px';
            $wrapper_styles[] = "height:$val";
        }

        if (!empty($wrapper_styles)) {
            $wrapper_args['style'] = implode(';', $wrapper_styles);
        }

        // Return wrapped content with proper block wrapper attributes (FSE support)
        return sprintf(
            '<div %s>%s</div>',
            get_block_wrapper_attributes($wrapper_args),
            $shortcode_content
        );
    }

    /**
     * Convert attributes array to shortcode string
     * 
     * @param array $attributes Block attributes.
     * @return string
     */
    private static function attributes_to_string(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $key => $value) {
            if (is_scalar($value)) {
                $out .= sprintf('%s="%s" ', esc_attr($key), esc_attr((string) $value));
            }
        }
        return trim($out);
    }
}
