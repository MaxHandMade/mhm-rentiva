<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Integration Class
 * 
 * Registers MHM Rentiva shortcodes as Elementor widgets
 * 
 * @since 3.0.1
 */
class ElementorIntegration
{
    /**
     * Register widgets
     * 
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager
     */
    public static function register_widgets($widgets_manager = null): void
    {
        // Elementor aktif mi kontrol et
        if (!self::is_elementor_active()) {
            return;
        }

        // Get Widgets Manager (from parameter or instance)
        $manager = $widgets_manager ?? \Elementor\Plugin::instance()->widgets_manager;

        // Register widgets (Elementor 3.5+ API)
        $manager->register(new VehicleCardWidget());
        $manager->register(new VehiclesListWidget());
        $manager->register(new BookingFormWidget());
        
        // Other widgets will be added here
        // $manager->register(new VehicleSearchWidget());
        // $manager->register(new QuickBookingWidget());
    }

    /**
     * Check if Elementor is active
     * 
     * @return bool Elementor aktif mi
     */
    public static function is_elementor_active(): bool
    {
        return (bool) did_action('elementor/loaded');
    }

    /**
     * Elementor kategorisini kaydeder
     * 
     * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager
     */
    public static function register_category($elements_manager = null): void
    {
        if (!self::is_elementor_active()) {
            return;
        }

        // Get Elements Manager (from parameter or instance)
        $manager = $elements_manager ?? \Elementor\Plugin::instance()->elements_manager;

        // Kategoriyi kaydet
        $manager->add_category(
            'mhm-rentiva',
            [
                'title' => __('MHM Rentiva', 'mhm-rentiva'),
                'icon' => 'fa fa-car',
            ],
            1 // Position (1 = at the beginning)
        );
    }

    /**
     * Load Elementor CSS files
     */
    public static function enqueue_styles(): void
    {
        if (!self::is_elementor_active()) {
            return;
        }

        // Load CSS file if exists
        $css_file = MHM_RENTIVA_PLUGIN_DIR . 'assets/css/frontend/elementor-widgets.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'mhm-rentiva-elementor',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/elementor-widgets.css',
                ['elementor-frontend', 'mhm-core-css'],
                MHM_RENTIVA_VERSION
            );
        }
    }

    /**
     * Load Elementor JavaScript files
     */
    public static function enqueue_scripts(): void
    {
        if (!self::is_elementor_active()) {
            return;
        }

        // Load JS file if exists
        $js_file = MHM_RENTIVA_PLUGIN_DIR . 'assets/js/frontend/elementor-widgets.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'mhm-rentiva-elementor',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/elementor-widgets.js',
                ['elementor-frontend', 'mhm-core-js'],
                MHM_RENTIVA_VERSION,
                true
            );

            // Localize script
            wp_localize_script('mhm-rentiva-elementor', 'mhmRentivaElementor', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_rentiva_elementor'),
                'i18n' => [
                    'loading' => __('Loading...', 'mhm-rentiva'),
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'success' => __('Success', 'mhm-rentiva'),
                ],
            ]);
        }
    }

    /**
     * Load Elementor editor CSS files
     */
    public static function enqueue_editor_styles(): void
    {
        if (!self::is_elementor_active()) {
            return;
        }

        // Load CSS file if exists
        $css_file = MHM_RENTIVA_PLUGIN_DIR . 'assets/css/admin/elementor-editor.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'mhm-rentiva-elementor-editor',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/elementor-editor.css',
                ['elementor-editor'],
                MHM_RENTIVA_VERSION
            );
        }
    }

    /**
     * Load Elementor editor JavaScript files
     */
    public static function enqueue_editor_scripts(): void
    {
        if (!self::is_elementor_active()) {
            return;
        }

        // Load JS file if exists
        $js_file = MHM_RENTIVA_PLUGIN_DIR . 'assets/js/admin/elementor-editor.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'mhm-rentiva-elementor-editor',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/elementor-editor.js',
                ['elementor-editor'],
                MHM_RENTIVA_VERSION,
                true
            );

            // Localize script
            wp_localize_script('mhm-rentiva-elementor-editor', 'mhmRentivaElementorEditor', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_rentiva_elementor_editor'),
                'i18n' => [
                    'select_vehicle' => __('Select Vehicle', 'mhm-rentiva'),
                    'no_vehicles' => __('No vehicles found', 'mhm-rentiva'),
                    'loading' => __('Loading...', 'mhm-rentiva'),
                ],
            ]);
        }
    }

    /**
     * Register Elementor hooks
     */
    public static function register_hooks(): void
    {
        // Exit if Elementor is not active
        if (!self::is_elementor_active()) {
            return;
        }
        
        // Register widgets
        add_action('elementor/widgets/register', [self::class, 'register_widgets'], 10);
        
        // Register category
        add_action('elementor/elements/categories_registered', [self::class, 'register_category'], 10);
        
        // Load CSS/JS
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_styles'], 10);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts'], 10);
        add_action('elementor/editor/before_enqueue_styles', [self::class, 'enqueue_editor_styles'], 10);
        add_action('elementor/editor/before_enqueue_scripts', [self::class, 'enqueue_editor_scripts'], 10);
    }

    /**
     * Initializes Elementor integration
     */
    public static function init(): void
    {
        // Register hooks after Elementor is loaded
        add_action('elementor/loaded', [self::class, 'register_hooks']);
        
        // Also register manually (for security)
        add_action('init', [self::class, 'register_hooks'], 20);
    }
}
