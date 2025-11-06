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
            'navigation' => self::get_navigation(),
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
            'navigation' => self::get_navigation(),
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
            'navigation' => self::get_navigation(),
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
        
        // Load only CSS files (JavaScript is handled in template)
        // Scripts are dequeued in AccountController::enqueue_assets()
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
        
        $data = [
            'user' => $user,
            'customer_email' => $user->user_email,
            'customer_name' => $user->display_name ?: $user->user_login,
            'navigation' => self::get_navigation(),
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
    public static function render_booking_detail(int $booking_id): string
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
        
        $data = [
            'booking' => $booking,
            'booking_id' => $booking_id,
            'navigation' => self::get_navigation(),
        ];
        
        return Templates::render('account/booking-detail', $data, true);
    }

    /**
     * Get navigation menu
     */
    private static function get_navigation(): array
    {
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
                $payment_gateway = $payment_method ?: 'offline';
            }

            // Build date with time if available
            $pickup_date = get_post_meta($booking->ID, '_mhm_pickup_date', true) ?: get_post_meta($booking->ID, '_booking_pickup_date', true);
            $pickup_time = get_post_meta($booking->ID, '_mhm_start_time', true) ?: get_post_meta($booking->ID, '_mhm_pickup_time', true) ?: get_post_meta($booking->ID, '_booking_pickup_time', true);
            $date_str = trim($pickup_date . ' ' . ($pickup_time ?: ''));
            $date_formatted = $date_str ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_str)) : get_the_date('', $booking->ID);
            
            if ($payment_status) {
                $payments[] = [
                    'booking_id' => $booking->ID,
                    'booking_title' => get_the_title($booking->ID),
                    'date' => $date_formatted,
                    'status' => $payment_status,
                    'method' => $payment_method,
                    'gateway' => $payment_gateway,
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

