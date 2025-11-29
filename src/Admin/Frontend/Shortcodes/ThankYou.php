<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thank You Page Shortcode
 *
 * Displays a thank you message after booking completion
 *
 * @since 4.0.0
 */
final class ThankYou extends AbstractShortcode
{
    /**
     * Returns the shortcode tag
     */
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_thank_you';
    }

    /**
     * Returns the template file path
     */
    protected static function get_template_path(): string
    {
        return 'shortcodes/thank-you';
    }

    /**
     * Returns default attributes for template
     */
    protected static function get_default_attributes(): array
    {
        return [
            'booking_id' => '',
            'show_details' => '1',
            'show_actions' => '1',
            'class' => '',
        ];
    }

    /**
     * Returns CSS filename
     */
    protected static function get_css_filename(): string
    {
        return 'thank-you.css';
    }

    /**
     * Prepares template data
     */
    protected static function prepare_template_data(array $atts): array
    {
        // Get booking_id from URL
        $booking_id = (int) ($atts['booking_id'] ?? 0);
        
        // Also check from URL parameter
        if (!$booking_id) {
            $booking_id = (int) ($_GET['booking_id'] ?? 0);
        }

        if (!$booking_id) {
            return [
                'error' => __('Invalid booking ID.', 'mhm-rentiva'),
                'atts' => $atts
            ];
        }

        $booking = get_post($booking_id);

        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            return [
                'error' => __('Booking not found.', 'mhm-rentiva'),
                'atts' => $atts
            ];
        }

        // Get vehicle information
        $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
        $vehicle = $vehicle_id ? get_post($vehicle_id) : null;

        if (!$vehicle) {
            return [
                'error' => __('Vehicle not found.', 'mhm-rentiva'),
                'atts' => $atts
            ];
        }

        // Get booking date information using BookingQueryHelper
        $date_info = BookingQueryHelper::getBookingDateInfo($booking_id);
        $pickup_date = $date_info['start_date'] ?? '';
        $dropoff_date = $date_info['end_date'] ?? '';
        $pickup_time = $date_info['start_time'] ?? '';
        $dropoff_time = $date_info['end_time'] ?? '';

        // Get customer information using BookingQueryHelper
        $customer_info = BookingQueryHelper::getBookingCustomerInfo($booking_id);
        $customer_first_name = $customer_info['first_name'] ?? '';
        $customer_last_name = $customer_info['last_name'] ?? '';
        $customer_name = $customer_info['name'] ?? '';
        $customer_email = $customer_info['email'] ?? '';
        $customer_phone = $customer_info['phone'] ?? '';

        // Get booking reference
        $booking_reference = get_post_meta($booking_id, '_mhm_booking_reference', true);
        if (empty($booking_reference)) {
            $booking_reference = 'BK-' . str_pad((string) $booking_id, 6, '0', STR_PAD_LEFT);
        }

        // Get booking status
        $status = get_post_meta($booking_id, '_mhm_status', true);
        if (empty($status)) {
            $status = 'pending';
        }

        // Get payment information
        $payment_type = get_post_meta($booking_id, '_mhm_payment_type', true);
        $payment_method = get_post_meta($booking_id, '_mhm_payment_method', true);
        $payment_status = get_post_meta($booking_id, '_mhm_payment_status', true);
        $total_price = (float) get_post_meta($booking_id, '_mhm_total_price', true);
        $deposit_amount = (float) get_post_meta($booking_id, '_mhm_deposit_amount', true);
        $remaining_amount = (float) get_post_meta($booking_id, '_mhm_remaining_amount', true);

        // Get selected addons
        $selected_addons = get_post_meta($booking_id, '_mhm_selected_addons', true);
        if (!is_array($selected_addons)) {
            $selected_addons = maybe_unserialize($selected_addons);
            if (!is_array($selected_addons)) {
                $selected_addons = [];
            }
        }

        // Vehicle image
        $vehicle_image = get_the_post_thumbnail_url($vehicle_id, 'medium');
        if (!$vehicle_image) {
            $vehicle_image = MHM_RENTIVA_PLUGIN_URL . 'assets/images/no-image.png';
        }

        // Currency symbol
        $currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

        // WooCommerce order ID
        $woocommerce_order_id = get_post_meta($booking_id, '_mhm_woocommerce_order_id', true);
        $woocommerce_order = null;
        if (!empty($woocommerce_order_id) && class_exists('WooCommerce')) {
            $woocommerce_order = wc_get_order($woocommerce_order_id);
        }

        return [
            'booking' => $booking,
            'booking_id' => $booking_id,
            'booking_reference' => $booking_reference,
            'vehicle' => $vehicle,
            'vehicle_id' => $vehicle_id,
            'vehicle_image' => $vehicle_image,
            'pickup_date' => $pickup_date,
            'dropoff_date' => $dropoff_date,
            'pickup_time' => $pickup_time,
            'dropoff_time' => $dropoff_time,
            'total_price' => $total_price,
            'status' => $status,
            'payment_type' => $payment_type,
            'deposit_amount' => $deposit_amount,
            'remaining_amount' => $remaining_amount,
            'selected_addons' => $selected_addons,
            'customer_first_name' => $customer_first_name,
            'customer_last_name' => $customer_last_name,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'payment_method' => $payment_method,
            'payment_status' => $payment_status,
            'currency_symbol' => $currency_symbol,
            'woocommerce_order' => $woocommerce_order,
            'woocommerce_order_id' => $woocommerce_order_id,
            'atts' => $atts,
        ];
    }

    /**
     * Shortcode register
     */
    public static function register(): void
    {
        parent::register();
    }

    /**
     * Enqueue asset files
     */
    protected static function enqueue_assets(): void
    {
        // CSS
        wp_enqueue_style(
            'mhm-rentiva-thank-you',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/thank-you.css',
            [],
            MHM_RENTIVA_VERSION
        );
    }

    /**
     * Create thank you page URL
     */
    public static function get_thank_you_url(int $booking_id): string
    {
        $thank_you_page = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_thank_you_page', '');
        
        if (!empty($thank_you_page) && is_numeric($thank_you_page)) {
            $thank_you_url = get_permalink((int) $thank_you_page);
            if ($thank_you_url) {
                return add_query_arg(['booking_id' => $booking_id], $thank_you_url);
            }
        }
        
        // ⭐ Try to find or create thank you page automatically
        $thank_you_page_id = self::find_or_create_thank_you_page();
        if ($thank_you_page_id) {
            $thank_you_url = get_permalink($thank_you_page_id);
            if ($thank_you_url) {
                return add_query_arg(['booking_id' => $booking_id], $thank_you_url);
            }
        }
        
        // Last fallback: Use booking confirmation page
        return \MHMRentiva\Admin\Frontend\Shortcodes\BookingConfirmation::get_confirmation_url($booking_id);
    }

    /**
     * Find or create thank you page
     */
    private static function find_or_create_thank_you_page(): ?int
    {
        // Try to find existing thank you page with shortcode
        $existing_page = self::locate_shortcode_page('rentiva_thank_you');
        if ($existing_page) {
            return $existing_page;
        }

        // Create new thank you page
        $page_data = [
            'post_title' => __('Thank You', 'mhm-rentiva'),
            'post_content' => '[rentiva_thank_you]',
            'post_status' => 'publish',
            'post_type' => 'page',
        ];

        $page_id = wp_insert_post($page_data);
        if (is_wp_error($page_id) || !$page_id) {
            return null;
        }

        // Save to settings
        $settings = get_option('mhm_rentiva_settings', []);
        $settings['mhm_rentiva_booking_thank_you_page'] = (string) $page_id;
        update_option('mhm_rentiva_settings', $settings);

        return $page_id;
    }

    /**
     * Locate page with shortcode
     */
    private static function locate_shortcode_page(string $shortcode): ?int
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => '[' . $shortcode . ']',
        ]);

        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, $shortcode)) {
                return $page->ID;
            }
        }

        return null;
    }
}

