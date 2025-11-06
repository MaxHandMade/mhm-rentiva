<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Asset Manager - Manages CSS and JS files centrally
 */
final class AssetManager
{
    /**
     * Core CSS files
     */
    private static array $core_css = [
        'mhm-css-variables' => [
            'url' => 'assets/css/core/css-variables.css',
            'deps' => [],
            'version' => MHM_RENTIVA_VERSION
        ],
        'mhm-core-css' => [
            'url' => 'assets/css/core/core.css',
            'deps' => ['mhm-css-variables'],
            'version' => MHM_RENTIVA_VERSION
        ],
        'mhm-animations' => [
            'url' => 'assets/css/core/animations.css',
            'deps' => ['mhm-css-variables'],
            'version' => MHM_RENTIVA_VERSION
        ]
    ];

    /**
     * Component CSS files
     */
    private static array $component_css = [
        'mhm-stats-cards' => [
            'url' => 'assets/css/components/stats-cards.css',
            'deps' => ['mhm-core-css'],
            'version' => MHM_RENTIVA_VERSION
        ],
        'mhm-calendars' => [
            'url' => 'assets/css/components/calendars.css',
            'deps' => ['mhm-core-css'],
            'version' => MHM_RENTIVA_VERSION
        ]
    ];

    /**
     * Core JS files
     */
    private static array $core_js = [
        'mhm-core-js' => [
            'url' => 'assets/js/core/core.js',
            'deps' => ['jquery'],
            'version' => MHM_RENTIVA_VERSION,
            'in_footer' => true
        ],
        'mhm-utilities' => [
            'url' => 'assets/js/core/utilities.js',
            'deps' => ['jquery', 'mhm-core-js'],
            'version' => MHM_RENTIVA_VERSION,
            'in_footer' => true
        ],
        'mhm-i18n' => [
            'url' => 'assets/js/core/i18n.js',
            'deps' => ['jquery', 'mhm-core-js'],
            'version' => MHM_RENTIVA_VERSION,
            'in_footer' => true
        ],
        'mhm-performance' => [
            'url' => 'assets/js/core/performance.js',
            'deps' => ['jquery', 'mhm-utilities'],
            'version' => MHM_RENTIVA_VERSION,
            'in_footer' => true
        ],
        'mhm-module-loader' => [
            'url' => 'assets/js/core/module-loader.js',
            'deps' => ['jquery', 'mhm-core-js'],
            'version' => MHM_RENTIVA_VERSION,
            'in_footer' => true
        ]
    ];

    /**
     * Initialize Asset Manager
     */
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('wp_head', [self::class, 'add_inline_styles']);
        add_action('admin_head', [self::class, 'add_inline_styles']);
    }

    /**
     * Load frontend assets
     * Note: Frontend shortcode assets are now handled by AbstractShortcode
     */
    public static function enqueue_frontend_assets(): void
    {
        // Load core CSS files
        self::enqueue_core_css();
        
        // Load core JS files
        self::enqueue_core_js();
        
        // Only load non-shortcode frontend assets
        self::enqueue_frontend_specific_assets();
    }

    /**
     * Load admin assets
     */
    public static function enqueue_admin_assets(): void
    {
        // Load core CSS files
        self::enqueue_core_css();
        
        // JS Kill-switch: allow disabling plugin admin JS for debugging
        $disableJs = isset($_GET['mhm_admin_no_js']) && (string) $_GET['mhm_admin_no_js'] === '1';
        if ($disableJs) {
            return; // Do not enqueue any JS if kill-switch is enabled
        }

        // Load core JS files
        self::enqueue_core_js();
        
        // Admin-specific assets
        self::enqueue_admin_specific_assets();
    }

    /**
     * Load core CSS files
     */
    public static function enqueue_core_css(): void
    {
        foreach (self::$core_css as $handle => $asset) {
            wp_enqueue_style(
                $handle,
                MHM_RENTIVA_PLUGIN_URL . $asset['url'],
                $asset['deps'],
                $asset['version']
            );
        }
    }

    /**
     * Load core JS files
     */
    public static function enqueue_core_js(): void
    {
        foreach (self::$core_js as $handle => $asset) {
            wp_enqueue_script(
                $handle,
                MHM_RENTIVA_PLUGIN_URL . $asset['url'],
                $asset['deps'],
                $asset['version'],
                $asset['in_footer'] ?? true
            );
        }

        // Localize JavaScript configuration
        self::localize_scripts();
    }

    /**
     * Load component CSS file
     * @param string $component - Component name
     */
    public static function enqueue_component_css(string $component): void
    {
        if (isset(self::$component_css[$component])) {
            $asset = self::$component_css[$component];
            wp_enqueue_style(
                $component,
                MHM_RENTIVA_PLUGIN_URL . $asset['url'],
                $asset['deps'],
                $asset['version']
            );
        }
    }

    /**
     * Load component JS file
     * @param string $component - Component name
     */
    public static function enqueue_component_js(string $component): void
    {
        $components = [
            'addon-booking' => [
                'url' => 'assets/js/components/addon-booking.js',
                'deps' => ['jquery'],
            ],
            'vehicle-meta' => [
                'url' => 'assets/js/components/vehicle-meta.js',
                'deps' => ['jquery', 'jquery-ui-sortable'],
            ],
            'vehicle-quick-edit' => [
                'url' => 'assets/js/components/vehicle-quick-edit.js',
                'deps' => ['jquery', 'inline-edit-post'],
            ],
        ];
        
        if (isset($components[$component])) {
            $asset = $components[$component];
            wp_enqueue_script(
                'mhm-' . $component,
                MHM_RENTIVA_PLUGIN_URL . $asset['url'],
                $asset['deps'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            // Component-specific localization
            self::localize_component_script($component);
        }
    }
    
    /**
     * Localize component script
     */
    private static function localize_component_script(string $component): void
    {
        switch ($component) {
            case 'addon-booking':
                wp_localize_script('mhm-addon-booking', 'mhmAddonBooking', [
                    'currency' => self::get_currency_symbol(),
                    'locale' => self::get_js_locale(),
                    'strings' => [
                        'totalAddons' => __('Total Add-ons', 'mhm-rentiva'),
                        'noAddonsSelected' => __('No add-ons selected', 'mhm-rentiva'),
                    ]
                ]);
                break;
                
            case 'vehicle-meta':
                wp_localize_script('mhm-vehicle-meta', 'mhmVehicleMeta', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mhm_vehicle_meta_nonce'),
                    'strings' => [
                        'orderUpdated' => __('Order updated!', 'mhm-rentiva'),
                        'orderSaveError' => __('Failed to save order', 'mhm-rentiva'),
                        'ajaxError' => __('AJAX error: Failed to save order', 'mhm-rentiva'),
                        'enterNewFeature' => __('Enter new feature name:', 'mhm-rentiva'),
                        'enterNewEquipment' => __('Enter new equipment name:', 'mhm-rentiva'),
                        'enterNewDetail' => __('Enter new detail name:', 'mhm-rentiva'),
                        'confirmRemoveFeature' => __('Are you sure you want to remove this feature?', 'mhm-rentiva'),
                        'confirmRemoveEquipment' => __('Are you sure you want to remove this equipment?', 'mhm-rentiva'),
                        'enterValue' => __('Enter value', 'mhm-rentiva'),
                        'remove' => __('Remove', 'mhm-rentiva'),
                        'available' => __('Available', 'mhm-rentiva'),
                        'notAvailable' => __('Not Available', 'mhm-rentiva'),
                        'validFormat' => __('Valid format', 'mhm-rentiva'),
                        'invalidFormat' => __('Invalid format', 'mhm-rentiva'),
                        'depositFormatHelp' => __('Fixed: 1000 | Percent: %20', 'mhm-rentiva'),
                        'depositPlaceholder' => __('1000 or %20', 'mhm-rentiva'),
                        'comingSoonCustomAdd' => __('Coming soon! Use the Custom Add button for now.', 'mhm-rentiva'),
                        'redirectingToSettings' => __('Redirecting to Vehicle Settings...', 'mhm-rentiva'),
                    ]
                ]);
                break;
                
            case 'vehicle-quick-edit':
                wp_localize_script('mhm-vehicle-quick-edit', 'mhmVehicleQuickEdit', [
                    'labels' => [
                        'manual' => __('Manual', 'mhm-rentiva'),
                        'diesel' => __('Diesel', 'mhm-rentiva'),
                        'hybrid' => __('Hybrid', 'mhm-rentiva'),
                        'electric' => __('Electric', 'mhm-rentiva'),
                        'passive' => __('Passive', 'mhm-rentiva'),
                        'maintenance' => __('Maintenance', 'mhm-rentiva'),
                    ]
                ]);
                break;
        }
    }

    /**
     * Load stats cards CSS
     */
    public static function enqueue_stats_cards(): void
    {
        self::enqueue_component_css('mhm-stats-cards');
    }

    /**
     * Load calendars CSS
     */
    public static function enqueue_calendars(): void
    {
        self::enqueue_component_css('mhm-calendars');
    }

    /**
     * Load frontend-specific assets
     */
    private static function enqueue_frontend_specific_assets(): void
    {
        // Frontend shortcode assets are now handled by AbstractShortcode
        // Only non-shortcode frontend assets should be loaded here
    }

    /**
     * Load admin-specific assets
     */
    private static function enqueue_admin_specific_assets(): void
    {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        // Global admin scripts
        self::enqueue_admin_global_scripts();
        
        // Screen-specific scripts
        self::enqueue_screen_specific_scripts($screen);
    }
    
    /**
     * Load global admin scripts
     */
    private static function enqueue_admin_global_scripts(): void
    {
        // General settings for admin
        wp_localize_script('mhm-core-js', 'mhmRentivaAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_admin_nonce'),
            'locale' => get_locale(),
            'currency' => get_option('mhm_rentiva_currency', 'USD'),
            'currencySymbol' => self::get_currency_symbol(),
            'currencyPosition' => get_option('mhm_rentiva_currency_position', 'left'),
            'dateFormat' => get_option('date_format', 'Y-m-d'),
            'timeFormat' => get_option('time_format', 'H:i'),
            'strings' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'error' => __('An error occurred', 'mhm-rentiva'),
                'success' => __('Operation successful', 'mhm-rentiva'),
                'confirm' => __('Are you sure?', 'mhm-rentiva'),
                'cancel' => __('Cancel', 'mhm-rentiva'),
                'save' => __('Save', 'mhm-rentiva'),
                'delete' => __('Delete', 'mhm-rentiva'),
                'processing' => __('Processing...', 'mhm-rentiva'),
                'confirmRefund' => __('Are you sure you want to refund this payment?', 'mhm-rentiva'),
                'processingRefund' => __('Processing refund...', 'mhm-rentiva'),
                'refundError' => __('Refund error', 'mhm-rentiva'),
                'refundCompleted' => __('Refund completed (or queued). Please refresh the page.', 'mhm-rentiva'),
                'required_field' => __('This field is required', 'mhm-rentiva'),
                'invalid_email' => __('Please enter a valid email address', 'mhm-rentiva'),
                'invalid_phone' => __('Please enter a valid phone number', 'mhm-rentiva'),
                'value_range' => __('Value must be between %min and %max', 'mhm-rentiva'),
                'dropoff_after_pickup' => __('Dropoff date must be after pickup date', 'mhm-rentiva'),
                'pickup_not_past' => __('Pickup date cannot be in the past', 'mhm-rentiva'),
                'dismiss' => __('Dismiss this notice', 'mhm-rentiva'),
                'confirmStatusChange' => __('Are you sure you want to change the booking status to "%s"?', 'mhm-rentiva'),
                'changing' => __('Changing status...', 'mhm-rentiva'),
            ],
            'statusLabels' => [
                'pending' => __('Pending', 'mhm-rentiva'),
                'confirmed' => __('Confirmed', 'mhm-rentiva'),
                'in_progress' => __('In Progress', 'mhm-rentiva'),
                'completed' => __('Completed', 'mhm-rentiva'),
                'cancelled' => __('Cancelled', 'mhm-rentiva'),
            ]
        ]);
    }
    
    /**
     * Load screen-specific scripts
     */
    private static function enqueue_screen_specific_scripts($screen): void
    {
        // Manual Booking Meta
        if ($screen->id === 'booking') {
            wp_enqueue_script(
                'mhm-manual-booking-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/manual-booking-meta.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-manual-booking-meta', 'mhmManualBooking', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_manual_booking_nonce'),
                'currency' => self::get_currency_symbol(),
                'locale' => self::get_js_locale(),
                'text' => [
                    'calculating' => __('Calculating...', 'mhm-rentiva'),
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'priceCalculated' => __('Price calculated', 'mhm-rentiva'),
                    'dropoffAfterPickup' => __('Dropoff date must be after pickup date', 'mhm-rentiva'),
                    'fillAllFields' => __('Please fill all required fields', 'mhm-rentiva'),
                    'calculatePrice' => __('Calculate Price', 'mhm-rentiva'),
                    'creating' => __('Creating...', 'mhm-rentiva'),
                    'createBooking' => __('Create Booking', 'mhm-rentiva'),
                    'selectedVehicle' => __('Selected Vehicle', 'mhm-rentiva'),
                    'vehicle' => __('Vehicle', 'mhm-rentiva'),
                    'dailyPrice' => __('Daily Price', 'mhm-rentiva'),
                    'notSpecified' => __('Not specified', 'mhm-rentiva'),
                    'rentalDays' => __('Rental Days', 'mhm-rentiva'),
                    'days' => __('days', 'mhm-rentiva'),
                    'vehicleTotal' => __('Vehicle Total', 'mhm-rentiva'),
                    'addons' => __('Add-ons', 'mhm-rentiva'),
                    'grandTotal' => __('Grand Total', 'mhm-rentiva'),
                    'deposit' => __('Deposit', 'mhm-rentiva'),
                    'remaining' => __('Remaining', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Booking Edit Meta
        if ($screen->id === 'booking' && isset($_GET['action']) && $_GET['action'] === 'edit') {
            wp_enqueue_script(
                'mhm-booking-edit-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-edit-meta.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            // Localize additional strings for booking edit
            wp_localize_script('mhm-booking-edit-meta', 'mhmBookingEdit', [
                'strings' => [
                    'confirmStatusChange' => __('Are you sure you want to change the booking status to "%s"?', 'mhm-rentiva'),
                    'changing' => __('Changing status...', 'mhm-rentiva'),
                    'required_field' => __('This field is required', 'mhm-rentiva'),
                    'invalid_email' => __('Please enter a valid email address', 'mhm-rentiva'),
                    'invalid_phone' => __('Please enter a valid phone number', 'mhm-rentiva'),
                    'value_range' => __('Value must be between %min and %max', 'mhm-rentiva'),
                    'dropoff_after_pickup' => __('Dropoff date must be after pickup date', 'mhm-rentiva'),
                    'pickup_not_past' => __('Pickup date cannot be in the past', 'mhm-rentiva'),
                    'dismiss' => __('Dismiss this notice', 'mhm-rentiva'),
                ],
                'statusLabels' => [
                    'pending' => __('Pending', 'mhm-rentiva'),
                    'confirmed' => __('Confirmed', 'mhm-rentiva'),
                    'in_progress' => __('In Progress', 'mhm-rentiva'),
                    'completed' => __('Completed', 'mhm-rentiva'),
                    'cancelled' => __('Cancelled', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Dashboard
        if ($screen->id === 'toplevel_page_mhm-rentiva') {
            wp_enqueue_script(
                'chart-js',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/vendor/chart.min.js',
                [],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_enqueue_script(
                'mhm-dashboard',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/dashboard.js',
                ['jquery', 'chart-js'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-dashboard', 'mhm_dashboard_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_dashboard_nonce'),
                'currency' => self::get_currency_symbol(),
                'locale' => self::get_js_locale(),
                'revenue_data' => self::get_dashboard_revenue_data(),
                'strings' => [
                    'daily_revenue' => __('Daily Revenue', 'mhm-rentiva'),
                    'revenue' => __('Revenue', 'mhm-rentiva'),
                    'this_month' => __('this month', 'mhm-rentiva'),
                    'available' => __('available', 'mhm-rentiva'),
                    'new' => __('new', 'mhm-rentiva'),
                    'refresh_error' => __('Error refreshing dashboard data', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Reports Charts
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-reports') {
            wp_enqueue_script(
                'chart-js',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/vendor/chart.min.js',
                [],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_enqueue_script(
                'mhm-reports-charts',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/reports-charts.js',
                ['jquery', 'chart-js'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-reports-charts', 'mhmRentivaCharts', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_charts_nonce'),
                'currencySymbol' => self::get_currency_symbol(),
                'locale' => self::get_js_locale(),
                'strings' => [
                    'daily_revenue' => __('Daily Revenue', 'mhm-rentiva'),
                    'no_data' => __('No data available', 'mhm-rentiva'),
                    'error_loading' => __('Error loading data', 'mhm-rentiva'),
                    'vip_customers' => __('VIP Customers', 'mhm-rentiva'),
                    'regular_customers' => __('Regular Customers', 'mhm-rentiva'),
                    'new_customers' => __('New Customers', 'mhm-rentiva'),
                    'booking_count' => __('Booking Count', 'mhm-rentiva'),
                ],
                'statusLabels' => [
                    'pending' => __('Pending', 'mhm-rentiva'),
                    'confirmed' => __('Confirmed', 'mhm-rentiva'),
                    'completed' => __('Completed', 'mhm-rentiva'),
                    'cancelled' => __('Cancelled', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Deposit Management
        if ($screen->id === 'booking' && isset($_GET['deposit_management'])) {
            wp_enqueue_script(
                'mhm-deposit-management',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/deposit-management.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-deposit-management', 'mhmDepositManagement', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_deposit_nonce'),
                'strings' => [
                    'confirmRemainingPayment' => __('Are you sure you want to process the remaining payment?', 'mhm-rentiva'),
                    'confirmApprovePayment' => __('Are you sure you want to approve this payment?', 'mhm-rentiva'),
                    'confirmCancelBooking' => __('Are you sure you want to cancel this booking?', 'mhm-rentiva'),
                    'confirmRefund' => __('Are you sure you want to process this refund?', 'mhm-rentiva'),
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'dismiss' => __('Dismiss this notice', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Gutenberg Blocks
        if ($screen->base === 'post' && $screen->is_block_editor) {
            wp_enqueue_script(
                'mhm-gutenberg-blocks',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/gutenberg-blocks.js',
                ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-gutenberg-blocks', 'mhmRentivaGutenberg', [
                'vehicleOptions' => self::get_vehicle_options_for_gutenberg(),
            ]);
        }
        
        // Shortcode Settings
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-shortcode-settings') {
            wp_enqueue_script(
                'mhm-shortcode-settings',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/shortcode-settings.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-shortcode-settings', 'mhmRentivaShortcodeSettings', [
                'i18n' => [
                    'copied' => __('Copied to clipboard', 'mhm-rentiva'),
                    'copy_failed' => __('Failed to copy', 'mhm-rentiva'),
                    'confirm_reset' => __('Are you sure you want to reset all settings to default?', 'mhm-rentiva'),
                    'disable_all' => __('Disable All', 'mhm-rentiva'),
                    'enable_all' => __('Enable All', 'mhm-rentiva'),
                    'unsaved_changes' => __('You have unsaved changes. Are you sure you want to leave this page?', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Vehicle Gallery & Meta
        if ($screen->id === 'vehicle' || $screen->post_type === 'vehicle') {
            wp_enqueue_media();
            wp_enqueue_script(
                'mhm-vehicle-gallery',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-gallery.js',
                ['jquery', 'jquery-ui-sortable'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-vehicle-gallery', 'mhmVehicleGallery', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_gallery_nonce'),
                'strings' => [
                    'maxImages' => __('Maximum 10 images allowed', 'mhm-rentiva'),
                    'selectImages' => __('Select Images', 'mhm-rentiva'),
                    'addImages' => __('Add to Gallery', 'mhm-rentiva'),
                    'uploadError' => __('Error uploading image', 'mhm-rentiva'),
                    'confirmRemove' => __('Are you sure you want to remove this image?', 'mhm-rentiva'),
                    'removeImage' => __('Remove Image', 'mhm-rentiva'),
                    'addError' => __('Error adding image', 'mhm-rentiva'),
                    'removeError' => __('Error removing image', 'mhm-rentiva'),
                    'reorderError' => __('Error reordering images', 'mhm-rentiva'),
                ]
            ]);
            
            // Vehicle Meta Component
            self::enqueue_component_js('vehicle-meta');
        }
        
        // Vehicle Quick Edit
        if ($screen->id === 'edit-vehicle' || $screen->post_type === 'vehicle') {
            self::enqueue_component_js('vehicle-quick-edit');
        }
        
        // Messages Settings
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-messages-settings') {
            wp_enqueue_script(
                'mhm-messages-settings',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/messages-settings.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-messages-settings', 'mhmMessagesSettings', [
                'strings' => [
                    'enterCategoryName' => __('Please enter a category name', 'mhm-rentiva'),
                    'categoryExists' => __('This category already exists', 'mhm-rentiva'),
                    'confirmDeleteCategory' => __('Are you sure you want to delete this category?', 'mhm-rentiva'),
                    'enterStatusName' => __('Please enter a status name', 'mhm-rentiva'),
                    'statusExists' => __('This status already exists', 'mhm-rentiva'),
                    'confirmDeleteStatus' => __('Are you sure you want to delete this status?', 'mhm-rentiva'),
                    'delete' => __('Delete', 'mhm-rentiva'),
                    'validAdminEmail' => __('Enter a valid admin email address', 'mhm-rentiva'),
                    'validFromEmail' => __('Enter a valid sender email address', 'mhm-rentiva'),
                    'maxMessagesRange' => __('Widget max messages must be between 1-20', 'mhm-rentiva'),
                    'duplicateCategory' => __('Duplicate category names are not allowed', 'mhm-rentiva'),
                    'duplicateStatus' => __('Duplicate status names are not allowed', 'mhm-rentiva'),
                    'formErrors' => __('Form errors', 'mhm-rentiva'),
                    'saving' => __('Saving...', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Customers Calendar
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-customers' && isset($_GET['view']) && $_GET['view'] === 'calendar') {
            wp_enqueue_script(
                'mhm-customers-calendar',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers-calendar.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-customers-calendar', 'mhmCustomersCalendar', [
                'locale' => self::get_js_locale(),
                'strings' => [
                    'selectedDate' => __('Selected date', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Booking Calendar
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-bookings' && isset($_GET['view']) && $_GET['view'] === 'calendar') {
            wp_enqueue_script(
                'mhm-booking-calendar',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-calendar.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-booking-calendar', 'mhmBookingCalendar', [
                'locale' => self::get_js_locale(),
                'strings' => [
                    'selectedDate' => __('Selected date', 'mhm-rentiva'),
                ]
            ]);
        }

        // Bookings List (CPT list) - ensure bulk actions work reliably
        if ($screen->id === 'edit-vehicle_booking') {
            wp_enqueue_script(
                'mhm-booking-bulk-actions',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-bulk-actions.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            wp_localize_script('mhm-booking-bulk-actions', 'mhmBookingBulkActions', [
                'strings' => [
                    'no_items_selected' => __('Please select at least one item to perform this action on.', 'mhm-rentiva'),
                    'confirm_bulk_trash' => __('Move %d selected bookings to Trash?', 'mhm-rentiva'),
                    'confirm_bulk_delete' => __('Permanently delete %d selected bookings?', 'mhm-rentiva'),
                    'confirm_single_trash' => __('Move this booking to Trash?', 'mhm-rentiva'),
                    'confirm_single_delete' => __('Permanently delete this booking?', 'mhm-rentiva'),
                    'confirm_empty_trash' => __('Empty Trash for all bookings?', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Monitoring
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-monitoring') {
            wp_enqueue_script(
                'mhm-monitoring',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/monitoring.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-monitoring', 'mhmMonitoring', [
                'nonce' => wp_create_nonce('mhm_monitoring_nonce'),
                'logsUrl' => admin_url('admin.php?page=mhm-rentiva-logs'),
                'strings' => [
                    'reportPrinted' => __('Performance report printed to console', 'mhm-rentiva'),
                    'reportFailed' => __('Failed to get performance report', 'mhm-rentiva'),
                    'confirmClearPerf' => __('Are you sure you want to clear performance data?', 'mhm-rentiva'),
                    'perfCleared' => __('Performance data cleared', 'mhm-rentiva'),
                    'clearFailed' => __('Failed to clear data', 'mhm-rentiva'),
                    'confirmClearLogs' => __('Are you sure you want to clear logs older than 7 days?', 'mhm-rentiva'),
                    'error' => __('Error', 'mhm-rentiva'),
                    'exportSoon' => __('Log export feature coming soon', 'mhm-rentiva'),
                    'noLogs' => __('No logs found', 'mhm-rentiva'),
                    'page' => __('Page', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Settings
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-settings') {
            wp_enqueue_script(
                'mhm-settings',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/settings.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-settings', 'mhmRentivaSettings', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_rentiva_settings'),
                'strings' => [
                    'categoryEmpty' => __('Category name cannot be empty', 'mhm-rentiva'),
                    'categoryExists' => __('This category already exists', 'mhm-rentiva'),
                    'confirmDeleteCategory' => __('Are you sure you want to delete this category?', 'mhm-rentiva'),
                    'statusEmpty' => __('Status name cannot be empty', 'mhm-rentiva'),
                    'statusExists' => __('This status already exists', 'mhm-rentiva'),
                    'confirmDeleteStatus' => __('Are you sure you want to delete this status?', 'mhm-rentiva'),
                    'validAdminEmail' => __('Enter a valid admin email address', 'mhm-rentiva'),
                    'validFromEmail' => __('Enter a valid sender email address', 'mhm-rentiva'),
                    'validEmail' => __('Enter a valid email address', 'mhm-rentiva'),
                    'maxMessagesRange' => __('Widget max messages must be between 1-20', 'mhm-rentiva'),
                    'duplicateCategory' => __('Duplicate category names are not allowed', 'mhm-rentiva'),
                    'duplicateStatus' => __('Duplicate status names are not allowed', 'mhm-rentiva'),
                    'formErrors' => __('Form errors', 'mhm-rentiva'),
                    'saving' => __('Saving...', 'mhm-rentiva'),
                    'delete' => __('Delete', 'mhm-rentiva'),
                    'templatePreview' => __('Template Preview', 'mhm-rentiva'),
                    'templateEmpty' => __('Template content is empty', 'mhm-rentiva'),
                    'templateResetSuccess' => __('Template reset to default', 'mhm-rentiva'),
                    'confirmResetTab' => __('Are you sure you want to reset this tab\'s settings to default values? This action cannot be undone.', 'mhm-rentiva'),
                    'resetting' => __('Resetting...', 'mhm-rentiva'),
                    'resetSuccess' => __('Settings reset to defaults successfully. Page will reload...', 'mhm-rentiva'),
                    'resetFailed' => __('Failed to reset settings to defaults.', 'mhm-rentiva'),
                    'errorOccurred' => __('An error occurred. Please try again.', 'mhm-rentiva'),
                    'adminEmailRequired' => __('Admin email address is required', 'mhm-rentiva'),
                    'fromNameRequired' => __('Sender name is required', 'mhm-rentiva'),
                    'fromEmailRequired' => __('Sender email address is required', 'mhm-rentiva'),
                    'minOneCategory' => __('At least one category must be defined', 'mhm-rentiva'),
                    'minOneStatus' => __('At least one status must be defined', 'mhm-rentiva'),
                    'confirmResetTemplate' => __('Are you sure you want to reset this template to default?', 'mhm-rentiva'),
                    'defaultNewMessage' => __('New message received: {{subject}}', 'mhm-rentiva'),
                    'defaultReply' => __('Reply to your message: {{subject}}', 'mhm-rentiva'),
                    'defaultStatusChange' => __('Message status changed: {{subject}}', 'mhm-rentiva'),
                    'defaultAutoReply' => __('Your message received: {{subject}}', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Messages Admin
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-messages') {
            wp_enqueue_script(
                'mhm-messages-admin',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/messages-admin.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-messages-admin', 'mhmMessagesAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_messages_nonce'),
                'locale' => self::get_js_locale(),
                'strings' => [
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'no_items_selected' => __('Please select at least one item', 'mhm-rentiva'),
                    'confirm_mark_read' => __('Mark %d message(s) as read?', 'mhm-rentiva'),
                    'confirm_mark_unread' => __('Mark %d message(s) as unread?', 'mhm-rentiva'),
                    'confirm_delete' => __('Delete %d message(s)? This action cannot be undone.', 'mhm-rentiva'),
                    'processing' => __('Processing...', 'mhm-rentiva'),
                    'justNow' => __('Just now', 'mhm-rentiva'),
                    'minutesAgo' => __('minutes ago', 'mhm-rentiva'),
                    'hoursAgo' => __('hours ago', 'mhm-rentiva'),
                    'daysAgo' => __('days ago', 'mhm-rentiva'),
                    'draftSaved' => __('Draft saved', 'mhm-rentiva'),
                    'sendReply' => __('Send Reply', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Addon List
        if ($screen->id === 'edit-addon' || $screen->post_type === 'addon') {
            wp_enqueue_script(
                'mhm-addon-list',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/addon-list.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-addon-list', 'mhm_addon_list_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_addon_list_nonce'),
                'currency' => self::get_currency_symbol(),
                'locale' => self::get_js_locale(),
                'no_items_selected' => __('Please select at least one item', 'mhm-rentiva'),
                'items_selected' => __('selected', 'mhm-rentiva'),
                'confirm_enable' => __('Enable selected add-ons?', 'mhm-rentiva'),
                'confirm_disable' => __('Disable selected add-ons?', 'mhm-rentiva'),
                'confirm_delete' => __('Delete selected add-ons? This action cannot be undone.', 'mhm-rentiva'),
                'processing' => __('Processing...', 'mhm-rentiva'),
                'error_occurred' => __('An error occurred', 'mhm-rentiva'),
                'strings' => [
                    'invalidPrice' => __('Invalid price value!', 'mhm-rentiva'),
                    'priceUpdateError' => __('Error updating price', 'mhm-rentiva'),
                    'unknownError' => __('Unknown error', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // About Page
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-about') {
            wp_enqueue_script(
                'mhm-about',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/about.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-about', 'mhmAboutAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_about_nonce'),
                'strings' => [
                    'loading' => __('Loading...', 'mhm-rentiva'),
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'copied' => __('Copied!', 'mhm-rentiva'),
                    'copyFailed' => __('Copy failed', 'mhm-rentiva'),
                    'refreshing' => __('Refreshing...', 'mhm-rentiva'),
                    'systemRefreshed' => __('System information refreshed', 'mhm-rentiva'),
                    'refreshSystemInfo' => __('Refresh System Info', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Customers
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-customers') {
            wp_enqueue_script(
                'mhm-customers',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-customers', 'mhm_rentiva_customers', [
                'nonce' => wp_create_nonce('mhm_customers_nonce'),
                'currencySymbol' => self::get_currency_symbol(),
                'strings' => [
                    'this_month' => __('this month', 'mhm-rentiva'),
                    'new' => __('new', 'mhm-rentiva'),
                    'total_spent' => __('Total Spent', 'mhm-rentiva'),
                    'statsError' => __('Error loading statistics', 'mhm-rentiva'),
                    'loadError' => __('Error loading customer data', 'mhm-rentiva'),
                    'selectAction' => __('Please select an action', 'mhm-rentiva'),
                    'selectCustomer' => __('Please select at least one customer', 'mhm-rentiva'),
                    'confirmBulkAction' => __('Are you sure you want to perform this action for %d customer(s)?', 'mhm-rentiva'),
                    'actionError' => __('Error performing action', 'mhm-rentiva'),
                    'actionSuccess' => __('Operation completed successfully', 'mhm-rentiva'),
                    'exportStarted' => __('Export process started', 'mhm-rentiva'),
                    'customerDetails' => __('Customer Details', 'mhm-rentiva'),
                    'name' => __('Name', 'mhm-rentiva'),
                    'email' => __('Email', 'mhm-rentiva'),
                    'phone' => __('Phone', 'mhm-rentiva'),
                    'totalBookings' => __('Total Bookings', 'mhm-rentiva'),
                    'lastBooking' => __('Last Booking', 'mhm-rentiva'),
                    'close' => __('Close', 'mhm-rentiva'),
                    'edit' => __('Edit', 'mhm-rentiva'),
                    'selected' => __('selected', 'mhm-rentiva'),
                    'detailsError' => __('Error loading customer details', 'mhm-rentiva'),
                ],
                'bulkActions' => [
                    'bulk_actions' => __('Bulk Actions', 'mhm-rentiva'),
                    'export' => __('Export', 'mhm-rentiva'),
                    'delete' => __('Delete', 'mhm-rentiva'),
                    'mark_active' => __('Mark as Active', 'mhm-rentiva'),
                    'mark_inactive' => __('Mark as Inactive', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Email Templates
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-email-templates') {
            wp_enqueue_script(
                'mhm-email-templates',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/email-templates.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-email-templates', 'mhm_email_templates_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_email_templates_nonce'),
                'preview_email' => __('Email Preview', 'mhm-rentiva'),
                'send_test' => __('Send Test Email', 'mhm-rentiva'),
                'processing' => __('Processing...', 'mhm-rentiva'),
                'test_email_sent' => __('Test email sent successfully', 'mhm-rentiva'),
                'test_email_failed' => __('Failed to send test email', 'mhm-rentiva'),
                'error_occurred' => __('An error occurred', 'mhm-rentiva'),
                'strings' => [
                    'sendTestEmail' => __('Send Test Email', 'mhm-rentiva'),
                    'emailAddress' => __('Email Address', 'mhm-rentiva'),
                    'cancel' => __('Cancel', 'mhm-rentiva'),
                    'enterEmail' => __('Please enter email address', 'mhm-rentiva'),
                    'editTemplate' => __('Edit Template', 'mhm-rentiva'),
                    'subject' => __('Subject', 'mhm-rentiva'),
                    'content' => __('Content', 'mhm-rentiva'),
                    'save' => __('Save', 'mhm-rentiva'),
                    'templateSaved' => __('Template saved successfully!', 'mhm-rentiva'),
                    'templateReset' => __('Template reset to default!', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Message List
        if ($screen->id === 'edit-message' || $screen->post_type === 'message') {
            wp_enqueue_script(
                'mhm-message-list',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/message-list.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-message-list', 'mhm_message_list_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_message_list_nonce'),
                'no_items_selected' => __('Please select at least one item', 'mhm-rentiva'),
                'items_selected' => __('selected', 'mhm-rentiva'),
                'confirm_mark_read' => __('Mark selected messages as read?', 'mhm-rentiva'),
                'confirm_mark_unread' => __('Mark selected messages as unread?', 'mhm-rentiva'),
                'confirm_delete' => __('Delete selected messages? This action cannot be undone.', 'mhm-rentiva'),
                'processing' => __('Processing...', 'mhm-rentiva'),
                'error_occurred' => __('An error occurred', 'mhm-rentiva'),
                'strings' => [
                    'quickReply' => __('Quick Reply', 'mhm-rentiva'),
                    'yourReply' => __('Your Reply', 'mhm-rentiva'),
                    'send' => __('Send', 'mhm-rentiva'),
                    'cancel' => __('Cancel', 'mhm-rentiva'),
                    'selectNewStatus' => __('Select new status:\n1. pending\n2. answered\n3. closed\n4. spam', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Export Page
        if ($screen->id === 'mhm-rentiva_page_mhm-rentiva-export') {
            wp_enqueue_style(
                'mhm-export',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/export.css',
                ['mhm-core-css'],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_script(
                'mhm-export',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/export.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-export', 'mhmExport', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_export_nonce'),
                'strings' => [
                    'loading' => __('Loading...', 'mhm-rentiva'),
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'success' => __('Operation successful', 'mhm-rentiva'),
                    'confirm' => __('Are you sure?', 'mhm-rentiva'),
                    'processing' => __('Processing...', 'mhm-rentiva'),
                    'exportStarted' => __('Export process started', 'mhm-rentiva'),
                    'exportCompleted' => __('Export completed successfully', 'mhm-rentiva'),
                    'exportFailed' => __('Export failed', 'mhm-rentiva'),
                    'filtersApplied' => __('Filters applied successfully', 'mhm-rentiva'),
                    'filtersReset' => __('Filters reset successfully', 'mhm-rentiva'),
                    'viewDetails' => __('View Details', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Booking Meta
        if ($screen->id === 'booking' || $screen->post_type === 'booking') {
            wp_enqueue_style(
                'mhm-booking-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/booking-meta.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_script(
                'mhm-booking-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-meta.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            // Addon Booking Component
            self::enqueue_component_js('addon-booking');
        }
        
        // Addon Admin
        if ($screen->id === 'addon') {
            wp_enqueue_script(
                'mhm-addon-admin',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/addon-admin.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            wp_localize_script('mhm-addon-admin', 'mhmAddonAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_addon_admin_nonce'),
                'strings' => [
                    'confirm_bulk_enable' => __('Enable selected add-ons?', 'mhm-rentiva'),
                    'confirm_delete' => __('Are you sure you want to delete this?', 'mhm-rentiva'),
                    'invalidPrice' => __('Enter a valid price (0 or greater)', 'mhm-rentiva'),
                    'autoSaved' => __('Auto-saved', 'mhm-rentiva'),
                    'autoSaveFailed' => __('Auto-save failed', 'mhm-rentiva'),
                ]
            ]);
        }
    }
    
    /**
     * Get currency symbol
     * 
     * @deprecated Use CurrencyHelper::get_currency_symbol() instead
     */
    private static function get_currency_symbol(): string
    {
        return CurrencyHelper::get_currency_symbol();
    }
    
    /**
     * Get locale for JavaScript
     * 
     * @deprecated Use LanguageHelper::get_current_js_locale() instead
     */
    private static function get_js_locale(): string
    {
        return \MHMRentiva\Admin\Core\LanguageHelper::get_current_js_locale();
    }
    
    /**
     * Get revenue data for dashboard
     */
    private static function get_dashboard_revenue_data(): array
    {
        // This function should return actual revenue data
        // For now, returning sample data
        return [
            'daily_data' => []
        ];
    }
    
    /**
     * Get vehicle options for Gutenberg
     */
    private static function get_vehicle_options_for_gutenberg(): array
    {
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        $options = [
            ['value' => 0, 'label' => __('Select a vehicle', 'mhm-rentiva')]
        ];
        
        foreach ($vehicles as $vehicle) {
            $options[] = [
                'value' => $vehicle->ID,
                'label' => $vehicle->post_title
            ];
        }
        
        return $options;
    }

    /**
     * Localize scripts
     */
    private static function localize_scripts(): void
    {
        // Configuration for Core JS
        wp_localize_script('mhm-core-js', 'mhm_rentiva_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_ajax_nonce'),
            'baseUrl' => MHM_RENTIVA_PLUGIN_URL, // Fixed as camelCase
            'base_url' => MHM_RENTIVA_PLUGIN_URL, // backward compatibility
            'locale' => get_locale(),
            'currency' => get_option('mhm_rentiva_currency', 'USD'),
            'date_format' => get_option('date_format', 'd/m/Y'),
            'time_format' => get_option('time_format', 'H:i'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);

        // Localize translations for i18n
        wp_localize_script('mhm-i18n', 'mhm_i18n_translations', [
            'mhm-rentiva' => self::get_translations()
        ]);
    }

    /**
     * Get translations
     * @return array
     */
    private static function get_translations(): array
    {
        return [
            'Loading...' => __('Loading...', 'mhm-rentiva'),
            'Error' => __('Error', 'mhm-rentiva'),
            'Success' => __('Success', 'mhm-rentiva'),
            'Warning' => __('Warning', 'mhm-rentiva'),
            'Info' => __('Info', 'mhm-rentiva'),
            'Yes' => __('Yes', 'mhm-rentiva'),
            'No' => __('No', 'mhm-rentiva'),
            'Cancel' => __('Cancel', 'mhm-rentiva'),
            'Confirm' => __('Confirm', 'mhm-rentiva'),
            'Save' => __('Save', 'mhm-rentiva'),
            'Delete' => __('Delete', 'mhm-rentiva'),
            'Edit' => __('Edit', 'mhm-rentiva'),
            'Add' => __('Add', 'mhm-rentiva'),
            'Update' => __('Update', 'mhm-rentiva'),
            'Search' => __('Search', 'mhm-rentiva'),
            'Filter' => __('Filter', 'mhm-rentiva'),
            'Reset' => __('Reset', 'mhm-rentiva'),
            'Close' => __('Close', 'mhm-rentiva'),
            'Back' => __('Back', 'mhm-rentiva'),
            'Next' => __('Next', 'mhm-rentiva'),
            'Previous' => __('Previous', 'mhm-rentiva'),
            'Submit' => __('Submit', 'mhm-rentiva'),
            'Clear' => __('Clear', 'mhm-rentiva'),
            'Select All' => __('Select All', 'mhm-rentiva'),
            'Select None' => __('Select None', 'mhm-rentiva'),
            'No data found' => __('No data found', 'mhm-rentiva'),
            'No results found' => __('No results found', 'mhm-rentiva'),
            'Please wait...' => __('Please wait...', 'mhm-rentiva'),
            'Processing...' => __('Processing...', 'mhm-rentiva'),
            'An error occurred' => __('An error occurred', 'mhm-rentiva'),
            'Please try again' => __('Please try again', 'mhm-rentiva'),
            'Operation completed successfully' => __('Operation completed successfully', 'mhm-rentiva'),
            'Operation failed' => __('Operation failed', 'mhm-rentiva'),
            'Are you sure?' => __('Are you sure?', 'mhm-rentiva'),
            'This action cannot be undone' => __('This action cannot be undone', 'mhm-rentiva'),
            'Invalid input' => __('Invalid input', 'mhm-rentiva'),
            'Required field' => __('Required field', 'mhm-rentiva'),
            'Please fill all required fields' => __('Please fill all required fields', 'mhm-rentiva')
        ];
    }

    /**
     * Add inline styles
     */
    public static function add_inline_styles(): void
    {
        // Add CSS variables inline
        $css_variables = self::get_css_variables();
        if ($css_variables) {
            echo '<style id="mhm-css-variables">' . $css_variables . '</style>';
        }
    }

    /**
     * Get CSS variables
     * @return string
     */
    private static function get_css_variables(): string
    {
        $primary_color = get_option('mhm_rentiva_primary_color', '#2271b1');
        $secondary_color = get_option('mhm_rentiva_secondary_color', '#00a32a');
        
        return "
        :root {
            --mhm-primary: {$primary_color};
            --mhm-secondary: {$secondary_color};
        }
        ";
    }

    /**
     * Check if asset is loaded
     * @param string $handle - Asset handle
     * @return bool
     */
    public static function is_asset_loaded(string $handle): bool
    {
        return wp_style_is($handle, 'enqueued') || wp_script_is($handle, 'enqueued');
    }

    /**
     * Remove asset
     * @param string $handle - Asset handle
     */
    public static function dequeue_asset(string $handle): void
    {
        wp_dequeue_style($handle);
        wp_dequeue_script($handle);
    }

    /**
     * Remove all core assets
     */
    public static function dequeue_all_core_assets(): void
    {
        foreach (array_keys(self::$core_css) as $handle) {
            wp_dequeue_style($handle);
        }
        
        foreach (array_keys(self::$core_js) as $handle) {
            wp_dequeue_script($handle);
        }
    }

    /**
     * Enqueue and localize PayPal payment script
     * This method can be called on payment pages
     */
    public static function enqueue_paypal_payment(): void
    {
        // Enqueue PayPal JS
        wp_enqueue_script(
            'mhm-paypal-payment',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/payment/paypal.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );
        
        // Enqueue PayPal CSS
        wp_enqueue_style(
            'mhm-paypal-payment',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/payment/paypal.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        // Localize PayPal config
        self::localize_paypal_script();
    }
    
    /**
     * Localize PayPal script
     */
    private static function localize_paypal_script(): void
    {
        $test_mode = get_option('mhm_rentiva_paypal_test_mode', '1') === '1';
        $client_id_key = $test_mode ? 'mhm_rentiva_paypal_client_id_test' : 'mhm_rentiva_paypal_client_id_live';
        $currency = get_option('mhm_rentiva_paypal_currency', 'USD');
        
        wp_localize_script('mhm-paypal-payment', 'mhmPayPalConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_paypal_nonce'),
            'clientId' => get_option($client_id_key, ''),
            'currency' => $currency,
            'testMode' => $test_mode,
            'locale' => self::get_js_locale(),
            'successUrl' => get_option('mhm_rentiva_paypal_success_url', home_url('/payment-success')),
            'strings' => [
                'sdkLoadError' => __('PayPal SDK could not be loaded. Please refresh the page.', 'mhm-rentiva'),
                'buttonRenderError' => __('PayPal buttons could not be created.', 'mhm-rentiva'),
                'noBookingId' => __('Booking information not found.', 'mhm-rentiva'),
                'orderCreateError' => __('PayPal order creation error', 'mhm-rentiva'),
                'serverConnectionError' => __('Server connection error.', 'mhm-rentiva'),
                'processingPayment' => __('Processing payment...', 'mhm-rentiva'),
                'paymentSuccess' => __('Payment completed successfully!', 'mhm-rentiva'),
                'paymentProcessError' => __('Payment processing error', 'mhm-rentiva'),
                'paymentProcessFailed' => __('An error occurred while processing payment.', 'mhm-rentiva'),
                'paymentError' => __('An error occurred during PayPal payment.', 'mhm-rentiva'),
                'paymentCancelled' => __('PayPal payment was cancelled.', 'mhm-rentiva'),
            ]
        ]);
    }
}
