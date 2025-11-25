<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode Service Provider
 * 
 * Service provider class that manages all shortcodes from a central location.
 * Organizes scattered shortcode registrations in Plugin.php.
 * 
 * @since 3.0.1
 */
final class ShortcodeServiceProvider
{
    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Registered shortcodes
     */
    private array $registered_shortcodes = [];

    /**
     * Shortcode registry - definitions of all shortcodes
     */
    private const SHORTCODE_REGISTRY = [
        // Booking Shortcodes
        'reservation' => [
            'rentiva_booking_form' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm',
                'priority' => 10,
                'dependencies' => ['deposit'], // Payment dependency kaldırıldı
                'requires_auth' => false,
            ],
            // rentiva_quick_booking kaldırıldı - rentiva_booking_form kullanın
            'rentiva_availability_calendar' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
            'rentiva_booking_confirmation' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\BookingConfirmation',
                'priority' => 10,
                'dependencies' => ['booking'],
                'requires_auth' => false,
            ],
            'rentiva_thank_you' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\ThankYou',
                'priority' => 10,
                'dependencies' => ['booking'],
                'requires_auth' => false,
            ],
        ],

        // Vehicle Display Shortcodes
        'vehicle' => [
            'rentiva_vehicle_details' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
            'rentiva_vehicles_list' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
            'rentiva_vehicles_grid' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
            'rentiva_search' => [
                'class' => '\MHMRentiva\Admin\Vehicle\Frontend\VehicleSearch',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
            'rentiva_search_results' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\SearchResults',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
            'rentiva_vehicle_comparison' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\VehicleComparison',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
        ],

        // Account Management Shortcodes
        'account' => [
            'rentiva_my_account' => [
                'class' => '\MHMRentiva\Admin\Frontend\Account\AccountController',
                'method' => 'render_my_account',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false, // Kendi içinde kontrol ediyor
            ],
            'rentiva_my_bookings' => [
                'class' => '\MHMRentiva\Admin\Frontend\Account\AccountController',
                'method' => 'render_my_bookings',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => true,
            ],
            'rentiva_my_favorites' => [
                'class' => '\MHMRentiva\Admin\Frontend\Account\AccountController',
                'method' => 'render_my_favorites',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => true,
            ],
            'rentiva_payment_history' => [
                'class' => '\MHMRentiva\Admin\Frontend\Account\AccountController',
                'method' => 'render_payment_history',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => true,
            ],
            'rentiva_account_details' => [
                'class' => '\MHMRentiva\Admin\Frontend\Account\AccountController',
                'method' => 'render_account_details',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => true,
            ],
            'rentiva_login_form' => [
                'class' => '\MHMRentiva\Admin\Frontend\Account\AccountController',
                'method' => 'render_login_form',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
            'rentiva_register_form' => [
                'class' => '\MHMRentiva\Admin\Frontend\Account\AccountController',
                'method' => 'render_register_form',
                'priority' => 10,
                'dependencies' => [],
                'requires_auth' => false,
            ],
        ],

        // Support and Contact Shortcodes
        'support' => [
            'rentiva_contact' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\ContactForm',
                'priority' => 10,
                'dependencies' => ['email'],
                'requires_auth' => false,
            ],
            'rentiva_testimonials' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\Testimonials',
                'priority' => 10,
                'dependencies' => ['booking'],
                'requires_auth' => false,
            ],
            'rentiva_vehicle_rating_form' => [
                'class' => '\MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm',
                'priority' => 10,
                'dependencies' => ['booking'],
                'requires_auth' => false,
            ],
        ],

        // Financial Shortcodes - Removed (not used)
        // 'financial' => [], // Deposit shortcodes are not used
    ];

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        // Singleton pattern
    }

    /**
     * Get singleton instance
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register shortcode service provider
     */
    public static function register(): void
    {
        $instance = self::instance();
        $instance->register_all_shortcodes();
    }

    /**
     * Register all shortcodes
     */
    private function register_all_shortcodes(): void
    {
        foreach (self::SHORTCODE_REGISTRY as $group => $shortcodes) {
            foreach ($shortcodes as $tag => $config) {
                $this->register_shortcode($tag, $config);
            }
        }
    }

    /**
     * Register a single shortcode
     */
    private function register_shortcode(string $tag, array $config): void
    {
        // Ensure AbstractShortcode is loaded first
        if (!class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\AbstractShortcode')) {
            $abstract_path = MHM_RENTIVA_PLUGIN_PATH . 'src/Admin/Frontend/Shortcodes/Core/AbstractShortcode.php';
            if (file_exists($abstract_path)) {
                require_once $abstract_path;
            }
        }
        
        // Check if class exists
        if (!class_exists($config['class'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("MHM Rentiva: Shortcode class not found: {$config['class']}");
            }
            return;
        }

        // Check dependencies
        if (!empty($config['dependencies']) && !$this->check_dependencies($config['dependencies'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("MHM Rentiva: Shortcode dependencies not met: {$tag}");
            }
            return;
        }

        // Register shortcode
        if (method_exists($config['class'], 'register')) {
            // Use static register method
            call_user_func([$config['class'], 'register']);
        } elseif (isset($config['method'])) {
            // Use specific method
            add_shortcode($tag, [$config['class'], $config['method']]);
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("MHM Rentiva: No register method found for: {$tag}");
            }
            return;
        }

        // Mark as registered
        $this->registered_shortcodes[$tag] = $config;

        // Log registration (only in debug mode)
        // Shortcode registered
    }

    /**
     * Check shortcode dependencies
     */
    private function check_dependencies(array $dependencies): bool
    {
        foreach ($dependencies as $dependency) {
            switch ($dependency) {


                case 'deposit':
                    if (!class_exists('\MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator')) {
                        return false;
                    }
                    break;

                case 'booking':
                    if (!class_exists('\MHMRentiva\Admin\Booking\Core\Handler')) {
                        return false;
                    }
                    break;

                case 'email':
                    if (!class_exists('\MHMRentiva\Admin\Emails\Core\EmailTemplates')) {
                        return false;
                    }
                    break;

                default:
                    // Unknown dependency
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("MHM Rentiva: Unknown dependency: {$dependency}");
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Get all registered shortcodes
     */
    public function get_registered_shortcodes(): array
    {
        return $this->registered_shortcodes;
    }

    /**
     * Get shortcode groups
     */
    public static function get_shortcode_groups(): array
    {
        $groups = [];
        
        foreach (self::SHORTCODE_REGISTRY as $group => $shortcodes) {
            $groups[$group] = [
                'name' => self::get_group_name($group),
                'shortcodes' => array_keys($shortcodes),
                'count' => count($shortcodes),
            ];
        }

        return $groups;
    }

    /**
     * Get group display name
     */
    private static function get_group_name(string $group): string
    {
        $names = [
            'reservation' => __('Booking', 'mhm-rentiva'),
            'vehicle' => __('Vehicle Display', 'mhm-rentiva'),
            'account' => __('Account Management', 'mhm-rentiva'),
            'support' => __('Support and Contact', 'mhm-rentiva'),
            'financial' => __('Financial', 'mhm-rentiva'),
        ];

        return $names[$group] ?? $group;
    }

    /**
     * Get shortcode info
     */
    public static function get_shortcode_info(string $tag): ?array
    {
        foreach (self::SHORTCODE_REGISTRY as $group => $shortcodes) {
            if (isset($shortcodes[$tag])) {
                return array_merge($shortcodes[$tag], [
                    'tag' => $tag,
                    'group' => $group,
                    'group_name' => self::get_group_name($group),
                ]);
            }
        }

        return null;
    }

    /**
     * Check if shortcode is registered
     */
    public function is_shortcode_registered(string $tag): bool
    {
        return isset($this->registered_shortcodes[$tag]);
    }

    /**
     * Get total shortcode count
     */
    public static function get_total_count(): int
    {
        $count = 0;
        foreach (self::SHORTCODE_REGISTRY as $shortcodes) {
            $count += count($shortcodes);
        }
        return $count;
    }

    /**
     * Clear registered shortcodes (for testing)
     */
    public function clear_registered(): void
    {
        $this->registered_shortcodes = [];
    }
}
