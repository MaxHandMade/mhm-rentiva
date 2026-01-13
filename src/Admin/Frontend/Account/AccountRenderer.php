<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Account Renderer
 * 
 * Renders My Account templates
 * 
 * @since 4.0.0
 */
final class AccountRenderer
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
     * Dashboard render
     */
    public static function render_dashboard(array $atts = []): string
    {
        $user = wp_get_current_user();
        
        // User bookings
        $bookings = self::get_user_bookings($user->ID);
        $active_bookings = array_filter($bookings, function($booking) {
            $status = get_post_meta($booking->ID, '_mhm_status', true);
            return in_array($status, ['confirmed', 'in_progress']);
        });
        
        $data = [
            'user' => $user,
            'bookings_count' => count($bookings),
            'active_bookings_count' => count($active_bookings),
            'recent_bookings' => array_slice($bookings, 0, 5),
            'navigation' => self::get_navigation(),
            'favorites' => SettingsCore::get('mhm_rentiva_customer_favorites', '1'),
            'booking_history' => SettingsCore::get('mhm_rentiva_customer_booking_history', '1'),
            'welcome_message' => SettingsCore::get('mhm_rentiva_customer_dashboard_welcome', __('Welcome to your dashboard. From your account dashboard you can view your recent orders, manage your shipping and billing addresses, and edit your password and account details.', 'mhm-rentiva')),
        ];
        
        // Include template directly (required for JavaScript)
        ob_start();
        extract($data);
        $template_path = WP_PLUGIN_DIR . '/mhm-rentiva/templates/account/dashboard.php';
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Bookings render
     */
    public static function render_bookings(array $atts = []): string
    {
        $user = wp_get_current_user();
        
        $args = [
            'limit' => (int) ($atts['limit'] ?? 10),
            'status' => self::sanitize_text_field_safe($atts['status'] ?? ''),
            'orderby' => self::sanitize_text_field_safe($atts['orderby'] ?? 'date'),
            'order' => self::sanitize_text_field_safe($atts['order'] ?? 'DESC'),
        ];
        
        $bookings = self::get_user_bookings($user->ID, $args);
        
        $data = [
            'user' => $user,
            'bookings' => $bookings,
            'navigation' => (isset($atts['hide_nav']) && $atts['hide_nav']) ? [] : self::get_navigation(),
        ];
        
        return Templates::render('account/bookings', $data, true);
    }

    /**
     * Favorites render
     */
    public static function render_favorites(array $atts = []): string
    {
        $user = wp_get_current_user();
        $favorites = get_user_meta($user->ID, 'mhm_rentiva_favorites', true);
        
        if (!is_array($favorites) || empty($favorites)) {
            $favorites = [];
        }
        
        // Load Vehicles Grid CSS (for grid view)
        wp_enqueue_style(
            'mhm-rentiva-vehicles-grid',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vehicles-grid.css',
            [],
            MHM_RENTIVA_VERSION
        );

        // Localize (grid/list ortak)
        wp_localize_script('mhm-rentiva-my-account', 'mhmRentivaVehiclesList', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rentiva_vehicles_list'),
            'bookingUrl' => ShortcodeUrlManager::get_page_url('rentiva_booking_form'),
            'loginUrl' => wp_login_url(),
            'text' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'no_vehicles' => __('No vehicles found', 'mhm-rentiva'),
                'error' => __('An error occurred', 'mhm-rentiva'),
                'book_now' => __('Book Now', 'mhm-rentiva'),
                'view_details' => __('View Details', 'mhm-rentiva'),
                'added_to_favorites' => __('Added to favorites', 'mhm-rentiva'),
                'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
                'login_required' => __('You must be logged in to add to favorites', 'mhm-rentiva'),
            ],
        ]);
        
        $data = [
            'user' => $user,
            'favorites' => $favorites,
            'columns' => (int) ($atts['columns'] ?? 3),
            'navigation' => (isset($atts['hide_nav']) && $atts['hide_nav']) ? [] : self::get_navigation(),
        ];
        
        return Templates::render('account/favorites', $data, true);
    }

    /**
     * Payment History render
     */
    public static function render_payment_history(array $atts = []): string
    {
        $user = wp_get_current_user();
        
        // Get user payments
        $payments = self::get_user_payments($user->ID, (int) ($atts['limit'] ?? 20));
        
        $data = [
            'user' => $user,
            'payments' => $payments,
            'navigation' => (isset($atts['hide_nav']) && $atts['hide_nav']) ? [] : self::get_navigation(),
        ];
        
        return Templates::render('account/payment-history', $data, true);
    }

    /**
     * Account Details render
     */
    public static function render_account_details(array $atts = []): string
    {
        $user = wp_get_current_user();
        
        $data = [
            'user' => $user,
            'phone' => get_user_meta($user->ID, 'mhm_rentiva_phone', true),
            'address' => get_user_meta($user->ID, 'mhm_rentiva_address', true),
            'navigation' => self::get_navigation(),
        ];
        
        return Templates::render('account/account-details', $data, true);
    }

    /**
     * Messages render
     */
    public static function render_messages(array $atts = []): string
    {
        // Check if Messages feature is enabled
        if (!class_exists(\MHMRentiva\Admin\Licensing\Mode::class) || 
            !\MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)) {
            return '<div class="mhm-rentiva-account-page"><div class="mhm-account-content"><p>' . 
                   __('Messages feature is available in Pro version.', 'mhm-rentiva') . 
                   '</p></div></div>';
        }

        $user = wp_get_current_user();
        
        // Load CSS files
        wp_enqueue_style(
            'mhm-customer-messages',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/customer-messages.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-customer-messages-standalone',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/customer-messages-standalone.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        // ⭐ Load JavaScript file (required for messages functionality)
        wp_enqueue_script(
            'mhm-rentiva-account-messages',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/account-messages.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );
        
        // ⭐ Localize JavaScript (required for REST API calls)
        wp_localize_script('mhm-rentiva-account-messages', 'mhmRentivaMessages', [
            'restUrl' => rest_url('mhm-rentiva/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'customerEmail' => $user->user_email,
            'customerName' => $user->display_name ?: $user->user_login,
            'i18n' => [
                'threadIdNotFound' => __('Thread ID not found.', 'mhm-rentiva'),
                'confirmClose' => __('Are you sure you want to close this conversation? You won\'t be able to send more messages.', 'mhm-rentiva'),
                'closing' => __('Closing...', 'mhm-rentiva'),
                'messageClosed' => __('Message closed successfully.', 'mhm-rentiva'),
                'closeFailed' => __('Failed to close message.', 'mhm-rentiva'),
                'loadingMessages' => __('Loading messages...', 'mhm-rentiva'),
                'noMessages' => __('No messages found yet.', 'mhm-rentiva'),
                'loadFailed' => __('Failed to load messages.', 'mhm-rentiva'),
                'loginRequired' => __('Please login to access your messages.', 'mhm-rentiva'),
                'permissionDenied' => __('You do not have permission to access messages.', 'mhm-rentiva'),
                'new' => __('New', 'mhm-rentiva'),
                'customer' => __('Customer', 'mhm-rentiva'),
                'administrator' => __('Administrator', 'mhm-rentiva'),
                'loadingThread' => __('Loading thread...', 'mhm-rentiva'),
                'threadLoadFailed' => __('Failed to load thread.', 'mhm-rentiva'),
                'noMessagesFound' => __('No messages found.', 'mhm-rentiva'),
                'closeMessage' => __('Close Message', 'mhm-rentiva'),
                'conversationClosed' => __('This conversation is closed.', 'mhm-rentiva'),
                'replySent' => __('Reply sent successfully.', 'mhm-rentiva'),
                'replyFailed' => __('Failed to send reply.', 'mhm-rentiva'),
                'messageSent' => __('Message sent successfully.', 'mhm-rentiva'),
                'messageFailed' => __('Failed to send message.', 'mhm-rentiva'),
                'sending' => __('Sending...', 'mhm-rentiva'),
                'sendMessage' => __('Send Message', 'mhm-rentiva'),
                'sendReply' => __('Send Reply', 'mhm-rentiva'),
                'cancel' => __('Cancel', 'mhm-rentiva'),
                'backToMessages' => __('Back to Messages', 'mhm-rentiva'),
                'newMessage' => __('New Message', 'mhm-rentiva'),
                'yourReply' => __('Your Reply:', 'mhm-rentiva'),
            ],
        ]);
        
        $data = [
            'user' => $user,
            'customer_email' => $user->user_email,
            'customer_name' => $user->display_name ?: $user->user_login,
            'navigation' => (isset($atts['hide_nav']) && $atts['hide_nav']) ? [] : self::get_navigation(),
        ];
        
        // Include template directly (required for JavaScript - whitespace must be preserved)
        ob_start();
        extract($data);
        $template_path = MHM_RENTIVA_PLUGIN_DIR . 'templates/account/messages.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
        return ob_get_clean();
    }

    /**
     * Login Form render
     */
    public static function render_login_form(array $atts = []): string
    {
        $redirect = !empty($atts['redirect']) ? 
            $atts['redirect'] : 
            AccountController::get_account_url();
        
        $data = [
            'redirect' => $redirect,
            'show_register_link' => ($atts['show_register_link'] ?? '1') === '1',
            'register_url' => ShortcodeUrlManager::get_page_url('rentiva_register_form'),
            'lost_password_url' => wp_lostpassword_url(),
        ];
        
        return Templates::render('account/login-form', $data, true);
    }

    /**
     * Register Form render
     */
    public static function render_register_form(array $atts = []): string
    {
        $redirect = !empty($atts['redirect']) ? 
            $atts['redirect'] : 
            AccountController::get_account_url();
        
        $data = [
            'redirect' => $redirect,
            'show_login_link' => ($atts['show_login_link'] ?? '1') === '1',
            'login_url' => ShortcodeUrlManager::get_page_url('rentiva_login_form'),
        ];
        
        return Templates::render('account/register-form', $data, true);
    }

    /**
     * Booking detail render
     */
    /**
     * Booking detail render
     */
    public static function render_booking_detail(int $booking_id, bool $hide_nav = false): string
    {
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            return '<p>' . __('Booking not found.', 'mhm-rentiva') . '</p>';
        }
        
        // User check
        $user = wp_get_current_user();
        $booking_user_id = get_post_meta($booking_id, '_mhm_customer_user_id', true);
        
        if ($booking_user_id != $user->ID && !current_user_can('manage_options')) {
            return '<p>' . __('You do not have permission to view this booking.', 'mhm-rentiva') . '</p>';
        }
        
        $navigation = $hide_nav ? [] : self::get_navigation();
        
        // If navigation is empty (e.g. WooCommerce integration), provide minimal nav for breadcrumbs
        if (empty($navigation)) {
            $dashboard_url = home_url('/');
            $bookings_url = home_url('/');
            
            if (class_exists('WooCommerce') && function_exists('wc_get_endpoint_url')) {
                $my_account_url = wc_get_page_permalink('myaccount');
                $dashboard_url = $my_account_url;
                $bookings_slug = AccountController::get_endpoint_slug('bookings', 'rentiva-bookings');
                $bookings_url = wc_get_endpoint_url($bookings_slug, '', $my_account_url);
            } else {
                 $dashboard_url = AccountController::get_account_url();
                 $bookings_url = AccountController::get_booking_view_url($booking_id); // Fallback, though typically not empty in standalone
                 // Actually for standalone get_navigation shouldn't be empty unless something is wrong.
                 // But for WooCommerce it returns empty array intentionally.
            }
            
            $navigation = [
                'dashboard' => ['url' => $dashboard_url],
                'bookings' => ['url' => $bookings_url],
            ];
        }
        
        $data = [
            'booking' => $booking,
            'booking_id' => $booking_id,
            'navigation' => $navigation,
            'is_integrated' => $hide_nav,
        ];
        
        return Templates::render('account/booking-detail', $data, true);
    }

    /**
     * Get navigation menu
     * Returns empty array if WooCommerce My Account page is active (to avoid duplicate navigation)
     */
    private static function get_navigation(): array
    {
        // ⭐ Don't show custom navigation on WooCommerce My Account page
        // WooCommerce already provides its own navigation menu
        if (self::is_woocommerce_account_page()) {
            return [];
        }
        
        // Main account page URL
        $base_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_my_account');
        
        return [
            'dashboard' => [
                'title' => __('Dashboard', 'mhm-rentiva'),
                'url' => $base_url,
                'icon' => '📊',
            ],
            'bookings' => [
                'title' => __('My Bookings', 'mhm-rentiva'),
                'url' => add_query_arg('endpoint', 'bookings', $base_url),
                'icon' => '📅',
            ],
            'favorites' => [
                'title' => __('Favorite Vehicles', 'mhm-rentiva'),
                'url' => add_query_arg('endpoint', 'favorites', $base_url),
                'icon' => '❤️',
            ],
            'payment-history' => [
                'title' => __('Payment History', 'mhm-rentiva'),
                'url' => add_query_arg('endpoint', 'payment-history', $base_url),
                'icon' => '💳',
            ],
            'messages' => [
                'title' => __('Messages', 'mhm-rentiva'),
                'url' => add_query_arg('endpoint', 'messages', $base_url),
                'icon' => '💬',
            ],
            'edit-account' => [
                'title' => __('Account Details', 'mhm-rentiva'),
                'url' => add_query_arg('endpoint', 'edit-account', $base_url),
                'icon' => '⚙️',
            ],
            'logout' => [
                'title' => __('Logout', 'mhm-rentiva'),
                'url' => wp_logout_url($base_url),
                'icon' => '🚪',
            ],
        ];
    }

    /**
     * Check if current page is WooCommerce My Account page
     * 
     * @return bool True if WooCommerce My Account page is active
     */
    private static function is_woocommerce_account_page(): bool
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_page_id')) {
            return false;
        }
        
        $account_page_id = wc_get_page_id('myaccount');
        if (!$account_page_id || $account_page_id <= 0) {
            return false;
        }
        
        // Check if we're on the WooCommerce My Account page
        return is_page($account_page_id) || is_account_page();
    }

    /**
     * Get user bookings
     */
    private static function get_user_bookings(int $user_id, array $args = []): array
    {
        global $wpdb;
        
        $defaults = [
            'limit' => 10,
            'status' => '',
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Meta query
        $meta_query = [
            [
                'key' => '_mhm_customer_user_id',
                'value' => $user_id,
                'compare' => '=',
            ],
        ];
        
        // Status filter
        if (!empty($args['status'])) {
            $meta_query[] = [
                'key' => '_mhm_status',
                'value' => self::sanitize_text_field_safe($args['status']),
                'compare' => '=',
            ];
        }
        
        $query_args = [
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'posts_per_page' => (int) $args['limit'],
            'orderby' => self::sanitize_text_field_safe($args['orderby']),
            'order' => self::sanitize_text_field_safe($args['order']),
            'meta_query' => $meta_query,
        ];
        
        $query = new \WP_Query($query_args);
        
        return $query->posts;
    }

    /**
     * Get user payments
     */
    private static function get_user_payments(int $user_id, int $limit = 20): array
    {
        // Get all user bookings
        $bookings = self::get_user_bookings($user_id, ['limit' => -1]);
        
        $payments = [];
        
        foreach ($bookings as $booking) {
            $payment_status = get_post_meta($booking->ID, '_mhm_payment_status', true);
            $payment_method = get_post_meta($booking->ID, '_mhm_payment_method', true);
            $payment_gateway = get_post_meta($booking->ID, '_mhm_payment_gateway', true);
            $total_price = (float) get_post_meta($booking->ID, '_mhm_total_price', true);
            $deposit_amount = (float) get_post_meta($booking->ID, '_mhm_deposit_amount', true);
            $payment_type = get_post_meta($booking->ID, '_mhm_payment_type', true);
            
            // Normalize status values from various sources
            $status_key = strtolower((string) $payment_status);
            $status_key = str_replace(' ', '_', $status_key);
            switch ($status_key) {
                case 'pending_verification':
                case 'verification_pending':
                case 'processing':
                case 'awaiting_payment':
                    $payment_status = 'pending';
                    break;
                case 'completed':
                case 'paid':
                case 'succeeded':
                    $payment_status = 'completed';
                    break;
                case 'cancelled':
                case 'canceled':
                case 'refunded':
                case 'failed':
                    $payment_status = 'cancelled';
                    break;
                default:
                    // Fallback to original or pending when unknown
                    $payment_status = $status_key ?: 'pending';
            }
            
            // Fallback gateway if empty (manual/offline)
            if (empty($payment_gateway)) {
                $payment_gateway = $payment_method ?: 'woocommerce';
            }

            // Build date with time if available
            $pickup_date = get_post_meta($booking->ID, '_mhm_pickup_date', true) ?: get_post_meta($booking->ID, '_booking_pickup_date', true);
            $pickup_time = get_post_meta($booking->ID, '_mhm_start_time', true) ?: get_post_meta($booking->ID, '_mhm_pickup_time', true) ?: get_post_meta($booking->ID, '_booking_pickup_time', true);
            $date_str = trim($pickup_date . ' ' . ($pickup_time ?: ''));
            $date_formatted = $date_str ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_str)) : get_the_date('', $booking->ID);
            
            if ($payment_status) {
                // Translate Status
                $status_labels = [
                    'pending' => __('Pending', 'mhm-rentiva'),
                    'completed' => __('Completed', 'mhm-rentiva'),
                    'cancelled' => __('Cancelled', 'mhm-rentiva'),
                ];
                $status_label = $status_labels[$payment_status] ?? ucfirst($payment_status);

                // Format Gateway/Method 
                $woocommerce_method_title = '';
                
                // Try to get method title from WooCommerce Order if exists
                $wc_order_id = get_post_meta($booking->ID, '_mhm_woocommerce_order_id', true);
                if ($wc_order_id && function_exists('wc_get_order')) {
                    $order = wc_get_order($wc_order_id);
                    if ($order) {
                        $woocommerce_method_title = $order->get_payment_method_title();
                    }
                }

                // If we found a real title, use it. Otherwise fallback to existing method or gateway
                if (!empty($woocommerce_method_title)) {
                    $method_display = $woocommerce_method_title;
                } elseif (!empty($payment_method) && $payment_method !== 'manual') {
                    $method_display = ucfirst($payment_method);
                } else {
                    // Fallback to gateway name if method is empty
                     $method_display = $payment_gateway === 'woocommerce' ? 'WooCommerce' : ucfirst($payment_gateway);
                }
                
                $payments[] = [
                    'booking_id' => $booking->ID,
                    'booking_title' => get_the_title($booking->ID),
                    'date' => $date_formatted,
                    'status' => $payment_status,
                    'status_label' => $status_label,
                    'method' => $method_display,
                    // 'gateway' => $gateway_label, // Removed as requested
                    'amount' => $payment_type === 'deposit' ? $deposit_amount : $total_price,
                    'total' => $total_price,
                    'type' => $payment_type,
                    'receipt' => [
                        'attachment_id' => (int) get_post_meta($booking->ID, '_mhm_receipt_attachment_id', true),
                        'status' => get_post_meta($booking->ID, '_mhm_receipt_status', true),
                        'url' => ($id = (int) get_post_meta($booking->ID, '_mhm_receipt_attachment_id', true)) ? wp_get_attachment_url($id) : '',
                    ],
                ];
            }
        }
        
        // Latest payments first
        usort($payments, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return array_slice($payments, 0, $limit);
    }
}

