<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\Core\PaymentGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Bridge Class
 * 
 * Handles all interactions between MHM Rentiva and WooCommerce.
 * Implements PaymentGatewayInterface for loose coupling.
 */
final class WooCommerceBridge implements PaymentGatewayInterface
{
    public const PRODUCT_SKU = 'mhm-rentiva-booking';

    public static function register(): void
    {
        // Cart item data
        add_filter('woocommerce_get_cart_item_from_session', [self::class, 'get_cart_item_from_session'], 10, 2);
        add_filter('woocommerce_get_item_data', [self::class, 'get_item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [self::class, 'calculate_totals'], 10, 1);

        // ⭐ Fix tax calculation - tax should be calculated on total price, not deposit
        add_filter('woocommerce_cart_item_price', [self::class, 'adjust_cart_item_price_display'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [self::class, 'adjust_cart_item_subtotal_display'], 10, 3);
        add_filter('woocommerce_cart_item_thumbnail', [self::class, 'display_vehicle_image'], 10, 3);

        // ⭐ Override tax calculation for booking items
        add_filter('woocommerce_cart_item_get_taxes', [self::class, 'adjust_cart_item_taxes'], 10, 2);
        add_action('woocommerce_cart_calculate_fees', [self::class, 'adjust_tax_calculation'], 10, 1);

        // Order processing
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'add_order_item_meta'], 10, 4);
        // ⭐ Create booking when order is processed - use primary hook with fallback
        add_action('woocommerce_checkout_order_processed', [self::class, 'create_booking_from_order'], 10, 3);
        // Fallback hook if primary fails (thankyou page load)
        add_action('woocommerce_thankyou', [self::class, 'create_booking_from_order_fallback'], 5, 1);
        add_action('woocommerce_order_status_changed', [self::class, 'handle_order_status_change'], 10, 4);

        // ⭐ WooCommerce refund hook - handles actual refund amounts
        add_action('woocommerce_refund_created', [self::class, 'handle_order_refunded'], 10, 2);

        // Hide quantity input for booking items
        add_filter('woocommerce_cart_item_quantity', [self::class, 'disable_cart_quantity'], 10, 3);

        // Checkout custom fields
        add_filter('woocommerce_checkout_fields', [self::class, 'add_checkout_payment_type_field'], 10, 1);
        add_action('woocommerce_checkout_update_order_meta', [self::class, 'save_checkout_payment_type'], 10, 2);
        add_action('woocommerce_review_order_before_payment', [self::class, 'display_payment_type_field'], 10);

        // Enqueue checkout CSS
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_checkout_styles'], 20);

        // ⭐ Validate availability before checkout (PREVENT PAYMENT)
        add_action('woocommerce_check_cart_items', [self::class, 'validate_cart_availability'], 10);
        add_action('woocommerce_checkout_process', [self::class, 'validate_checkout_availability'], 10);

        // AJAX handlers for payment type change
        add_action('wp_ajax_mhm_update_booking_payment_type', [self::class, 'ajax_update_payment_type']);
        add_action('wp_ajax_nopriv_mhm_update_booking_payment_type', [self::class, 'ajax_update_payment_type']);
    }

    /**
     * Enqueue checkout styles
     */
    public static function enqueue_checkout_styles(): void
    {
        if (function_exists('is_checkout') && \is_checkout()) {
            wp_enqueue_style(
                'mhm-woocommerce-checkout',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/payment/woocommerce-checkout.css',
                [],
                MHM_RENTIVA_VERSION
            );
        }
    }

    /**
     * Ensure WooCommerce session and cart are initialized
     * 
     * @return bool True if cart is available, false otherwise
     */
    private static function ensure_wc_session(): bool
    {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        // Initialize WooCommerce session and cart if missing (common in admin-post.php)
        if (!isset(\WC()->cart) || null === \WC()->cart) {
            if (function_exists('wc_load_cart')) {
                \wc_load_cart();
            } elseif (function_exists('WC') && isset(\WC()->session)) {
                // Fallback manual load
                if (!\WC()->session->has_session()) {
                    \WC()->session->set_customer_session_cookie(true);
                }
                \WC()->cart = new \WC_Cart();
                \WC()->cart->get_cart(); // Load cart from session
            }
        }

        return isset(\WC()->cart);
    }

    /**
     * Get normalized booking data from cart item
     * Returns booking ID, booking data, vehicle ID in a consistent format
     * 
     * @param array $cart_item Cart item array
     * @return array{booking_id: int|null, booking_data: array|null, vehicle_id: int|null}
     */
    private static function get_normalized_booking_data(array $cart_item): array
    {
        $booking_id = null;
        $booking_data = null;
        $vehicle_id = null;

        // Check for existing booking (legacy)
        if (isset($cart_item['mhm_booking_id'])) {
            $booking_id = (int) $cart_item['mhm_booking_id'];
            $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
        }
        // Check for pending booking (from cart data)
        elseif (isset($cart_item['mhm_booking_data']) && is_array($cart_item['mhm_booking_data'])) {
            $booking_data = $cart_item['mhm_booking_data'];
            $vehicle_id = (int) ($booking_data['vehicle_id'] ?? 0);
        }

        return [
            'booking_id' => $booking_id,
            'booking_data' => $booking_data,
            'vehicle_id' => $vehicle_id,
        ];
    }

    /**
     * Convert date and time strings to timestamp
     * 
     * @param string $date Date string (Y-m-d format)
     * @param string $time Time string (H:i format, optional)
     * @return int|null Timestamp or null on failure
     */
    private static function parse_datetime_to_timestamp(string $date, string $time = ''): ?int
    {
        $datetime = trim($date . ' ' . $time);
        $timestamp = strtotime($datetime);

        return $timestamp !== false ? $timestamp : null;
    }

    /**
     * Add booking data to WooCommerce cart (without creating booking yet)
     * Booking will be created when order is processed
     * 
     * @param array $booking_data Booking data array
     * @param float $amount Amount to charge (deposit or full)
     * @return bool Success
     */
    public static function add_booking_data_to_cart(array $booking_data, float $amount): bool
    {
        if (!self::ensure_wc_session()) {
            return false;
        }

        $product_id = self::get_booking_product_id();
        if (!$product_id) {
            return false;
        }

        // Empty cart first (optional, depending on business logic)
        // WC()->cart->empty_cart();

        // Store booking data in cart item (will be used to create booking after payment)
        $cart_item_data = [
            'mhm_booking_data' => $booking_data, // ⭐ Store full booking data instead of booking_id
            'mhm_booking_price' => $amount,
            'mhm_booking_pending' => true // Flag to indicate booking is not created yet
        ];

        try {
            \WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
            return true;
        } catch (\Exception $e) {
            error_log('MHM Rentiva: Failed to add to cart - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add booking to WooCommerce cart (legacy method - for existing bookings)
     * 
     * @param int $booking_id Booking ID
     * @param float $amount Amount to charge (deposit or full)
     * @return bool Success
     */
    public static function add_booking_to_cart(int $booking_id, float $amount): bool
    {
        if (!self::ensure_wc_session()) {
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
            \WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
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
        $product_id = \wc_get_product_id_by_sku(self::PRODUCT_SKU);

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
        // ⭐ Also restore pending booking data
        if (isset($values['mhm_booking_data'])) {
            $cart_item['mhm_booking_data'] = $values['mhm_booking_data'];
            $cart_item['mhm_booking_price'] = $values['mhm_booking_price'];
            $cart_item['mhm_booking_pending'] = $values['mhm_booking_pending'] ?? true;
        }
        return $cart_item;
    }

    /**
     * Display booking details in cart
     */
    public static function get_item_data($item_data, $cart_item)
    {
        $normalized = self::get_normalized_booking_data($cart_item);
        $booking_id = $normalized['booking_id'];
        $booking_data = $normalized['booking_data'];
        $vehicle_id = $normalized['vehicle_id'];

        // Check if we're on checkout or cart page - hide vehicle image on both
        $is_checkout = function_exists('is_checkout') && \is_checkout();
        $is_cart = function_exists('is_cart') && \is_cart();
        $hide_image = $is_checkout || $is_cart;

        if ($booking_id) {
            $pickup_date = get_post_meta($booking_id, '_mhm_pickup_date', true);
            $dropoff_date = get_post_meta($booking_id, '_mhm_dropoff_date', true);
            $pickup_time = get_post_meta($booking_id, '_mhm_pickup_time', true);
            $dropoff_time = get_post_meta($booking_id, '_mhm_dropoff_time', true);

            // Vehicle image - hide on checkout page
            if (!$is_checkout) {
                $vehicle_image = get_the_post_thumbnail_url($vehicle_id, 'thumbnail');
                if ($vehicle_image) {
                    $item_data[] = [
                        'key' => __('Vehicle Image', 'mhm-rentiva'),
                        'value' => '<img src="' . esc_url($vehicle_image) . '" alt="' . esc_attr(get_the_title($vehicle_id)) . '" style="max-width: 80px; height: auto; border-radius: 4px;">'
                    ];
                }
            }

            $item_data[] = [
                'key' => __('Vehicle', 'mhm-rentiva'),
                'value' => get_the_title($vehicle_id)
            ];

            // Pickup date and time
            $pickup_display = $pickup_date;
            if ($pickup_time) {
                $pickup_display .= ' ' . $pickup_time;
            }

            // Dropoff date and time
            $dropoff_display = $dropoff_date;
            if ($dropoff_time) {
                $dropoff_display .= ' ' . $dropoff_time;
            }

            $item_data[] = [
                'key' => __('Pickup Date & Time', 'mhm-rentiva'),
                'value' => $pickup_display
            ];

            $item_data[] = [
                'key' => __('Return Date & Time', 'mhm-rentiva'),
                'value' => $dropoff_display
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

            // Add remaining amount for deposit payments
            if ($payment_type === 'deposit') {
                $remaining_amount = (float) get_post_meta($booking_id, '_mhm_remaining_amount', true);
                if ($remaining_amount > 0) {
                    $item_data[] = [
                        'key' => __('Remaining Amount', 'mhm-rentiva'),
                        'value' => wc_price($remaining_amount)
                    ];
                }
            }
        }
        // ⭐ Handle pending booking (from cart data)
        elseif ($booking_data && $vehicle_id) {
            // Vehicle image - hide on checkout and cart pages
            if (!$hide_image) {
                $vehicle_image = get_the_post_thumbnail_url($vehicle_id, 'thumbnail');
                if ($vehicle_image) {
                    $item_data[] = [
                        'key' => __('Vehicle Image', 'mhm-rentiva'),
                        'value' => '<img src="' . esc_url($vehicle_image) . '" alt="' . esc_attr(get_the_title($vehicle_id)) . '" class="mhm-vehicle-thumbnail" style="max-width: 80px; height: auto; border-radius: 4px;">'
                    ];
                }
            }

            $item_data[] = [
                'key' => __('Vehicle', 'mhm-rentiva'),
                'value' => get_the_title($vehicle_id)
            ];

            // Pickup date and time
            $pickup_time = $booking_data['pickup_time'] ?? '';
            $pickup_display = $booking_data['pickup_date'];
            if ($pickup_time) {
                $pickup_display .= ' ' . $pickup_time;
            }

            // Dropoff date and time
            $dropoff_time = $booking_data['dropoff_time'] ?? '';
            $dropoff_display = $booking_data['dropoff_date'];
            if ($dropoff_time) {
                $dropoff_display .= ' ' . $dropoff_time;
            }

            $item_data[] = [
                'key' => __('Pickup Date & Time', 'mhm-rentiva'),
                'value' => $pickup_display
            ];

            $item_data[] = [
                'key' => __('Return Date & Time', 'mhm-rentiva'),
                'value' => $dropoff_display
            ];

            // Add Payment Type info
            $payment_type = $booking_data['payment_type'] ?? 'deposit';
            $type_label = $payment_type === 'deposit' ? __('Deposit Payment', 'mhm-rentiva') : __('Full Payment', 'mhm-rentiva');

            $item_data[] = [
                'key' => __('Payment Type', 'mhm-rentiva'),
                'value' => $type_label
            ];

            // Add remaining amount for deposit payments
            if ($payment_type === 'deposit') {
                $remaining_amount = (float) ($booking_data['remaining_amount'] ?? 0);
                if ($remaining_amount > 0) {
                    $item_data[] = [
                        'key' => __('Remaining Amount', 'mhm-rentiva'),
                        'value' => wc_price($remaining_amount)
                    ];
                }
            }
        }
        return $item_data;
    }

    /**
     * Override price in cart
     * ⭐ IMPORTANT: Set price to deposit amount for payment, but tax should be calculated on total price
     */
    public static function calculate_totals($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['mhm_booking_price'])) {
                // Set cart item price to deposit amount (for payment)
                $cart_item['data']->set_price($cart_item['mhm_booking_price']);

                // ⭐ Store total price in cart item data for tax calculation
                if (isset($cart_item['mhm_booking_data'])) {
                    $booking_data = $cart_item['mhm_booking_data'];
                    $total_price = (float) ($booking_data['total_price'] ?? 0);
                    if ($total_price > 0) {
                        // Store total price for tax calculation
                        $cart->cart_contents[$cart_item_key]['mhm_booking_total_price'] = $total_price;
                    }
                } elseif (isset($cart_item['mhm_booking_id'])) {
                    $booking_id = $cart_item['mhm_booking_id'];
                    $total_price = (float) get_post_meta($booking_id, '_mhm_total_price', true);
                    if ($total_price > 0) {
                        $cart->cart_contents[$cart_item_key]['mhm_booking_total_price'] = $total_price;
                    }
                }
            }
        }
    }

    /**
     * ⭐ Display vehicle image in cart/checkout
     * Note: Let WooCommerce show default product image, we just override with vehicle image when available
     */
    public static function display_vehicle_image($image, $cart_item, $cart_item_key)
    {
        $normalized = self::get_normalized_booking_data($cart_item);
        $vehicle_id = $normalized['vehicle_id'];

        if ($vehicle_id) {
            $vehicle_image = get_the_post_thumbnail_url($vehicle_id, 'woocommerce_thumbnail');
            if ($vehicle_image) {
                return '<img src="' . esc_url($vehicle_image) . '" alt="' . esc_attr(get_the_title($vehicle_id)) . '" class="mhm-vehicle-thumbnail" style="max-width: 80px; height: auto; border-radius: 4px;">';
            }
        }

        return $image;
    }

    /**
     * ⭐ Adjust tax calculation - tax should be calculated on total price, not deposit
     * Override WooCommerce tax calculation for booking items
     */
    public static function adjust_tax_calculation($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!wc_tax_enabled()) {
            return;
        }

        // Get tax rates
        $tax_rates = \WC_Tax::get_rates();
        if (empty($tax_rates)) {
            return;
        }

        $tax_inclusive = wc_prices_include_tax();
        $first_rate = reset($tax_rates);
        $rate = (float) ($first_rate['rate'] ?? 0);

        if ($rate <= 0) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['mhm_booking_total_price']) && isset($cart_item['mhm_booking_price'])) {
                $total_price = (float) $cart_item['mhm_booking_total_price'];
                $deposit_price = (float) $cart_item['mhm_booking_price'];

                // Only adjust if deposit is less than total (deposit payment)
                if ($total_price > $deposit_price && $total_price > 0) {
                    // Calculate tax on total price
                    if ($tax_inclusive) {
                        // Tax is included in price - extract it
                        $tax_amount_on_total = ($total_price * $rate) / (100 + $rate);
                    } else {
                        // Tax is added to price
                        $tax_amount_on_total = ($total_price * $rate) / 100;
                    }

                    // Calculate proportional tax on deposit
                    $tax_proportion = $deposit_price / $total_price;
                    $tax_on_deposit = $tax_amount_on_total * $tax_proportion;

                    // Store calculated tax for later use
                    $cart->cart_contents[$cart_item_key]['mhm_calculated_tax'] = $tax_on_deposit;
                    $cart->cart_contents[$cart_item_key]['mhm_total_tax'] = $tax_amount_on_total;
                }
            }
        }
    }

    /**
     * ⭐ Override cart item taxes - use calculated tax from total price
     */
    public static function adjust_cart_item_taxes($taxes, $cart_item)
    {
        if (isset($cart_item['mhm_calculated_tax'])) {
            // Get tax rates to find rate ID
            $tax_rates = \WC_Tax::get_rates();
            if (!empty($tax_rates)) {
                $first_rate = reset($tax_rates);
                $rate_id = $first_rate['id'] ?? 1;

                // Override tax with calculated tax from total price
                $taxes = [];
                $taxes[$rate_id] = (float) $cart_item['mhm_calculated_tax'];
            }
        }

        return $taxes;
    }

    /**
     * ⭐ Adjust cart item price display (show deposit but calculate tax on total)
     */
    public static function adjust_cart_item_price_display($price, $cart_item, $cart_item_key)
    {
        // Price display is already correct (shows deposit)
        return $price;
    }

    /**
     * ⭐ Adjust cart item subtotal display
     */
    public static function adjust_cart_item_subtotal_display($subtotal, $cart_item, $cart_item_key)
    {
        // Subtotal display is already correct (shows deposit)
        return $subtotal;
    }

    /**
     * Save booking ID or booking data to order item meta
     */
    public static function add_order_item_meta($item, $cart_item_key, $values, $order)
    {
        error_log('MHM Rentiva: add_order_item_meta called for order ID: ' . $order->get_id());
        error_log('MHM Rentiva: Cart item values keys: ' . implode(', ', array_keys($values)));

        // Handle existing booking (legacy)
        if (isset($values['mhm_booking_id'])) {
            error_log('MHM Rentiva: Legacy booking ID found: ' . $values['mhm_booking_id']);
            $item->add_meta_data('_mhm_booking_id', $values['mhm_booking_id']);

            // Also link order to booking
            update_post_meta($values['mhm_booking_id'], '_mhm_wc_order_id', $order->get_id());
        }
        // ⭐ Handle pending booking (from cart data)
        elseif (isset($values['mhm_booking_data'])) {
            error_log('MHM Rentiva: Pending booking data found, saving to order item meta');
            $item->add_meta_data('_mhm_booking_data', $values['mhm_booking_data']);
            $item->add_meta_data('_mhm_booking_pending', $values['mhm_booking_pending'] ?? true);
            $item->add_meta_data('_mhm_booking_price', $values['mhm_booking_price'] ?? 0);

            // ⭐ ADD VISIBLE META DATA for Order Details Table
            $booking_data = $values['mhm_booking_data'];

            // 1. Vehicle Name
            if (!empty($booking_data['vehicle_id'])) {
                $item->add_meta_data(__('Vehicle', 'mhm-rentiva'), get_the_title($booking_data['vehicle_id']));
            }

            // 2. Dates
            if (!empty($booking_data['pickup_date'])) {
                $pickup = $booking_data['pickup_date'] . ' ' . ($booking_data['pickup_time'] ?? '');
                $dropoff = $booking_data['dropoff_date'] . ' ' . ($booking_data['dropoff_time'] ?? '');

                $item->add_meta_data(__('Pickup', 'mhm-rentiva'), trim($pickup));
                $item->add_meta_data(__('Dropoff', 'mhm-rentiva'), trim($dropoff));
            }

            // 3. Payment Type
            $payment_type_label = ($booking_data['payment_type'] ?? 'full') === 'deposit'
                ? __('Deposit Payment', 'mhm-rentiva')
                : __('Full Payment', 'mhm-rentiva');
            $item->add_meta_data(__('Payment Type', 'mhm-rentiva'), $payment_type_label);
        } else {
            error_log('MHM Rentiva: WARNING - No booking data found in cart item values');
        }
    }

    /**
     * Create booking from order when checkout is processed
     * This ensures booking is only created after order is placed (not before payment)
     */
    public static function create_booking_from_order($order_id, $data = null, $order = null)
    {
        if (!class_exists('WooCommerce')) {
            error_log('MHM Rentiva: WooCommerce not available in create_booking_from_order');
            return;
        }

        // Get order object if not provided
        if (!$order) {
            $order = \wc_get_order($order_id);
        }

        if (!$order) {
            error_log('MHM Rentiva: Could not get order object for order ID: ' . $order_id);
            return;
        }

        // Check if booking already created (prevent duplicate creation)
        $existing_booking_id = $order->get_meta('_mhm_booking_id', true);
        if ($existing_booking_id) {
            error_log('MHM Rentiva: Booking already exists for order ID: ' . $order_id . ', booking ID: ' . $existing_booking_id);
            return;
        }

        error_log('MHM Rentiva: create_booking_from_order called for order ID: ' . $order_id);

        $items = $order->get_items();

        if (empty($items)) {
            error_log('MHM Rentiva: No items found in order ' . $order_id);
            return;
        }

        foreach ($items as $item) {
            // Check if this is a pending booking (not yet created)
            // ⭐ WooCommerce stores meta as serialized, so we need to get it properly
            $booking_data = $item->get_meta('_mhm_booking_data', true);
            $is_pending = $item->get_meta('_mhm_booking_pending', true);

            // If booking_data is a string (serialized), unserialize it
            if (is_string($booking_data) && !empty($booking_data)) {
                $unserialized = maybe_unserialize($booking_data);
                if (is_array($unserialized)) {
                    $booking_data = $unserialized;
                }
            }

            error_log('MHM Rentiva: Order item ID ' . $item->get_id() . ' - booking_data: ' . (empty($booking_data) ? 'EMPTY' : (is_array($booking_data) ? 'ARRAY' : gettype($booking_data))) . ', is_pending: ' . ($is_pending ? 'true' : 'false'));

            if ($booking_data && is_array($booking_data) && $is_pending) {
                error_log('MHM Rentiva: Creating booking from order data for order ID: ' . $order_id);
                // Create booking from cart data
                $booking_id = self::create_booking_from_data($booking_data, $order_id);

                if ($booking_id) {
                    // Update order item meta to link booking
                    $item->update_meta_data('_mhm_booking_id', $booking_id);
                    $item->update_meta_data('_mhm_booking_pending', false);
                    $item->save();

                    // Update order meta
                    $order->update_meta_data('_mhm_booking_id', $booking_id);
                    $order->save();

                    // Clear availability cache
                    if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
                        \MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($booking_data['vehicle_id']);
                    }

                    // Trigger booking created action (with booking_data for addons)
                    // Note: AddonManager::save_booking_addons expects 2 parameters: booking_id and booking_data
                    do_action('mhm_rentiva_booking_created', $booking_id, $booking_data);
                }
            }
        }

        error_log('MHM Rentiva: create_booking_from_order completed for order ID: ' . $order_id);
    }

    /**
     * Fallback hook handler for woocommerce_thankyou (after payment)
     * Only creates booking if it wasn't created by the primary hook
     */
    public static function create_booking_from_order_fallback($order_id): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $order = \wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if booking already exists (prevent duplicate creation)
        $existing_booking_id = $order->get_meta('_mhm_booking_id', true);
        if ($existing_booking_id) {
            return; // Already created by primary hook
        }

        // Only attempt to create if order is paid/processing (fallback safety check)
        if (!$order->is_paid() && $order->get_status() !== 'processing') {
            return;
        }

        error_log('MHM Rentiva: Fallback hook triggered for order ID: ' . $order_id);
        self::create_booking_from_order($order_id);
    }

    /**
     * Create booking from booking data array
     */
    private static function create_booking_from_data(array $booking_data, int $order_id): ?int
    {
        // Parse dates to timestamps
        $start_ts = self::parse_datetime_to_timestamp($booking_data['pickup_date'], $booking_data['pickup_time']);
        $end_ts = self::parse_datetime_to_timestamp($booking_data['dropoff_date'], $booking_data['dropoff_time']);

        if (!$start_ts || !$end_ts) {
            error_log('MHM Rentiva: Invalid dates in booking data for order ID: ' . $order_id);
            return null;
        }

        // ⭐ CRITICAL: Final atomic overlap check before creating booking
        // Clear cache first to ensure fresh data
        if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
            \MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($booking_data['vehicle_id']);
        }

        // Use locked overlap check to prevent concurrent bookings
        if (\MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($booking_data['vehicle_id'], $start_ts, $end_ts)) {
            error_log('MHM Rentiva: Cannot create booking - vehicle already booked for selected dates. Order ID: ' . $order_id . ', Vehicle ID: ' . $booking_data['vehicle_id']);
            // ⚠️ Cancel the WooCommerce order if booking cannot be created
            $order = \wc_get_order($order_id);
            if ($order) {
                $order->update_status('cancelled', __('Booking cancelled: Vehicle already booked for selected dates.', 'mhm-rentiva'));
            }
            return null;
        }

        // Create booking post
        $post_data = [
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title' => sprintf(__('Booking - %s', 'mhm-rentiva'), get_the_title($booking_data['vehicle_id'])),
            'meta_input' => [
                '_mhm_vehicle_id' => $booking_data['vehicle_id'],
                // ⭐ Standard meta keys (for queries and compatibility)
                '_mhm_start_date' => $booking_data['pickup_date'],
                '_mhm_end_date' => $booking_data['dropoff_date'],
                '_mhm_start_ts' => $start_ts,
                '_mhm_end_ts' => $end_ts,
                '_mhm_start_time' => $booking_data['pickup_time'],
                '_mhm_end_time' => $booking_data['dropoff_time'],
                // ⭐ User-friendly meta keys (pickup/dropoff for clarity)
                '_mhm_pickup_date' => $booking_data['pickup_date'],
                '_mhm_dropoff_date' => $booking_data['dropoff_date'],
                '_mhm_pickup_time' => $booking_data['pickup_time'],
                '_mhm_dropoff_time' => $booking_data['dropoff_time'],
                '_mhm_guests' => $booking_data['guests'],
                '_mhm_customer_user_id' => $booking_data['customer_user_id'],
                '_mhm_customer_name' => $booking_data['customer_name'],
                '_mhm_customer_first_name' => $booking_data['customer_first_name'],
                '_mhm_customer_last_name' => $booking_data['customer_last_name'],
                '_mhm_customer_email' => $booking_data['customer_email'],
                '_mhm_customer_phone' => $booking_data['customer_phone'],
                '_mhm_status' => 'pending',
                '_mhm_booking_type' => 'booking_form',
                '_mhm_created_via' => 'woocommerce_checkout',
                '_mhm_woocommerce_order_id' => $order_id,
                '_mhm_payment_type' => $booking_data['payment_type'],
                '_mhm_payment_method' => $booking_data['payment_method'],
                '_mhm_payment_gateway' => $booking_data['payment_gateway'],
                '_mhm_payment_status' => 'pending',
                '_mhm_deposit_amount' => $booking_data['deposit_amount'],
                '_mhm_remaining_amount' => $booking_data['remaining_amount'],
                '_mhm_deposit_type' => $booking_data['deposit_type'],
                '_mhm_payment_display' => $booking_data['payment_display'],
                '_mhm_total_price' => $booking_data['total_price'],
                '_mhm_rental_days' => $booking_data['rental_days'],
                '_mhm_selected_addons' => $booking_data['selected_addons'],
                '_mhm_cancellation_policy' => $booking_data['cancellation_policy'] ?? self::get_default_cancellation_policy(),
                '_mhm_cancellation_deadline' => $booking_data['cancellation_deadline'] ?? date('Y-m-d H:i:s', strtotime('+24 hours')),
                // ⭐ Ensure payment_deadline is always set for auto-cancellation
                '_mhm_payment_deadline' => !empty($booking_data['payment_deadline']) ? $booking_data['payment_deadline'] : self::get_payment_deadline(),
            ]
        ];

        $booking_id = wp_insert_post($post_data);

        if (is_wp_error($booking_id)) {
            error_log('MHM Rentiva: Failed to create booking from order - ' . $booking_id->get_error_message());
            return null;
        }

        // ⭐ Ensure payment_deadline is set (meta_input may not always work)
        // Double-check and set payment_deadline if missing
        $payment_deadline = get_post_meta($booking_id, '_mhm_payment_deadline', true);
        if (empty($payment_deadline)) {
            $deadline = self::get_payment_deadline();
            update_post_meta($booking_id, '_mhm_payment_deadline', $deadline);
            error_log("MHM Rentiva: Payment deadline was missing for booking #$booking_id, set to: $deadline");
        }

        // Add booking history note
        if (class_exists('MHMRentiva\Admin\Booking\Meta\BookingMeta')) {
            \MHMRentiva\Admin\Booking\Meta\BookingMeta::add_history_note(
                $booking_id,
                __('Booking created from WooCommerce order', 'mhm-rentiva'),
                'system'
            );
        }

        return $booking_id;
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
                        // Order refunded - update status only (amount handled by handle_order_refunded)
                        Status::update_status($booking_id, 'refunded', get_current_user_id());
                        // ⭐ Also update payment status
                        update_post_meta($booking_id, '_mhm_payment_status', 'refunded');
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
        if (isset($cart_item['mhm_booking_id']) || isset($cart_item['mhm_booking_data'])) {
            return sprintf('%s <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item['quantity'], $cart_item_key);
        }
        return $product_quantity;
    }

    /**
     * Check if cart contains booking items
     */
    private static function cart_has_booking(): bool
    {
        if (!function_exists('WC') || !\WC()->cart) {
            return false;
        }

        foreach (\WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['mhm_booking_id']) || isset($cart_item['mhm_booking_data'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get booking ID from cart
     */
    private static function get_booking_id_from_cart(): ?int
    {
        if (!function_exists('WC') || !\WC()->cart) {
            return null;
        }

        foreach (\WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['mhm_booking_id'])) {
                return (int) $cart_item['mhm_booking_id'];
            }
        }
        return null;
    }

    /**
     * Get booking data from cart (for pending bookings)
     */
    private static function get_booking_data_from_cart(): ?array
    {
        if (!function_exists('WC') || !\WC()->cart) {
            return null;
        }

        foreach (\WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['mhm_booking_data']) && isset($cart_item['mhm_booking_pending']) && $cart_item['mhm_booking_pending']) {
                return $cart_item['mhm_booking_data'];
            }
        }
        return null;
    }

    /**
     * Add payment type field to checkout (using custom display method)
     */
    public static function add_checkout_payment_type_field($fields)
    {
        // We'll use custom display method instead of adding to standard fields
        return $fields;
    }

    /**
     * Display payment type field in checkout
     */
    public static function display_payment_type_field()
    {
        if (!self::cart_has_booking()) {
            return;
        }

        // Initialize sums
        $total_full_price = 0.0;
        $total_deposit_amount = 0.0;
        $total_remaining_amount = 0.0;

        $current_payment_type = 'deposit'; // Default
        $has_booking_items = false;

        // Loop through ALL cart items to calculate totals
        foreach (\WC()->cart->get_cart() as $cart_item) {
            $booking_id = isset($cart_item['mhm_booking_id']) ? (int) $cart_item['mhm_booking_id'] : 0;
            $booking_data = $cart_item['mhm_booking_data'] ?? null;

            // Skip non-booking items
            if (!$booking_id && !$booking_data) {
                continue;
            }

            $has_booking_items = true;

            $item_total_price = 0.0;
            $item_deposit_amount = 0.0;
            $item_remaining = 0.0;
            $item_payment_type = 'deposit';

            if ($booking_id) {
                // Existing booking
                $item_payment_type = get_post_meta($booking_id, '_mhm_payment_type', true) ?: 'deposit';
                $item_total_price = (float) get_post_meta($booking_id, '_mhm_total_price', true);
                $item_deposit_amount = (float) get_post_meta($booking_id, '_mhm_deposit_amount', true);
                $item_remaining = (float) get_post_meta($booking_id, '_mhm_remaining_amount', true);
            } elseif ($booking_data) {
                // Pending booking
                $item_payment_type = $booking_data['payment_type'] ?? 'deposit';
                $item_total_price = (float) ($booking_data['total_price'] ?? 0);
                $item_deposit_amount = (float) ($booking_data['deposit_amount'] ?? 0);
                $item_remaining = (float) ($booking_data['remaining_amount'] ?? 0);
            }

            // Accumulate Full Payment Total
            $total_full_price += $item_total_price;

            // Accumulate Deposit Payment Total
            // Logic: If user selects "Deposit", they pay deposit amount for this item.
            // If item has no deposit option (deposit = full), they pay full amount.
            // Ensure we handle items where deposit_amount might be 0 or same as total correctly
            if ($item_deposit_amount > 0) {
                $total_deposit_amount += $item_deposit_amount;
            } else {
                // Determine if this item supports deposit at all.
                // If deposit_amount is 0 or not set, maybe it's full payment only.
                // Safest to assume if deposit is 0/empty, "deposit payment" means full price for this item.
                // However, for Rentiva logic, usually deposit_amount is set even if equal to total.
                $total_deposit_amount += $item_total_price;
            }

            // Accumulate Remaining Amount (Future Payment)
            // If we are in "Deposit" mode, remaining is total - deposit.
            // If item forces full payment (deposit=total), remaining is 0.
            $calculated_remaining = $item_total_price - ($item_deposit_amount > 0 ? $item_deposit_amount : $item_total_price);
            $total_remaining_amount += max(0, $calculated_remaining);

            // Use the first item's payment type as the "current" type for the UI initially
            // Logic: If any item is 'full', checking if we should show 'full' selected.
            // Usually invalid as the radio button is global. We'll trust the session/first item persistence.
            if ($current_payment_type === 'deposit' && $item_payment_type === 'full') {
                // Keep it as is or logic to detect mixed states?
                // Let's rely on the first item or last updated state.
                // Ideally, we check if ALL are full? No, just pick one to represent state.
                $current_payment_type = $item_payment_type;
            }
        }

        if (!$has_booking_items || $total_full_price <= 0) {
            return;
        }

?>
        <div id="mhm-booking-payment-type" class="mhm-checkout-payment-type">
            <h3>
                <?php echo esc_html__('Payment Options', 'mhm-rentiva'); ?>
            </h3>

            <div class="mhm-payment-type-options">
                <label class="mhm-payment-type-option <?php echo $current_payment_type === 'deposit' ? 'selected' : ''; ?>">
                    <input type="radio"
                        name="mhm_booking_payment_type"
                        value="deposit"
                        <?php checked($current_payment_type, 'deposit'); ?>
                        data-amount="<?php echo esc_attr($total_deposit_amount); ?>">
                    <div class="mhm-payment-type-option-content">
                        <strong class="mhm-payment-type-option-title">
                            <?php echo esc_html__('Deposit Payment', 'mhm-rentiva'); ?>
                        </strong>
                        <small class="mhm-payment-type-option-description">
                            <?php echo esc_html__('Pay deposit for booking, pay remaining amount at vehicle delivery', 'mhm-rentiva'); ?>
                        </small>
                        <div class="mhm-payment-type-option-price">
                            <?php echo wc_price($total_deposit_amount); ?>
                            <?php if ($total_remaining_amount > 0): ?>
                                <span class="mhm-payment-type-option-price-remaining">
                                    (<?php echo esc_html__('Remaining:', 'mhm-rentiva'); ?> <?php echo wc_price($total_remaining_amount); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>

                <label class="mhm-payment-type-option <?php echo $current_payment_type === 'full' ? 'selected' : ''; ?>">
                    <input type="radio"
                        name="mhm_booking_payment_type"
                        value="full"
                        <?php checked($current_payment_type, 'full'); ?>
                        data-amount="<?php echo esc_attr($total_full_price); ?>">
                    <div class="mhm-payment-type-option-content">
                        <strong class="mhm-payment-type-option-title">
                            <?php echo esc_html__('Full Payment', 'mhm-rentiva'); ?>
                        </strong>
                        <small class="mhm-payment-type-option-description">
                            <?php echo esc_html__('Pay full amount now', 'mhm-rentiva'); ?>
                        </small>
                        <div class="mhm-payment-type-option-price">
                            <?php echo wc_price($total_full_price); ?>
                        </div>
                    </div>
                </label>
            </div>
        </div>


        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Function to update selected state visual
                function updateSelectedState() {
                    $('.mhm-payment-type-option').removeClass('selected');
                    $('.mhm-payment-type-option:has(input[type="radio"]:checked)').addClass('selected');
                }

                // Update on page load
                updateSelectedState();

                $('input[name="mhm_booking_payment_type"]').on('change', function() {
                    const paymentType = $(this).val();

                    // Update selected state immediately for better UX
                    updateSelectedState();

                    // Update cart price via AJAX
                    $.ajax({
                        url: wc_checkout_params.ajax_url || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: {
                            action: 'mhm_update_booking_payment_type',
                            payment_type: paymentType,
                            nonce: '<?php echo wp_create_nonce('mhm_booking_payment_type'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Trigger cart update
                                $('body').trigger('update_checkout');
                            } else {
                                console.error('MHM Rentiva: Payment type update failed', response);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('MHM Rentiva: AJAX error', error);
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * Save payment type from checkout
     * ⭐ Supports both existing bookings and pending bookings (cart data)
     */
    public static function save_checkout_payment_type($order_id, $data)
    {
        if (!isset($_POST['mhm_booking_payment_type'])) {
            return;
        }

        $payment_type = sanitize_text_field($_POST['mhm_booking_payment_type']);

        // Always save to order meta
        update_post_meta($order_id, '_mhm_wc_payment_type', $payment_type);
        update_post_meta($order_id, '_mhm_booking_payment_type', $payment_type);

        // ⭐ Handle existing booking (has booking_id)
        $booking_id = self::get_booking_id_from_cart();
        if ($booking_id) {
            update_post_meta($booking_id, '_mhm_payment_type', $payment_type);
        }

        // ⭐ Handle pending booking (cart data) - update cart data before booking creation
        if (!$booking_id && function_exists('WC') && \WC()->cart) {
            foreach (\WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if (isset($cart_item['mhm_booking_data']) && isset($cart_item['mhm_booking_pending']) && $cart_item['mhm_booking_pending']) {
                    // Update payment type in cart data (will be used when creating booking)
                    $booking_data = $cart_item['mhm_booking_data'];
                    $booking_data['payment_type'] = $payment_type;
                    \WC()->cart->cart_contents[$cart_item_key]['mhm_booking_data'] = $booking_data;
                    break;
                }
            }
        }
    }

    /**
     * AJAX handler to update payment type and cart price
     * ⭐ Supports both existing bookings and pending bookings (cart data)
     */
    public static function ajax_update_payment_type()
    {
        check_ajax_referer('mhm_booking_payment_type', 'nonce');

        $payment_type = sanitize_text_field($_POST['payment_type'] ?? 'deposit');

        if (!function_exists('WC') || !\WC()->cart) {
            wp_send_json_error(__('Cart not available', 'mhm-rentiva'));
            return;
        }

        $amount_to_pay = 0;
        $cart_updated = false;

        // Iterate through ALL cart items and update any booking items
        foreach (\WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $updated_item_amount = 0;
            $is_booking_item = false;

            // 1. Pending Bookings (Cart Data)
            if (isset($cart_item['mhm_booking_data']) && isset($cart_item['mhm_booking_pending']) && $cart_item['mhm_booking_pending']) {
                $is_booking_item = true;
                $booking_data = $cart_item['mhm_booking_data'];

                // Update payment type
                $booking_data['payment_type'] = $payment_type;

                // Calculate amount
                $total_price = (float) ($booking_data['total_price'] ?? 0);
                $deposit_amount = (float) ($booking_data['deposit_amount'] ?? 0);

                // For deposit payments, check if deposit is > 0. If 0 (full payment forced), take total price.
                $item_amount_to_pay = ($payment_type === 'deposit' && $deposit_amount > 0) ? $deposit_amount : $total_price;

                // Update Cart
                \WC()->cart->cart_contents[$cart_item_key]['mhm_booking_data'] = $booking_data;
                \WC()->cart->cart_contents[$cart_item_key]['mhm_booking_price'] = $item_amount_to_pay;
                \WC()->cart->cart_contents[$cart_item_key]['data']->set_price($item_amount_to_pay);

                $updated_item_amount = $item_amount_to_pay;
                $cart_updated = true;
            }
            // 2. Existing Bookings (Database)
            elseif (isset($cart_item['mhm_booking_id'])) {
                $is_booking_item = true;
                $booking_id = (int) $cart_item['mhm_booking_id'];

                // Update DB
                update_post_meta($booking_id, '_mhm_payment_type', $payment_type);

                // Get totals
                $total_price = (float) get_post_meta($booking_id, '_mhm_total_price', true);
                $deposit_amount = (float) get_post_meta($booking_id, '_mhm_deposit_amount', true);

                $item_amount_to_pay = ($payment_type === 'deposit' && $deposit_amount > 0) ? $deposit_amount : $total_price;

                // Update Cart
                \WC()->cart->cart_contents[$cart_item_key]['mhm_booking_price'] = $item_amount_to_pay;
                \WC()->cart->cart_contents[$cart_item_key]['data']->set_price($item_amount_to_pay);

                $updated_item_amount = $item_amount_to_pay;
                $cart_updated = true;
            }

            if ($is_booking_item) {
                // error_log("MHM Rentiva: Updated cart item $cart_item_key to payment type $payment_type. Amount: $updated_item_amount");
            }
        }

        if (!$cart_updated) {
            wp_send_json_error(__('Booking not found in cart', 'mhm-rentiva'));
            return;
        }

        // Recalculate cart totals
        \WC()->cart->calculate_totals();

        wp_send_json_success([
            'message' => __('Payment type updated', 'mhm-rentiva'),
            'amount' => $amount_to_pay,
            'formatted_amount' => wc_price($amount_to_pay)
        ]);
    }

    /**
     * Get default cancellation policy from settings
     * 
     * @return string Cancellation policy string
     */
    private static function get_default_cancellation_policy(): string
    {
        if (class_exists('MHMRentiva\Admin\Booking\Core\Handler')) {
            return \MHMRentiva\Admin\Booking\Core\Handler::get_cancellation_policy();
        }

        // Fallback to default
        return '24_hours';
    }

    /**
     * Get payment deadline for auto-cancellation
     * 
     * @return string Payment deadline in 'Y-m-d H:i:s' format (WordPress timezone)
     */
    private static function get_payment_deadline(): string
    {
        if (class_exists('MHMRentiva\Admin\Booking\Core\Handler')) {
            return \MHMRentiva\Admin\Booking\Core\Handler::get_payment_deadline();
        }

        // Fallback if Handler not available (should rare)
        // Get payment deadline minutes from settings (default: 30 minutes)
        $deadline_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
            'mhm_rentiva_booking_payment_deadline_minutes',
            30
        );

        // Minimum 5 minutes
        if ($deadline_minutes < 5) {
            $deadline_minutes = 5;
        }

        // ⭐ Use current_time() instead of date() to match WordPress timezone
        // This ensures consistency with AutoCancel which uses current_time('mysql')
        $current_timestamp = current_time('timestamp');
        $deadline_timestamp = $current_timestamp + ($deadline_minutes * 60);
        return date('Y-m-d H:i:s', $deadline_timestamp);
    }

    /**
     * ⭐ Validate cart items availability (runs on cart page)
     * Prevents adding unavailable vehicles to cart
     */
    public static function validate_cart_availability()
    {
        if (!function_exists('WC') || !\WC()->cart) {
            return;
        }

        foreach (\WC()->cart->get_cart() as $cart_item) {
            // Check pending booking (from cart data)
            if (isset($cart_item['mhm_booking_data']) && isset($cart_item['mhm_booking_pending']) && $cart_item['mhm_booking_pending']) {
                $booking_data = $cart_item['mhm_booking_data'];
                $vehicle_id = (int) ($booking_data['vehicle_id'] ?? 0);

                if (!$vehicle_id) {
                    continue;
                }

                // Parse dates to timestamps
                $start_ts = self::parse_datetime_to_timestamp($booking_data['pickup_date'], $booking_data['pickup_time'] ?? '00:00');
                $end_ts = self::parse_datetime_to_timestamp($booking_data['dropoff_date'], $booking_data['dropoff_time'] ?? '23:59');

                if (!$start_ts || !$end_ts) {
                    continue;
                }

                // Note: Cache should NOT be invalidated on read operations - only when booking is created/updated

                // Check for overlap (locked check for real-time availability)
                if (\MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($vehicle_id, $start_ts, $end_ts)) {
                    $vehicle_title = get_the_title($vehicle_id);
                    $pickup_date = date_i18n(get_option('date_format'), $start_ts);
                    $dropoff_date = date_i18n(get_option('date_format'), $end_ts);

                    \wc_add_notice(
                        sprintf(
                            /* translators: 1: Vehicle title, 2: Pickup date, 3: Dropoff date */
                            __('Sorry, the vehicle "%1$s" is no longer available for the selected dates (%2$s - %3$s). Please select different dates or another vehicle.', 'mhm-rentiva'),
                            $vehicle_title,
                            $pickup_date,
                            $dropoff_date
                        ),
                        'error'
                    );

                    // Remove invalid cart item
                    \WC()->cart->remove_cart_item($cart_item['key']);
                }
            }
            // Check existing booking
            elseif (isset($cart_item['mhm_booking_id'])) {
                $booking_id = (int) $cart_item['mhm_booking_id'];
                $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);

                if (!$vehicle_id) {
                    continue;
                }

                // ⭐ Use consistent meta keys - prefer pickup/dropoff for clarity
                $pickup_date = get_post_meta($booking_id, '_mhm_pickup_date', true) ?: get_post_meta($booking_id, '_mhm_start_date', true);
                $pickup_time = get_post_meta($booking_id, '_mhm_pickup_time', true) ?: get_post_meta($booking_id, '_mhm_start_time', true) ?: '00:00';
                $dropoff_date = get_post_meta($booking_id, '_mhm_dropoff_date', true) ?: get_post_meta($booking_id, '_mhm_end_date', true);
                $dropoff_time = get_post_meta($booking_id, '_mhm_dropoff_time', true) ?: get_post_meta($booking_id, '_mhm_end_time', true) ?: '23:59';

                if (!$pickup_date || !$dropoff_date) {
                    continue;
                }

                $start_ts = self::parse_datetime_to_timestamp($pickup_date, $pickup_time);
                $end_ts = self::parse_datetime_to_timestamp($dropoff_date, $dropoff_time);

                if (!$start_ts || !$end_ts) {
                    continue;
                }

                // Note: Cache should NOT be invalidated on read operations - only when booking is created/updated

                // Check for overlap (exclude current booking from check)
                // Note: For existing bookings, we should check if dates have changed
                // For now, we'll do a basic check - if booking status is cancelled, remove from cart
                $booking_status = get_post_meta($booking_id, '_mhm_status', true);
                if ($booking_status === 'cancelled') {
                    $vehicle_title = get_the_title($vehicle_id);
                    \wc_add_notice(
                        sprintf(
                            /* translators: %s: Vehicle title */
                            __('Sorry, booking for vehicle "%s" has been cancelled. Please select another vehicle.', 'mhm-rentiva'),
                            $vehicle_title
                        ),
                        'error'
                    );
                    \WC()->cart->remove_cart_item($cart_item['key']);
                }
            }
        }
    }

    /**
     * ⭐ Validate checkout availability (runs BEFORE payment processing)
     * CRITICAL: This prevents payment from being processed if vehicle is no longer available
     */
    public static function validate_checkout_availability()
    {
        if (!function_exists('WC') || !\WC()->cart) {
            return;
        }

        foreach (\WC()->cart->get_cart() as $cart_item) {
            // Check pending booking (from cart data) - MOST IMPORTANT CHECK
            if (isset($cart_item['mhm_booking_data']) && isset($cart_item['mhm_booking_pending']) && $cart_item['mhm_booking_pending']) {
                $booking_data = $cart_item['mhm_booking_data'];
                $vehicle_id = (int) ($booking_data['vehicle_id'] ?? 0);

                if (!$vehicle_id) {
                    continue;
                }

                // Parse dates to timestamps
                $start_ts = self::parse_datetime_to_timestamp($booking_data['pickup_date'], $booking_data['pickup_time'] ?? '00:00');
                $end_ts = self::parse_datetime_to_timestamp($booking_data['dropoff_date'], $booking_data['dropoff_time'] ?? '23:59');

                if (!$start_ts || !$end_ts) {
                    \wc_add_notice(
                        __('Invalid booking dates. Please try again.', 'mhm-rentiva'),
                        'error'
                    );
                    continue;
                }

                // ⭐ CRITICAL: Note: Cache invalidation should happen when booking is created, not on validation
                // has_overlap_locked will check the database directly for real-time availability

                // Use locked overlap check to prevent concurrent bookings
                if (\MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($vehicle_id, $start_ts, $end_ts)) {
                    $vehicle_title = get_the_title($vehicle_id);
                    $pickup_date = date_i18n(get_option('date_format'), $start_ts);
                    $dropoff_date = date_i18n(get_option('date_format'), $end_ts);

                    // ⭐ This error will STOP checkout process - NO PAYMENT WILL BE PROCESSED
                    \wc_add_notice(
                        sprintf(
                            /* translators: 1: Vehicle title, 2: Pickup date, 3: Dropoff date */
                            __('Sorry, the vehicle "%1$s" is no longer available for the selected dates (%2$s - %3$s). The vehicle may have been booked by another customer. Please select different dates or another vehicle.', 'mhm-rentiva'),
                            $vehicle_title,
                            $pickup_date,
                            $dropoff_date
                        ),
                        'error'
                    );

                    // Log for debugging
                    error_log('MHM Rentiva: Checkout validation failed - vehicle no longer available. Vehicle ID: ' . $vehicle_id . ', Dates: ' . $pickup_date . ' - ' . $dropoff_date);
                }
            }
            // Check existing booking
            elseif (isset($cart_item['mhm_booking_id'])) {
                $booking_id = (int) $cart_item['mhm_booking_id'];
                $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);

                if (!$vehicle_id) {
                    continue;
                }

                // Check if booking was cancelled
                $booking_status = get_post_meta($booking_id, '_mhm_status', true);
                if ($booking_status === 'cancelled') {
                    $vehicle_title = get_the_title($vehicle_id);
                    \wc_add_notice(
                        sprintf(
                            /* translators: %s: Vehicle title */
                            __('Sorry, booking for vehicle "%s" has been cancelled. Please select another vehicle.', 'mhm-rentiva'),
                            $vehicle_title
                        ),
                        'error'
                    );
                }
            }
        }
    }

    /**
     * ⭐ Handle WooCommerce order refund (when refund is actually created)
     * This hook is triggered when a refund is created in WooCommerce
     * 
     * @param int $refund_id Refund ID
     * @param array $args Refund arguments
     */
    public static function handle_order_refunded(int $refund_id, array $args): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $order_id = $args['order_id'] ?? 0;
        if (!$order_id) {
            return;
        }

        $order = \wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get booking ID from order
        $booking_id = self::get_booking_id_from_order($order);
        if (!$booking_id) {
            return;
        }

        // Get refund amount (in order currency, convert to smallest unit)
        $refund_amount = $order->get_total_refunded();
        $currency = $order->get_currency();

        // Convert to smallest currency unit (kurus/cent)
        // WooCommerce stores amounts as floats, we need to convert to smallest unit
        $refund_amount_kurus = (int) round($refund_amount * 100);

        // Get total paid amount
        $total_paid = (float) $order->get_total();
        $total_paid_kurus = (int) round($total_paid * 100);

        // Update booking refund meta
        update_post_meta($booking_id, '_mhm_refunded_amount', $refund_amount_kurus);
        update_post_meta($booking_id, '_mhm_payment_currency', $currency);

        // Determine payment status
        if ($refund_amount_kurus >= $total_paid_kurus) {
            // Full refund
            update_post_meta($booking_id, '_mhm_payment_status', 'refunded');
            Status::update_status($booking_id, 'refunded', get_current_user_id());
        } else {
            // Partial refund
            update_post_meta($booking_id, '_mhm_payment_status', 'partially_refunded');
        }

        // Save refund transaction ID
        $refund = \wc_get_order($refund_id);
        if ($refund) {
            $refund_reason = $refund->get_reason() ?: '';
            add_post_meta($booking_id, '_mhm_refund_txn_id', (string) $refund_id);
            update_post_meta($booking_id, '_mhm_refund_reason', $refund_reason);
        }

        // Send refund notification
        if (class_exists('\MHMRentiva\Admin\Emails\Notifications\RefundNotifications')) {
            try {
                $payment_status = $refund_amount_kurus >= $total_paid_kurus ? 'refunded' : 'partially_refunded';
                $refund_reason = $refund ? $refund->get_reason() : '';
                \MHMRentiva\Admin\Emails\Notifications\RefundNotifications::notify(
                    $booking_id,
                    $refund_amount_kurus,
                    $currency,
                    $payment_status,
                    $refund_reason
                );
            } catch (\Throwable $e) {
                error_log('MHM Rentiva: Refund notification error: ' . $e->getMessage());
            }
        }

        // Add log
        $logs = get_post_meta($booking_id, '_mhm_booking_logs', true) ?: [];
        $logs[] = [
            'action' => 'wc_refund_created',
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id() ?: 0,
            'data' => [
                'order_id' => $order_id,
                'refund_id' => $refund_id,
                'refund_amount' => $refund_amount,
                'currency' => $currency,
            ]
        ];
        update_post_meta($booking_id, '_mhm_booking_logs', $logs);
    }

    /**
     * ⭐ Get booking ID from WooCommerce order
     * 
     * @param \WC_Order $order WooCommerce order object
     * @return int|null Booking ID or null if not found
     */
    private static function get_booking_id_from_order(\WC_Order $order): ?int
    {
        // First check order meta
        $booking_id = (int) $order->get_meta('_mhm_booking_id');
        if ($booking_id > 0) {
            return $booking_id;
        }

        // Check order items
        $items = $order->get_items();
        foreach ($items as $item) {
            $booking_id = (int) $item->get_meta('_mhm_booking_id');
            if ($booking_id > 0) {
                return $booking_id;
            }
        }

        return null;
    }

    // ⭐ PaymentGatewayInterface implementation methods

    /**
     * Check if this payment gateway is available/active
     * 
     * @return bool True if gateway is available
     */
    public function is_available(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Get gateway name/identifier
     * 
     * @return string Gateway identifier
     */
    public function get_gateway_name(): string
    {
        return 'woocommerce';
    }

    /**
     * Get gateway display name
     * 
     * @return string Human-readable gateway name
     */
    public function get_display_name(): string
    {
        return __('WooCommerce', 'mhm-rentiva');
    }

    /**
     * Add booking data to payment system (cart, session, etc.)
     * 
     * @param array<string, mixed> $booking_data Booking data array
     * @param float $amount Amount to charge
     * @return bool True on success, false on failure
     */
    public function add_booking_to_payment(array $booking_data, float $amount): bool
    {
        return self::add_booking_data_to_cart($booking_data, $amount);
    }

    /**
     * Get payment/checkout URL
     * 
     * Implements PaymentGatewayInterface::get_checkout_url()
     * 
     * @return string URL to redirect user for payment
     */
    public function get_checkout_url(): string
    {
        if (!function_exists('wc_get_checkout_url')) {
            return '';
        }
        return wc_get_checkout_url();
    }

    /**
     * Process payment for a booking
     * 
     * @param int $booking_id Booking ID
     * @param float $amount Payment amount
     * @param array<string, mixed> $payment_data Additional payment data
     * @return array<string, mixed> Payment result with 'success', 'message', 'transaction_id', etc.
     */
    public function process_payment(int $booking_id, float $amount, array $payment_data = []): array
    {
        // With WooCommerce, payment is processed by WC itself.
        // We just ensure the order exists and is linked to the booking.
        if ($booking_id <= 0) {
            return [
                'success' => false,
                'message' => __('Invalid booking ID.', 'mhm-rentiva'),
            ];
        }

        // Check if order already exists for this booking
        $order_id = (int) get_post_meta($booking_id, '_mhm_wc_order_id', true);
        if ($order_id > 0) {
            $order = \wc_get_order($order_id);
            if ($order && $order->is_paid()) {
                return [
                    'success' => true,
                    'message' => __('Payment already processed by WooCommerce.', 'mhm-rentiva'),
                    'transaction_id' => (string) $order_id,
                ];
            }
        }

        // If no order exists, add booking to cart and redirect to checkout
        $booking_data = [
            'booking_id' => $booking_id,
            'vehicle_id' => (int) get_post_meta($booking_id, '_mhm_vehicle_id', true),
            'pickup_date' => get_post_meta($booking_id, '_mhm_pickup_date', true),
            'dropoff_date' => get_post_meta($booking_id, '_mhm_dropoff_date', true),
            'payment_type' => get_post_meta($booking_id, '_mhm_payment_type', true) ?: 'deposit',
        ];

        if (self::add_booking_data_to_cart($booking_data, $amount)) {
            return [
                'success' => true,
                'message' => __('Redirecting to checkout...', 'mhm-rentiva'),
                'redirect_url' => $this->get_checkout_url(),
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to add booking to cart.', 'mhm-rentiva'),
        ];
    }

    /**
     * Validate payment data before processing
     * 
     * @param array<string, mixed> $payment_data Payment data to validate
     * @return array<string, mixed> Validation result with 'valid' (bool) and 'errors' (array)
     */
    public function validate_payment_data(array $payment_data): array
    {
        $errors = [];

        // Check if WooCommerce is available
        if (!class_exists('WooCommerce')) {
            $errors[] = __('WooCommerce is not installed or activated.', 'mhm-rentiva');
        }

        // Validate booking data if present
        if (isset($payment_data['booking_id'])) {
            $booking_id = (int) $payment_data['booking_id'];
            if ($booking_id <= 0 || !get_post($booking_id)) {
                $errors[] = __('Invalid booking ID.', 'mhm-rentiva');
            }
        }

        // Validate amount
        if (isset($payment_data['amount'])) {
            $amount = (float) $payment_data['amount'];
            if ($amount <= 0) {
                $errors[] = __('Invalid payment amount.', 'mhm-rentiva');
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
