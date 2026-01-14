<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode Pages Admin Page
 * 
 * Shows which pages all shortcodes are used on
 * 
 * @since 4.0.0
 */
final class ShortcodePages
{
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * Register admin page
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_admin_menu'], 12); // Priority 12 to run after Menu.php
        add_action('init', [self::class, 'register_ajax_handlers']);
    }

    /**
     * Add to admin menu
     */
    public static function add_admin_menu(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Shortcode Pages', 'mhm-rentiva'),
            __('Shortcode Pages', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-shortcode-pages',
            [self::class, 'render_page']
        );
    }

    /**
     * Register AJAX handlers (only once)
     */
    public static function register_ajax_handlers(): void
    {
        // Register only once
        if (has_action('wp_ajax_mhm_clear_shortcode_cache')) {
            return;
        }
        add_action('wp_ajax_mhm_clear_shortcode_cache', [self::class, 'ajax_clear_cache']);
        add_action('wp_ajax_mhm_create_shortcode_page', [self::class, 'ajax_create_shortcode_page']);
        add_action('wp_ajax_mhm_delete_shortcode_page', [self::class, 'ajax_delete_shortcode_page']);
        add_action('wp_ajax_mhm_debug_shortcode_search', [self::class, 'ajax_debug_search']);
    }

    /**
     * AJAX: Clear cache
     */
    public static function ajax_clear_cache(): void
    {
        // Check if AJAX handler was called
        
        try {
            // Action check
            $action = self::sanitize_text_field_safe(wp_unslash($_POST['action'] ?? ''));
            if ($action !== 'mhm_clear_shortcode_cache') {
                wp_send_json_error(['message' => __('Invalid action: ', 'mhm-rentiva') . esc_html($action)]);
            }

            // Nonce check
            $nonce = self::sanitize_text_field_safe(wp_unslash($_POST['nonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'mhm_clear_shortcode_cache')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            // Permission check
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('You do not have permission.', 'mhm-rentiva')]);
            }

            // Check if ShortcodeUrlManager class exists
            if (!class_exists('\MHMRentiva\Admin\Core\ShortcodeUrlManager')) {
                wp_send_json_error(['message' => __('ShortcodeUrlManager class not found.', 'mhm-rentiva')]);
            }

            // Clear cache
            ShortcodeUrlManager::clear_cache();

            wp_send_json_success(['message' => __('Cache cleared successfully.', 'mhm-rentiva')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Error while clearing cache: ', 'mhm-rentiva') . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Create shortcode page
     */
    public static function ajax_create_shortcode_page(): void
    {
        // Nonce check
        if (!wp_verify_nonce(self::sanitize_text_field_safe(wp_unslash($_POST['nonce'] ?? '')), 'mhm_create_shortcode_page')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
        }

        $shortcode = self::sanitize_text_field_safe(wp_unslash($_POST['shortcode'] ?? ''));
        if (empty($shortcode)) {
            wp_send_json_error(['message' => __('Shortcode not specified.', 'mhm-rentiva')]);
        }

        // Create page
        $page_id = self::create_shortcode_page($shortcode);
        
        if ($page_id) {
            // Clear cache
            ShortcodeUrlManager::clear_cache($shortcode);
            
            wp_send_json_success([
                'message' => __('Page created successfully.', 'mhm-rentiva'),
                'page_id' => $page_id,
                'edit_url' => admin_url('post.php?post=' . $page_id . '&action=edit'),
                'view_url' => get_permalink($page_id)
            ]);
        } else {
            wp_send_json_error(['message' => __('Error occurred while creating page.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX: Delete page
     */
    public static function ajax_delete_shortcode_page(): void
    {

        // Nonce check
        if (!isset($_POST['nonce']) || !wp_verify_nonce(self::sanitize_text_field_safe(wp_unslash($_POST['nonce'])), 'mhm_delete_shortcode_page')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
            return;
        }

        $page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;

        if ($page_id <= 0) {
            wp_send_json_error(['message' => __('Invalid page ID.', 'mhm-rentiva')]);
            return;
        }

        // Move page to trash
        $result = wp_trash_post($page_id);

        if ($result) {
            // Clear cache
            \MHMRentiva\Admin\Core\ShortcodeUrlManager::clear_cache();

            wp_send_json_success([
                'message' => __('Page successfully moved to trash.', 'mhm-rentiva'),
                'page_id' => $page_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Error occurred while removing page.', 'mhm-rentiva')]);
        }
    }

    /**
     * Create page for shortcode
     */
    private static function create_shortcode_page(string $shortcode): ?int
    {
        // Get shortcode information
        $shortcode_info = self::get_shortcode_info($shortcode);
        if (!$shortcode_info) {
            return null;
        }

        // Create special content for shortcode
        $shortcode_content = self::get_shortcode_content($shortcode);
        
        // Page content
        $content = sprintf(
            '<!-- %s page - %s -->' . PHP_EOL . PHP_EOL . '%s' . PHP_EOL . PHP_EOL . '<!-- You can edit this page, change the title and content. -->',
            $shortcode_info['title'],
            $shortcode_info['description'],
            $shortcode_content
        );

        // Page data
        $page_data = [
            'post_title' => $shortcode_info['title'],
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'post_name' => sanitize_title($shortcode_info['slug']),
            'post_excerpt' => $shortcode_info['description']
        ];

        // Create page
        $page_id = wp_insert_post($page_data);
        
        if ($page_id && !is_wp_error($page_id)) {
            // Add meta information
            update_post_meta($page_id, '_mhm_shortcode', $shortcode);
            update_post_meta($page_id, '_mhm_auto_created', true);
            
            return $page_id;
        }

        // Log in case of error
        if (is_wp_error($page_id)) {
            error_log('Shortcode page creation error: ' . $page_id->get_error_message());
        } else {
            error_log('Shortcode page creation failed for: ' . $shortcode);
        }

        return null;
    }

    /**
     * Create appropriate content for shortcode
     */
    private static function get_shortcode_content(string $shortcode): string
    {
        // Shortcodes requiring special parameters
        $special_shortcodes = [
            'rentiva_vehicle_comparison' => '[rentiva_vehicle_comparison vehicle_ids="1,2,3"] <!-- Change vehicle_ids parameter to the vehicle IDs you want to compare -->',
            'rentiva_availability_calendar' => '[rentiva_availability_calendar vehicle_id="1"] <!-- Change vehicle_id parameter to the vehicle ID you want -->',
            'rentiva_search' => '[rentiva_search] <!-- Search form is displayed automatically -->',
            'rentiva_vehicle_details' => '[rentiva_vehicle_details vehicle_id="1"] <!-- Change vehicle_id parameter to the vehicle ID you want to display -->',
            'rentiva_vehicles_grid' => '[rentiva_vehicles_grid columns="3" limit="12"] <!-- Change columns and limit parameters as needed -->',
            'rentiva_vehicles_list' => '[rentiva_vehicles_list limit="10"] <!-- Change limit parameter as needed -->',
            'rentiva_booking_form' => '[rentiva_booking_form vehicle_id="1"] <!-- Change vehicle_id parameter to the vehicle ID you want to book -->',
            'rentiva_booking_confirmation' => '[rentiva_booking_confirmation booking_id="1"] <!-- Change booking_id parameter to the booking ID you want to confirm -->',
            'rentiva_vehicle_rating_form' => '[rentiva_vehicle_rating_form vehicle_id="1"] <!-- Change vehicle_id parameter to the vehicle ID you want to rate -->',
        ];

        // Use special shortcode if available
        if (isset($special_shortcodes[$shortcode])) {
            return $special_shortcodes[$shortcode];
        }


        // Standard format for other shortcodes
        return '[' . $shortcode . ']';
    }

    /**
     * Get shortcode information
     */
    private static function get_shortcode_info(string $shortcode): ?array
    {
        $shortcodes = [
            'rentiva_my_bookings' => [
                'title' => 'My Bookings',
                'slug' => 'my-bookings',
                'description' => 'All user bookings'
            ],
            'rentiva_my_favorites' => [
                'title' => 'My Favorites',
                'slug' => 'my-favorites',
                'description' => 'User favorite vehicles'
            ],
            'rentiva_payment_history' => [
                'title' => 'Payment History',
                'slug' => 'payment-history',
                'description' => 'User payment history'
            ],
            // 'rentiva_account_details' => [...], // Removed as per request
            'rentiva_login_form' => [
                'title' => 'Login Form',
                'slug' => 'login-form',
                'description' => 'User login form'
            ],
            'rentiva_register_form' => [
                'title' => 'Registration Form',
                'slug' => 'registration-form',
                'description' => 'New user registration form'
            ],
            // rentiva_quick_booking removed - use rentiva_booking_form
            'rentiva_booking_form' => [
                'title' => 'Booking Form',
                'slug' => 'booking-form',
                'description' => 'Detailed booking form - with all booking options'
            ],
            'rentiva_search' => [
                'title' => 'Vehicle Search',
                'slug' => 'vehicle-search',
                'description' => 'Vehicle search and filtering page - customers can search vehicles'
            ],
            'rentiva_search_results' => [
                'title' => 'Search Results',
                'slug' => 'search-results',
                'description' => 'Vehicle search results page - detailed results with sidebar filters'
            ],
            'rentiva_vehicle_comparison' => [
                'title' => 'Vehicle Comparison',
                'slug' => 'vehicle-comparison',
                'description' => 'Vehicle comparison page - multiple vehicles can be compared'
            ],
            'rentiva_testimonials' => [
                'title' => 'Customer Reviews',
                'slug' => 'customer-reviews',
                'description' => 'Customer reviews and ratings'
            ],
            'rentiva_availability_calendar' => [
                'title' => 'Availability Calendar',
                'slug' => 'availability-calendar',
                'description' => 'Vehicle availability calendar - which vehicles are available on which dates'
            ],
            'rentiva_booking_confirmation' => [
                'title' => 'Booking Confirmation',
                'slug' => 'booking-confirmation',
                'description' => 'Booking confirmation page - shows booking details and payment status'
            ],
            'rentiva_vehicle_details' => [
                'title' => 'Vehicle Details',
                'slug' => 'vehicle-details',
                'description' => 'Single vehicle details page - shows vehicle information, images and booking form'
            ],
            'rentiva_vehicles_grid' => [
                'title' => 'Vehicles Grid',
                'slug' => 'vehicles-grid',
                'description' => 'Vehicles displayed in grid layout - multiple vehicles in grid format'
            ],
            'rentiva_vehicles_list' => [
                'title' => 'Vehicles List',
                'slug' => 'vehicles-list',
                'description' => 'Vehicles displayed in list layout - multiple vehicles in list format'
            ],
            'rentiva_contact' => [
                'title' => 'Contact Form',
                'slug' => 'contact-form',
                'description' => 'Contact form page - customers can send messages to admin'
            ],
            'rentiva_vehicle_rating_form' => [
                'title' => 'Vehicle Rating Form',
                'slug' => 'vehicle-rating-form',
                'description' => 'Vehicle rating and review form - customers can rate and review vehicles'
            ],
        ];

        return $shortcodes[$shortcode] ?? null;
    }

    /**
     * AJAX: Debug shortcode search
     */
    public static function ajax_debug_search(): void
    {
        // Nonce check
        if (!wp_verify_nonce(self::sanitize_text_field_safe(wp_unslash($_POST['nonce'] ?? '')), 'mhm_debug_shortcode_search')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
        }

        global $wpdb;
        
        // Search all pages
        $all_pages = $wpdb->get_results(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status = 'publish'
             ORDER BY post_date DESC"
        );

        $debug_info = [];
                        $shortcodes = [
                            // Account Management Shortcodes
                            'rentiva_my_account', 'rentiva_my_bookings', 'rentiva_my_favorites', 
                            'rentiva_payment_history', 'rentiva_login_form',
                            'rentiva_register_form',
                            
                            // Booking Shortcodes
                            'rentiva_booking_form', 'rentiva_availability_calendar', 'rentiva_booking_confirmation',
                            
                            // Vehicle Display Shortcodes
                            'rentiva_vehicle_details', 'rentiva_vehicles_grid', 'rentiva_vehicles_list',
                            'rentiva_vehicle_comparison', 'rentiva_search', 'rentiva_search_results',
                            
                            // Support Shortcodes
                            'rentiva_contact', 'rentiva_testimonials', 'rentiva_vehicle_rating_form',
                        ];

        foreach ($all_pages as $page) {
            $found_shortcodes = [];
            
            foreach ($shortcodes as $shortcode) {
                if (strpos($page->post_content, '[' . $shortcode . ']') !== false ||
                    strpos($page->post_content, '[' . $shortcode . ' ') !== false ||
                    strpos($page->post_content, '[' . $shortcode . '=') !== false) {
                    $found_shortcodes[] = $shortcode;
                }
            }
            
            if (!empty($found_shortcodes)) {
                $debug_info[] = [
                    'id' => $page->ID,
                    'title' => $page->post_title,
                    'shortcodes' => $found_shortcodes,
                    'url' => get_permalink($page->ID),
                    'content_preview' => substr(strip_tags($page->post_content), 0, 200) . '...'
                ];
            }
        }

        wp_send_json_success([
            /* translators: %d placeholder. */
            'message' => sprintf(__('%d pages found.', 'mhm-rentiva'), count($debug_info)),
            'pages' => $debug_info
        ]);
    }

    /**
     * Render admin page
     */
    public static function render_page(): void
    {
        $pages = ShortcodeUrlManager::get_all_pages();
        
        ?>
        <style>
            .button-link-delete {
                color: #b32d2e !important;
                border-color: #b32d2e !important;
            }
            .button-link-delete:hover {
                background: #b32d2e !important;
                color: #fff !important;
                border-color: #a02020 !important;
            }
            .mhm-status-ok {
                color: #46b450;
                font-weight: 500;
            }
            .mhm-status-missing {
                color: #dc3232;
                font-weight: 500;
            }
        </style>
        <div class="wrap">
            <h1><?php echo esc_html__('Shortcode Pages', 'mhm-rentiva'); ?></h1>
            <p class="description">
                <?php echo esc_html__('Below is a list of all MHM Rentiva shortcodes and which pages they are used on.', 'mhm-rentiva'); ?>
            </p>

            <div class="mhm-shortcode-pages">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html__('Shortcode', 'mhm-rentiva'); ?></th>
                            <th scope="col"><?php echo esc_html__('Page', 'mhm-rentiva'); ?></th>
                            <th scope="col"><?php echo esc_html__('URL', 'mhm-rentiva'); ?></th>
                            <th scope="col"><?php echo esc_html__('Status', 'mhm-rentiva'); ?></th>
                            <th scope="col"><?php echo esc_html__('Actions', 'mhm-rentiva'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $all_shortcodes = [
                            // Account Management Shortcodes
                            'rentiva_my_bookings' => __('My Bookings', 'mhm-rentiva'),
                            'rentiva_my_favorites' => __('My Favorites', 'mhm-rentiva'),
                            'rentiva_payment_history' => __('Payment History', 'mhm-rentiva'),
                            // 'rentiva_account_details' => __('Account Details', 'mhm-rentiva'), // Removed
                            'rentiva_login_form' => __('Login Form', 'mhm-rentiva'),
                            'rentiva_register_form' => __('Registration Form', 'mhm-rentiva'),
                            
                            // Booking Shortcodes
                            'rentiva_booking_form' => __('Booking Form', 'mhm-rentiva'),
                            'rentiva_availability_calendar' => __('Availability Calendar', 'mhm-rentiva'),
                            'rentiva_booking_confirmation' => __('Booking Confirmation', 'mhm-rentiva'),
                            
                            // Vehicle Display Shortcodes
                            'rentiva_vehicle_details' => __('Vehicle Details', 'mhm-rentiva'),
                            'rentiva_vehicles_grid' => __('Vehicles Grid', 'mhm-rentiva'),
                            'rentiva_vehicles_list' => __('Vehicles List', 'mhm-rentiva'),
                            'rentiva_vehicle_comparison' => __('Vehicle Comparison', 'mhm-rentiva'),
                            'rentiva_search' => __('Vehicle Search', 'mhm-rentiva'),
                            'rentiva_search_results' => __('Search Results', 'mhm-rentiva'),
                            
                            // Support Shortcodes
                            'rentiva_contact' => __('Contact Form', 'mhm-rentiva'),
                            'rentiva_testimonials' => __('Customer Reviews', 'mhm-rentiva'),
                            'rentiva_vehicle_rating_form' => __('Vehicle Rating Form', 'mhm-rentiva'),
                        ];

                        foreach ($all_shortcodes as $shortcode => $title):
                            $page_id = $pages[$shortcode] ?? null;
                            $page = $page_id ? get_post($page_id) : null;
                        ?>
                            <tr>
                                <td>
                                    <code><?php echo esc_html($shortcode); ?></code>
                                    <br>
                                    <small><?php echo esc_html($title); ?></small>
                                </td>
                                <td>
                                    <?php if ($page): ?>
                                        <strong><?php echo esc_html($page->post_title); ?></strong>
                                        <br>
                                        <small><?php echo esc_html__('ID:', 'mhm-rentiva'); ?> <?php echo esc_html($page_id); ?></small>
                                    <?php else: ?>
                                        <span class="mhm-status-missing"><?php echo esc_html__('Page not found', 'mhm-rentiva'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($page): ?>
                                        <a href="<?php echo esc_url(get_permalink($page_id)); ?>" target="_blank">
                                            <?php echo esc_html(get_permalink($page_id)); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="mhm-status-missing">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($page): ?>
                                        <span class="mhm-status-ok">✅ <?php echo esc_html__('Active', 'mhm-rentiva'); ?></span>
                                    <?php else: ?>
                                        <span class="mhm-status-missing">❌ <?php echo esc_html__('Missing', 'mhm-rentiva'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($page): ?>
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $page_id . '&action=edit')); ?>" class="button button-small">
                                            <?php echo esc_html__('Edit', 'mhm-rentiva'); ?>
                                        </a>
                                        <a href="<?php echo esc_url(get_permalink($page_id)); ?>" target="_blank" class="button button-small">
                                            <?php echo esc_html__('View', 'mhm-rentiva'); ?>
                                        </a>
                                        <button type="button" class="button button-small button-link-delete" onclick="deleteShortcodePage(<?php echo esc_js($page_id); ?>, '<?php echo esc_js($page->post_title); ?>')">
                                            <?php echo esc_html__('Remove', 'mhm-rentiva'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="button button-primary button-small" onclick="createShortcodePage('<?php echo esc_js($shortcode); ?>')">
                                            <?php echo esc_html__('Create Page', 'mhm-rentiva'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mhm-shortcode-actions" style="margin-top: 20px;">
                <h3><?php echo esc_html__('Actions', 'mhm-rentiva'); ?></h3>
                <p>
                    <button type="button" class="button" onclick="clearShortcodeCache()">
                        <?php echo esc_html__('Clear Cache', 'mhm-rentiva'); ?>
                    </button>
                    <button type="button" class="button" onclick="debugShortcodeSearch()">
                        <?php echo esc_html__('Debug Search', 'mhm-rentiva'); ?>
                    </button>
                    <span class="description">
                        <?php echo esc_html__('Clears shortcode page cache and performs debug search. Use after page updates.', 'mhm-rentiva'); ?>
                    </span>
                </p>
            </div>
        </div>

        <style>
        .mhm-status-ok { color: #46b450; font-weight: bold; }
        .mhm-status-missing { color: #dc3232; font-weight: bold; }
        .mhm-shortcode-pages table { margin-top: 20px; }
        .mhm-shortcode-pages code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; }
        </style>

        <script>
        // Define AJAX URL (conflict check)
        if (typeof ajaxurl === 'undefined') {
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        }
        console.log('AJAX URL:', ajaxurl);
        
        // Global functions
        window.clearShortcodeCache = function() {
            if (confirm('<?php echo esc_js(__('Cache will be cleared. Do you want to continue?', 'mhm-rentiva')); ?>')) {
                // Clear cache via AJAX
                const data = new FormData();
                data.append('action', 'mhm_clear_shortcode_cache');
                data.append('nonce', '<?php echo wp_create_nonce('mhm_clear_shortcode_cache'); ?>');

                // Debug: Check sent data
                console.log('Sent action:', 'mhm_clear_shortcode_cache');
                console.log('Sent nonce:', '<?php echo wp_create_nonce('mhm_clear_shortcode_cache'); ?>');

                console.log('Sending cache clear request...');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);

                    if (data.success) {
                        alert('<?php echo esc_js(__('Cache cleared!', 'mhm-rentiva')); ?>');
                        location.reload();
                    } else {
                        const errorMsg = data.data?.message || 'Unknown error';
                        console.error('Server error:', errorMsg);
                        alert('<?php echo esc_js(__('Error while clearing cache: ', 'mhm-rentiva')); ?>' + errorMsg);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('<?php echo esc_js(__('Error while clearing cache: ', 'mhm-rentiva')); ?>' + error.message);
                });
            }
        };

        window.createShortcodePage = function(shortcode) {
            if (confirm('<?php echo esc_js(__('A page will be created for this shortcode. Do you want to continue?', 'mhm-rentiva')); ?>')) {
                // Loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<?php echo esc_js(__('Creating...', 'mhm-rentiva')); ?>';
                button.disabled = true;

                // Create page via AJAX
                const data = new FormData();
                data.append('action', 'mhm_create_shortcode_page');
                data.append('nonce', '<?php echo wp_create_nonce('mhm_create_shortcode_page'); ?>');
                data.append('shortcode', shortcode);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Offer option to user
                        if (confirm('<?php echo esc_js(__('Page created successfully! Do you want to go to the page editor to edit it?', 'mhm-rentiva')); ?>')) {
                            // First open new tab
                            window.open(data.data.edit_url, '_blank');
                            // Then refresh page (with short delay)
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        } else {
                            // Just refresh page
                            location.reload();
                        }
                    } else {
                        alert('<?php echo esc_js(__('Error occurred while creating page: ', 'mhm-rentiva')); ?>' + (data.data?.message || 'Unknown error'));
                        // Restore button state
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    alert('<?php echo esc_js(__('Error occurred while creating page.', 'mhm-rentiva')); ?>');
                    // Restore button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
        };

        window.deleteShortcodePage = function(pageId, pageTitle) {
            if (confirm('<?php echo esc_js(__('Are you sure you want to move this page to trash?', 'mhm-rentiva')); ?>\n\n' + pageTitle)) {
                // Loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<?php echo esc_js(__('Removing...', 'mhm-rentiva')); ?>';
                button.disabled = true;

                // Delete page via AJAX
                const data = new FormData();
                data.append('action', 'mhm_delete_shortcode_page');
                data.append('nonce', '<?php echo wp_create_nonce('mhm_delete_shortcode_page'); ?>');
                data.append('page_id', pageId);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('<?php echo esc_js(__('Page successfully moved to trash!', 'mhm-rentiva')); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Error occurred while removing page: ', 'mhm-rentiva')); ?>' + (data.data?.message || 'Unknown error'));
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    alert('<?php echo esc_js(__('Error occurred while removing page.', 'mhm-rentiva')); ?>');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
        };

        window.debugShortcodeSearch = function() {
            // Debug search via AJAX
            const data = new FormData();
            data.append('action', 'mhm_debug_shortcode_search');
            data.append('nonce', '<?php echo wp_create_nonce('mhm_debug_shortcode_search'); ?>');

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Debug Results:', data.data);

                    let message = data.data.message + '\n\n';
                    data.data.pages.forEach(page => {
                        message += `Page: ${page.title} (ID: ${page.id})\n`;
                        message += `URL: ${page.url}\n`;
                        message += `Shortcodes: ${page.shortcodes.join(', ')}\n`;
                        message += `Content: ${page.content_preview}\n\n`;
                    });

                    alert(message);
                } else {
                    alert('Debug search failed: ' + (data.data?.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Debug search error: ' + error.message);
            });
        };
        </script>
        <?php
    }

}
