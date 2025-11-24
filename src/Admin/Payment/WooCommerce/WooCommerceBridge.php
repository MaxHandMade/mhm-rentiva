<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Core\Status;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Bridge Class
 * 
 * Handles all interactions between MHM Rentiva and WooCommerce.
 */
class WooCommerceBridge
{
    public const PRODUCT_SKU = 'mhm-rentiva-booking';
    
    public static function register(): void
    {
        // Cart item data
        add_filter('woocommerce_get_cart_item_from_session', [self::class, 'get_cart_item_from_session'], 10, 2);
        add_filter('woocommerce_get_item_data', [self::class, 'get_item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [self::class, 'calculate_totals'], 10, 1);
        
        // Order processing
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'add_order_item_meta'], 10, 4);
        add_action('woocommerce_order_status_changed', [self::class, 'handle_order_status_change'], 10, 4);
        
        // Hide quantity input for booking items
        add_filter('woocommerce_cart_item_quantity', [self::class, 'disable_cart_quantity'], 10, 3);
    }

    /**
     * Add booking to WooCommerce cart
     * 
     * @param int $booking_id Booking ID
     * @param float $amount Amount to charge (deposit or full)
     * @return bool Success
     */
    public static function add_booking_to_cart(int $booking_id, float $amount): bool
    {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        // Initialize WooCommerce session and cart if missing (common in admin-post.php)
        if (!isset(WC()->cart) || null === WC()->cart) {
            if (function_exists('wc_load_cart')) {
                wc_load_cart();
            } elseif (function_exists('WC') && isset(WC()->session)) {
                // Fallback manual load
                if (!WC()->session->has_session()) {
                    WC()->session->set_customer_session_cookie(true);
                }
                WC()->cart = new \WC_Cart();
                WC()->cart->get_cart(); // Load cart from session
            }
        }

        if (!isset(WC()->cart)) {
            return false;
        }

        $product_id = self::get_booking_product_id();
        if (!$product_id) {
            return false;
        }

        // Empty cart first (optional, depending on business logic)
        // WC()->cart->empty_cart();

        $cart_item_data = [
            'mhm_booking_id' => $booking_id,
            'mhm_booking_price' => $amount
        ];

        try {
            WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
            return true;
        } catch (\Exception $e) {
            error_log('MHM Rentiva: Failed to add to cart - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get or create the placeholder product for bookings
     */
    public static function get_booking_product_id(): int
    {
        $product_id = wc_get_product_id_by_sku(self::PRODUCT_SKU);
        
        if ($product_id) {
            return $product_id;
        }

        // Create product if not exists
        $product = new \WC_Product_Simple();
        $product->set_name(__('Vehicle Rental Booking', 'mhm-rentiva'));
        $product->set_sku(self::PRODUCT_SKU);
        $product->set_price(0);
        $product->set_regular_price(0);
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->save();

        return $product->get_id();
    }

    /**
     * Restore custom data from session
     */
    public static function get_cart_item_from_session($cart_item, $values)
    {
        if (isset($values['mhm_booking_id'])) {
            $cart_item['mhm_booking_id'] = $values['mhm_booking_id'];
            $cart_item['mhm_booking_price'] = $values['mhm_booking_price'];
        }
        return $cart_item;
    }

    /**
     * Display booking details in cart
     */
    public static function get_item_data($item_data, $cart_item)
    {
        if (isset($cart_item['mhm_booking_id'])) {
            $booking_id = $cart_item['mhm_booking_id'];
            $vehicle_id = get_post_meta($booking_id, '_mhm_vehicle_id', true);
            $pickup_date = get_post_meta($booking_id, '_mhm_pickup_date', true);
            $dropoff_date = get_post_meta($booking_id, '_mhm_dropoff_date', true);
            
            $item_data[] = [
                'key' => __('Vehicle', 'mhm-rentiva'),
                'value' => get_the_title($vehicle_id)
            ];
            
            $item_data[] = [
                'key' => __('Dates', 'mhm-rentiva'),
                'value' => $pickup_date . ' - ' . $dropoff_date
            ];

            $item_data[] = [
                'key' => __('Booking ID', 'mhm-rentiva'),
                'value' => '#' . $booking_id
            ];

            // Add Payment Type info
            $payment_type = get_post_meta($booking_id, '_mhm_payment_type', true);
            $type_label = $payment_type === 'deposit' ? __('Deposit Payment', 'mhm-rentiva') : __('Full Payment', 'mhm-rentiva');
            
            $item_data[] = [
                'key' => __('Payment Type', 'mhm-rentiva'),
                'value' => $type_label
            ];
        }
        return $item_data;
    }

    /**
     * Override price in cart
     */
    public static function calculate_totals($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['mhm_booking_price'])) {
                $cart_item['data']->set_price($cart_item['mhm_booking_price']);
            }
        }
    }

    /**
     * Save booking ID to order item meta
     */
    public static function add_order_item_meta($item, $cart_item_key, $values, $order)
    {
        if (isset($values['mhm_booking_id'])) {
            $item->add_meta_data('_mhm_booking_id', $values['mhm_booking_id']);
            
            // Also link order to booking
            update_post_meta($values['mhm_booking_id'], '_mhm_wc_order_id', $order->get_id());
        }
    }

    /**
     * Handle order status changes
     */
    public static function handle_order_status_change($order_id, $old_status, $new_status, $order)
    {
        $items = $order->get_items();
        
        foreach ($items as $item) {
            $booking_id = (int) $item->get_meta('_mhm_booking_id');
            
            if ($booking_id) {
                // Status Mapping Logic
                switch ($new_status) {
                    case 'completed':
                        // Order completed (Service done)
                        update_post_meta($booking_id, '_mhm_payment_status', 'paid');
                        Status::update_status($booking_id, 'completed', get_current_user_id());
                        break;

                    case 'processing':
                        // Payment confirmed
                        update_post_meta($booking_id, '_mhm_payment_status', 'paid');
                        Status::update_status($booking_id, 'confirmed', get_current_user_id());
                        break;

                    case 'on-hold':
                        // Payment pending (Bank transfer etc.)
                        update_post_meta($booking_id, '_mhm_payment_status', 'pending');
                        Status::update_status($booking_id, 'pending', get_current_user_id());
                        break;

                    case 'cancelled':
                    case 'failed':
                        // Order cancelled
                        Status::update_status($booking_id, 'cancelled', get_current_user_id());
                        break;

                    case 'refunded':
                        // Order refunded
                        Status::update_status($booking_id, 'refunded', get_current_user_id());
                        break;
                }

                // Add log
                $logs = get_post_meta($booking_id, '_mhm_booking_logs', true) ?: [];
                $logs[] = [
                    'action' => 'wc_status_change',
                    'timestamp' => current_time('mysql'),
                    'user_id' => 0, // System
                    'data' => [
                        'order_id' => $order_id,
                        'old_status' => $old_status,
                        'new_status' => $new_status
                    ]
                ];
                update_post_meta($booking_id, '_mhm_booking_logs', $logs);
            }
        }
    }

    /**
     * Disable quantity input for booking items
     */
    public static function disable_cart_quantity($product_quantity, $cart_item_key, $cart_item)
    {
        if (isset($cart_item['mhm_booking_id'])) {
            return sprintf('%s <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item['quantity'], $cart_item_key);
        }
        return $product_quantity;
    }
}
