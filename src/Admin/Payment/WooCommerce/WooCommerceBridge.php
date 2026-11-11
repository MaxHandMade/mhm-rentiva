<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

if (! defined('ABSPATH')) {
	exit;
}






use MHMRentiva\Admin\Booking\Core\PaymentView;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentGatewayInterface;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;



/**
 * WooCommerce Bridge Class
 *
 * Handles all interactions between MHM Rentiva and WooCommerce.
 * Implements PaymentGatewayInterface for loose coupling.
 */
final class WooCommerceBridge implements PaymentGatewayInterface {




	public const PRODUCT_SKU = 'mhm-rentiva-booking';

	public static function register(): void
	{
		// Cart item data
		add_filter('woocommerce_get_cart_item_from_session', array( self::class, 'get_cart_item_from_session' ), 10, 2);
		add_filter('woocommerce_get_item_data', array( self::class, 'get_item_data' ), 10, 2);
		add_action('woocommerce_before_calculate_totals', array( self::class, 'calculate_totals' ), 10, 1);

		// ⭐ Fix tax calculation - tax should be calculated on total price, not deposit
		add_filter('woocommerce_cart_item_price', array( self::class, 'adjust_cart_item_price_display' ), 10, 3);
		add_filter('woocommerce_cart_item_subtotal', array( self::class, 'adjust_cart_item_subtotal_display' ), 10, 3);
		add_filter('woocommerce_cart_item_thumbnail', array( self::class, 'set_universal_vehicle_thumbnail' ), 999, 3);
		// Fallback for WooCommerce Blocks - modify product image directly
		add_filter('woocommerce_product_get_image', array( self::class, 'filter_product_image_for_cart' ), 10, 5);
		// ⭐ WooCommerce Cart Block (Store API) - inject vehicle images
		add_filter('woocommerce_store_api_cart_item_images', array( self::class, 'inject_vehicle_image_for_blocks' ), 10, 3);

		// Email order item image — replace placeholder with vehicle image
		add_filter('woocommerce_order_item_thumbnail', array( self::class, 'filter_email_order_item_thumbnail' ), 10, 2);

		// Order processing
		add_action('woocommerce_checkout_create_order_line_item', array( self::class, 'add_order_item_meta' ), 10, 4);
		// The snapshot array would lose every backslash on its way into the row.
		add_action('added_order_item_meta', array( self::class, 'keep_booking_snapshot_backslashes' ), 10, 4);
		// ⭐ Create booking when order is processed - use primary hook with fallback
		add_action('woocommerce_checkout_order_processed', array( self::class, 'create_booking_from_order' ), 10, 3);
		// Safety net: a rental order that still reaches the Store API gets its booking.
		add_action('woocommerce_store_api_checkout_order_processed', array( self::class, 'store_api_order_processed' ), 10, 1);
		add_action('woocommerce_store_api_cart_errors', array( self::class, 'store_api_cart_errors' ), 10, 2);
		// Above WooCommerce's own draft-status filter (999).
		add_filter('woocommerce_valid_order_statuses_for_payment', array( self::class, 'refuse_payment_without_booking' ), 1000, 2);
		// Classic order-pay form: before the gateway, a booking the order pays
		// for must still hold its dates.
		add_action('woocommerce_before_pay_action', array( self::class, 'claim_before_pay' ), 10, 1);
		// No cash on delivery for a rental: the classic checkout, its validation,
		// the order-pay form and the Store API all read this list.
		add_filter('woocommerce_available_payment_gateways', array( self::class, 'without_cash_on_delivery' ), 10, 1);
		// The list is only what is shown; these refuse on every classic entry.
		add_action('woocommerce_after_checkout_validation', array( self::class, 'refuse_cash_on_delivery_at_checkout' ), 20, 2);
		add_action('woocommerce_before_pay_action', array( self::class, 'refuse_cash_on_delivery_on_pay' ), 5, 1);
		// A refused classic order-pay keeps no COD method (WooCommerce saves it
		// before it counts the notices).
		add_action('woocommerce_after_pay_action', array( self::class, 'forget_refused_cash_on_delivery' ), 10, 1);
		add_filter('woocommerce_no_available_payment_methods_message', array( self::class, 'no_payment_methods_message' ), 10, 1);
		// Classic checkout: billing field labels in the error notices.
		add_action('woocommerce_checkout_process', array( self::class, 'open_field_label_window' ), 5, 0);
		add_action('woocommerce_after_checkout_validation', array( self::class, 'close_field_label_window' ), 10, 0);
		// Fallback hook if primary fails (thankyou page load)
		add_action('woocommerce_thankyou', array( self::class, 'create_booking_from_order_fallback' ), 5, 1);
		add_action('woocommerce_order_status_changed', array( self::class, 'handle_order_status_change' ), 10, 4);
		add_action('mhmrentiva_booking_status_changed', array( self::class, 'sync_completed_to_wc' ), 10, 3);

		// ⭐ WooCommerce refund hook - handles actual refund amounts
		add_action('woocommerce_refund_created', array( self::class, 'handle_order_refunded' ), 10, 2);

		// Hide quantity input for booking items
		add_filter('woocommerce_cart_item_quantity', array( self::class, 'disable_cart_quantity' ), 10, 3);

		// Checkout custom fields
		add_filter('woocommerce_checkout_fields', array( self::class, 'reorder_checkout_fields' ), 20, 1);
		add_filter('woocommerce_get_country_locale', array( self::class, 'booking_address_locale' ), 20, 1);
		add_filter('woocommerce_get_country_locale_default', array( self::class, 'booking_address_locale' ), 20, 1);
		add_filter('woocommerce_checkout_fields', array( self::class, 'add_checkout_payment_type_field' ), 10, 1);
		add_action('woocommerce_checkout_update_order_meta', array( self::class, 'save_checkout_payment_type' ), 10, 2);
		add_action('woocommerce_review_order_before_payment', array( self::class, 'display_payment_type_field' ), 10);

		// Enqueue checkout CSS
		add_action('wp_enqueue_scripts', array( self::class, 'enqueue_checkout_styles' ), 20);

		// ⭐ Validate availability before checkout (PREVENT PAYMENT)
		add_action('woocommerce_check_cart_items', array( self::class, 'validate_cart_availability' ), 10);
		add_action('woocommerce_checkout_process', array( self::class, 'validate_checkout_availability' ), 10);

		// AJAX handlers for payment type change
		add_action('wp_ajax_mhmrentiva_update_booking_payment_type', array( self::class, 'ajax_update_payment_type' ));
		add_action('wp_ajax_nopriv_mhmrentiva_update_booking_payment_type', array( self::class, 'ajax_update_payment_type' ));

		// ⭐ Add "Return to Cart" link at checkout
		add_action('woocommerce_checkout_before_order_review', array( self::class, 'add_return_to_cart_link' ), 10);
		add_action('woocommerce_checkout_order_review', array( self::class, 'add_return_to_cart_link_in_review' ), 5);

		// ⭐ Inject Custom VAT Row
		add_action('woocommerce_review_order_before_order_total', array( self::class, 'add_custom_tax_row_checkout' ));

		// ⭐ Clean Total HTML (Remove tax suffix)
		add_filter('woocommerce_cart_totals_order_total_html', array( self::class, 'clean_checkout_total_html' ), 999);

		// Journey step bar and Rentiva summary on the checkout and thank-you pages.
		CheckoutJourney::register();
	}

	/**
	 * Enqueue checkout styles
	 */
	public static function enqueue_checkout_styles(): void
	{
		// The journey's styles and rvs-checkout.css go in the head, before the
		// hooks that draw. The Cart and Mini-Cart blocks' image script is
		// enqueued as they render (RentalCheckoutLook::with_block_image_injector()).
		CheckoutJourney::enqueue_assets();
	}

	/**
	 * Get vehicle images for cart items
	 *
	 * @return array Vehicle images keyed by cart item key
	 */
	public static function cart_vehicle_images(): array
	{
		$images = array();

		if (! function_exists('WC') || ! WC()->cart) {
			return $images;
		}

		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$normalized = self::get_normalized_booking_data($cart_item);
			$vehicle_id = $normalized['vehicle_id'];

			// Fallback: If no vehicle_id but we have booking_id, fetch from booking meta
			if (! $vehicle_id && $normalized['booking_id']) {
				$vehicle_id = (int) get_post_meta($normalized['booking_id'], '_mhmrentiva_vehicle_id', true);
			}

			if ($vehicle_id) {
				$thumbnail_id = get_post_thumbnail_id($vehicle_id);
				if ($thumbnail_id) {
					$thumbnail_src = wp_get_attachment_image_src($thumbnail_id, 'woocommerce_thumbnail');
					if ($thumbnail_src) {
						$images[ $cart_item_key ] = array(
							'url'    => $thumbnail_src[0],
							'srcset' => wp_get_attachment_image_srcset($thumbnail_id, 'woocommerce_thumbnail') ?: '',
							'alt'    => get_the_title($vehicle_id),
						);
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Ensure WooCommerce session and cart are initialized
	 *
	 * @return bool True if cart is available, false otherwise
	 */
	private static function ensure_wc_session(): bool
	{
		if (! class_exists('WooCommerce')) {
			return false;
		}

		// Initialize WooCommerce session and cart if missing (common in admin-post.php)
		if (! isset(\WC()->cart) || null === \WC()->cart) {
			if (function_exists('wc_load_cart')) {
				call_user_func('wc_load_cart');
			} elseif (function_exists('WC') && isset(\WC()->session)) {
				// Fallback manual load
				/** @var mixed $session */
				$session = \WC()->session;
				if (method_exists($session, 'has_session') && ! $session->has_session()) {
					$session->set_customer_session_cookie(true);
				}

				$cart_class = '\WC_Cart';
				if (class_exists($cart_class)) {
					\WC()->cart = new $cart_class();
					// Load cart from session
					if (method_exists(\WC()->cart, 'get_cart')) {
						\WC()->cart->get_cart();
					}
				}
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
		$booking_id   = null;
		$booking_data = null;
		$vehicle_id   = null;

		// Check for existing booking (legacy)
		if (isset($cart_item['mhmrentiva_booking_id'])) {
			$booking_id = (int) $cart_item['mhmrentiva_booking_id'];
			$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		}
		// Check for pending booking (from cart data)
		elseif (isset($cart_item['mhmrentiva_booking_data']) && is_array($cart_item['mhmrentiva_booking_data'])) {
			$booking_data = $cart_item['mhmrentiva_booking_data'];
			$vehicle_id   = (int) ( $booking_data['vehicle_id'] ?? 0 );
		}

		return array(
			'booking_id'   => $booking_id,
			'booking_data' => $booking_data,
			'vehicle_id'   => $vehicle_id,
		);
	}

	/**
	 * Local date and time (site timezone) as the real instant.
	 *
	 * @param string $date Date string (Y-m-d format)
	 * @param string $time Time string (H:i format, optional; empty = 00:00)
	 * @return int|null Timestamp or null on failure
	 */
	private static function parse_datetime_to_timestamp(string $date, string $time = ''): ?int
	{
		$time = trim($time);

		return \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant(trim($date), '' === $time ? '00:00' : $time);
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
		if (! self::ensure_wc_session()) {
			return false;
		}

		$product_id = self::get_booking_product_id();
		if (! $product_id) {
			return false;
		}

		// Empty cart first (optional, depending on business logic)
		// WC()->cart->empty_cart();

		// The add-on rows as priced now, beside the charge fixed now: an add-on
		// re-priced, renamed or deleted before checkout keeps its row.
		if (! isset($booking_data['addon_lines'])) {
			$booking_data['addon_lines'] = self::price_rental_addon_lines($booking_data);
		}

		// Store booking data in cart item (will be used to create booking after payment)
		$cart_item_data = array(
			'mhmrentiva_booking_data'    => $booking_data, // ⭐ Store full booking data instead of booking_id
			'mhmrentiva_booking_price'   => $amount,
			'mhmrentiva_booking_pending' => true, // Flag to indicate booking is not created yet
		);

		try {
			\WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
			return true;
		} catch (\Exception $e) {
			AdvancedLogger::error('Failed to add to cart', array( 'exception' => $e->getMessage() ), AdvancedLogger::CATEGORY_PAYMENT);
			return false;
		}
	}

	/**
	 * Add booking to WooCommerce cart (legacy method - for existing bookings)
	 *
	 * @param int   $booking_id Booking ID
	 * @param float $amount Amount to charge (deposit or full)
	 * @return bool Success
	 */
	public static function add_booking_to_cart(int $booking_id, float $amount): bool
	{
		if (! self::ensure_wc_session()) {
			return false;
		}

		$product_id = self::get_booking_product_id();
		if (! $product_id) {
			return false;
		}

		// Empty cart first (optional, depending on business logic)
		// WC()->cart->empty_cart();

		$cart_item_data = array(
			'mhmrentiva_booking_id'    => $booking_id,
			'mhmrentiva_booking_price' => $amount,
		);

		try {
			\WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
			return true;
		} catch (\Exception $e) {
			AdvancedLogger::error(
				'Failed to add booking to cart',
				array(
					'booking_id' => $booking_id,
					'exception'  => $e->getMessage(),
				),
				AdvancedLogger::CATEGORY_PAYMENT
			);
			return false;
		}
	}

	/**
	 * Get or create the placeholder product for bookings
	 */
	public static function get_booking_product_id(): int
	{
		$product_id = 0;
		if (function_exists('wc_get_product_id_by_sku')) {
			$product_id = call_user_func('wc_get_product_id_by_sku', self::PRODUCT_SKU);
		}

		if ($product_id) {
			return $product_id;
		}

		// Create product if not exists
		$product_class = '\WC_Product_Simple';
		if (class_exists($product_class)) {
			/** @var mixed $product */
			$product = new $product_class();
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

		return 0;
	}

	/**
	 * Restore custom data from session
	 */
	public static function get_cart_item_from_session($cart_item, $values)
	{
		if (isset($values['mhmrentiva_booking_id'])) {
			$cart_item['mhmrentiva_booking_id']    = $values['mhmrentiva_booking_id'];
			$cart_item['mhmrentiva_booking_price'] = $values['mhmrentiva_booking_price'];
		}
		// ⭐ Also restore pending booking data
		if (isset($values['mhmrentiva_booking_data'])) {
			$cart_item['mhmrentiva_booking_data']    = $values['mhmrentiva_booking_data'];
			$cart_item['mhmrentiva_booking_price']   = $values['mhmrentiva_booking_price'];
			$cart_item['mhmrentiva_booking_pending'] = $values['mhmrentiva_booking_pending'] ?? true;
		}

		self::name_booking_line($cart_item);

		return $cart_item;
	}

	/**
	 * Put the booking's own name on its cart line's product object.
	 *
	 * Every booking rides on one hidden product (PRODUCT_SKU), whose stored title
	 * says nothing about what was booked. WooCommerce loads a separate product
	 * object for each cart line (WC_Cart_Session::get_cart_from_session(),
	 * WC_Cart::add_to_cart()), so naming that object changes only this line and
	 * never the stored product. Every surface reads it from there: the block and
	 * mini cart (Store API CartItemSchema, `get_title()`), the classic cart and
	 * checkout (`get_name()`), and the order line saved at checkout
	 * (WC_Checkout::create_order_line_items(), `'name' => $product->get_name()`),
	 * which order-received, My Account, emails and the admin order screen show.
	 *
	 * @param array<string, mixed> $cart_item Cart line with its `data` product.
	 */
	private static function name_booking_line(array $cart_item): void
	{
		if (! isset($cart_item['data']) || ! ( $cart_item['data'] instanceof \WC_Product )) {
			return;
		}
		if (! isset($cart_item['mhmrentiva_booking_data']) && ! isset($cart_item['mhmrentiva_booking_id'])) {
			return;
		}

		$cart_item['data']->set_name(self::booking_line_name($cart_item));
	}

	/**
	 * The name a booking's cart and order line carries.
	 *
	 * A rental is named after its vehicle. Lite cannot name a transfer (its
	 * locations belong to the Pro add-on), so a transfer -- like a line whose
	 * vehicle is gone -- keeps the generic booking name until a filter answers.
	 *
	 * @param array<string, mixed> $cart_item Cart line.
	 */
	public static function booking_line_name(array $cart_item): string
	{
		$normalized   = self::get_normalized_booking_data($cart_item);
		$booking_data = is_array($normalized['booking_data']) ? $normalized['booking_data'] : array();
		$vehicle_id   = (int) $normalized['vehicle_id'];
		$vehicle_name = $vehicle_id > 0 ? (string) get_post_field('post_title', $vehicle_id, 'raw') : '';
		$is_transfer  = 'transfer' === (string) ( $booking_data['booking_type'] ?? '' );

		$name = __('Vehicle Rental Booking', 'mhm-rentiva');
		if (! $is_transfer && '' !== $vehicle_name) {
			/* translators: %s: vehicle name */
			$name = sprintf(__('%s rental', 'mhm-rentiva'), $vehicle_name);
		}

		/**
		 * Filters the name of a booking's cart line, which becomes its order line name at checkout.
		 *
		 * @param string               $name         Default name.
		 * @param array<string, mixed> $booking_data Pending booking data (empty for a line that carries only a booking ID).
		 * @param array<string, mixed> $cart_item    The WooCommerce cart line.
		 */
		$filtered = apply_filters('mhmrentiva_cart_item_name', $name, $booking_data, $cart_item);

		return is_string($filtered) && '' !== $filtered ? $filtered : $name;
	}

	/**
	 * Display booking details in cart
	 */
	public static function get_item_data($item_data, $cart_item)
	{
		$normalized   = self::get_normalized_booking_data($cart_item);
		$booking_id   = $normalized['booking_id'];
		$booking_data = $normalized['booking_data'];
		$vehicle_id   = $normalized['vehicle_id'];

		// Check if we're on checkout or cart page - hide vehicle image on both
		$is_checkout = function_exists('is_checkout') && \is_checkout();
		$is_cart     = function_exists('is_cart') && \is_cart();
		$hide_image  = $is_checkout || $is_cart;

		if ($booking_id) {
			$pickup_date  = get_post_meta($booking_id, '_mhmrentiva_pickup_date', true);
			$dropoff_date = get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true);
			$pickup_time  = get_post_meta($booking_id, '_mhmrentiva_pickup_time', true);
			$dropoff_time = get_post_meta($booking_id, '_mhmrentiva_dropoff_time', true);

			// ⭐ Format Date according to WP Settings
			$wp_date_format    = get_option('date_format');
			$formatted_pickup  = $pickup_date ? date_i18n($wp_date_format, strtotime($pickup_date)) : $pickup_date;
			$formatted_dropoff = $dropoff_date ? date_i18n($wp_date_format, strtotime($dropoff_date)) : $dropoff_date;

			// Product name
			$item_data[] = array(
				'key'   => __('Vehicle', 'mhm-rentiva'),
				'value' => get_the_title($vehicle_id),
			);

			// Pickup date and time
			$pickup_display = $formatted_pickup;
			if ($pickup_time) {
				$pickup_display .= ' ' . $pickup_time;
			}

			// Dropoff date and time
			$dropoff_display = $formatted_dropoff;
			if ($dropoff_time) {
				$dropoff_display .= ' ' . $dropoff_time;
			}

			$item_data[] = array(
				'key'   => esc_html__('Pickup Date & Time', 'mhm-rentiva'),
				'value' => $pickup_display,
			);

			$item_data[] = array(
				'key'   => esc_html__('Return Date & Time', 'mhm-rentiva'),
				'value' => $dropoff_display,
			);

			$item_data[] = array(
				'key'   => esc_html__('Booking ID', 'mhm-rentiva'),
				'value' => '#' . $booking_id,
			);

			// Add Payment Type info
			$payment_type = get_post_meta($booking_id, '_mhmrentiva_payment_type', true);
			$type_label   = $payment_type === 'deposit' ? esc_html__('Deposit Payment', 'mhm-rentiva') : esc_html__('Full Payment', 'mhm-rentiva');

			$item_data[] = array(
				'key'   => esc_html__('Payment Type', 'mhm-rentiva'),
				'value' => $type_label,
			);

			// Add remaining amount for deposit payments
			if ($payment_type === 'deposit') {
				$remaining_amount = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);
				if ($remaining_amount > 0) {
					$item_data[] = array(
						'key'   => esc_html__('Remaining Amount', 'mhm-rentiva'),
						'value' => self::cart_money($remaining_amount),
					);
				}
			}
		}
		// ⭐ Handle pending booking (from cart data)
		elseif ($booking_data && $vehicle_id) {
			$item_data[] = array(
				'key'   => esc_html__('Vehicle', 'mhm-rentiva'),
				'value' => get_the_title($vehicle_id),
			);

			// ⭐ Format Date according to WP Settings
			$wp_date_format   = get_option('date_format');
			$pickup_raw       = $booking_data['pickup_date'] ?? '';
			$formatted_pickup = $pickup_raw ? date_i18n($wp_date_format, strtotime($pickup_raw)) : $pickup_raw;

			// Pickup date and time
			$pickup_time    = $booking_data['pickup_time'] ?? '';
			$pickup_display = $formatted_pickup;
			if ($pickup_time) {
				$pickup_display .= ' ' . $pickup_time;
			}

			// Dropoff date and time
			$dropoff_raw       = $booking_data['dropoff_date'] ?? '';
			$formatted_dropoff = $dropoff_raw ? date_i18n($wp_date_format, strtotime($dropoff_raw)) : $dropoff_raw;

			$dropoff_time    = $booking_data['dropoff_time'] ?? '';
			$dropoff_display = $formatted_dropoff;
			if ($dropoff_time) {
				$dropoff_display .= ' ' . $dropoff_time;
			}

			$item_data[] = array(
				'key'   => esc_html__('Pickup Date & Time', 'mhm-rentiva'),
				'value' => $pickup_display,
			);

			// A transfer has no return: its drop-off is the estimated arrival.
			if (! self::is_transfer_booking($booking_data)) {
				$item_data[] = array(
					'key'   => esc_html__('Return Date & Time', 'mhm-rentiva'),
					'value' => $dropoff_display,
				);
			}

			// One row per add-on with its price -- a rental's; the add-on that
			// prices transfer add-ons lists a transfer's.
			foreach (self::rental_addon_lines($booking_data) as $line) {
				$item_data[] = array(
					'key'   => esc_html($line['title']),
					'value' => self::cart_money( (float) $line['line_total']),
				);
			}

			// Add Payment Type info
			$payment_type = $booking_data['payment_type'] ?? 'deposit';
			$type_label   = $payment_type === 'deposit' ? esc_html__('Deposit Payment', 'mhm-rentiva') : esc_html__('Full Payment', 'mhm-rentiva');

			$item_data[] = array(
				'key'   => esc_html__('Payment Type', 'mhm-rentiva'),
				'value' => $type_label,
			);

			// Add remaining amount for deposit payments
			if ($payment_type === 'deposit') {
				$remaining_amount = (float) ( $booking_data['remaining_amount'] ?? 0 );
				if ($remaining_amount > 0) {
					$item_data[] = array(
						'key'   => __('Remaining Amount', 'mhm-rentiva'),
						'value' => self::cart_money($remaining_amount),
					);
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
		if (is_admin() && ! defined('DOING_AJAX')) {
			return;
		}

		foreach ($cart->get_cart() as $cart_item) {
			if (isset($cart_item['mhmrentiva_booking_price'])) {
				// Set cart item price to deposit amount (for payment)
				$cart_item['data']->set_price($cart_item['mhmrentiva_booking_price']);
				self::name_booking_line($cart_item);
			}
		}
	}

	/**
	 * ⭐ Set universal vehicle thumbnail or placeholder
	 *
	 * @param string $thumbnail The original thumbnail HTML
	 * @param array $cart_item The cart item data
	 * @param string $cart_item_key The cart item key
	 * @return string The modified thumbnail HTML
	 */
	public static function set_universal_vehicle_thumbnail($thumbnail, $cart_item, $cart_item_key): string
	{
		// Only apply to MHM Rentiva products
		$normalized = self::get_normalized_booking_data($cart_item);
		$vehicle_id = $normalized['vehicle_id'];
		$booking_id = $normalized['booking_id'];

		// Fallback: If no vehicle_id but we have booking_id, fetch from booking meta
		if (! $vehicle_id && $booking_id) {
			$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		}

		// If this is not an MHM booking item, return unchanged
		if (! $vehicle_id && ! $booking_id && ! $normalized['booking_data']) {
			return $thumbnail;
		}

		// Priority 1: Try to fetch vehicle image directly
		if ($vehicle_id) {
			$thumbnail_id = get_post_thumbnail_id($vehicle_id);
			if ($thumbnail_id) {
				// Sized by rvs-checkout.css (`.rvs-cart-thumb`), the theme elsewhere.
				return wp_get_attachment_image($thumbnail_id, 'woocommerce_thumbnail', false, array(
					'class' => 'mhm-vehicle-thumbnail rvs-cart-thumb attachment-woocommerce_thumbnail size-woocommerce_thumbnail',
					'alt'   => esc_attr(get_the_title($vehicle_id)),
				));
			}
		}

		// Priority 2: Universal fallback with professional SVG icon
		return '<div class="mhm-vehicle-placeholder rvs-cart-thumb rvs-cart-thumb--empty">
					<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
						<circle cx="7" cy="17" r="2"/>
						<path d="M9 17h6"/>
						<circle cx="17" cy="17" r="2"/>
					</svg>
				</div>';
	}

	/**
	 * The size part of an image's class, as wp_get_attachment_image() builds it.
	 *
	 * WooCommerce's order emails request the product image with an array size
	 * (width, height). Running esc_attr() on that array logged "Array to string
	 * conversion" on every order email and produced the class "attachment-Array".
	 *
	 * @param string|int[] $size Registered size name or [width, height].
	 */
	private static function image_size_class($size): string
	{
		if (is_array($size)) {
			return implode('x', array_map('intval', $size));
		}

		return sanitize_html_class( (string) $size);
	}

	/**
	 * ⭐ Filter product image for cart context (WooCommerce Blocks compatibility)
	 *
	 * @param string $image The product image HTML
	 * @param \WC_Product $product The product object
	 * @param string|int[] $size The image size (WooCommerce emails pass [width, height])
	 * @param array $attr Image attributes
	 * @param bool $placeholder Whether to show placeholder
	 * @return string The filtered image HTML
	 */
	public static function filter_product_image_for_cart($image, $product, $size, $attr, $placeholder)
	{
		// Only apply on cart/checkout pages
		if (! function_exists('is_cart') || ( ! is_cart() && ! is_checkout() )) {
			return $image;
		}

		// Check if this is our booking product
		if (! ( $product instanceof \WC_Product )) {
			return $image;
		}

		// Safely get SKU (using call_user_func to bypass IDE lint)
		$current_sku = '';
		if (\method_exists($product, 'get_sku')) {
			$current_sku = (string) \call_user_func([ $product, 'get_sku' ]);
		}

		if ($current_sku === self::PRODUCT_SKU) {
			// Get vehicle_id from cart session
			$cart = WC()->cart;
			if ($cart) {
				foreach ($cart->get_cart() as $cart_item) {
					if (isset($cart_item['product_id']) && $cart_item['product_id'] === $product->get_id()) {
						$normalized = self::get_normalized_booking_data($cart_item);
						$vehicle_id = $normalized['vehicle_id'];

						if (! $vehicle_id && $normalized['booking_id']) {
							$vehicle_id = (int) get_post_meta($normalized['booking_id'], '_mhmrentiva_vehicle_id', true);
						}

						if ($vehicle_id) {
							$thumbnail_id = get_post_thumbnail_id($vehicle_id);
							if ($thumbnail_id) {
								return wp_get_attachment_image($thumbnail_id, $size, false, array(
									'class' => 'mhm-vehicle-thumbnail rvs-cart-thumb attachment-' . self::image_size_class($size) . ' size-' . self::image_size_class($size),
									'alt'   => esc_attr(get_the_title($vehicle_id)),
								));
							}
						}
						break; // Found our cart item, exit loop
					}
				}
			}
		}

		return $image;
	}

	/**
	 * Replace placeholder product image with vehicle image in WooCommerce emails.
	 *
	 * @param string                $image The order item thumbnail HTML.
	 * @param \WC_Order_Item_Product $item  The order item.
	 * @return string
	 */
	public static function filter_email_order_item_thumbnail( $image, $item ): string {
		if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
			return $image;
		}

		// Get vehicle ID from booking meta stored on the order item.
		$booking_id = (int) $item->get_meta( '_mhmrentiva_booking_id' );
		$vehicle_id = 0;

		if ( $booking_id ) {
			$vehicle_id = (int) get_post_meta( $booking_id, '_mhmrentiva_vehicle_id', true );
		}

		// Fallback: check pending booking data.
		if ( ! $vehicle_id ) {
			$booking_data = $item->get_meta( '_mhmrentiva_booking_data' );
			if ( is_array( $booking_data ) && ! empty( $booking_data['vehicle_id'] ) ) {
				$vehicle_id = (int) $booking_data['vehicle_id'];
			}
		}

		if ( $vehicle_id ) {
			$thumbnail_id = get_post_thumbnail_id( $vehicle_id );
			if ( $thumbnail_id ) {
				return wp_get_attachment_image( $thumbnail_id, 'woocommerce_thumbnail', false, array(
					'style' => 'width: 80px; height: 80px; object-fit: cover; border-radius: 4px;',
					'alt'   => esc_attr( get_the_title( $vehicle_id ) ),
				) );
			}
		}

		return $image;
	}

	/**
	 * ⭐ Inject vehicle image for WooCommerce Cart Block (Store API)
	 *
	 * This filter modifies the image data sent to the Cart Block via Store API.
	 *
	 * WooCommerce reads each entry as an object (CartItemSchema::get_cart_images()
	 * skips any entry without `->id` and falls back to the product's own images),
	 * so the vehicle image is built in ImageAttachmentSchema::get_item_response()'s
	 * shape. A vehicle without an image leaves WooCommerce's images as they are.
	 *
	 * @param array $images The current images array
	 * @param array $cart_item The cart item data
	 * @param string $cart_item_key The cart item key
	 * @return array The modified images array
	 */
	public static function inject_vehicle_image_for_blocks($images, $cart_item, $cart_item_key): array
	{
		$normalized = self::get_normalized_booking_data($cart_item);
		$vehicle_id = $normalized['vehicle_id'];
		$booking_id = $normalized['booking_id'];

		// Fallback: If no vehicle_id but we have booking_id, fetch from booking meta
		if (! $vehicle_id && $booking_id) {
			$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		}

		// If this is not an MHM booking item, return unchanged
		if (! $vehicle_id) {
			return $images;
		}

		// Get vehicle thumbnail
		$thumbnail_id = get_post_thumbnail_id($vehicle_id);
		if (! $thumbnail_id) {
			return $images;
		}

		$full_src      = wp_get_attachment_image_src($thumbnail_id, 'full');
		$thumbnail_src = wp_get_attachment_image_src($thumbnail_id, 'woocommerce_thumbnail');
		if (! $full_src || ! $thumbnail_src) {
			return $images;
		}

		$vehicle_name = (string) get_post_field('post_title', $vehicle_id, 'raw');

		return array(
			(object) array(
				'id'               => (int) $thumbnail_id,
				'src'              => $full_src[0],
				'thumbnail'        => $thumbnail_src[0],
				'srcset'           => (string) wp_get_attachment_image_srcset($thumbnail_id, 'full'),
				'sizes'            => (string) wp_get_attachment_image_sizes($thumbnail_id, 'full'),
				'thumbnail_srcset' => (string) wp_get_attachment_image_srcset($thumbnail_id, 'woocommerce_thumbnail'),
				'thumbnail_sizes'  => (string) wp_get_attachment_image_sizes($thumbnail_id, 'woocommerce_thumbnail'),
				'name'             => $vehicle_name,
				'alt'              => $vehicle_name,
			),
		);
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
	 * A stored booking date (Y-m-d) in the site's date format, as the cart shows it.
	 */
	private static function format_booking_date(string $date): string
	{
		$timestamp = '' !== $date ? strtotime($date) : false;

		// date_i18n() returns an int for the 'U' format.
		return false !== $timestamp ? (string) date_i18n( (string) get_option('date_format'), $timestamp) : $date;
	}

	/**
	 * Rewrites the saved booking snapshot with the value the line holds in memory.
	 *
	 * WC_Data_Store_WP::add_meta() slashes a string value for add_metadata(),
	 * which unslashes every value, but passes an array as it is: each "\" in the
	 * snapshot (a signed-in customer's name or phone, an add-on title) was lost
	 * in the row. The classic checkout reloads the order before booking from it,
	 * so the booking lost them too. WC_Data::save_meta_data() fires this hook
	 * right after the insert with the in-memory value, and
	 * update_metadata_by_mid() does not unslash, so the row ends up exact while
	 * the in-memory value -- which the Store API books from -- is left alone.
	 * Slashing the value before add_meta_data() would fix the row and leave the
	 * in-memory copy double-slashed instead.
	 *
	 * add_metadata() fires the same hook first, with the lossy value; WooCommerce
	 * fires it after with the exact one, so the last write is exact.
	 *
	 * @param int|false $meta_id    The new row's id (false when the insert failed).
	 * @param int       $item_id    Order item id.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value The value as it was written.
	 */
	public static function keep_booking_snapshot_backslashes($meta_id, $item_id, $meta_key, $meta_value): void
	{
		if ( (int) $meta_id <= 0 || '_mhmrentiva_booking_data' !== $meta_key || ! is_array($meta_value)) {
			return;
		}

		// Nothing add_metadata() could have stripped: no second write.
		if (! self::holds_a_backslash($meta_value)) {
			return;
		}

		// The stub types $meta_value as string; core serializes it itself
		// ("Must be serializable if non-scalar"), and a pre-serialized string
		// would be serialized twice (phpstan.neon ignoreErrors says so too).
		update_metadata_by_mid('order_item', (int) $meta_id, $meta_value);
	}

	/**
	 * Whether a value holds a backslash anywhere. Read-only: wp_unslash() would
	 * change an object inside the value in place (map_deep()).
	 *
	 * @param mixed $value
	 */
	private static function holds_a_backslash($value): bool
	{
		if (is_string($value)) {
			return str_contains($value, '\\');
		}
		if (is_array($value)) {
			// Values only: map_deep() leaves array keys as they are.
			foreach ($value as $inner) {
				if (self::holds_a_backslash($inner)) {
					return true;
				}
			}
			return false;
		}

		// An object is rewritten as it is. That cannot bring a backslash back:
		// add_metadata()'s wp_unslash() already stripped the object in place.
		// No snapshot carries one today.
		return is_object($value);
	}

	/**
	 * Save booking ID or booking data to order item meta
	 */
	public static function add_order_item_meta($item, $cart_item_key, $values, $order)
	{
		// Handle existing booking (legacy)
		if (isset($values['mhmrentiva_booking_id'])) {
			$item->add_meta_data('_mhmrentiva_booking_id', $values['mhmrentiva_booking_id']);

			// Also link order to booking
			update_post_meta($values['mhmrentiva_booking_id'], '_mhmrentiva_wc_order_id', $order->get_id());
		}
		// ⭐ Handle pending booking (from cart data)
		elseif (isset($values['mhmrentiva_booking_data'])) {
			$item->add_meta_data('_mhmrentiva_booking_data', $values['mhmrentiva_booking_data']);
			$item->add_meta_data('_mhmrentiva_booking_pending', $values['mhmrentiva_booking_pending'] ?? true);
			$item->add_meta_data('_mhmrentiva_booking_price', $values['mhmrentiva_booking_price'] ?? 0);

			// ⭐ ADD VISIBLE META DATA for Order Details Table
			$booking_data = $values['mhmrentiva_booking_data'];

			// 1. Vehicle Name
			if (! empty($booking_data['vehicle_id'])) {
				$item->add_meta_data(__('Vehicle', 'mhm-rentiva'), get_the_title($booking_data['vehicle_id']));
			}

			// 2. Dates
			if (! empty($booking_data['pickup_date'])) {
				$pickup  = self::format_booking_date( (string) $booking_data['pickup_date']) . ' ' . ( $booking_data['pickup_time'] ?? '' );
				$dropoff = self::format_booking_date( (string) ( $booking_data['dropoff_date'] ?? '' )) . ' ' . ( $booking_data['dropoff_time'] ?? '' );

				// The cart's labels, so one row keeps one name from cart to order.
				$item->add_meta_data(__('Pickup Date & Time', 'mhm-rentiva'), trim($pickup));
				// A transfer has no return: its drop-off is the estimated arrival.
				if (! self::is_transfer_booking($booking_data)) {
					$item->add_meta_data(__('Return Date & Time', 'mhm-rentiva'), trim($dropoff));
				}
			}

			// 3. Payment Type
			$payment_type       = $booking_data['payment_type'] ?? 'full';
			$payment_type_label = $payment_type === 'deposit'
				? __('Deposit Payment', 'mhm-rentiva')
				: __('Full Payment', 'mhm-rentiva');
			$item->add_meta_data(__('Payment Type', 'mhm-rentiva'), $payment_type_label);

			// 4. Remaining Amount (only for deposit payments)
			if ($payment_type === 'deposit') {
				$remaining_amount = floatval($booking_data['remaining_amount'] ?? 0);
				if ($remaining_amount > 0) {
					$item->add_meta_data(__('Remaining Amount', 'mhm-rentiva'), self::order_meta_price($remaining_amount));
				}
			}

			// 5. Add-on services: one row per add-on with its price, as the cart
			// shows them. A transfer's are written by the add-on that prices them.
			foreach (self::rental_addon_lines($booking_data) as $line) {
				$item->add_meta_data($line['title'], self::order_meta_price($line['line_total']));
			}
		} else {
			// Task 14b item 3: left at warning() deliberately. This hook
			// (woocommerce_add_order_item_meta) runs for EVERY order item on
			// EVERY checkout, including ordinary non-rental WooCommerce
			// products a site may sell alongside bookings -- the "no booking
			// data" branch is the expected, frequent case for those, not a
			// failure. Promoting it would make an unrelated product
			// purchase generate an operator-visible error on every order.
			AdvancedLogger::warning('No booking data found in cart item values', array(), AdvancedLogger::CATEGORY_PAYMENT);
		}
	}

	/**
	 * Create booking from order when checkout is processed
	 * This ensures booking is only created after order is placed (not before payment)
	 */
	public static function create_booking_from_order($order_id, $data = null, $order = null)
	{
		if (! class_exists('WooCommerce')) {
			return;
		}

		// Get order object if not provided
		if (! $order) {
			$order = function_exists('wc_get_order') ? call_user_func('wc_get_order', $order_id) : false;
		}

		if (! $order) {
			AdvancedLogger::error('Could not get order object', array( 'order_id' => $order_id ), AdvancedLogger::CATEGORY_PAYMENT);
			return;
		}

		// A booking id on the order is not enough to skip it: on a retry WC_Checkout
		// resumes the order with fresh lines (remove_order_items()), and a booking
		// cancelled by a failed payment may have lost its dates to someone else.
		// Each line is linked, relinked to the order's own booking, or booked.

		// From the two order-placed hooks a refused booking stops the request, so
		// no gateway ever sees the order. The thank-you fallback runs after payment
		// and cannot stop anything: it cancels instead.
		$halt = doing_action('woocommerce_checkout_order_processed') || doing_action('woocommerce_store_api_checkout_order_processed');

		$items = $order->get_items();

		if (empty($items)) {
			// Task 14b item 3: promoted from warning() to error(). This
			// runs on order-placed for every WooCommerce checkout that
			// carries a pending booking; if it fires, create_booking_from_order()
			// returns having created nothing, and the paid order is left
			// with no linked booking at all -- silent under the default log
			// level, on money that already moved.
			AdvancedLogger::error_linked(
				sprintf(
					/* translators: %d: the WooCommerce order id with no items. */
					__( 'No items found in order #%d', 'mhm-rentiva' ),
					(int) $order_id
				),
				0,
				array( 'order_id' => $order_id ),
				AdvancedLogger::CATEGORY_PAYMENT
			);
			return;
		}

		// Announced after the loop: on a refused later line the earlier bookings
		// are taken back, and nobody may have heard of them.
		$created = array();
		// Bookings that existed before this request: they must still hold their
		// dates before the gateway runs.
		$existing = array();

		foreach ($items as $item) {
			$linked = (int) $item->get_meta('_mhmrentiva_booking_id', true);
			if ($linked > 0) {
				$existing[] = $linked;
				continue;
			}

			// Check if this is a pending booking (not yet created)
			// ⭐ WooCommerce stores meta as serialized, so we need to get it properly
			$booking_data = $item->get_meta('_mhmrentiva_booking_data', true);
			$is_pending   = $item->get_meta('_mhmrentiva_booking_pending', true);

			// If booking_data is a string (serialized), unserialize it
			if (is_string($booking_data) && ! empty($booking_data)) {
				$unserialized = maybe_unserialize($booking_data);
				if (is_array($unserialized)) {
					$booking_data = $unserialized;
				}
			}

			if ($booking_data && is_array($booking_data) && $is_pending) {
				// A resumed order's line: its booking was made on the first attempt.
				$own = self::order_booking_for( (int) $order_id, $booking_data, $existing);
				if ($own > 0) {
					$item->update_meta_data('_mhmrentiva_booking_id', $own);
					$item->update_meta_data('_mhmrentiva_booking_pending', false);
					$item->save();
					$order->update_meta_data('_mhmrentiva_booking_id', $own);
					$order->save();
					$existing[] = $own;
					continue;
				}

				// Create booking from cart data
				try {
					$booking_id = self::create_booking_from_data($booking_data, $order_id, $halt);
					if ($halt && ! $booking_id) {
						self::halt_checkout(__('Your booking could not be created and no payment was taken. Please try again.', 'mhm-rentiva'), (int) $order_id);
					}
				} catch (\Throwable $e) {
					self::take_back_bookings($order, $created);
					throw $e;
				}

				if ($booking_id) {
					// Update order item meta to link booking
					$item->update_meta_data('_mhmrentiva_booking_id', $booking_id);
					$item->update_meta_data('_mhmrentiva_booking_pending', false);
					$item->save();

					// Update order meta
					$order->update_meta_data('_mhmrentiva_booking_id', $booking_id);
					$order->save();

					// Clear availability cache
					if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
						\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($booking_data['vehicle_id']);
					}

					$created[] = array(
						'booking_id' => $booking_id,
						'data'       => $booking_data,
						'item'       => $item,
					);
				}
			}
		}

		// Only where the request can still stop: the thank-you fallback is a page
		// view and must not revive a booking the operator cancelled. After money
		// moves, handle_order_status_change() asks the same question.
		if ($halt && array() !== $existing) {
			$refusal = self::claim_order_bookings($order, $existing);
			if ('' !== $refusal) {
				self::take_back_bookings($order, $created);
				self::halt_checkout($refusal, (int) $order_id);
			}
		}

		foreach ($created as $booking) {
			// Trigger booking created action (with booking_data for addons)
			// Note: AddonManager::save_booking_addons expects 2 parameters: booking_id and booking_data
			do_action('mhmrentiva_booking_created', $booking['booking_id'], $booking['data']);
		}
	}

	/**
	 * Undo the bookings an order made before a later line was refused, so that a
	 * retry of the same order -- which skips an order carrying a booking id --
	 * books every line again.
	 *
	 * @param \WC_Order                                                                 $order
	 * @param array<int, array{booking_id: int, data: array<string, mixed>, item: mixed}> $created
	 */
	private static function take_back_bookings($order, array $created): void
	{
		foreach ($created as $booking) {
			$booking['item']->delete_meta_data('_mhmrentiva_booking_id');
			$booking['item']->update_meta_data('_mhmrentiva_booking_pending', true);
			$booking['item']->save();

			wp_delete_post($booking['booking_id'], true);
			if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
				\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($booking['data']['vehicle_id']);
			}
		}

		if (array() !== $created) {
			$order->delete_meta_data('_mhmrentiva_booking_id');
			$order->save();
		}
	}

	/**
	 * Stop an order-placed request: the Store API answers 400 with this message,
	 * WC_Checkout::process_checkout() catches it and shows it as a notice. Either
	 * way the gateway is never called. The order stays `pending`, so it says
	 * why on the order screen.
	 *
	 * @param string $note The order note when the reason is not the booking
	 *                     (the payment method, for one); '' for the booking refusal note.
	 * @throws \Exception Always.
	 */
	private static function halt_checkout(string $message, int $order_id = 0, string $note = ''): void
	{
		// Plain text, not HTML: JSON on the Store API, and wc_add_notice() runs
		// wp_kses_post() when it prints on the classic checkout. get_the_title()
		// and esc_html() would leave entities the customer reads literally.
		$plain = self::plain_text($message);

		$order = $order_id > 0 && function_exists('wc_get_order') ? wc_get_order($order_id) : false;
		if ($order instanceof \WC_Order) {
			/* translators: %s: why the booking was refused, as the customer read it. */
			$order->add_order_note('' !== $note ? $note : sprintf(__('Booking refused, no payment taken: %s', 'mhm-rentiva'), $plain));
		}

		$route_exception = '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException';
		$exception       = class_exists($route_exception)
			? new $route_exception('mhmrentiva_booking_refused', $plain, 400)
			: new \Exception($plain);

		throw $exception;
	}

	private static function plain_text(string $message): string
	{
		return wp_strip_all_tags(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
	}

	/**
	 * A halted checkout leaves its order `pending` with no booking, and
	 * WC_Order::needs_payment() would offer it for payment: the Pay action in My
	 * Account, the order-pay form and WC_Form_Handler::pay_action() all ask it.
	 * Money there would have no booking, so such an order is not payable.
	 *
	 * The Store API stays open: DraftOrderTrait::is_valid_draft_order() asks the
	 * same question to reuse the order on a retry, and both its routes
	 * (Checkout, CheckoutOrder) fire the order-processed hook before payment, so
	 * store_api_order_processed() books or halts first.
	 *
	 * An order with a booking stays payable here: whether that booking still
	 * holds its dates is asked where the payment starts (claim_before_pay(),
	 * create_booking_from_order() on the Store API) and where it lands
	 * (handle_order_status_change()), under the vehicle's lock.
	 *
	 * WC_Order::status_transition() applies the same filter to ask "was this
	 * a payment?" once the order already sits in a paid status (an operator
	 * moving a halted order to processing). Such an order is not payable
	 * either way, so it is left alone: the transition keeps its payment hook.
	 *
	 * @param mixed $statuses The statuses an order may be paid in.
	 * @param mixed $order    The order asking.
	 * @return mixed
	 */
	public static function refuse_payment_without_booking($statuses, $order)
	{
		if (! $order instanceof \WC_Order || ! is_array($statuses) || $order->get_meta('_mhmrentiva_booking_id', true)) {
			return $statuses;
		}
		if ($order->has_status(wc_get_is_paid_statuses())) {
			return $statuses;
		}
		if (array() === self::pending_booking_snapshots($order) || \WC()->is_store_api_request()) {
			return $statuses;
		}

		return array();
	}

	/**
	 * The snapshots of the order's lines still waiting for a booking, by item.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function pending_booking_snapshots(\WC_Order $order): array
	{
		$snapshots = array();
		foreach ($order->get_items() as $item_id => $item) {
			$snapshot = $item->get_meta('_mhmrentiva_booking_data', true);
			if (is_array($snapshot) && $item->get_meta('_mhmrentiva_booking_pending', true)) {
				$snapshots[ (int) $item_id ] = $snapshot;
			}
		}

		return $snapshots;
	}

	/**
	 * The snapshot's payment type; without one, what its amounts charge: a
	 * deposit leaves something to pay, a full payment does not.
	 *
	 * @param array<string, mixed> $snapshot
	 */
	private static function snapshot_payment_type(array $snapshot): string
	{
		$type = (string) ( $snapshot['payment_type'] ?? '' );
		if ('' !== $type) {
			return $type;
		}

		return (float) ( $snapshot['deposit_amount'] ?? 0 ) > 0 && (float) ( $snapshot['remaining_amount'] ?? 0 ) > 0 ? 'deposit' : 'full';
	}

	/**
	 * A guest's snapshot has no contact: the booking form asks nothing, checkout
	 * does. Each empty field comes from the order's billing details; what the
	 * snapshot holds stands.
	 *
	 * @param array<string, mixed> $booking_data
	 * @return array<string, mixed>
	 */
	private static function with_order_contact(array $booking_data, int $order_id): array
	{
		$order = function_exists('wc_get_order') ? call_user_func('wc_get_order', $order_id) : false;

		$billing = $order instanceof \WC_Order
			? array(
				'customer_first_name' => $order->get_billing_first_name(),
				'customer_last_name'  => $order->get_billing_last_name(),
				'customer_name'       => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
				'customer_email'      => $order->get_billing_email(),
				'customer_phone'      => $order->get_billing_phone(),
			)
			: array_fill_keys(array( 'customer_first_name', 'customer_last_name', 'customer_name', 'customer_email', 'customer_phone' ), '');

		foreach ($billing as $key => $value) {
			if (empty($booking_data[ $key ])) {
				$booking_data[ $key ] = $value;
			}
		}

		// A guest who creates an account at checkout: WC_Checkout::process_customer()
		// makes the user and puts it on the order before the order-placed hook.
		// My Account finds a booking by this id only.
		if (empty($booking_data['customer_user_id']) && $order instanceof \WC_Order && $order->get_customer_id() > 0) {
			$booking_data['customer_user_id'] = $order->get_customer_id();
		}

		return $booking_data;
	}

	/**
	 * The bookings an order pays for: its lines' ids, else the order's own.
	 *
	 * @return list<int>
	 */
	private static function linked_booking_ids(\WC_Order $order): array
	{
		$ids = array();
		foreach ($order->get_items() as $item) {
			$id = (int) $item->get_meta('_mhmrentiva_booking_id', true);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if (array() === $ids) {
			$id = (int) $order->get_meta('_mhmrentiva_booking_id', true);
			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}

	private static function is_remaining_order(\WC_Order $order): bool
	{
		return '1' === (string) $order->get_meta('_mhmrentiva_is_remaining_payment', true);
	}

	/**
	 * Is this remaining-payment order still for what the booking owes? '' when
	 * it is; otherwise the customer-facing reason, and no payment is taken.
	 *
	 * The booking's remaining amount moves when an admin re-prices it (the
	 * difference becomes owed, H2 face 2) -- and an order built before that, or
	 * between the admin's check and the write, still asks for the old amount.
	 * Checked here, before the gateway, on both the order-pay form and the
	 * Store API path, so a stale order can never take money, whenever it was
	 * built. The order carries the gross amount it was built for
	 * (`_mhmrentiva_remaining_due_minor`, RemainingPaymentHandler): its own total
	 * can differ through tax for the billing address. An order built before the
	 * stamp existed is compared by its total.
	 *
	 * The booking's amount is read from the database, not the object cache. It
	 * is not read under a row lock. Such a lock would only wait out a single
	 * meta UPDATE still in flight; released after this check, it could not stop
	 * an admin save landing between the check and the charge. Closing that
	 * window takes the admin save and the payment holding the same lock, which
	 * is PR-2 (plan option A, user decision 2026-11-09; Codex r12 2).
	 *
	 * @param array<int, int|string> $booking_ids
	 */
	private static function stale_remaining_order_notice(\WC_Order $order, array $booking_ids): string
	{
		$stale = __('The amount on this payment link is no longer current. Please ask us for a new payment link.', 'mhm-rentiva');

		// A cancelled order takes no money (PR-2 A2). WooCommerce's own pay form
		// refuses it as well, but its needs_payment() set is filterable, and the
		// claim must not depend on that.
		if ($order->has_status('cancelled')) {
			return $stale;
		}

		foreach ($booking_ids as $booking_id) {
			$booking_id = (int) $booking_id;
			if ($booking_id <= 0) {
				continue;
			}
			// From the database, not the object cache another request may have
			// refilled: an admin may have re-priced it, or sent a new link, a
			// moment ago (Codex r10 4).
			try {
				$owed = RemainingPaymentHandler::debt_minor(self::read_booking_meta($booking_id, '_mhmrentiva_remaining_amount'));
				$link = (int) self::read_booking_meta($booking_id, '_mhmrentiva_remaining_order_id');
			} catch (\RuntimeException $e) {
				return $stale;
			}
			// Only the link the business sent last can take money (PR-2 A2). An
			// order that is no longer it -- a new link was sent, or a cancelled
			// one cleared it -- could otherwise be paid from an old e-mail and
			// collect the balance twice. An order whose pointer was never
			// written is refused the same way; sending a new link is the safe
			// recovery, and accepting a missing pointer would let every
			// superseded order through.
			if ($link !== (int) $order->get_id()) {
				return $stale;
			}
			if (RemainingPaymentHandler::VERDICT_MATCH !== RemainingPaymentHandler::amount_verdict($order, $owed)) {
				return $stale;
			}
		}

		return '';
	}

	/**
	 * The booking this order made for a line on an earlier attempt: same order,
	 * vehicle and instants. 0 when none, or when it is already another line's.
	 *
	 * @param array<string, mixed> $snapshot The line's booking data.
	 * @param list<int>            $taken    Bookings other lines already have.
	 */
	private static function order_booking_for(int $order_id, array $snapshot, array $taken = array()): int
	{
		$vehicle_id = (int) ( $snapshot['vehicle_id'] ?? 0 );
		$start_ts   = self::parse_datetime_to_timestamp( (string) ( $snapshot['pickup_date'] ?? '' ), (string) ( $snapshot['pickup_time'] ?? '' ));
		$end_ts     = self::parse_datetime_to_timestamp( (string) ( $snapshot['dropoff_date'] ?? '' ), (string) ( $snapshot['dropoff_time'] ?? '' ));
		if ($order_id <= 0 || $vehicle_id <= 0 || ! $start_ts || ! $end_ts) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'        => 'mhmrentiva_booking',
				'post_status'      => 'any',
				'fields'           => 'ids',
				'numberposts'      => 20,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Two exact keys, one order's bookings: a handful of rows, only on a checkout retry.
				'meta_query'       => array(
					array(
						'key'   => '_mhmrentiva_woocommerce_order_id',
						'value' => $order_id,
					),
					array(
						'key'   => '_mhmrentiva_vehicle_id',
						'value' => $vehicle_id,
					),
				),
			)
		);

		foreach ($ids as $id) {
			$id = (int) $id;
			if (in_array($id, $taken, true)) {
				continue;
			}
			if ( (int) get_post_meta($id, '_mhmrentiva_start_ts', true) === $start_ts && (int) get_post_meta($id, '_mhmrentiva_end_ts', true) === $end_ts) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * The customer's own booking for a cart line, when the checkout is a retry of
	 * an order whose first attempt already booked it (the gateway failed and
	 * left the order pending or failed). The cart checks leave out exactly this
	 * booking; every other booking still refuses the dates.
	 *
	 * @param array<string, mixed> $booking_data The cart line's booking data.
	 */
	private static function own_booking_of_cart_line(array $booking_data): int
	{
		if (! function_exists('WC') || null === \WC()->session || ! function_exists('wc_get_order')) {
			return 0;
		}

		// WC_Checkout::create_order() resumes order_awaiting_payment; the Store
		// API reuses store_api_draft_order (DraftOrderTrait).
		$order_ids = array_unique(
			array_filter(
				array(
					(int) \WC()->session->get('order_awaiting_payment', 0),
					(int) \WC()->session->get('store_api_draft_order', 0),
				)
			)
		);

		foreach ($order_ids as $order_id) {
			$order = wc_get_order($order_id);
			if (! $order instanceof \WC_Order || ! $order->has_status(array( 'pending', 'failed', 'checkout-draft' ))) {
				continue;
			}
			$booking_id = self::order_booking_for($order_id, $booking_data);
			if ($booking_id > 0) {
				return $booking_id;
			}
		}

		return 0;
	}

	/**
	 * '' when every booking the order pays for holds its dates, or holds them
	 * again; else what to tell the customer. Asked before money is taken.
	 *
	 * All or nothing: when a later booking is refused -- or the check throws --
	 * the earlier ones this call revived are put back (undo_revivals(), which
	 * keeps whatever happened to them meanwhile), and nobody hears of a revival
	 * that did not stand: the hooks run only once every booking holds.
	 *
	 * @param list<int>|null $booking_ids The bookings to ask about; null for the order's.
	 */
	private static function claim_order_bookings(\WC_Order $order, ?array $booking_ids = null): string
	{
		$remaining = self::is_remaining_order($order);
		if ($remaining) {
			$stale = self::stale_remaining_order_notice($order, $booking_ids ?? self::linked_booking_ids($order));
			if ('' !== $stale) {
				return $stale;
			}
		}
		$revived = array();
		try {
			foreach ($booking_ids ?? self::linked_booking_ids($order) as $booking_id) {
				$refusal = self::claim_booking( (int) $booking_id, $remaining, $revived);
				if ('' !== $refusal) {
					self::undo_revivals($revived);
					return $refusal;
				}
			}
		} catch (\Throwable $e) {
			self::undo_revivals($revived);
			throw $e;
		}
		self::announce_revivals($revived);

		return '';
	}

	/**
	 * The statuses a payment may bring back to life: a booking that was never
	 * paid (pending), or that a failed payment cancelled. Refunded and no-show
	 * are closed for good, and a payment does not reopen them.
	 *
	 * @var list<string>
	 */
	private const REVIVABLE = array( Status::CANCELLED, Status::PENDING, Status::PENDING_PAYMENT );

	/**
	 * Has the booking let go of its dates? Cancelled, refunded or no-show: it
	 * holds nothing. Pending past its payment deadline: BookingConflicts no
	 * longer counts it (PAYMENT_SQL), so someone else may have booked over it.
	 */
	private static function booking_lost_its_dates(int $booking_id, bool $from_database = false): bool
	{
		$read   = static function (string $key) use ($booking_id, $from_database): string {
			return $from_database ? self::read_booking_meta($booking_id, $key) : (string) get_post_meta($booking_id, $key, true);
		};
		$status = $read('_mhmrentiva_status');
		if (in_array($status, array( Status::CANCELLED, Status::REFUNDED, Status::NO_SHOW ), true)) {
			return true;
		}
		if (! in_array($status, array( Status::PENDING, Status::PENDING_PAYMENT ), true)) {
			return false;
		}
		$deadline = $read('_mhmrentiva_payment_deadline');

		return '' !== $deadline && $deadline <= current_time('mysql', true);
	}

	/**
	 * The booking's vehicle and instants; zeros when it has none.
	 *
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function booking_window(int $booking_id): array
	{
		$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		$start_ts   = (int) get_post_meta($booking_id, '_mhmrentiva_start_ts', true);
		$end_ts     = (int) get_post_meta($booking_id, '_mhmrentiva_end_ts', true);
		if ($start_ts <= 0 || $end_ts <= 0) {
			$start_ts = (int) self::parse_datetime_to_timestamp( (string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true), (string) get_post_meta($booking_id, '_mhmrentiva_pickup_time', true));
			$end_ts   = (int) self::parse_datetime_to_timestamp( (string) get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true), (string) get_post_meta($booking_id, '_mhmrentiva_dropoff_time', true));
		}

		return $vehicle_id > 0 && $start_ts > 0 && $end_ts > $start_ts ? array( $vehicle_id, $start_ts, $end_ts ) : array( 0, 0, 0 );
	}

	private static function booking_gone_notice(): string
	{
		return __('This booking was cancelled and can no longer be paid. Please make a new booking.', 'mhm-rentiva');
	}

	/**
	 * A count, a lock or a transaction that could not be read decides nothing:
	 * the booking is refused, never taken as free (Codex PR K delta H2).
	 */
	private static function availability_unknown_notice(): string
	{
		return __('The availability of this booking could not be checked, so no payment was taken. Please try again in a moment.', 'mhm-rentiva');
	}

	/**
	 * A booking write that did not hold: nothing was changed (Codex PR K delta2 H2).
	 */
	private static function write_failed_notice(): string
	{
		return __('The booking could not be updated, so nothing was changed. Please try again in a moment.', 'mhm-rentiva');
	}

	/**
	 * One booking meta straight from the database, past the object cache. A
	 * deleted cache entry does not make the next get_post_meta() read the
	 * database: on a shared object cache (Redis) another request can put a
	 * value it read before a payment committed back in between, and a decision
	 * taken on it overwrites that payment (Codex PR K delta3 H1). Inside a
	 * Locker transaction this is the locked connection, so it sees the
	 * transaction's own writes.
	 *
	 * Every row of the key is read: rows that disagree (a duplicate key with
	 * two values) are not a value, and a decision on the first of them would
	 * leave the other for BookingConflicts' JOIN to see (Codex PR K delta4 3).
	 * They read as a failure, so the caller refuses. No row reads as ''.
	 *
	 * @throws \RuntimeException When the read failed or the rows disagree ('mhmrentiva_booking_read_failed').
	 */
	private static function read_booking_meta(int $booking_id, string $key): string
	{
		$rows = self::read_booking_meta_rows($booking_id, $key);
		if (count(array_unique($rows)) > 1) {
			throw new \RuntimeException('mhmrentiva_booking_read_failed');
		}

		return $rows[0] ?? '';
	}

	/** A write_booking_meta() answer: the value was written and every row holds it. */
	private const META_WRITTEN = 'written';

	/** A write_booking_meta() answer: every row already held the value; nothing was written. */
	private const META_ALREADY = 'already';

	/** A write_booking_meta() answer: not written -- the expected value was no longer there; nothing was changed by this write. */
	private const META_NOT_WRITTEN = 'not_written';

	/** A write_booking_meta() answer: a value other than the one asked for is there now (a sanitize filter, another writer). */
	private const META_OTHER_VALUE = 'other_value';

	/** A write_booking_meta() answer: the write failed in the database, or the value could not be read back; the final state is unknown. */
	private const META_UNREADABLE = 'unreadable';

	/**
	 * Does a write_booking_meta() answer mean the value holds?
	 */
	private static function meta_holds(string $result): bool
	{
		return self::META_WRITTEN === $result || self::META_ALREADY === $result;
	}

	/**
	 * Every row of one booking meta key, oldest first, from the database.
	 *
	 * @param bool $lock A locking read (`FOR UPDATE`): the latest committed rows
	 *                   (and this transaction's own writes), not the
	 *                   transaction's snapshot, and the rows stay locked until
	 *                   the transaction ends (Fable PR K delta6 F1).
	 * @return list<string>
	 * @throws \RuntimeException When the read failed ('mhmrentiva_booking_read_failed').
	 */
	private static function read_booking_meta_rows(int $booking_id, string $key, bool $lock = false): array
	{
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC",
			$booking_id,
			$key
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- A locked decision must not trust the object cache (Codex PR K delta3 H1); $sql is prepared above.
		$values = $wpdb->get_col($lock ? $sql . ' FOR UPDATE' : $sql);
		if ('' !== (string) $wpdb->last_error || ! is_array($values)) {
			throw new \RuntimeException('mhmrentiva_booking_read_failed');
		}

		return array_values(array_map('strval', $values));
	}

	/**
	 * Write one booking meta, only over the value it is expected to replace,
	 * with the key's rows locked from the check to the write.
	 *
	 * `SELECT … FOR UPDATE` reads the latest committed rows and locks them.
	 * Inside a transaction -- Locker::withLock()'s, or a caller's own -- they
	 * stay locked until it ends, so no other writer can change an existing
	 * row between the check and the write; that makes the write conditional
	 * for every expected value, '' and '0' included (Codex PR K delta4 2).
	 * Outside a transaction (autocommit) the lock lasts one statement: the
	 * check and the write are not covered against an unlocked writer that
	 * lands in between -- the same pre-existing class as every other unlocked
	 * booking writer; the locking read-back still reports what holds.
	 *
	 * A missing row has no row to lock: the read takes a gap (next-key) lock,
	 * and gap locks do not exclude each other. Two writers that both find the
	 * row missing can both pass the check; their inserts then wait on each
	 * other and InnoDB rolls one back as a deadlock (Fable PR K delta6 F3).
	 * The locked booking paths do not meet there -- they serialize on the
	 * vehicle's lock first -- and a write that fails in the database answers
	 * "not held" at once (a deadlock victim's transaction is gone, so nothing
	 * after it may be read as confirmation). It fails closed.
	 *
	 * The write itself is WordPress's own (update_post_meta() for an existing
	 * row, add_post_meta(..., true) for a missing one): every core hook,
	 * wp_unslash(), sanitize_meta() and the meta cache are core's (Codex PR K
	 * delta5). Every row is then read back with a locking read, not a
	 * snapshot read: inside a transaction a plain SELECT may answer from an
	 * older snapshot than the locked check, and would report a write core
	 * skipped as held (Fable delta6 F1). A value a sanitize filter changed is
	 * not held either.
	 *
	 * Order of the answers: rows that disagree, or a failed read, throw
	 * (read failure); a value every row already holds is "already" -- whatever
	 * was expected, it is what the caller wants and no hook should run twice
	 * (Fable F2); a current value other than the expected one is "not held";
	 * else the write.
	 *
	 * @param string|null $expected The value the write replaces; null takes what
	 *                              the database holds now.
	 * @return string One of META_WRITTEN, META_ALREADY, META_NOT_WRITTEN, META_OTHER_VALUE, META_UNREADABLE.
	 * @throws \RuntimeException When a read failed or the rows disagree ('mhmrentiva_booking_read_failed').
	 */
	private static function write_booking_meta(int $booking_id, string $key, string $value, ?string $expected = null): string
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A row lock from the check to the write (Codex PR K delta5).
		$locked = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC FOR UPDATE",
				$booking_id,
				$key
			),
			ARRAY_A
		);
		if ('' !== (string) $wpdb->last_error || ! is_array($locked)) {
			throw new \RuntimeException('mhmrentiva_booking_read_failed');
		}
		$rows = array_map(static fn($row): string => (string) ( is_array($row) ? ( $row['meta_value'] ?? '' ) : '' ), $locked);
		if (count(array_unique($rows)) > 1) {
			throw new \RuntimeException('mhmrentiva_booking_read_failed');
		}
		$current = $rows[0] ?? '';

		if ($current === $value) {
			wp_cache_delete($booking_id, 'post_meta');
			return self::META_ALREADY;
		}
		if (null !== $expected && $current !== $expected) {
			wp_cache_delete($booking_id, 'post_meta');
			return self::META_NOT_WRITTEN;
		}

		// update_metadata() compares with the cached value first; a stale entry
		// equal to $value would skip the write.
		wp_cache_delete($booking_id, 'post_meta');
		$written = array() === $rows
			? add_post_meta($booking_id, $key, $value, true)
			: update_post_meta($booking_id, $key, $value);
		$failed  = false === $written && self::last_query_failed();
		wp_cache_delete($booking_id, 'post_meta');
		if ($failed) {
			// A database error (a deadlock victim among them): nothing that
			// follows on this connection confirms anything.
			return self::META_UNREADABLE;
		}

		try {
			$after = self::read_booking_meta_rows($booking_id, $key, true);
		} catch (\RuntimeException $e) {
			return self::META_UNREADABLE;
		}

		return array() !== $after && array( $value ) === array_values(array_unique($after)) ? self::META_WRITTEN : self::META_OTHER_VALUE;
	}

	/**
	 * Did the statement just run fail? wpdb clears last_error before each query.
	 *
	 * @phpstan-impure It reads the state the latest query left.
	 */
	private static function last_query_failed(): bool
	{
		global $wpdb;

		return '' !== (string) $wpdb->last_error;
	}

	/**
	 * The same as write_booking_meta(), outside a Locker transaction. It opens
	 * no transaction of its own: on a connection where WooCommerce or another
	 * plugin already has one open (wc_transaction_query()), a START TRANSACTION
	 * would commit that one implicitly (Codex PR K delta6 H). The locking reads
	 * (write_booking_meta()) still read the latest committed rows; inside the
	 * caller's transaction they hold their locks until it ends, under
	 * autocommit for one statement -- see write_booking_meta().
	 *
	 * @return string One of META_WRITTEN, META_ALREADY, META_NOT_WRITTEN, META_OTHER_VALUE, META_UNREADABLE.
	 * @throws \RuntimeException When a read failed or the rows disagree ('mhmrentiva_booking_read_failed').
	 */
	private static function write_booking_meta_alone(int $booking_id, string $key, string $value, ?string $expected = null): string
	{
		try {
			return self::write_booking_meta($booking_id, $key, $value, $expected);
		} finally {
			wp_cache_delete($booking_id, 'post_meta');
		}
	}

	/**
	 * The same, inside a Locker callback: a write that did not hold throws, so
	 * Locker::withLock() rolls the whole transaction back and nothing of it is
	 * kept or announced.
	 *
	 * @return string META_WRITTEN or META_ALREADY.
	 * @throws \RuntimeException When the value did not hold.
	 */
	private static function write_booking_meta_or_roll_back(int $booking_id, string $key, string $value, ?string $expected = null): string
	{
		$result = self::write_booking_meta($booking_id, $key, $value, $expected);
		if (! self::meta_holds($result)) {
			throw new \RuntimeException('mhmrentiva_booking_write_failed');
		}

		return $result;
	}

	/**
	 * Run a booking decision under the vehicle's lock. The decision reads and
	 * verifies the booking from the database (read_booking_meta()), never from
	 * the object cache. Locker rolls back on any Throwable; whatever the
	 * request cache took in meanwhile is dropped then too, and once after a
	 * COMMIT (Codex PR K delta3). A write that did not hold answers
	 * 'write_failed', a read that failed 'unknown'; any other Throwable reaches
	 * the caller.
	 *
	 * @param callable(): mixed $decide
	 * @return array{0: string, 1?: mixed, 2?: mixed}
	 */
	private static function decide_under_lock(int $vehicle_id, int $booking_id, callable $decide): array
	{
		$locked = false;
		try {
			$outcome = \MHMRentiva\Admin\Booking\Helpers\Locker::withLock($vehicle_id, $decide, $locked);
		} catch (\Throwable $e) {
			wp_cache_delete($booking_id, 'post_meta');
			if ($e instanceof \RuntimeException && 'mhmrentiva_booking_write_failed' === $e->getMessage()) {
				return array( 'write_failed' );
			}
			if ($e instanceof \RuntimeException && 'mhmrentiva_booking_read_failed' === $e->getMessage()) {
				return array( 'unknown' );
			}
			throw $e;
		}
		wp_cache_delete($booking_id, 'post_meta');

		if (true !== $locked || ! is_array($outcome) || ! isset($outcome[0])) {
			return array( 'unknown' );
		}

		return $outcome;
	}

	/**
	 * Make sure a booking still holds its dates before money is taken for it
	 * (Codex PR K 1: a failed payment cancels the booking, another customer
	 * books the dates, the first order is paid again).
	 *
	 * A booking that holds its dates passes untouched. One that let go of them
	 * takes them back under the vehicle's lock -- the same lock and the same
	 * rule a new booking goes through, leaving only itself out -- or is
	 * refused. Taking back means: a cancelled booking is pending again, and a
	 * fresh payment deadline. A refunded or no-show booking, and any booking of
	 * a remaining-payment order, is not revived: the deposit side of that money
	 * is not known here. An unreadable count, a failed lock or a write that did
	 * not hold refuses.
	 *
	 * What it revived is added to $revived (status and deadline before and
	 * after, and which of the two this attempt wrote); the caller announces it
	 * (announce_revivals()) or puts it back (undo_revivals()). A value that
	 * already held is not this attempt's (Fable PR K delta6 F2): its status
	 * change is not announced a second time, and it is not undone -- undoing it
	 * would put back a revival somebody else made and announced. Both already
	 * holding is no revival at all.
	 *
	 * @param list<array{id: int, status: string, deadline: string, wrote_status: string, wrote_deadline: string, status_written: bool, deadline_written: bool, vehicle: int}> $revived
	 * @return string '' when it holds its dates; else the customer's message.
	 */
	private static function claim_booking(int $booking_id, bool $remaining, array &$revived): string
	{
		wp_cache_delete($booking_id, 'post_meta');
		// From the database: a stale cached status must not pass a booking that
		// let go of its dates (Codex PR K delta4 1, same class).
		try {
			if (! self::booking_lost_its_dates($booking_id, true)) {
				return '';
			}
			$status = self::read_booking_meta($booking_id, '_mhmrentiva_status');
		} catch (\RuntimeException $e) {
			return self::availability_unknown_notice();
		}

		list($vehicle_id, $start_ts, $end_ts) = self::booking_window($booking_id);
		if ($remaining || ! in_array($status, self::REVIVABLE, true) || $vehicle_id <= 0) {
			return self::booking_gone_notice();
		}

		$claimed = self::decide_under_lock(
			$vehicle_id,
			$booking_id,
			static function () use ($booking_id, $vehicle_id, $start_ts, $end_ts) {
				// What the lock protects, read from the database.
				if (! self::booking_lost_its_dates($booking_id, true)) {
					return array( 'held' );
				}
				$overlaps = \MHMRentiva\Admin\Booking\Helpers\Util::count_overlaps_locked($vehicle_id, $start_ts, $end_ts, $booking_id);
				if (null === $overlaps) {
					return array( 'unknown' );
				}
				if ($overlaps > 0) {
					return array( 'taken' );
				}

				$from           = self::read_booking_meta($booking_id, '_mhmrentiva_status');
				$deadline       = self::read_booking_meta($booking_id, '_mhmrentiva_payment_deadline');
				$to             = Status::CANCELLED === $from ? Status::PENDING : $from;
				$fresh          = self::get_payment_deadline();
				$wrote_status   = self::write_booking_meta_or_roll_back($booking_id, '_mhmrentiva_status', $to, $from);
				$wrote_deadline = self::write_booking_meta_or_roll_back($booking_id, '_mhmrentiva_payment_deadline', $fresh, $deadline);
				if (self::META_WRITTEN !== $wrote_status && self::META_WRITTEN !== $wrote_deadline) {
					// Both already held: someone else took the dates back for
					// it. Nothing of this attempt to announce or to undo.
					return array( 'held' );
				}

				return array( 'revived', array( $from, $deadline ), array( $to, $fresh ), array( self::META_WRITTEN === $wrote_status, self::META_WRITTEN === $wrote_deadline ) );
			}
		);

		switch ($claimed[0]) {
			case 'held':
				return '';
			case 'taken':
				return self::booked_meanwhile_notice($vehicle_id, $start_ts, $end_ts);
			case 'write_failed':
				return self::write_failed_notice();
			case 'revived':
				$before    = (array) ( $claimed[1] ?? array() );
				$after     = (array) ( $claimed[2] ?? array() );
				$ours      = (array) ( $claimed[3] ?? array() );
				$revived[] = array(
					'id'               => $booking_id,
					'status'           => (string) ( $before[0] ?? '' ),
					'deadline'         => (string) ( $before[1] ?? '' ),
					'wrote_status'     => (string) ( $after[0] ?? '' ),
					'wrote_deadline'   => (string) ( $after[1] ?? '' ),
					'status_written'   => true === ( $ours[0] ?? false ),
					'deadline_written' => true === ( $ours[1] ?? false ),
					'vehicle'          => $vehicle_id,
				);
				return '';
		}

		return self::availability_unknown_notice();
	}

	/**
	 * Tell everyone about the revivals that stand. Outside the transaction, like
	 * every hook of the booking path.
	 *
	 * @param list<array{id: int, status: string, deadline: string, wrote_status: string, wrote_deadline: string, status_written: bool, deadline_written: bool, vehicle: int}> $revived
	 */
	private static function announce_revivals(array $revived): void
	{
		foreach ($revived as $revival) {
			if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
				\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($revival['vehicle']);
			}
			if (class_exists('MHMRentiva\Admin\Booking\Meta\BookingMeta')) {
				\MHMRentiva\Admin\Booking\Meta\BookingMeta::add_history_note(
					$revival['id'],
					__('The dates were still free when the order was paid again; the booking holds them again.', 'mhm-rentiva'),
					'system'
				);
			}
			if (Status::CANCELLED === $revival['status'] && $revival['status_written']) {
				do_action('mhmrentiva_booking_status_changed', $revival['id'], Status::CANCELLED, Status::PENDING);
			}
		}
	}

	/**
	 * Put revived bookings back as they were: a later booking of the same order
	 * was refused, so no payment is taken and these dates are not held for it.
	 * Silent -- nobody was told of the revival.
	 *
	 * Under the vehicle's lock, and only while the booking still carries what
	 * this attempt wrote (status and deadline): a payment that landed meanwhile
	 * (confirmed, paid) is kept, never overwritten (Codex PR K delta2 H1). A
	 * write that does not hold rolls that one booking's undo back; it stays
	 * pending until its fresh deadline, which holds nothing longer than any
	 * unpaid booking does.
	 *
	 * @param list<array{id: int, status: string, deadline: string, wrote_status: string, wrote_deadline: string, status_written: bool, deadline_written: bool, vehicle: int}> $revived
	 */
	private static function undo_revivals(array $revived): void
	{
		foreach ($revived as $revival) {
			$booking_id = $revival['id'];
			$undone     = self::decide_under_lock(
				$revival['vehicle'],
				$booking_id,
				static function () use ($revival, $booking_id) {
					// From the database, and only over what this attempt wrote: a
					// payment that landed meanwhile -- confirmed, or only marked
					// paid (Fable PR K delta3 m2) -- is kept.
					$still_ours = self::read_booking_meta($booking_id, '_mhmrentiva_status') === $revival['wrote_status']
						&& self::read_booking_meta($booking_id, '_mhmrentiva_payment_deadline') === $revival['wrote_deadline']
						&& 'paid' !== self::read_booking_meta($booking_id, '_mhmrentiva_payment_status');
					if (! $still_ours) {
						return array( 'kept' );
					}
					// Only what this attempt wrote (Fable delta6 F2).
					if ($revival['status_written']) {
						self::write_booking_meta_or_roll_back($booking_id, '_mhmrentiva_status', $revival['status'], $revival['wrote_status']);
					}
					if ($revival['deadline_written']) {
						self::write_booking_meta_or_roll_back($booking_id, '_mhmrentiva_payment_deadline', $revival['deadline'], $revival['wrote_deadline']);
					}

					return array( 'undone' );
				}
			);

			if ('undone' !== $undone[0] && 'kept' !== $undone[0]) {
				AdvancedLogger::error_linked(
					sprintf(
						/* translators: %d: booking id. */
						__('Booking #%d was revived for a payment that was then refused, and could not be put back; it stays pending until its payment deadline.', 'mhm-rentiva'),
						$booking_id
					),
					$booking_id,
					array( 'reason' => $undone[0] ),
					AdvancedLogger::CATEGORY_BOOKING
				);
			}
			if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
				\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($revival['vehicle']);
			}
		}
	}

	/**
	 * Mark a booking paid and move it as the status table allows, outside a
	 * lock (a booking that already holds its dates, or one closed for good).
	 *
	 * The transition is decided on the status the database holds -- never the
	 * request cache, which a write that was not needed leaves stale (Codex PR K
	 * delta4 1: a no-show booking read as confirmed from the cache was
	 * completed) -- and written only over that status. The paid mark and the
	 * status write are both verified: an allowed transition that does not hold
	 * is a failure, not a success (delta3 3). A transition the table does not
	 * allow keeps the status, as before.
	 *
	 * Each write takes the key's row lock (write_booking_meta_alone(), no
	 * transaction of its own). The status hook runs only for a status that was
	 * written, never for one that already held (Fable F2). A write that does
	 * not hold answers UNLOCKED_NOT_HELD with what the booking holds now
	 * (unlocked_write_did_not_hold()), not a refusal: the payment is recorded on
	 * the order (Fable F1, Codex delta7 3).
	 *
	 * @param string|null $from The status the caller read from the database; null reads it now.
	 * @return string|null null when the payment stands; UNLOCKED_NOT_HELD; else why it was not recorded.
	 */
	private static function mark_paid_unlocked(int $booking_id, string $to, ?string $from = null): ?string
	{
		self::$unlocked_outcome = array();
		try {
			if (! self::meta_holds(self::write_booking_meta_alone($booking_id, '_mhmrentiva_payment_status', 'paid'))) {
				return self::unlocked_write_did_not_hold($booking_id, $to, (string) $from);
			}
			$from      = $from ?? self::read_booking_meta($booking_id, '_mhmrentiva_status');
			$effective = in_array($from, Status::allowed(), true) ? $from : Status::PENDING;
			if (! in_array($to, Status::allowed(), true) || ! Status::can_transition($effective, $to)) {
				return null;
			}
			$wrote = self::write_booking_meta_alone($booking_id, '_mhmrentiva_status', $to, $from);
		} catch (\RuntimeException $e) {
			if ('mhmrentiva_booking_read_failed' !== $e->getMessage()) {
				throw $e;
			}
			wp_cache_delete($booking_id, 'post_meta');
			return self::availability_unknown_notice();
		}
		if (! self::meta_holds($wrote)) {
			return self::unlocked_write_did_not_hold($booking_id, $to, $from);
		}
		if (self::META_WRITTEN === $wrote) {
			do_action('mhmrentiva_booking_status_changed', $booking_id, $effective, $to);
		}

		return null;
	}

	/**
	 * An unlocked write did not hold. Without a transaction that is not
	 * "nothing changed": under autocommit a sanitize filter's value, or a
	 * concurrent writer's, may be what persisted (Codex PR K delta7 3). So
	 * what the booking holds now is read back -- payment status and status --
	 * and the operator note is built from those values
	 * (unlocked_write_note()). No status hook runs.
	 *
	 * @param string $from The status the transition was decided on ('' when not read yet).
	 */
	private static function unlocked_write_did_not_hold(int $booking_id, string $to, string $from): string
	{
		wp_cache_delete($booking_id, 'post_meta');
		try {
			$payment = self::read_booking_meta($booking_id, '_mhmrentiva_payment_status');
			$status  = self::read_booking_meta($booking_id, '_mhmrentiva_status');
		} catch (\RuntimeException $e) {
			$payment = null;
			$status  = null;
		}
		self::$unlocked_outcome = array(
			'payment' => $payment,
			'status'  => $status,
			'from'    => $from,
			'target'  => $to,
		);

		return self::UNLOCKED_NOT_HELD;
	}

	/**
	 * The mark_paid_unlocked() answer when a write did not hold: the order
	 * note is built from what the booking holds now
	 * (self::$unlocked_outcome, report_unclaimed_payment() reason
	 * unlocked_write). Not a customer message.
	 */
	private const UNLOCKED_NOT_HELD = 'mhmrentiva_unlocked_not_held';

	/**
	 * What the booking held after an unlocked write that did not hold: its
	 * payment status and status as read back (null when unreadable), the
	 * status the move was decided on, and the status it should have reached.
	 *
	 * @var array{payment?: string|null, status?: string|null, from?: string, target?: string}
	 */
	private static array $unlocked_outcome = array();

	/**
	 * Statuses that hold no dates: a booking left in one is paid for nothing.
	 *
	 * @var list<string>
	 */
	private const RELEASED = array( Status::CANCELLED, Status::REFUNDED, Status::NO_SHOW, Status::DRAFT );

	/**
	 * The order note for an unlocked write that did not hold, from the values
	 * read back -- never "nothing was changed" or "holds its dates" unless
	 * those values say so (Codex PR K delta7 3).
	 *
	 * @param array{payment?: string|null, status?: string|null, from?: string, target?: string} $outcome
	 */
	private static function unlocked_write_note(int $booking_id, array $outcome): string
	{
		$payment = $outcome['payment'] ?? null;
		$status  = $outcome['status'] ?? null;
		$from    = (string) ( $outcome['from'] ?? '' );
		$target  = (string) ( $outcome['target'] ?? '' );

		if (null === $payment || null === $status) {
			return sprintf(
				/* translators: %d: booking id. */
				__('Payment recorded on the order, but booking #%d could not be read back afterwards: its payment status and status are not known. Check the booking before anything else.', 'mhm-rentiva'),
				$booking_id
			);
		}
		if ('paid' !== $payment) {
			return sprintf(
				/* translators: 1: booking id, 2: the booking's payment status as stored, 3: the booking status. */
				__('Payment recorded on the order, but booking #%1$d is not marked paid: its payment status is "%2$s" and its status %3$s. Check the booking.', 'mhm-rentiva'),
				$booking_id,
				'' !== $payment ? $payment : '-',
				'' === $status ? '-' : Status::get_label($status)
			);
		}
		if (in_array($status, self::RELEASED, true)) {
			return sprintf(
				/* translators: 1: booking id, 2: the booking status. */
				__('Payment recorded and booking #%1$d is marked paid, but its status is %2$s, so it no longer holds its dates. Reconcile it: give the booking its dates back, or refund the payment.', 'mhm-rentiva'),
				$booking_id,
				Status::get_label($status)
			);
		}
		if ('' === $from || $status === $from) {
			return sprintf(
				/* translators: 1: booking id, 2: the booking status it should have moved to. */
				__('Payment recorded and booking #%1$d marked paid, but it could not be moved to %2$s. Check the booking.', 'mhm-rentiva'),
				$booking_id,
				Status::get_label($target)
			);
		}

		return sprintf(
			/* translators: 1: booking id, 2: the booking status now, 3: the booking status it should have moved to. */
			__('Payment recorded and booking #%1$d marked paid, but it is %2$s instead of %3$s. Check the booking.', 'mhm-rentiva'),
			$booking_id,
			'' === $status ? '-' : Status::get_label($status),
			Status::get_label($target)
		);
	}

	/**
	 * Confirm (or complete) a booking because its order was paid, deciding and
	 * writing under the vehicle's lock in one transaction (Codex PR K delta
	 * H1). A pending booking whose deadline runs out between the payment and
	 * this write cannot be confirmed over someone who took the dates
	 * meanwhile: whoever holds the lock first decides, and the other sees it.
	 * Each write is read back; one that does not hold rolls the transaction
	 * back and nothing is announced (delta2 H2).
	 *
	 * Only a revivable booking (cancelled, pending, pending payment) is asked
	 * about its dates (Fable PR K delta M1); any other keeps the status table's
	 * old answer, unlocked -- a confirmed booking already holds its dates, and
	 * a refunded or no-show booking is closed without a word to the operator.
	 * The notification hooks run after COMMIT.
	 *
	 * @param string $to Status::CONFIRMED or Status::COMPLETED.
	 * @return string|null null when the payment stands (confirmed, completed or
	 *                     left as the status table allows); else why the booking
	 *                     was not confirmed.
	 */
	private static function confirm_paid_booking(int $booking_id, bool $remaining, string $to): ?string
	{
		wp_cache_delete($booking_id, 'post_meta');
		// Which path decides is read from the database too: a stale cached
		// status must not send a revivable booking down the unlocked path.
		try {
			$status = self::read_booking_meta($booking_id, '_mhmrentiva_status');
		} catch (\RuntimeException $e) {
			return self::availability_unknown_notice();
		}
		if (! in_array($status, self::REVIVABLE, true)) {
			return self::mark_paid_unlocked($booking_id, $to, $status);
		}

		list($vehicle_id, $start_ts, $end_ts) = self::booking_window($booking_id);
		if ($remaining && Status::CANCELLED === $status) {
			return self::booking_gone_notice();
		}
		if ($vehicle_id <= 0) {
			// No vehicle or window: nothing to lock and nobody to compete with.
			// One that still holds its dates is confirmed as before; one that
			// let go of them cannot be checked, so it is refused.
			try {
				$lost = self::booking_lost_its_dates($booking_id, true);
			} catch (\RuntimeException $e) {
				return self::availability_unknown_notice();
			}
			if ($lost) {
				return self::booking_gone_notice();
			}
			return self::mark_paid_unlocked($booking_id, $to, $status);
		}

		$outcome = self::decide_under_lock(
			$vehicle_id,
			$booking_id,
			static function () use ($booking_id, $vehicle_id, $start_ts, $end_ts, $remaining, $to) {
				$from = self::read_booking_meta($booking_id, '_mhmrentiva_status');
				if (! in_array($from, self::REVIVABLE, true)) {
					return array( 'moved', $from );
				}
				if (self::booking_lost_its_dates($booking_id, true)) {
					if ($remaining && Status::CANCELLED === $from) {
						return array( 'gone', $from );
					}
					$overlaps = \MHMRentiva\Admin\Booking\Helpers\Util::count_overlaps_locked($vehicle_id, $start_ts, $end_ts, $booking_id);
					if (null === $overlaps) {
						return array( 'unknown', $from );
					}
					if ($overlaps > 0) {
						return array( 'taken', $from );
					}
				}

				self::write_booking_meta_or_roll_back($booking_id, '_mhmrentiva_payment_status', 'paid');
				// cancelled -> pending -> confirmed|completed are both in the
				// table, so a revivable booking always reaches $to, except
				// pending_payment -> completed, which keeps its status.
				$effective = Status::CANCELLED === $from ? Status::PENDING : $from;
				if (! Status::can_transition($effective, $to)) {
					return array( 'kept', $from );
				}
				$wrote = self::write_booking_meta_or_roll_back($booking_id, '_mhmrentiva_status', $to, $from);

				// Announced only for a write that happened (Fable F2).
				return array( self::META_WRITTEN === $wrote ? 'written' : 'kept', $from );
			}
		);

		switch ($outcome[0]) {
			case 'taken':
				return self::booked_meanwhile_notice($vehicle_id, $start_ts, $end_ts);
			case 'gone':
				return self::booking_gone_notice();
			case 'write_failed':
				return self::write_failed_notice();
			case 'moved':
				// Someone else moved it out of the revivable set meanwhile; the
				// status table answers, as for any booking that holds its dates.
				return self::mark_paid_unlocked($booking_id, $to, (string) ( $outcome[1] ?? '' ));
			case 'written':
			case 'kept':
				break;
			default:
				return self::availability_unknown_notice();
		}

		if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
			\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
		}
		if ('written' === $outcome[0]) {
			do_action('mhmrentiva_booking_status_changed', $booking_id, (string) ( $outcome[1] ?? '' ), $to);
		}

		return null;
	}

	/**
	 * The classic order-pay form, before the gateway: an error notice here stops
	 * WC_Form_Handler::pay_action() (it pays only while wc_notice_count('error')
	 * is 0).
	 *
	 * @param mixed $order The order being paid.
	 */
	public static function claim_before_pay($order): void
	{
		if (! $order instanceof \WC_Order) {
			return;
		}

		$refusal = self::claim_order_bookings($order);
		if ('' === $refusal) {
			return;
		}

		$plain = self::plain_text($refusal);
		/* translators: %s: why the booking was refused, as the customer read it. */
		$order->add_order_note(sprintf(__('Booking refused, no payment taken: %s', 'mhm-rentiva'), $plain));
		wc_add_notice($plain, 'error');
	}

	/**
	 * A payment landed, or an order went on hold, for a booking that could not
	 * take its dates back. Nothing is confirmed; the operator reads why on the
	 * order and in the log. A refund through WooCommerce reverses what the
	 * payment started elsewhere (the add-on's commission reverses on the
	 * order refund).
	 *
	 * Reason unlocked_write is the other case (Fable F1, Codex delta7 3): the
	 * payment is recorded on the order, and an unlocked booking write did not
	 * hold. The note says what the booking holds now (unlocked_write_note()).
	 *
	 * @param string $reason 'dates_lost' (the default) or 'unlocked_write'.
	 * @param array{payment?: string|null, status?: string|null, from?: string, target?: string} $outcome What the booking holds now (unlocked_write).
	 */
	private static function report_unclaimed_payment(\WC_Order $order, int $booking_id, string $refusal, string $new_status, string $reason = 'dates_lost', array $outcome = array()): void
	{
		$why = self::plain_text($refusal);
		if ('unlocked_write' === $reason) {
			$note = self::unlocked_write_note($booking_id, $outcome);
		} else {
			$reason = 'dates_lost';
			$note   = 'on-hold' === $new_status
			? sprintf(
				/* translators: 1: booking id, 2: why, as the customer would have read it. */
				__('Order on hold, but booking #%1$d was not confirmed: it no longer holds its dates (%2$s). Do not accept the payment for it, or move the booking first.', 'mhm-rentiva'),
				$booking_id,
				$why
			)
			: sprintf(
				/* translators: 1: booking id, 2: why, as the customer would have read it. */
				__('Payment recorded, but booking #%1$d was not confirmed: it no longer holds its dates (%2$s). Refund the payment in WooCommerce, or move the booking.', 'mhm-rentiva'),
				$booking_id,
				$why
			);
		}
		$order->add_order_note($note);
		AdvancedLogger::error_linked(
			$note,
			$booking_id,
			array(
				'order_id' => $order->get_id(),
				'surface'  => 'wc_status_change',
				'reason'   => $reason,
			),
			AdvancedLogger::CATEGORY_PAYMENT
		);
	}

	/**
	 * A rental order placed through the Store API (a Checkout block the classic
	 * swap did not reach): the booking WC_Checkout would have made, on the
	 * payment type the snapshot was priced with. That type is what the line
	 * charges, so nothing here may change it.
	 *
	 * @param \WC_Order $order The order, about to go to the gateway.
	 * @throws \Exception When the booking is refused (halt_checkout()).
	 */
	public static function store_api_order_processed($order): void
	{
		if (! $order instanceof \WC_Order) {
			return;
		}

		// The Store API's one cash-on-delivery rule, on both routes (POST
		// /checkout and /checkout/{id}): the gateway list is left alone in REST
		// (without_cash_on_delivery()), and the order actually paid decides
		// here. A rental order paid with COD is stopped before any booking or
		// payment (Codex PR K delta7).
		if ('cod' === $order->get_payment_method() && self::order_has_booking($order)) {
			// Not left on the order: RentalMoneyLabels would read a refused
			// order as "pay at pickup" (Fable PR K delta8 3).
			$order->set_payment_method('');
			$order->set_payment_method_title('');
			$order->save();
			self::halt_checkout(
				__('Cash on delivery is not available for a booking. Please choose another payment method.', 'mhm-rentiva'),
				$order->get_id(),
				__('Payment method refused: cash on delivery is not available for a booking. No booking was made and no payment was taken.', 'mhm-rentiva')
			);
		}

		$snapshots = self::pending_booking_snapshots($order);
		if (array() === $snapshots) {
			// A retry (POST /checkout/{id}, or a reused draft) of an order that
			// already has its bookings: a failed payment may have cancelled
			// them and someone else may hold the dates now.
			if (array() !== self::linked_booking_ids($order)) {
				self::create_booking_from_order($order->get_id(), null, $order);
			}
			return;
		}

		self::create_booking_from_order($order->get_id(), null, $order);

		// One order-level type only when it is true of every line; each booking
		// carries its own either way.
		$types = array_unique(array_map(array( self::class, 'snapshot_payment_type' ), array_values($snapshots)));
		if (1 === count($types)) {
			$order->update_meta_data('_mhmrentiva_wc_payment_type', reset($types));
			$order->update_meta_data('_mhmrentiva_booking_payment_type', reset($types));
		}
		$order->add_order_note(__('Rentiva booking created from the block checkout. The customer could not change the payment type there.', 'mhm-rentiva'));
		$order->save();
	}

	/**
	 * Cash on delivery is never offered for a rental (owner, 2026-11-02): a full
	 * payment at the door makes no sense, and a deposit paid at the door is no
	 * deposit; what is left is paid at pickup anyway. The store's own COD setting
	 * is not touched, so the shop's own orders keep it.
	 *
	 * Like the other checkout rules it follows any Rentiva line, so a mixed cart
	 * loses it too: one order, one gateway. The order-pay page answers from the
	 * order its URL names (CheckoutJourney::pay_page_order()), never from the
	 * cart; with a wrong key the list is WooCommerce's, which refuses the page.
	 * Existing COD orders keep their labels (RentalMoneyLabels::order_state()).
	 *
	 * The list only shapes what is shown; refusals on every entry decide
	 * (refuse_cash_on_delivery_at_checkout(), refuse_cash_on_delivery_on_pay(),
	 * store_api_order_processed()). In any REST request (wp_is_rest_endpoint())
	 * the list is left alone: the Store API builds it from the session's cart
	 * even when it pays another order, and telling its routes apart from REST
	 * state proved fragile (Codex PR K delta9: a caught inner exception, an
	 * upper-case route). There the order hook decides for the order actually
	 * paid, on both routes, and a shop order is never touched.
	 *
	 * Accepted cost (ruling, delta9): with the classic cart and checkout swap
	 * turned off, the Checkout block's REST refreshes list COD for a rental,
	 * and choosing it is refused with the COD message. The product's own path
	 * is the classic checkout, so this is a fallback only.
	 *
	 * @param mixed $gateways The available gateways, by id.
	 * @return mixed
	 */
	public static function without_cash_on_delivery($gateways)
	{
		// A list without COD, or one left alone, must not keep an earlier
		// rental's message (Fable PR K delta6 3, Codex delta7 note).
		self::$cod_was_the_only_gateway = false;
		if (! is_array($gateways) || ! isset($gateways['cod'])) {
			return $gateways;
		}
		if (function_exists('wp_is_rest_endpoint') && wp_is_rest_endpoint()) {
			return $gateways;
		}

		if (function_exists('is_checkout_pay_page') && is_checkout_pay_page()) {
			$order  = CheckoutJourney::pay_page_order();
			$rental = null !== $order && self::order_has_booking($order);
		} else {
			$rental = self::cart_has_booking();
		}

		if ($rental) {
			unset($gateways['cod']);
			self::$cod_was_the_only_gateway = array() === $gateways;
		}

		return $gateways;
	}

	/**
	 * The last without_cash_on_delivery() call removed cash on delivery from a
	 * rental's list, and nothing was left.
	 */
	private static bool $cod_was_the_only_gateway = false;

	/**
	 * Classic checkout, whatever the gateway list says: a rental cart paid
	 * with cash on delivery is refused in validation, before WooCommerce calls
	 * any gateway (Codex PR K delta8: the list is only what is shown; an
	 * integration's caught REST exception can leave it with COD).
	 *
	 * @param mixed $data   The posted checkout data.
	 * @param mixed $errors The validation errors.
	 */
	public static function refuse_cash_on_delivery_at_checkout($data, $errors): void
	{
		if (! $errors instanceof \WP_Error || ! is_array($data) || 'cod' !== (string) ( $data['payment_method'] ?? '' )) {
			return;
		}
		if (self::cart_has_booking()) {
			// WooCommerce's own "Invalid payment method." (COD not in the list)
			// would stand next to ours: one error, the one that says why
			// (Fable PR K delta9 2).
			$errors->remove('payment');
			$errors->add('payment', __('Cash on delivery is not available for a booking. Please choose another payment method.', 'mhm-rentiva'));
		}
	}

	/**
	 * Classic order-pay form, whatever the gateway list says: a rental order
	 * paid with cash on delivery gets an error notice, and
	 * WC_Form_Handler::pay_action() calls the gateway only while there is none.
	 * woocommerce_before_pay_action runs after WooCommerce checked the nonce
	 * and the order key.
	 *
	 * @param mixed $order The order being paid.
	 */
	public static function refuse_cash_on_delivery_on_pay($order): void
	{
		self::$cod_refused_pay_order = 0;
		// The same nonce WC_Form_Handler::pay_action() checked before this action.
		$nonce = isset($_REQUEST['woocommerce-pay-nonce']) ? sanitize_text_field(wp_unslash( (string) $_REQUEST['woocommerce-pay-nonce'])) : '';
		if ('' === $nonce && isset($_REQUEST['_wpnonce'])) {
			$nonce = sanitize_text_field(wp_unslash( (string) $_REQUEST['_wpnonce']));
		}
		if (! wp_verify_nonce($nonce, 'woocommerce-pay')) {
			return;
		}
		$method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash( (string) $_POST['payment_method'])) : '';
		if ('cod' !== $method || ! $order instanceof \WC_Order || ! self::order_has_booking($order)) {
			return;
		}
		wc_add_notice(__('Cash on delivery is not available for a booking. Please choose another payment method.', 'mhm-rentiva'), 'error');
		self::$cod_refused_pay_order = (int) $order->get_id();
	}

	/**
	 * The order refuse_cash_on_delivery_on_pay() refused on this request; 0 for none.
	 */
	private static int $cod_refused_pay_order = 0;

	/**
	 * After a refused classic order-pay (woocommerce_after_pay_action, which
	 * WC_Form_Handler::pay_action() reaches when no payment went through):
	 * WooCommerce set the order's method to COD and saved it before it counted
	 * the notices, and RentalMoneyLabels would read it as "pay at pickup". That
	 * order only gets its method and title cleared (Codex PR K delta9 3), and
	 * only while COD is what WooCommerce wrote: a tampered post it refused
	 * before set_payment_method() keeps the order's own method (Fable delta10 1).
	 *
	 * @param mixed $order The order the form was for.
	 */
	public static function forget_refused_cash_on_delivery($order): void
	{
		$refused                     = self::$cod_refused_pay_order;
		self::$cod_refused_pay_order = 0;
		if (0 === $refused || ! $order instanceof \WC_Order || $refused !== (int) $order->get_id() || 'cod' !== $order->get_payment_method()) {
			return;
		}
		$order->set_payment_method('');
		$order->set_payment_method_title('');
		$order->save();
	}

	/**
	 * A rental whose only gateway was cash on delivery would read WooCommerce's
	 * generic "no available payment methods" (Fable PR K delta6 F4). The classic
	 * checkout and order-pay templates print this filter's answer through
	 * wc_print_notice(); the Checkout block has its own message. On the classic
	 * checkout, a customer with no billing country yet reads WooCommerce's
	 * "fill in your details" (templates/checkout/payment.php), which stays
	 * (Fable delta7 1).
	 *
	 * @param mixed $message WooCommerce's message, escaped.
	 * @return mixed
	 */
	public static function no_payment_methods_message($message)
	{
		if (! self::$cod_was_the_only_gateway) {
			return $message;
		}
		$pay_page = function_exists('is_checkout_pay_page') && is_checkout_pay_page();
		if (! $pay_page && function_exists('WC') && WC()->customer instanceof \WC_Customer && '' === (string) WC()->customer->get_billing_country()) {
			return $message;
		}

		return esc_html__('Cash on delivery is not offered for rentals, and no other payment method is set up yet. Please contact us to complete your booking.', 'mhm-rentiva');
	}

	/**
	 * Does the order carry a Rentiva line -- one waiting for its booking, or one
	 * (or the order itself, a remaining payment) linked to a booking?
	 */
	private static function order_has_booking(\WC_Order $order): bool
	{
		return array() !== self::pending_booking_snapshots($order) || array() !== self::linked_booking_ids($order);
	}

	/**
	 * The Store API's cart check on the checkout request: the same refusals as
	 * validate_checkout_availability(), reported on the WP_Error it passes, and
	 * on that request the line stays. (A cart GET goes through
	 * woocommerce_check_cart_items, where validate_cart_availability() removes
	 * a refused line.) A retry of the same order does not collide with the
	 * booking its first attempt made (own_booking_of_cart_line()).
	 *
	 * @param \WP_Error $errors Collected by WooCommerce.
	 * @param mixed     $cart   The cart.
	 */
	public static function store_api_cart_errors($errors, $cart): void
	{
		if (! $errors instanceof \WP_Error || ! $cart instanceof \WC_Cart) {
			return;
		}

		foreach ($cart->get_cart() as $cart_item) {
			if (empty($cart_item['mhmrentiva_booking_pending']) || ! isset($cart_item['mhmrentiva_booking_data'])) {
				continue;
			}

			$booking_data = $cart_item['mhmrentiva_booking_data'];
			$vehicle_id   = (int) ( $booking_data['vehicle_id'] ?? 0 );
			if (! $vehicle_id) {
				continue;
			}

			if (self::is_refused_rental($booking_data, $vehicle_id)) {
				$errors->add('mhmrentiva_booking_refused', self::rental_refusal_notice($vehicle_id));
				continue;
			}

			$start_ts = self::parse_datetime_to_timestamp( (string) ( $booking_data['pickup_date'] ?? '' ), (string) ( $booking_data['pickup_time'] ?? '00:00' ));
			$end_ts   = self::parse_datetime_to_timestamp( (string) ( $booking_data['dropoff_date'] ?? '' ), (string) ( $booking_data['dropoff_time'] ?? '23:59' ));
			if (! $start_ts || ! $end_ts) {
				$errors->add('mhmrentiva_booking_refused', __('Invalid booking dates. Please try again.', 'mhm-rentiva'));
				continue;
			}

			if (\MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($vehicle_id, $start_ts, $end_ts, self::own_booking_of_cart_line($booking_data))) {
				$errors->add('mhmrentiva_booking_refused', self::booked_meanwhile_notice($vehicle_id, $start_ts, $end_ts));
			}
		}
	}

	/**
	 * The dates were free when the customer chose them and are not any more.
	 */
	private static function booked_meanwhile_notice(int $vehicle_id, int $start_ts, int $end_ts): string
	{
		return sprintf(
			/* translators: 1: Vehicle title, 2: Pickup date, 3: Dropoff date */
			__('Sorry, the vehicle "%1$s" is no longer available for the selected dates (%2$s - %3$s). The vehicle may have been booked by another customer. Please select different dates or another vehicle.', 'mhm-rentiva'),
			get_the_title($vehicle_id),
			wp_date(get_option('date_format'), $start_ts),
			wp_date(get_option('date_format'), $end_ts)
		);
	}

	/**
	 * Fallback hook handler for woocommerce_thankyou (after payment)
	 * Only creates booking if it wasn't created by the primary hook
	 */
	public static function create_booking_from_order_fallback($order_id): void
	{
		if (! class_exists('WooCommerce')) {
			return;
		}

		$order = function_exists('wc_get_order') ? call_user_func('wc_get_order', $order_id) : false;
		if (! $order) {
			return;
		}

		// Nothing left to book: every rental line already carries its booking. An
		// order-level id alone does not say that (a resumed order's fresh lines).
		// A linked booking is not re-checked here: this is a page view, and
		// handle_order_status_change() asked when the payment landed.
		if (array() === self::pending_booking_snapshots($order)) {
			return;
		}

		// Only attempt to create if order is paid/processing (fallback safety check)
		if (! $order->is_paid() && $order->get_status() !== 'processing') {
			return;
		}

		self::create_booking_from_order($order_id);
	}

	/**
	 * Create booking from booking data array
	 */
	private static function create_booking_from_data(array $booking_data, int $order_id, bool $halt = false): ?int
	{
		$booking_data['payment_type'] = self::snapshot_payment_type($booking_data);
		$booking_data                 = self::with_order_contact($booking_data, $order_id);

		// Parse dates to timestamps
		$start_ts = self::parse_datetime_to_timestamp($booking_data['pickup_date'], $booking_data['pickup_time']);
		$end_ts   = self::parse_datetime_to_timestamp($booking_data['dropoff_date'], $booking_data['dropoff_time']);

		if (! $start_ts || ! $end_ts) {
			AdvancedLogger::error(
				'Invalid dates in booking data',
				array(
					'order_id'     => $order_id,
					'booking_data' => array_intersect_key($booking_data, array_flip(array( 'pickup_date', 'dropoff_date' ))),
				),
				AdvancedLogger::CATEGORY_BOOKING
			);
			return null;
		}

		// Last line of defence for a rental of a transfer-only vehicle (the
		// checkout validation normally stops it before payment). Refused before
		// the before-creation hook and the lock: nothing is written, and the
		// order is cancelled the same way a date conflict cancels it below.
		if (self::is_refused_rental($booking_data, (int) $booking_data['vehicle_id'])) {
			AdvancedLogger::error(
				'Cannot create booking - vehicle is not offered for rental',
				array(
					'order_id'   => $order_id,
					'vehicle_id' => $booking_data['vehicle_id'],
				),
				AdvancedLogger::CATEGORY_BOOKING
			);
			if ($halt) {
				self::halt_checkout(self::rental_refusal_notice( (int) $booking_data['vehicle_id']), $order_id);
			}
			$order = function_exists('wc_get_order') ? call_user_func('wc_get_order', $order_id) : false;
			if ($order) {
				$order->update_status('cancelled', __('Booking cancelled: Vehicle is not available for rental.', 'mhm-rentiva'));
			}
			return null;
		}

		// Clear cache first so the conflict check below reads fresh data.
		if (class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
			\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($booking_data['vehicle_id']);
		}

		// Create booking post
		$post_data = array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: %s: vehicle title */
				__('Booking - %s', 'mhm-rentiva'),
				get_the_title($booking_data['vehicle_id'])
			),
			'meta_input'  => array(
				'_mhmrentiva_vehicle_id'           => $booking_data['vehicle_id'],
				// ⭐ Standard meta keys (for queries and compatibility)
				'_mhmrentiva_start_date'           => $booking_data['pickup_date'],
				'_mhmrentiva_end_date'             => $booking_data['dropoff_date'],
				'_mhmrentiva_start_ts'             => $start_ts,
				'_mhmrentiva_end_ts'               => $end_ts,
				'_mhmrentiva_start_time'           => $booking_data['pickup_time'],
				'_mhmrentiva_end_time'             => $booking_data['dropoff_time'],
				// ⭐ User-friendly meta keys (pickup/dropoff for clarity)
				'_mhmrentiva_pickup_date'          => $booking_data['pickup_date'],
				'_mhmrentiva_dropoff_date'         => $booking_data['dropoff_date'],
				'_mhmrentiva_pickup_time'          => $booking_data['pickup_time'],
				'_mhmrentiva_dropoff_time'         => $booking_data['dropoff_time'],
				'_mhmrentiva_guests'               => $booking_data['guests'],
				'_mhmrentiva_customer_user_id'     => $booking_data['customer_user_id'],
				'_mhmrentiva_customer_name'        => $booking_data['customer_name'],
				'_mhmrentiva_customer_first_name'  => $booking_data['customer_first_name'],
				'_mhmrentiva_customer_last_name'   => $booking_data['customer_last_name'],
				'_mhmrentiva_customer_email'       => $booking_data['customer_email'],
				'_mhmrentiva_customer_phone'       => $booking_data['customer_phone'],
				'_mhmrentiva_status'               => 'pending',
				'_mhmrentiva_booking_type'         => 'booking_form',
				'_mhmrentiva_created_via'          => 'woocommerce_checkout',
				'_mhmrentiva_woocommerce_order_id' => $order_id,
				'_mhmrentiva_payment_type'         => $booking_data['payment_type'],
				'_mhmrentiva_payment_method'       => $booking_data['payment_method']  ?? '',
				'_mhmrentiva_payment_gateway'      => $booking_data['payment_gateway'] ?? '',
				'_mhmrentiva_payment_status'       => 'pending',
				'_mhmrentiva_deposit_amount'       => $booking_data['deposit_amount'],
				'_mhmrentiva_remaining_amount'     => $booking_data['remaining_amount'],
				'_mhmrentiva_deposit_type'         => $booking_data['deposit_type']    ?? '',
				'_mhmrentiva_payment_display'      => $booking_data['payment_display'],
				'_mhmrentiva_total_price'          => $booking_data['total_price'],
				'_mhmrentiva_rental_days'          => $booking_data['rental_days'],
				'_mhmrentiva_selected_addons'      => $booking_data['selected_addons'],
				'_mhmrentiva_cancellation_policy'  => $booking_data['cancellation_policy'] ?? self::get_default_cancellation_policy(),
				// ⭐ Ensure payment_deadline is always set for auto-cancellation
				'_mhmrentiva_payment_deadline'     => ! empty($booking_data['payment_deadline']) ? $booking_data['payment_deadline'] : self::get_payment_deadline(),
			),
		);

		// Neutral extension point: fires immediately before the booking record
		// is created, on the REAL live booking-creation path (WooCommerce
		// checkout → woocommerce_checkout_order_processed → this method). The
		// admin-post entry point in Handler::handle() fires the same hook for
		// compatibility, but that path has no live UI trigger — this is the
		// one that actually runs for real customer bookings. The WC order
		// already exists at this point (checkout has completed), but a
		// subscriber that halts the request (e.g. wp_die() for missing GDPR
		// consent) still does so before the booking post itself is written.
		// No-op by default; the add-on's GDPRManager only enforces when GDPR +
		// consent-required are both explicitly enabled by the admin.
		// ⭐ CRITICAL: the conflict check and the INSERT are one atomic critical
		// section. Before 6.0.3 they were two bare statements under autocommit:
		// has_overlap_locked() issued SELECT ... FOR UPDATE without an open
		// transaction, so MySQL released the row lock the instant that statement
		// finished -- well before the INSERT. Two concurrent checkouts could both
		// read "free" and both insert, double-booking one vehicle.
		//
		// Locker::withLock() opens the transaction, takes FOR UPDATE on the
		// vehicle's postmeta rows, runs this callback and COMMITs (ROLLBACK +
		// rethrow on exception). The callback returns false for a date conflict
		// and null for a failed insert, so the caller can tell the two apart
		// after the transaction has closed.
		// Fired OUTSIDE the transaction below. Its contract is only "before the
		// booking record is created", and it must not run inside the critical
		// section: a subscriber that halts the request (Pro's GDPRManager calls
		// wp_die() when consent is missing) would otherwise leave the transaction
		// open while WordPress runs its shutdown actions, so those writes join a
		// transaction that is rolled back on disconnect -- and the vehicle's row
		// lock would be held for the whole of it. Halting here still prevents the
		// booking, because nothing has been written yet.
		do_action('mhmrentiva_before_booking_creation', array(
			'vehicle_id'      => $booking_data['vehicle_id'],
			'pickup_date'     => $booking_data['pickup_date'],
			'pickup_time'     => $booking_data['pickup_time'],
			'dropoff_date'    => $booking_data['dropoff_date'],
			'dropoff_time'    => $booking_data['dropoff_time'],
			'contact_name'    => $booking_data['customer_name']  ?? '',
			'contact_email'   => $booking_data['customer_email'] ?? '',
			'contact_phone'   => $booking_data['customer_phone'] ?? '',
			'selected_addons' => $booking_data['selected_addons'] ?? array(),
			'order_id'        => $order_id,
		));

		$creation_result = \MHMRentiva\Admin\Booking\Helpers\Locker::withLock(
			(int) $booking_data['vehicle_id'],
			static function () use ($post_data, $booking_data, $order_id, $start_ts, $end_ts) {
				// Authoritative conflict check, now genuinely holding the lock. A
				// count that could not be read books nothing (null: the caller's
				// "could not be created" path, not "taken by someone").
				$overlaps = \MHMRentiva\Admin\Booking\Helpers\Util::count_overlaps_locked( (int) $booking_data['vehicle_id'], $start_ts, $end_ts);
				if (null === $overlaps) {
					return null;
				}
				if ($overlaps > 0) {
					return false;
				}

				// $wp_error = true. Without it WordPress returns 0 on failure
				// (documented as "the value 0 or WP_Error"), which the
				// is_wp_error() guard below could never catch: the callback
				// returned 0, is_int(0) was true, and the caller -- which only
				// tests truthiness -- silently left the paid WooCommerce order
				// with no booking attached and nothing in the log.
				// The order's values are unslashed; wp_insert_post() unslashes the
				// post fields and update_post_meta() each meta_input value
				// (backslash class).
				$booking_id = wp_insert_post(wp_slash($post_data), true);

				if (is_wp_error($booking_id) || (int) $booking_id <= 0) {
					AdvancedLogger::error(
						'Failed to create booking from order',
						array(
							'order_id' => $order_id,
							'error'    => is_wp_error($booking_id)
								? $booking_id->get_error_message()
								: 'wp_insert_post() returned ' . (int) $booking_id,
						),
						AdvancedLogger::CATEGORY_BOOKING
					);
					return null;
				}

				$booking_id = (int) $booking_id;

				// The booking row now exists, but only inside this transaction, and
				// wp_insert_post() has already populated the object cache with it.
				// If anything below throws, withLock() rolls the row back while the
				// cache keeps serving a post that no longer exists in the database --
				// so drop it on the way out.
				try {
					// ⭐ Ensure payment_deadline is set (meta_input may not always work)
					// Double-check and set payment_deadline if missing
					$payment_deadline = get_post_meta($booking_id, '_mhmrentiva_payment_deadline', true);
					if (empty($payment_deadline)) {
						$deadline = self::get_payment_deadline();
						update_post_meta($booking_id, '_mhmrentiva_payment_deadline', $deadline);
						AdvancedLogger::info(
							'Payment deadline was missing for booking, set automatically',
							array(
								'booking_id' => $booking_id,
								'deadline'   => $deadline,
							),
							AdvancedLogger::CATEGORY_BOOKING
						);
					}

					// Add booking history note
					if (class_exists('MHMRentiva\Admin\Booking\Meta\BookingMeta')) {
						\MHMRentiva\Admin\Booking\Meta\BookingMeta::add_history_note(
							$booking_id,
							__('Booking created from WooCommerce order', 'mhm-rentiva'),
							'system'
						);
					}
				} catch (\Throwable $e) {
					clean_post_cache($booking_id);
					throw $e;
				}

				return $booking_id;
			}
		);

		// Date conflict: log and cancel the order OUTSIDE the transaction, so a
		// WooCommerce write cannot be rolled back with the booking guard and does
		// not hold the vehicle's row lock while it runs.
		if (false === $creation_result) {
			AdvancedLogger::error(
				'Cannot create booking - vehicle already booked for selected dates',
				array(
					'order_id'   => $order_id,
					'vehicle_id' => $booking_data['vehicle_id'],
				),
				AdvancedLogger::CATEGORY_BOOKING
			);
			if ($halt) {
				self::halt_checkout(self::booked_meanwhile_notice( (int) $booking_data['vehicle_id'], $start_ts, $end_ts), $order_id);
			}
			// ⚠️ Cancel the WooCommerce order if booking cannot be created
			$order = function_exists('wc_get_order') ? call_user_func('wc_get_order', $order_id) : false;
			if ($order) {
				$order->update_status('cancelled', __('Booking cancelled: Vehicle already booked for selected dates.', 'mhm-rentiva'));
			}
			return null;
		}

		return is_int($creation_result) ? $creation_result : null;
	}

	/**
	 * Handle order status changes
	 */
	public static function handle_order_status_change($order_id, $old_status, $new_status, $order)
	{
		$items = $order->get_items();

		// One payment-history scope per booking on the order (plan r4 R9),
		// all opened BEFORE the loop and closed after it: handling one booking
		// can run another order's hook (Status::update_status ->
		// sync_completed_to_wc) that changes a booking this loop has not
		// reached yet; with its scope already open, that change and this
		// loop's later one make one row, not two (Codex #170).
		$scopes = array();
		try {
			foreach ($items as $item) {
				$booking_id = (int) $item->get_meta('_mhmrentiva_booking_id');
				if ($booking_id && ! isset($scopes[ $booking_id ])) {
					$scopes[ $booking_id ] = PaymentView::begin($booking_id);
				}
			}

			foreach ($items as $item) {
				$booking_id = (int) $item->get_meta('_mhmrentiva_booking_id');

				if ($booking_id) {
					self::apply_order_status_to_booking($booking_id, $order_id, $old_status, $new_status, $order);
				}
			}
		} finally {
			foreach ($scopes as $scope) {
				PaymentView::end($scope);
			}
		}
	}

	/**
	 * One booking of handle_order_status_change(): its order-status work,
	 * unchanged, moved here so that the caller's payment scope closes on
	 * every exit (each former `continue` is a `return`).
	 *
	 * @param int|string $order_id
	 * @param string     $old_status
	 * @param string     $new_status
	 * @param \WC_Order  $order
	 */
	private static function apply_order_status_to_booking(int $booking_id, $order_id, $old_status, $new_status, $order): void
	{
		// Money landed (a gateway's late callback, an operator marking the
		// order paid): a booking that lost its dates is not revived over
		// someone else's. Paid: confirm_paid_booking() decides and writes
		// under the vehicle's lock. On hold: only a revivable booking is
		// asked (a refunded or no-show one keeps the old, silent answer).
		// The operator is told; the money is theirs to return.
		$is_remaining = self::is_remaining_order($order);
		if (in_array($new_status, array( 'processing', 'completed' ), true)) {
			// Same rule as the deposit screen, and for the same reason:
			// a date-only comparison completes a booking at midnight on
			// the drop-off day, hours before the car is back.
			$target  = 'completed' === $new_status && \MHMRentiva\Admin\Booking\Helpers\Util::rental_has_ended($booking_id) ? Status::COMPLETED : Status::CONFIRMED;
			$refusal = self::confirm_paid_booking($booking_id, $is_remaining, $target);
			if (self::UNLOCKED_NOT_HELD === $refusal) {
				// An unlocked write did not hold: the note says what the
				// booking holds now. Only a paid mark that held reaches the
				// mapping below, which may clear the remaining balance
				// (Fable PR K delta8 2).
				$outcome                = self::$unlocked_outcome;
				self::$unlocked_outcome = array();
				self::report_unclaimed_payment($order, $booking_id, '', (string) $new_status, 'unlocked_write', $outcome);
				if ('paid' !== ( $outcome['payment'] ?? null )) {
					return;
				}
			} elseif (null !== $refusal) {
				self::report_unclaimed_payment($order, $booking_id, $refusal, (string) $new_status);
				return;
			}
		} elseif ('on-hold' === $new_status && in_array( (string) get_post_meta($booking_id, '_mhmrentiva_status', true), self::REVIVABLE, true)) {
			$revived = array();
			$refusal = self::claim_booking($booking_id, $is_remaining, $revived);
			if ('' !== $refusal) {
				self::report_unclaimed_payment($order, $booking_id, $refusal, 'on-hold');
				return;
			}
			self::announce_revivals($revived);
		}

		// Status Mapping Logic
		switch ($new_status) {
			case 'completed':
				// WC order completed - the booking is paid and confirmed, or
				// completed once the rental has ended: written above, under
				// the lock (confirm_paid_booking()).
				// Clear the remaining balance only when THIS order is the one
				// that settles it. Until 6.1.0 the branch looked only at the
				// booking's payment_type, so completing the FIRST (deposit)
				// order wiped a debt the customer had never paid -- and
				// sync_completed_to_wc() drives the original order to completed
				// itself, so the plugin could trigger it with nobody touching
				// the order. The processing branch below has always carried
				// this ownership check; this is the same rule, same key.
				if ($is_remaining) {
					self::settle_remaining_from_order($booking_id, $order, (string) $old_status);
				}
				break;

			case 'processing':
				// Payment confirmed - paid and confirmed above, under the lock
				// (confirm_paid_booking()). Full payment: nothing is left to
				// pay. The remaining payment's own order settles the balance
				// only when it was paid against it (PR-2 A3).
				if ($is_remaining) {
					self::settle_remaining_from_order($booking_id, $order, (string) $old_status);
				} elseif (get_post_meta($booking_id, '_mhmrentiva_payment_type', true) === 'full') {
					update_post_meta($booking_id, '_mhmrentiva_remaining_amount', 0);
				}
				break;

			case 'on-hold':
				// Payment pending (Bank transfer etc.)
				// Same ownership rule the completed and processing branches
				// carry, and for the same reason: a REMAINING order going
				// on-hold says nothing about a deposit the customer already
				// paid. Without this, choosing bank transfer for the
				// remainder demoted the booking to pending/pending --
				// precisely the pair AutoCancel sweep #1 selects, so the
				// plugin cancelled a healthy booking and its paid deposit
				// order along with it.
				if ($order->get_meta('_mhmrentiva_is_remaining_payment') === '1'
					&& get_post_meta($booking_id, '_mhmrentiva_payment_status', true) === 'paid') {
					break;
				}

				update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');
				Status::update_status($booking_id, 'pending', get_current_user_id());
				break;

			case 'cancelled':
			case 'failed':
				// A remaining-payment order is the collection instrument for
				// the balance, never the booking itself (PR-2 A1). Its failure
				// or cancellation used to cancel the whole booking when no
				// other WooCommerce payment existed -- a manual booking whose
				// link was sent before anything was collected lost its dates to
				// one declined card. It takes the sibling branch below instead:
				// the booking stays, a human is told.
				$has_paid_sibling = self::has_paid_sibling_order( $booking_id, (int) $order_id );
				if ( $is_remaining || $has_paid_sibling ) {
					// A sibling order still holds paid money. Cancelling
					// the collection instrument is not cancelling the
					// debt: the balance stays, the dead order id is
					// cleared so the operator can issue a new payment
					// link, and a human is told.
					//
					// Only the remaining-order pointer is ever cleared
					// here, never _mhmrentiva_woocommerce_order_id: if
					// the DEPOSIT order is the one dying, deleting the
					// primary key would discard the only reference to
					// the (still paid) deposit order -- data loss, and
					// worse than the stale pointer this leaves instead.
					//
					// And the pointer goes only when the order is CANCELLED and
					// NEVER PAID (PR-2 A1, Codex r4 B1). A failed order is
					// retried on the same order, so it stays the link. A paid
					// order that is later cancelled still holds the money --
					// cancelled is not a refund, and WooCommerce keeps its
					// date_paid -- and the pointer is how PaymentState::orders()
					// and the refund service find it; clearing it would move
					// that money to the offline channel and its refund off the
					// gateway.
					$dead_link = 'cancelled' === $new_status
						&& null === $order->get_date_paid()
						&& (int) get_post_meta( $booking_id, '_mhmrentiva_remaining_order_id', true ) === (int) $order_id;
					if ( $dead_link ) {
						delete_post_meta( $booking_id, '_mhmrentiva_remaining_order_id' );
					}

					if ( $is_remaining && 'cancelled' === $new_status && null !== $order->get_date_paid() ) {
						AdvancedLogger::error_linked(
							sprintf(
								/* translators: 1: the WooCommerce order id that was cancelled, 2: the booking id it belongs to. */
								__( 'Remaining-payment order #%1$d was cancelled on booking #%2$d after it had been paid. The money is still on that order: refund it, or restore the order.', 'mhm-rentiva' ),
								(int) $order_id,
								$booking_id
							),
							$booking_id,
							array(
								'order_id' => (int) $order_id,
								'surface'  => 'wc_status_change',
								'reason'   => 'paid_remaining_order_cancelled',
							),
							AdvancedLogger::CATEGORY_SYSTEM
						);
					}

					// The e-mail and its failure log say "a sibling order still
					// holds paid money". A remaining order with no such sibling
					// (nothing collected yet) says what did happen, on the order
					// itself, instead (Codex code r1).
					if ( ! $has_paid_sibling ) {
						$order->add_order_note( __( 'The booking was kept: a remaining-payment order that fails or is cancelled does not cancel its booking. Send the customer a new payment link if one is needed.', 'mhm-rentiva' ) );
						break;
					}

					// The bool is load-bearing, same reasons as
					// AutoCancel.php's cancel_booking_with_orders():
					// send_*_email() returns false without throwing
					// when admin_email does not validate
					// (NotificationHelper.php:64-66) and when
					// wp_mail() fails, and src/ registers no
					// wp_mail_failed listener. Discarding it here is
					// worse than that site: there the booking lands in
					// a visible needs_review state, but here the
					// remaining-order pointer is already deleted and
					// the booking looks perfectly healthy -- silently
					// dropping the e-mail leaves nothing else for
					// anyone to find.
					$helper_exists = class_exists( '\MHMRentiva\Helpers\NotificationHelper' );
					$notified      = $helper_exists
						&& \MHMRentiva\Helpers\NotificationHelper::send_order_cancelled_on_live_booking_email( $booking_id, (int) $order_id );

					if ( ! $notified ) {
						// Task 14b item 1: was error() + add(), the
						// same pair and same reasons as AutoCancel.php's
						// lock-refusal/notify-failure branches; one call
						// now covers both.
						//
						// Task 14b item 7: the reason used to read
						// 'notification_failed' even when
						// $helper_exists was false -- practically
						// unreachable (NotificationHelper ships in the
						// Lite manifest), but the exact class item 2
						// exists for: a reason this branch cannot
						// actually know is wrong to state as fact.
						AdvancedLogger::error_linked(
							sprintf(
								/* translators: 1: the WooCommerce order id that was cancelled/failed, 2: the booking id it belongs to. */
								__( 'Order #%1$d was cancelled/failed on booking #%2$d while a sibling order still holds paid money, but the notification e-mail could not be sent -- no one has been told a new payment link may be needed.', 'mhm-rentiva' ),
								(int) $order_id,
								$booking_id
							),
							$booking_id,
							array(
								'order_id' => (int) $order_id,
								'surface'  => 'wc_status_change',
								'reason'   => $helper_exists ? 'notification_failed' : 'helper_missing',
							),
							AdvancedLogger::CATEGORY_SYSTEM
						);
					}

					break;
				}

				Status::update_status($booking_id, 'cancelled', get_current_user_id());
				break;

			case 'refunded':
				// This switch operates per ORDER, but WooCommerce flips an
				// order's own status to 'refunded' the moment THAT order is
				// fully drained -- not when the booking is. A deposit
				// booking's smaller leg routinely hits zero long before the
				// larger one does, and this handler runs BEFORE
				// handle_order_refunded(): wc_create_refund() changes the
				// order's own status (WC 11.0.1 wc-order-functions.php :731)
				// before it fires woocommerce_refund_created (:742).
				// Blindly mirroring the per-order flip here is the exact
				// terminal mid-walk defect Task 8 removed from that hook,
				// one hop earlier -- so this asks PaymentState the same
				// booking-level question before touching the booking.
				//
				// _mhmrentiva_payment_status is left untouched here on
				// purpose. handle_order_refunded() runs immediately after
				// this (same wc_create_refund() call) and is that meta's
				// single writer for a refund event; writing a value here
				// that gets overwritten a moment later is a second writer
				// under a new name -- and a wrong one, during the not-fully-
				// refunded case, is live for however long that gap is.
				if (PaymentState::forBooking($booking_id)->isFullyRefunded()) {
					Status::update_status($booking_id, 'refunded', get_current_user_id());
				}
				break;
		}

		// Add log
		$logs   = get_post_meta($booking_id, '_mhmrentiva_booking_logs', true) ?: array();
		$logs[] = array(
			'action'    => 'wc_status_change',
			'timestamp' => current_time('mysql'),
			'user_id'   => 0, // System
			'data'      => array(
				'order_id'   => $order_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
			),
		);
		// Rewritten whole; slashed so older rows keep their backslashes.
		update_post_meta($booking_id, '_mhmrentiva_booking_logs', wp_slash($logs));
	}

	/**
	 * May the checkout's payment-type switch rewrite this booking's payment
	 * type and remaining amount? Only while nothing has been collected on it
	 * (PR-2 A4): no paid WooCommerce order, and a payment status that is still
	 * waiting for one.
	 */
	public static function may_retype_booking(int $booking_id): bool
	{
		if (\MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking_id)->orders() !== array()) {
			return false;
		}

		return in_array( (string) get_post_meta($booking_id, '_mhmrentiva_payment_status', true), array( 'pending', 'unpaid', '' ), true);
	}

	/**
	 * A paid remaining-payment order settles the balance only when it was paid
	 * against it (PR-2 A3, plan r4-r5).
	 *
	 * Until PR-2 both paid branches zeroed the remaining amount whatever it had
	 * been paid against: an amount that moved after the customer started paying,
	 * or a balance the operator had already settled by hand, was silently
	 * written off or silently collected twice. Now:
	 *
	 *  - Applied already (the order carries `_mhmrentiva_remaining_applied`):
	 *    nothing to do. This is the only proof that the order's payment reached
	 *    the booking: a paid previous status is not, because the handler can
	 *    leave early on a failed read and the next transition must retry
	 *    (Codex r5 R5-1, Fable r5 Ş1).
	 *  - An order from before the amount stamp (PR-1): today's behaviour,
	 *    the balance is cleared. Kept apart from the shared rule on purpose:
	 *    comparing such an order by its total would turn a tax difference into
	 *    a false alarm (Fable r5 Ş2).
	 *  - Found not to fit already (`_mhmrentiva_remaining_mismatch`): decided
	 *    once, by a human; a later transition neither clears a balance they
	 *    corrected nor flags it again (Fable/Codex code r1).
	 *  - A previous paid status and nothing left to pay, without the applied
	 *    mark: an order the old code already applied (processing before this
	 *    release, completed after it). Nothing to do -- unless the order's own
	 *    first settle could not read the balance (`_mhmrentiva_remaining_retry`):
	 *    then it was never judged, and is judged now (Codex code r1).
	 *  - Otherwise the shared rule, for the booking's link only: the order asks
	 *    for exactly what is owed now → cleared and marked applied. Any other
	 *    order, or any other amount → the balance is left as it is, the order is
	 *    marked, and the booking is flagged for a human, with an order note and
	 *    a linked log entry.
	 *
	 * The booking's amount is read from the database, not the object cache.
	 */
	private static function settle_remaining_from_order(int $booking_id, \WC_Order $order, string $old_status): void
	{
		if ('' !== (string) $order->get_meta('_mhmrentiva_remaining_applied', true)) {
			return;
		}
		// A payment already found not to fit is decided once, by a human: a
		// later transition (processing -> completed) must not clear a balance
		// the operator has corrected since, nor flag it again (Fable code r1 Ş1).
		if ('' !== (string) $order->get_meta('_mhmrentiva_remaining_mismatch', true)) {
			return;
		}

		try {
			$owed_major = self::read_booking_meta($booking_id, '_mhmrentiva_remaining_amount');
			$link       = (int) self::read_booking_meta($booking_id, '_mhmrentiva_remaining_order_id');
		} catch (\RuntimeException $e) {
			// Remember that this payment was never judged, so the
			// pre-upgrade exception below cannot take it for one the old
			// code applied (Codex code r1).
			$order->update_meta_data('_mhmrentiva_remaining_retry', '1');
			$order->save_meta_data();
			AdvancedLogger::error_linked(
				/* translators: %d: the WooCommerce order id. */
				sprintf(__('The remaining amount of a booking could not be read when its payment (order #%d) arrived; the balance was left unchanged and will be settled on the next status change.', 'mhm-rentiva'), (int) $order->get_id()),
				$booking_id,
				array(
					'order_id' => (int) $order->get_id(),
					'surface'  => 'wc_status_change',
					'reason'   => 'remaining_read_failed',
				),
				AdvancedLogger::CATEGORY_SYSTEM
			);
			return;
		}
		$owed = RemainingPaymentHandler::debt_minor($owed_major);

		if (! RemainingPaymentHandler::is_stamped($order)) {
			if ($owed > 0) {
				update_post_meta($booking_id, '_mhmrentiva_remaining_amount', 0);
			}
			return;
		}

		// The pre-upgrade exception: a stamped order the old code already
		// applied on processing (no applied mark then) reaches completed with
		// nothing owed. An order whose own first settle failed to read is not
		// that order, and is judged.
		if ($owed <= 0
			&& in_array($old_status, wc_get_is_paid_statuses(), true)
			&& '' === (string) $order->get_meta('_mhmrentiva_remaining_retry', true)
		) {
			return;
		}

		// Only the booking's link settles it: PaymentState reads the link, so a
		// balance cleared by any other order would hide where the money is. A
		// late gateway return or a status set by hand does not pass
		// claim_before_pay() (Fable code r1 Ş2).
		$is_link = $link === (int) $order->get_id();
		if ($is_link && RemainingPaymentHandler::VERDICT_MATCH === RemainingPaymentHandler::amount_verdict($order, $owed)) {
			update_post_meta($booking_id, '_mhmrentiva_remaining_amount', 0);
			$order->update_meta_data('_mhmrentiva_remaining_applied', (string) $owed);
			$order->save_meta_data();
			return;
		}

		$flag = array(
			'order_id'      => (int) $order->get_id(),
			'due_minor'     => $owed,
			'stamped_minor' => (int) $order->get_meta('_mhmrentiva_remaining_due_minor', true),
			'reason'        => $is_link ? 'amount' : 'not_link',
			'at'            => time(),
		);
		update_post_meta($booking_id, '_mhmrentiva_payment_mismatch', $flag);
		$order->update_meta_data('_mhmrentiva_remaining_mismatch', (string) $owed);
		$order->save_meta_data();

		$decimals = RemainingPaymentHandler::debt_decimals();
		$message  = self::payment_mismatch_message(
			$flag,
			// What the order collected is its own total; the stamp is what it was
			// built for and can differ by tax (RemainingPaymentHandler).
			wp_strip_all_tags(wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ))),
			wp_strip_all_tags(wc_price($owed / ( 10 ** $decimals ), array( 'currency' => RemainingPaymentHandler::debt_currency() )))
		);
		$order->add_order_note(wp_strip_all_tags($message));
		AdvancedLogger::error_linked(
			wp_strip_all_tags($message),
			$booking_id,
			array(
				'order_id' => (int) $order->get_id(),
				'surface'  => 'wc_status_change',
				'reason'   => 'remaining_payment_mismatch',
			),
			AdvancedLogger::CATEGORY_SYSTEM
		);
	}

	/**
	 * The words for a booking's `_mhmrentiva_payment_mismatch` flag: one place
	 * for the order note, the log entry and the edit-screen notice.
	 *
	 * @param array<string, mixed> $flag      The flag settle_remaining_from_order() wrote.
	 * @param string               $collected What the order collected, as plain text.
	 * @param string               $due       The booking's remaining amount then, as plain text.
	 */
	public static function payment_mismatch_message(array $flag, string $collected, string $due): string
	{
		if ('not_link' === ( $flag['reason'] ?? '' )) {
			return sprintf(
				/* translators: 1: the WooCommerce order id, 2: the amount the order collected, 3: the booking's current remaining amount. */
				__('A payment arrived on this booking through order #%1$d, which is no longer its payment link (it collected %2$s; the remaining amount is %3$s). The remaining amount was left unchanged: check it and correct it by hand.', 'mhm-rentiva'),
				(int) ( $flag['order_id'] ?? 0 ),
				$collected,
				$due
			);
		}

		return sprintf(
			/* translators: 1: the WooCommerce order id, 2: the amount the order collected, 3: the booking's current remaining amount. */
			__('A payment arrived on this booking for an amount that is no longer current (order #%1$d collected %2$s; the remaining amount is %3$s). The remaining amount was left unchanged: check it and correct it by hand.', 'mhm-rentiva'),
			(int) ( $flag['order_id'] ?? 0 ),
			$collected,
			$due
		);
	}

	/**
	 * Does the booking have another order, besides this one, that has ever
	 * been paid?
	 *
	 * Delegates to PaymentState::orders() rather than re-deriving the
	 * candidate list: that list is the same two-candidate lookup
	 * (resolve_wc_order_id() + _mhmrentiva_remaining_order_id) this method
	 * used to duplicate, and the duplicate had already dropped
	 * resolvePaidOrders()'s function_exists('wc_get_order') guard
	 * (PaymentState.php:135-137) -- load-bearing for callers that can run
	 * with WooCommerce inactive.
	 *
	 * "Ever paid", not "still holds money": PaymentState::orders() keeps a
	 * fully refunded order in its set on purpose (PaymentState.php:153-155),
	 * because get_date_paid() proves money arrived even after it was later
	 * returned. The name and the branch below rely on that meaning.
	 */
	private static function has_paid_sibling_order( int $booking_id, int $order_id ): bool {
		return (bool) array_diff(
			PaymentState::forBooking( $booking_id )->orders(),
			array( $order_id )
		);
	}

	/**
	 * Sync booking "completed"/"cancelled" status back to WooCommerce order.
	 * Triggered by mhmrentiva_booking_status_changed hook.
	 * Loop-safe both ways: Status::can_transition('completed','completed') and
	 * Status::can_transition('cancelled','cancelled') both return false, so any
	 * re-entry from woocommerce_order_status_changed self-terminates whichever
	 * arm ran.
	 */
	public static function sync_completed_to_wc(int $booking_id, string $old_status, string $new_status): void
	{
		if (! in_array($new_status, array( 'completed', 'cancelled' ), true)) {
			return;
		}

		$order_id = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( (int) $booking_id);
		if ($order_id <= 0) {
			return; // No WC order (manual booking) — nothing to sync
		}

		if (! function_exists('wc_get_order')) {
			return;
		}

		$order = wc_get_order($order_id);
		if (! $order instanceof \WC_Order) {
			return;
		}

		if ($new_status === 'completed') {
			// Only advance from processing/on-hold — do not touch already completed or cancelled orders
			if (! $order->has_status(array( 'processing', 'on-hold' ))) {
				return;
			}

			$order->update_status(
				'completed',
				__('Booking completed — rental period ended.', 'mhm-rentiva')
			);
		} elseif ($new_status === 'cancelled') {
			// The fourth order-cancelling caller in this class, and the one a
			// single-line grep for update_status('cancelled' cannot see
			// because the call spans lines. It fires on ANY surface's
			// cancellation, so without this it undid Task 6 one hop later.
			//
			// The membership question has one home: PaymentState::orders() is
			// the set of this booking's orders whose money actually arrived
			// (get_date_paid() !== null -- PaymentState.php:153-155 says why
			// that and not is_paid()). Task 6's has_paid_sibling_order() asks
			// the booking-level question against the same list; this asks the
			// order-level one.
			if ( in_array( $order_id, PaymentState::forBooking( $booking_id )->orders(), true ) ) {
				return;
			}

			// Cancel WC order if not already cancelled/refunded
			if ($order->has_status(array( 'cancelled', 'refunded' ))) {
				return;
			}

			$order->update_status(
				'cancelled',
				__('Booking cancelled by customer.', 'mhm-rentiva')
			);
		}
	}

	/**
	 * Disable quantity input for booking items
	 */
	public static function disable_cart_quantity($product_quantity, $cart_item_key, $cart_item)
	{
		if (isset($cart_item['mhmrentiva_booking_id']) || isset($cart_item['mhmrentiva_booking_data'])) {
			return sprintf('%s <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item['quantity'], $cart_item_key);
		}
		return $product_quantity;
	}

	/**
	 * Check if cart contains booking items
	 */
	public static function cart_has_booking(): bool
	{
		if (! function_exists('WC') || ! \WC()->cart) {
			return false;
		}

		foreach (\WC()->cart->get_cart() as $cart_item) {
			if (isset($cart_item['mhmrentiva_booking_id']) || isset($cart_item['mhmrentiva_booking_data'])) {
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
		if (! function_exists('WC') || ! \WC()->cart) {
			return null;
		}

		foreach (\WC()->cart->get_cart() as $cart_item) {
			if (isset($cart_item['mhmrentiva_booking_id'])) {
				return (int) $cart_item['mhmrentiva_booking_id'];
			}
		}
		return null;
	}

	/**
	 * The pending rental's booking data when the cart holds exactly that one
	 * rental line and nothing else; null for an empty, mixed, multi-rental or
	 * transfer cart. The checkout journey (CheckoutJourney) draws only then.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function rental_cart_data(): ?array
	{
		if (! function_exists('WC') || ! \WC()->cart) {
			return null;
		}

		$items = \WC()->cart->get_cart();
		if (1 !== count($items)) {
			return null;
		}

		$item = reset($items);
		if (empty($item['mhmrentiva_booking_pending']) || ! isset($item['mhmrentiva_booking_data']) || ! is_array($item['mhmrentiva_booking_data'])) {
			return null;
		}

		return self::is_transfer_booking($item['mhmrentiva_booking_data']) ? null : $item['mhmrentiva_booking_data'];
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
		if (! self::cart_has_booking()) {
			return;
		}

		// Enqueue the selector behavior here — this hook fires exactly where the radios
		// render on the checkout, so the script loads even when the generic is_checkout()
		// asset guard does not (e.g. page-builder checkouts). in_footer, so a late enqueue
		// during the order-review render still prints. Replaces a former inline script block.
		wp_enqueue_script(
			'mhm-rentiva-checkout-payment-type',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/frontend/checkout-payment-type.js',
			array( 'jquery' ),
			MHMRENTIVA_VERSION,
			true
		);
		wp_localize_script(
			'mhm-rentiva-checkout-payment-type',
			'mhmCheckoutPaymentType',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('mhmrentiva_booking_payment_type'),
			)
		);

		// Initialize sums
		$total_full_price       = 0.0;
		$total_deposit_amount   = 0.0;
		$total_remaining_amount = 0.0;

		$current_payment_type = 'deposit'; // Default
		$has_booking_items    = false;

		// Loop through ALL cart items to calculate totals
		foreach (\WC()->cart->get_cart() as $cart_item) {
			$booking_id   = isset($cart_item['mhmrentiva_booking_id']) ? (int) $cart_item['mhmrentiva_booking_id'] : 0;
			$booking_data = $cart_item['mhmrentiva_booking_data'] ?? null;

			// Skip non-booking items
			if (! $booking_id && ! $booking_data) {
				continue;
			}

			$has_booking_items = true;

			$item_total_price    = 0.0;
			$item_deposit_amount = 0.0;
			$item_remaining      = 0.0;
			$item_payment_type   = 'deposit';

			if ($booking_id) {
				// Existing booking
				$item_payment_type   = get_post_meta($booking_id, '_mhmrentiva_payment_type', true) ?: 'deposit';
				$item_total_price    = (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true);
				$item_deposit_amount = (float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true);
				$item_remaining      = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);
			} elseif ($booking_data) {
				// Pending booking
				$item_payment_type   = $booking_data['payment_type'] ?? 'deposit';
				$item_total_price    = (float) ( $booking_data['total_price'] ?? 0 );
				$item_deposit_amount = (float) ( $booking_data['deposit_amount'] ?? 0 );
				$item_remaining      = (float) ( $booking_data['remaining_amount'] ?? 0 );
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
			$calculated_remaining    = $item_total_price - ( $item_deposit_amount > 0 ? $item_deposit_amount : $item_total_price );
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

		if (! $has_booking_items || $total_full_price <= 0) {
			return;
		}

		// Nothing left for pickup: no deposit applies, the only choice is to pay in full.
		if ($total_remaining_amount <= 0) {
			$current_payment_type = 'full';
		}

		?>
		<div id="mhm-booking-payment-type" class="mhm-checkout-payment-type rvs-shell">
			<h3>
				<?php echo esc_html__('Payment Options', 'mhm-rentiva'); ?>
			</h3>

			<div class="rvs-payment-type__options">
				<?php if ($total_remaining_amount > 0) : ?>
					<label class="rvs-option <?php echo 'deposit' === $current_payment_type ? 'rvs-option--selected' : ''; ?>" data-rvs-payment-option>
						<input type="radio"
							name="mhmrentiva_booking_payment_type"
							value="deposit"
							<?php checked($current_payment_type, 'deposit'); ?>
							data-amount="<?php echo esc_attr($total_deposit_amount); ?>">
						<span class="rvs-option__body">
							<strong class="rvs-option__title"><?php echo esc_html__('Deposit Payment', 'mhm-rentiva'); ?></strong>
							<span class="rvs-option__desc">
								<?php echo esc_html__('Pay deposit for booking, pay remaining amount at vehicle delivery', 'mhm-rentiva'); ?>
								<span class="rvs-payment-type__remaining">
									<?php echo esc_html__('Remaining:', 'mhm-rentiva'); ?>
									<?php echo wp_kses(self::cart_money($total_remaining_amount), PriceDisplay::allowed_html()); ?>
								</span>
							</span>
						</span>
						<span class="rvs-option__price"><?php echo wp_kses(self::cart_money($total_deposit_amount), PriceDisplay::allowed_html()); ?></span>
					</label>
				<?php endif; ?>

				<label class="rvs-option <?php echo 'full' === $current_payment_type ? 'rvs-option--selected' : ''; ?>" data-rvs-payment-option>
					<input type="radio"
						name="mhmrentiva_booking_payment_type"
						value="full"
						<?php checked($current_payment_type, 'full'); ?>
						data-amount="<?php echo esc_attr($total_full_price); ?>">
					<span class="rvs-option__body">
						<strong class="rvs-option__title"><?php echo esc_html__('Full Payment', 'mhm-rentiva'); ?></strong>
						<span class="rvs-option__desc"><?php echo esc_html__('Pay full amount now', 'mhm-rentiva'); ?></span>
					</span>
					<span class="rvs-option__price"><?php echo wp_kses(self::cart_money($total_full_price), PriceDisplay::allowed_html()); ?></span>
				</label>
			</div>
		</div>

		<?php
		// Selector behavior is enqueued as assets/js/frontend/checkout-payment-type.js.
	}

	/**
	 * The checkout submission, or null when this request carries no valid
	 * WooCommerce checkout nonce.
	 *
	 * The woocommerce_checkout_update_order_meta hook fires from inside
	 * WC_Checkout::process_checkout(), which has already verified this nonce.
	 * Verifying it again here is what lets the field read stand on its own: an
	 * annotation asserting "the caller checked" is not something a reviewer --
	 * or a scanner -- can confirm from this file. WooCommerce accepts the nonce
	 * under either field name (class-wc-checkout.php:1286), so both are tried,
	 * each as its own early return.
	 */
	private static function verified_checkout_request(): ?VerifiedRequest
	{
		$checkout_nonce = sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'] ?? ''));
		if (wp_verify_nonce($checkout_nonce, 'woocommerce-process_checkout')) {
			return VerifiedRequest::from($_POST);
		}

		$fallback_nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
		if (wp_verify_nonce($fallback_nonce, 'woocommerce-process_checkout')) {
			return VerifiedRequest::from($_POST);
		}

		return null;
	}

	/**
	 * Save payment type from checkout
	 * ⭐ Supports both existing bookings and pending bookings (cart data)
	 */
	public static function save_checkout_payment_type($order_id, $data)
	{
		$request = self::verified_checkout_request();
		if (null === $request) {
			return;
		}

		$payment_type = $request->text('mhmrentiva_booking_payment_type');
		if ('' === $payment_type) {
			return;
		}

		// Always save to order meta -- through the ORDER, not through post meta.
		//
		// Under High-Performance Order Storage (WooCommerce's custom order tables,
		// the default for new installs since 8.2) an order is not a post, so
		// update_post_meta( $order_id, ... ) writes a row keyed to a post that is not
		// the order; $order->get_meta() would never read it back. update_meta_data()
		// writes to whichever store is active, and on a non-HPOS site that store IS
		// postmeta -- so existing installs keep finding this value in exactly the
		// place it has always been, with no migration and no discontinuity.
		$order = wc_get_order($order_id);
		if ($order) {
			$order->update_meta_data('_mhmrentiva_wc_payment_type', $payment_type);
			$order->update_meta_data('_mhmrentiva_booking_payment_type', $payment_type);
			$order->save();
		}

		// ⭐ Handle existing booking (has booking_id)
		$booking_id = self::get_booking_id_from_cart();
		if ($booking_id) {
			$scope = PaymentView::begin($booking_id);
			try {
				update_post_meta($booking_id, '_mhmrentiva_payment_type', $payment_type);
			} finally {
				PaymentView::end($scope);
			}
		}

		// ⭐ Handle pending booking (cart data) - update cart data before booking creation
		/** @var mixed $cart */
		$cart = ( function_exists('WC') && isset(\WC()->cart) ) ? \WC()->cart : null;

		if (! $booking_id && $cart) {
			foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
				if (isset($cart_item['mhmrentiva_booking_data']) && isset($cart_item['mhmrentiva_booking_pending']) && $cart_item['mhmrentiva_booking_pending']) {
					// Update payment type in cart data (will be used when creating booking)
					$booking_data                 = $cart_item['mhmrentiva_booking_data'];
					$booking_data['payment_type'] = $payment_type;
					$cart->cart_contents[ $cart_item_key ]['mhmrentiva_booking_data'] = $booking_data;
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
		if (! check_ajax_referer('mhmrentiva_booking_payment_type', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		$payment_type = isset($_POST['payment_type']) ? sanitize_text_field(wp_unslash($_POST['payment_type'])) : 'deposit';

		/** @var mixed $cart */
		$cart = ( function_exists('WC') && isset(\WC()->cart) ) ? \WC()->cart : null;

		if (! $cart) {
			wp_send_json_error(__('Cart not available', 'mhm-rentiva'));
			return;
		}

		$cart_updated = false;

		// Iterate through ALL cart items and update any booking items
		foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
			$is_booking_item = false;

			// 1. Pending Bookings (Cart Data)
			if (isset($cart_item['mhmrentiva_booking_data']) && isset($cart_item['mhmrentiva_booking_pending']) && $cart_item['mhmrentiva_booking_pending']) {
				$is_booking_item = true;
				$booking_data    = $cart_item['mhmrentiva_booking_data'];

				// Calculate amount
				$total_price    = (float) ( $booking_data['total_price'] ?? 0 );
				$deposit_amount = (float) ( $booking_data['deposit_amount'] ?? 0 );

				// Only honor the checkout-wide "deposit" selection when this item
				// actually has a partial-payment configuration of its own
				// (deposit_amount < total_price). Items whose deposit_amount was
				// set equal to total_price at creation time (e.g. a transfer with
				// "Full Payment Required") always stay 'full' regardless of what
				// the cart-wide radio selects.
				$effective_payment_type       = ( $payment_type === 'deposit' && $deposit_amount > 0 && $deposit_amount < $total_price )
					? 'deposit'
					: 'full';
				$booking_data['payment_type'] = $effective_payment_type;

				$item_amount_to_pay = ( $effective_payment_type === 'deposit' ) ? $deposit_amount : $total_price;

				// For full payment, remaining amount is 0 (all paid now). For deposit,
				// (re)compute remaining so switching full->deposit restores it instead
				// of leaving it at a stale 0 from a prior full-payment selection.
				$booking_data['remaining_amount'] = $effective_payment_type === 'full'
					? 0
					: max(0, round($total_price - $deposit_amount, 2));

				// Update Cart
				$cart->cart_contents[ $cart_item_key ]['mhmrentiva_booking_data']  = $booking_data;
				$cart->cart_contents[ $cart_item_key ]['mhmrentiva_booking_price'] = $item_amount_to_pay;
				$cart->cart_contents[ $cart_item_key ]['data']->set_price($item_amount_to_pay);

				$cart_updated = true;
			}
			// 2. Existing Bookings (Database)
			elseif (isset($cart_item['mhmrentiva_booking_id'])) {
				$is_booking_item = true;
				$booking_id      = (int) $cart_item['mhmrentiva_booking_id'];

				// A booking that already holds money keeps its payment type, its
				// remaining amount and its cart price (PR-2 A4). This branch
				// rewrote all three from total - deposit, so a paid booking left
				// in a cart had its settled balance reopened and a second payment
				// link could collect it again.
				if (! self::may_retype_booking($booking_id)) {
					unset($is_booking_item);
					continue;
				}

				// Get totals
				$total_price    = (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true);
				$deposit_amount = (float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true);

				// Only honor the checkout-wide "deposit" selection when this
				// booking actually has a partial-payment configuration of its
				// own (deposit_amount < total_price). Bookings whose
				// deposit_amount equals total_price (e.g. a transfer created
				// while "Full Payment Required" is configured) always stay
				// 'full' regardless of what the cart-wide radio selects.
				$effective_payment_type = ( $payment_type === 'deposit' && $deposit_amount > 0 && $deposit_amount < $total_price )
					? 'deposit'
					: 'full';

				// Update DB (one payment-history scope per booking in the cart)
				$scope = PaymentView::begin($booking_id);
				try {
					update_post_meta($booking_id, '_mhmrentiva_payment_type', $effective_payment_type);

					// For full payment, clear remaining amount immediately. For deposit,
					// (re)compute remaining so switching full->deposit restores it instead
					// of leaving it at a stale 0 from a prior full-payment selection.
					update_post_meta(
						$booking_id,
						'_mhmrentiva_remaining_amount',
						$effective_payment_type === 'full' ? 0 : max(0, round($total_price - $deposit_amount, 2))
					);
				} finally {
					PaymentView::end($scope);
				}

				$item_amount_to_pay = ( $effective_payment_type === 'deposit' ) ? $deposit_amount : $total_price;

				// Update Cart
				$cart->cart_contents[ $cart_item_key ]['mhmrentiva_booking_price'] = $item_amount_to_pay;
				$cart->cart_contents[ $cart_item_key ]['data']->set_price($item_amount_to_pay);

				$cart_updated = true;
			}

			// Note: $is_booking_item carry-through; logging happens in caller layer.
			unset($is_booking_item);
		}

		if (! $cart_updated) {
			wp_send_json_error(__('Booking not found in cart', 'mhm-rentiva'));
			return;
		}

		// Recalculate cart totals
		$cart->calculate_totals();

		// Report what the customer now owes, read back from the cart after
		// recalculation. Before 6.0.3 this reported a $amount_to_pay that was
		// initialised to 0 and never assigned again -- every successful switch
		// answered with 0.00 -- while the per-item figure was written to a
		// $updated_item_amount that nothing ever read.
		$amount_to_pay = (float) $cart->get_total('edit');

		wp_send_json_success(
			array(
				'message'          => __('Payment type updated', 'mhm-rentiva'),
				'amount'           => $amount_to_pay,
				'formatted_amount' => wc_price($amount_to_pay),
			)
		);
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
	 * @return string Payment deadline in 'Y-m-d H:i:s' format (GMT)
	 */
	private static function get_payment_deadline(): string
	{
		if (class_exists('MHMRentiva\Admin\Booking\Core\Handler')) {
			return \MHMRentiva\Admin\Booking\Core\Handler::get_payment_deadline();
		}

		return self::fallback_payment_deadline();
	}

	/**
	 * Fallback when Handler is not available (should be rare): GMT, like Handler.
	 *
	 * @return string Payment deadline in 'Y-m-d H:i:s' format (GMT)
	 */
	private static function fallback_payment_deadline(): string
	{
		// Get payment deadline minutes from settings (default: 30 minutes)
		$deadline_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhmrentiva_booking_payment_deadline_minutes',
			30
		);

		// Minimum 5 minutes
		if ($deadline_minutes < 5) {
			$deadline_minutes = 5;
		}

		// GMT, like Handler::get_payment_deadline() and every reader of the meta.
		return gmdate('Y-m-d H:i:s', time() + ( $deadline_minutes * 60 ));
	}

	/**
	 * Is this cart/order item a RENTAL of a vehicle not offered for rental?
	 *
	 * The add-on's transfer checkout puts its items through this same cart and
	 * create_booking_from_data(), marked `booking_type => 'transfer'`; those are
	 * not rentals and are left to the add-on. Every other item is a rental --
	 * the booking form sets no booking_type at all.
	 *
	 * @param array<string, mixed> $booking_data Pending booking data.
	 */
	private static function is_refused_rental(array $booking_data, int $vehicle_id): bool
	{
		if (self::is_transfer_booking($booking_data)) {
			return false;
		}

		return ! \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::allows_service($vehicle_id, 'rental');
	}

	/**
	 * Is this pending booking a transfer (put in the cart by the add-on)?
	 *
	 * Same two markers Mailer uses to recognise a transfer booking. The cart
	 * data is built server-side by the add-on, never taken from the browser.
	 *
	 * @param array<string, mixed> $booking_data Pending booking data.
	 */
	public static function is_transfer_booking(array $booking_data): bool
	{
		return 'transfer' === (string) ( $booking_data['booking_type'] ?? '' )
			|| (int) ( $booking_data['transfer_origin_id'] ?? 0 ) > 0;
	}

	/**
	 * A rental's add-ons, each with its price, for its cart and order rows.
	 *
	 * Empty for a transfer: the add-on prices transfer add-ons (per passenger,
	 * per transfer) and lists them itself; a Lite row beside its row was the
	 * duplicate the order used to show.
	 *
	 * @param array<string, mixed> $booking_data Pending booking data.
	 * @return array<int, array{title: string, line_total: float}>
	 */
	private static function rental_addon_lines(array $booking_data): array
	{
		if (self::is_transfer_booking($booking_data)) {
			return array();
		}

		// Priced when the booking entered the cart (add_booking_data_to_cart).
		if (isset($booking_data['addon_lines']) && is_array($booking_data['addon_lines'])) {
			$lines = array();
			foreach ($booking_data['addon_lines'] as $line) {
				if (is_array($line)) {
					$lines[] = array(
						'title'      => (string) ( $line['title'] ?? '' ),
						'line_total' => (float) ( $line['line_total'] ?? 0 ),
					);
				}
			}

			return $lines;
		}

		// A cart from before the snapshot: price it now.
		return self::price_rental_addon_lines($booking_data);
	}

	/**
	 * A rental's add-ons priced from their current settings.
	 *
	 * @param array<string, mixed> $booking_data Pending booking data.
	 * @return array<int, array{title: string, line_total: float}>
	 */
	private static function price_rental_addon_lines(array $booking_data): array
	{
		$selected = $booking_data['selected_addons'] ?? array();
		if (self::is_transfer_booking($booking_data) || ! is_array($selected) || array() === $selected) {
			return array();
		}

		$lines = array();
		foreach (\MHMRentiva\Admin\Addons\AddonManager::build_addon_details($selected, $booking_data) as $detail) {
			// A charged add-on keeps its row even without a usable name; one
			// taken more than once says so ("Child seat × 2").
			$detail['title'] = self::order_meta_label($detail['title']);
			$lines[]         = array(
				'title'      => \MHMRentiva\Admin\Addons\AddonManager::line_label($detail),
				'line_total' => $detail['line_total'],
			);
		}

		return $lines;
	}

	/**
	 * A booking amount on the cart and checkout, as the rental's breakdown shows it.
	 */
	private static function cart_money(float $amount): string
	{
		return wp_kses(PriceDisplay::for_cart($amount)['html'], PriceDisplay::allowed_html());
	}

	/**
	 * A price for an order line's meta: text, with the symbol as a character.
	 *
	 * Order meta is shown as stored; a plain-text email strips tags and a stored
	 * "&#8378;" entity came out as nothing ("30,00 ").
	 */
	private static function order_meta_price(float $amount): string
	{
		return html_entity_decode(wp_strip_all_tags(PriceDisplay::for_cart($amount)['html']), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * A name used as an order meta key, kept visible: WooCommerce hides keys
	 * that start with "_".
	 */
	private static function order_meta_label(string $label): string
	{
		$visible = ltrim($label, '_ ');

		// Nothing but "_" left nothing to show: name the row as the add-ons it is.
		return '' !== $visible ? $visible : __('Add-ons', 'mhm-rentiva');
	}

	private static function rental_refusal_notice(int $vehicle_id): string
	{
		return sprintf(
			/* translators: %s: Vehicle title */
			__('Sorry, the vehicle "%s" is not available for rental. Please select another vehicle.', 'mhm-rentiva'),
			get_the_title($vehicle_id)
		);
	}

	/**
	 * ⭐ Validate cart items availability (runs on cart page)
	 * Prevents adding unavailable vehicles to cart
	 */
	public static function validate_cart_availability()
	{
		/** @var mixed $cart */
		$cart = ( function_exists('WC') && isset(\WC()->cart) ) ? \WC()->cart : null;

		if (! $cart) {
			return;
		}

		foreach ($cart->get_cart() as $cart_item) {
			// Check pending booking (from cart data)
			if (isset($cart_item['mhmrentiva_booking_data']) && isset($cart_item['mhmrentiva_booking_pending']) && $cart_item['mhmrentiva_booking_pending']) {
				$booking_data = $cart_item['mhmrentiva_booking_data'];
				$vehicle_id   = (int) ( $booking_data['vehicle_id'] ?? 0 );

				if (! $vehicle_id) {
					continue;
				}

				if (self::is_refused_rental($booking_data, $vehicle_id)) {
					\wc_add_notice(self::rental_refusal_notice($vehicle_id), 'error');
					$cart->remove_cart_item($cart_item['key']);
					continue;
				}

				// Parse dates to timestamps
				$start_ts = self::parse_datetime_to_timestamp($booking_data['pickup_date'], $booking_data['pickup_time'] ?? '00:00');
				$end_ts   = self::parse_datetime_to_timestamp($booking_data['dropoff_date'], $booking_data['dropoff_time'] ?? '23:59');

				if (! $start_ts || ! $end_ts) {
					continue;
				}

				// Note: Cache should NOT be invalidated on read operations - only when booking is created/updated

				// Check for overlap (locked check for real-time availability); a
				// retried order's own booking is not a conflict.
				if (\MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($vehicle_id, $start_ts, $end_ts, self::own_booking_of_cart_line($booking_data))) {
					$vehicle_title = get_the_title($vehicle_id);
					$pickup_date   = wp_date(get_option('date_format'), $start_ts);
					$dropoff_date  = wp_date(get_option('date_format'), $end_ts);

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
					$cart->remove_cart_item($cart_item['key']);
				}
			}
			// Check existing booking
			elseif (isset($cart_item['mhmrentiva_booking_id'])) {
				$booking_id = (int) $cart_item['mhmrentiva_booking_id'];
				$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);

				if (! $vehicle_id) {
					continue;
				}

				// ⭐ Use consistent meta keys - prefer pickup/dropoff for clarity
				$pickup_date  = get_post_meta($booking_id, '_mhmrentiva_pickup_date', true) ?: get_post_meta($booking_id, '_mhmrentiva_start_date', true);
				$pickup_time  = get_post_meta($booking_id, '_mhmrentiva_pickup_time', true) ?: get_post_meta($booking_id, '_mhmrentiva_start_time', true) ?: '00:00';
				$dropoff_date = get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true) ?: get_post_meta($booking_id, '_mhmrentiva_end_date', true);
				$dropoff_time = get_post_meta($booking_id, '_mhmrentiva_dropoff_time', true) ?: get_post_meta($booking_id, '_mhmrentiva_end_time', true) ?: '23:59';

				if (! $pickup_date || ! $dropoff_date) {
					continue;
				}

				$start_ts = self::parse_datetime_to_timestamp($pickup_date, $pickup_time);
				$end_ts   = self::parse_datetime_to_timestamp($dropoff_date, $dropoff_time);

				if (! $start_ts || ! $end_ts) {
					continue;
				}

				// Note: Cache should NOT be invalidated on read operations - only when booking is created/updated

				// Check for overlap (exclude current booking from check)
				// Note: For existing bookings, we should check if dates have changed
				// For now, we'll do a basic check - if booking status is cancelled, remove from cart
				$booking_status = get_post_meta($booking_id, '_mhmrentiva_status', true);
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
					$cart->remove_cart_item($cart_item['key']);
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
		/** @var mixed $cart */
		$cart = ( function_exists('WC') && isset(\WC()->cart) ) ? \WC()->cart : null;

		if (! $cart) {
			return;
		}

		foreach ($cart->get_cart() as $cart_item) {
			// Check pending booking (from cart data) - MOST IMPORTANT CHECK
			if (isset($cart_item['mhmrentiva_booking_data']) && isset($cart_item['mhmrentiva_booking_pending']) && $cart_item['mhmrentiva_booking_pending']) {
				$booking_data = $cart_item['mhmrentiva_booking_data'];
				$vehicle_id   = (int) ( $booking_data['vehicle_id'] ?? 0 );

				if (! $vehicle_id) {
					continue;
				}

				// ⭐ This error STOPS checkout -- no payment is taken for a rental
				// of a transfer-only vehicle.
				if (self::is_refused_rental($booking_data, $vehicle_id)) {
					\wc_add_notice(self::rental_refusal_notice($vehicle_id), 'error');
					continue;
				}

				// Parse dates to timestamps
				$start_ts = self::parse_datetime_to_timestamp($booking_data['pickup_date'], $booking_data['pickup_time'] ?? '00:00');
				$end_ts   = self::parse_datetime_to_timestamp($booking_data['dropoff_date'], $booking_data['dropoff_time'] ?? '23:59');

				if (! $start_ts || ! $end_ts) {
					\wc_add_notice(
						__('Invalid booking dates. Please try again.', 'mhm-rentiva'),
						'error'
					);
					continue;
				}

				// Advisory checkout-time validation: an uncached, real-time read so
				// the customer is told at checkout rather than after paying. It is
				// NOT atomic and takes no lock (no transaction is open here). The
				// authoritative locked check runs in create_booking_from_data().
				// A retried order's own booking is not a conflict.
				if (\MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($vehicle_id, $start_ts, $end_ts, self::own_booking_of_cart_line($booking_data))) {
					$pickup_date  = wp_date(get_option('date_format'), $start_ts);
					$dropoff_date = wp_date(get_option('date_format'), $end_ts);

					// ⭐ This error will STOP checkout process - NO PAYMENT WILL BE PROCESSED
					\wc_add_notice(self::booked_meanwhile_notice($vehicle_id, $start_ts, $end_ts), 'error');

					// Task 14b item 3: left at warning() deliberately -- the
					// customer already sees the wc_add_notice() error above,
					// on the checkout page, before checkout can even
					// complete. This entry is a supplementary debug trace
					// for the same event, not the only record of it.
					// Log for debugging
					AdvancedLogger::warning(
						'Checkout validation failed - vehicle no longer available',
						array(
							'vehicle_id' => $vehicle_id,
							'dates'      => $pickup_date . ' - ' . $dropoff_date,
						),
						AdvancedLogger::CATEGORY_BOOKING
					);
				}
			}
			// Check existing booking
			elseif (isset($cart_item['mhmrentiva_booking_id'])) {
				$booking_id = (int) $cart_item['mhmrentiva_booking_id'];
				$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);

				if (! $vehicle_id) {
					continue;
				}

				// Check if booking was cancelled
				$booking_status = get_post_meta($booking_id, '_mhmrentiva_status', true);
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
	 * @param int   $refund_id Refund ID
	 * @param array $args Refund arguments
	 */
	public static function handle_order_refunded(int $refund_id, array $args): void
	{
		if (! class_exists('WooCommerce')) {
			return;
		}

		$order_id = (int) ( $args['order_id'] ?? 0 );
		if (! $order_id) {
			return;
		}

		$order = function_exists('wc_get_order') ? call_user_func('wc_get_order', $order_id) : false;
		if (! $order) {
			return;
		}

		// Get booking ID from order
		$booking_id = self::get_booking_id_from_order($order);
		if (! $booking_id) {
			return;
		}

		// Payment-history scope (PaymentView): the refund's meta writes below
		// run inside it; the body is its own method so every early return
		// still closes the scope.
		$scope = PaymentView::begin($booking_id);
		try {
			self::apply_order_refund_to_booking($refund_id, $args, $order, $order_id, $booking_id);
		} finally {
			PaymentView::end($scope);
		}
	}

	/**
	 * The booking half of handle_order_refunded(), unchanged, inside the
	 * caller's payment scope.
	 *
	 * @param array<string, mixed> $args
	 * @param \WC_Order            $order
	 * @param int|string           $order_id
	 */
	private static function apply_order_refund_to_booking(int $refund_id, array $args, $order, $order_id, int $booking_id): void
	{
		$currency = $order->get_currency();

		// Kept only for the activity-log entry below, which records what THIS
		// WooCommerce order's own refund total is -- not the booking-wide figure
		// PaymentState now owns.
		$refund_amount = $order->get_total_refunded();

		// The refund figures come from PaymentState, not from this one order.
		// A deposit booking has two paid orders; writing $order's own
		// get_total_refunded() here overwrote the booking's record with half of
		// it, and the second refund erased the first. PaymentState::refunded()
		// is the sum across the set, so this write is absolute and idempotent
		// no matter which order fired the hook or how many times.
		$state = PaymentState::forBooking($booking_id);

		if (in_array($order_id, $state->orders(), true)) {
			$recorded_refunded = $state->refunded();
			$fully_refunded    = $state->isFullyRefunded();
		} else {
			// This order reached the booking through get_booking_id_from_order()
			// (order meta, or an item's), but PaymentState::resolvePaidOrders()
			// -- which only follows _mhmrentiva_woocommerce_order_id and
			// _mhmrentiva_remaining_order_id -- does not know this order
			// exists, so it is never in $state->orders() and never
			// contributes to $state->refunded(). Substituting this order's
			// own figure for $state->refunded() (as an earlier version of
			// this fallback did) discarded every OTHER order's refund --
			// N-01, recreated inside the branch meant to guard against it.
			// Adding this order's own total on top instead keeps the
			// resolvable legs' contribution alongside this one -- for a
			// SINGLE invisible leg. With two or more invisible orders this
			// fallback still can't see any invisible order other than the
			// one that fired it, so a second invisible leg's own event
			// would still discard the first invisible leg's contribution;
			// that is left unfixed here, not claimed fixed.
			AdvancedLogger::error(
				'Refunded order is not resolvable through PaymentState; booking-level refund figures may be incomplete',
				array(
					'booking_id' => $booking_id,
					'order_id'   => $order_id,
				),
				AdvancedLogger::CATEGORY_PAYMENT
			);

			// $state->refunded() is NOT a peer running total when
			// $state->orders() is empty: PaymentState::resolveOfflineChannel()
			// opens in that case (PaymentState.php :195-201) and refunded()
			// reads back _mhmrentiva_refunded_amount itself -- the very meta
			// this line is about to overwrite. Adding this order's own total
			// on top of THAT is read-modify-write accumulation on every
			// refund of this order: previous + running_total, growing
			// without bound (three successive partial refunds of one
			// invisible 40 order would record 1500, then 4500, then 8500,
			// against a true 4000) -- the exact double-write shape this
			// slice exists to remove. So the pure case uses this order's own
			// total alone, same as the pre-round-3 fallback.
			//
			// Trade-off, stated rather than left implicit: if this same
			// booking ALSO carries an offline refund in that same meta key
			// (Service::finish()'s channel), the pure-case branch drops it,
			// because it no longer adds $state->refunded() (which is what
			// would have read that offline figure back). The alternative --
			// adding $state->refunded() in the pure case too -- doubles
			// every WooCommerce refund on this order after the first, which
			// is strictly worse: an offline refund can only be lost once,
			// while an unboundedly growing WooCommerce figure is precisely
			// the bug this task exists to remove.
			$recorded_refunded = array() === $state->orders()
				? Money::toMinor($refund_amount)
				: $state->refunded() + Money::toMinor($refund_amount);

			// Terminal-status authority is granted here ONLY when this
			// invisible order is the booking's ENTIRE known WooCommerce
			// presence ($state->orders() === []) -- the shape
			// RefundUnresolvableOrderFallbackTest covers. A mixed booking,
			// with one resolvable leg and one invisible, must not let this
			// order's own totals decide 'refunded' for the whole booking:
			// that is the same terminal mid-walk shape Task 8 removed from
			// handle_order_status_change() and this hook's main branch,
			// re-created one level down inside this fallback.
			//
			// Deferring to $state->isFullyRefunded() in the mixed case is
			// the best available answer, not a proven-safe one: it is blind
			// to this order's paid AND refunded contribution, so if the
			// resolvable legs alone are already fully refunded it reports
			// true regardless of what this invisible leg still owes -- a
			// resolvable 70/70 next to an invisible 30-paid/10-refunded
			// reports fully refunded with 20 still outstanding. That
			// specific flip is not newly reachable here: PaymentState is
			// blind to this order everywhere, including the main branch
			// above, so the resolvable leg alone already marks the booking
			// refunded before this fallback ever runs. What deferring
			// avoids is THIS fallback adding a second, independent way to
			// reach the same terminal write from one order's own totals.
			$fully_refunded = array() === $state->orders()
				? Money::toMinor($refund_amount) >= Money::toMinor( (float) $order->get_total() )
				: $state->isFullyRefunded();
		}

		update_post_meta($booking_id, '_mhmrentiva_refunded_amount', $recorded_refunded);
		update_post_meta($booking_id, '_mhmrentiva_payment_currency', $currency);

		if ($fully_refunded) {
			update_post_meta($booking_id, '_mhmrentiva_payment_status', 'refunded');
			Status::update_status($booking_id, 'refunded', get_current_user_id());
		} else {
			update_post_meta($booking_id, '_mhmrentiva_payment_status', 'partially_refunded');
		}

		// Save refund transaction ID
		$refund = function_exists('wc_get_order') ? call_user_func('wc_get_order', $refund_id) : false;
		if ($refund) {
			$refund_reason = $refund->get_reason() ?: '';
			add_post_meta($booking_id, '_mhmrentiva_refund_txn_id', (string) $refund_id);
			update_post_meta($booking_id, '_mhmrentiva_refund_reason', wp_slash($refund_reason));
		}

		// One e-mail per refund OPERATION, not per WooCommerce refund object.
		// While Service is walking a booking's orders it owns the message and
		// sends it once at the end; a refund created from WooCommerce's own
		// order screen has no operation behind it, so this hook owns it.
		if (! \MHMRentiva\Admin\Payment\Refunds\Service::isRefundInFlight($booking_id)
			&& class_exists('\MHMRentiva\Admin\Emails\Notifications\RefundNotifications')) {
			try {
				// Mirrors the write above -- not a second, independently-derived
				// comparison -- for both branches above (PaymentState-resolvable
				// or the per-order fallback).
				$payment_status = $fully_refunded ? 'refunded' : 'partially_refunded';
				$refund_reason  = $refund ? $refund->get_reason() : '';

				// WooCommerce passes the operator's own gateway-vs-manual choice
				// straight through as 'refund_payment' -- its admin AJAX handler
				// sets this from the "Refund via gateway" vs "Refund manually"
				// toggle the operator picked on the order screen. A missing key
				// means no gateway refund was requested, so MODE_MANUAL is the
				// fail-safe reading -- the same direction
				// RefundValidator::modeForOrder() already defaults to when it
				// cannot ask the gateway at all.
				$mode = ( $args['refund_payment'] ?? false )
					? \MHMRentiva\Admin\Payment\Refunds\RefundValidator::MODE_AUTO
					: \MHMRentiva\Admin\Payment\Refunds\RefundValidator::MODE_MANUAL;

				// The template speaks of ONE event ("a refund of {amount} has
				// been processed"), so the mail needs THIS refund's own amount,
				// not $recorded_refunded -- the booking's CUMULATIVE refunded
				// total, which is correct for the meta write above but wrong
				// here: a second refund of 30 after an earlier 40 would report
				// 70, an amount that never moved in this event. get_amount() on
				// the WC_Order_Refund is this refund object's own figure alone
				// (WC 11.0.1 wc-order-functions.php :583-588 -- the positive
				// value wc_create_refund() stored as 'amount', validated against
				// the order's remaining balance, never against another refund's),
				// so a second 30 reports 30 here, not 70. The Service path
				// (Service.php :338) already passes the operation's own amount;
				// this brings the WooCommerce-screen path to the same meaning.
				if ( ! $refund ) {
					// wc_get_order($refund_id) returning false here means
					// WooCommerce cannot reload the very refund object that fired
					// this hook a moment ago -- practically unreachable. There is
					// no other source for 'this refund's own amount': falling back
					// to $recorded_refunded would silently reintroduce the
					// cumulative-vs-single bug this fix removes, so the mail is
					// skipped -- caught below and logged -- instead of showing the
					// customer a number for a refund this code could not confirm.
					throw new \RuntimeException( 'Refund order object unavailable for amount calculation' );
				}

				$this_refund_amount_kurus = Money::toMinor( (float) $refund->get_amount() );

				\MHMRentiva\Admin\Emails\Notifications\RefundNotifications::notify(
					$booking_id,
					$this_refund_amount_kurus,
					$currency,
					$payment_status,
					$refund_reason,
					$mode
				);
			} catch (\Throwable $e) {
				AdvancedLogger::error(
					'Refund notification error',
					array(
						'booking_id' => $booking_id,
						'exception'  => $e->getMessage(),
					),
					AdvancedLogger::CATEGORY_PAYMENT
				);
			}
		}

		// Add log
		$logs   = get_post_meta($booking_id, '_mhmrentiva_booking_logs', true) ?: array();
		$logs[] = array(
			'action'    => 'wc_refund_created',
			'timestamp' => current_time('mysql'),
			'user_id'   => get_current_user_id() ?: 0,
			'data'      => array(
				'order_id'      => $order_id,
				'refund_id'     => $refund_id,
				'refund_amount' => $refund_amount,
				'currency'      => $currency,
			),
		);
		// Rewritten whole; slashed so older rows keep their backslashes.
		update_post_meta($booking_id, '_mhmrentiva_booking_logs', wp_slash($logs));
	}

	/**
	 * ⭐ Get booking ID from WooCommerce order
	 *
	 * @param mixed $order WooCommerce order object
	 * @return int|null Booking ID or null if not found
	 */
	private static function get_booking_id_from_order($order): ?int
	{
		// First check order meta
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');
		if ($booking_id > 0) {
			return $booking_id;
		}

		// Check order items
		$items = $order->get_items();
		foreach ($items as $item) {
			$booking_id = (int) $item->get_meta('_mhmrentiva_booking_id');
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
	 * @param float                $amount Amount to charge
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
		if (! function_exists('wc_get_checkout_url')) {
			return '';
		}
		return wc_get_checkout_url();
	}

	/**
	 * Process payment for a booking
	 *
	 * @param int                  $booking_id Booking ID
	 * @param float                $amount Payment amount
	 * @param array<string, mixed> $payment_data Additional payment data
	 * @return array<string, mixed> Payment result with 'success', 'message', 'transaction_id', etc.
	 */
	public function process_payment(int $booking_id, float $amount, array $payment_data = array()): array
	{
		// With WooCommerce, payment is processed by WC itself.
		// We just ensure the order exists and is linked to the booking.
		if ($booking_id <= 0) {
			return array(
				'success' => false,
				'message' => __('Invalid booking ID.', 'mhm-rentiva'),
			);
		}

		// Check if order already exists for this booking
		$order_id = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( (int) $booking_id);
		if ($order_id > 0) {
			$order = function_exists('wc_get_order') ? call_user_func('wc_get_order', $order_id) : false;
			if ($order && $order->is_paid()) {
				return array(
					'success'        => true,
					'message'        => __('Payment already processed by WooCommerce.', 'mhm-rentiva'),
					'transaction_id' => (string) $order_id,
				);
			}
		}

		// If no order exists, add booking to cart and redirect to checkout
		$booking_data = array(
			'booking_id'   => $booking_id,
			'vehicle_id'   => (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true),
			'pickup_date'  => get_post_meta($booking_id, '_mhmrentiva_pickup_date', true),
			'dropoff_date' => get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true),
			'payment_type' => get_post_meta($booking_id, '_mhmrentiva_payment_type', true) ?: 'deposit',
		);

		if (self::add_booking_data_to_cart($booking_data, $amount)) {
			return array(
				'success'      => true,
				'message'      => __('Redirecting to checkout...', 'mhm-rentiva'),
				'redirect_url' => $this->get_checkout_url(),
			);
		}

		return array(
			'success' => false,
			'message' => __('Failed to add booking to cart.', 'mhm-rentiva'),
		);
	}

	/**
	 * Validate payment data before processing
	 *
	 * @param array<string, mixed> $payment_data Payment data to validate
	 * @return array<string, mixed> Validation result with 'valid' (bool) and 'errors' (array)
	 */
	public function validate_payment_data(array $payment_data): array
	{
		$errors = array();

		// Check if WooCommerce is available
		if (! class_exists('WooCommerce')) {
			$errors[] = __('WooCommerce is not installed or activated.', 'mhm-rentiva');
		}

		// Validate booking data if present
		if (isset($payment_data['booking_id'])) {
			$booking_id = (int) $payment_data['booking_id'];
			if ($booking_id <= 0 || ! get_post($booking_id)) {
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

		return array(
			'valid'  => empty($errors),
			'errors' => $errors,
		);
	}

	/**
	 * The billing grid of a booking's checkout (canvas `Odeme`): name, country
	 * and city, district and postcode, street, phone and email. Priority and
	 * column of each field; reorder_checkout_fields() writes the same values.
	 */
	private const BOOKING_ADDRESS_LAYOUT = array(
		'first_name' => array( 10, 'form-row-first' ),
		'last_name'  => array( 11, 'form-row-last' ),
		'country'    => array( 20, 'form-row-first' ),
		'state'      => array( 21, 'form-row-last' ),
		'city'       => array( 30, 'form-row-first' ),
		'postcode'   => array( 31, 'form-row-last' ),
		'address_1'  => array( 40, 'form-row-wide' ),
		'phone'      => array( 50, 'form-row-first' ),
		'email'      => array( 51, 'form-row-last' ),
	);

	/**
	 * WooCommerce's address-i18n.js re-sorts and re-columns the address fields
	 * from the country locale as soon as the country is known, which undid the
	 * order reorder_checkout_fields() prints (the email landed between street
	 * and postcode). The locale of a booking's checkout carries the same order.
	 *
	 * @param mixed $locale One locale (default) or all of them, keyed by country.
	 * @return mixed
	 */
	public static function booking_address_locale($locale)
	{
		if (! is_array($locale) || ! function_exists('is_checkout') || ! is_checkout() || ! self::cart_has_booking()) {
			return $locale;
		}

		$is_one = isset($locale['first_name']) || isset($locale['address_1']) || isset($locale['country']);
		if ($is_one) {
			return self::with_booking_layout($locale, true);
		}

		foreach ($locale as $country => $fields) {
			if (is_array($fields)) {
				$locale[ $country ] = self::with_booking_layout($fields, false);
			}
		}

		return $locale;
	}

	/**
	 * @param array<string, mixed> $fields A locale's fields.
	 * @param bool                 $all    Add the fields a locale leaves out (the default locale).
	 * @return array<string, mixed>
	 */
	private static function with_booking_layout(array $fields, bool $all): array
	{
		foreach (self::BOOKING_ADDRESS_LAYOUT as $key => $layout) {
			if (! isset($fields[ $key ]) && ! $all) {
				continue;
			}
			$field             = isset($fields[ $key ]) && is_array($fields[ $key ]) ? $fields[ $key ] : array();
			$field['priority'] = $layout[0];
			$field['class']    = array( $layout[1] );
			// The booking's phone is required (reorder_checkout_fields()); the
			// locale must not mark it optional when the country changes.
			if ('phone' === $key) {
				$field['required'] = true;
			}
			// The default locale's labels are what address-i18n.js puts back
			// for a country that sets none: the same words the form printed
			// (reorder_checkout_fields()), not WooCommerce's "Town / City".
			if ($all && isset(self::booking_address_labels()[ $key ])) {
				$field['label'] = self::booking_address_labels()[ $key ];
			}
			$fields[ $key ] = $field;
		}

		return $fields;
	}

	/**
	 * The labels a booking's checkout gives the address fields.
	 *
	 * @return array<string, string>
	 */
	private static function booking_address_labels(): array
	{
		return array(
			'state' => __('City', 'mhm-rentiva'),
			'city'  => __('District / Neighborhood', 'mhm-rentiva'),
		);
	}

	/**
	 * Reorder and modify checkout fields for a better UX
	 *
	 * @param array $fields
	 * @return array
	 */
	public static function reorder_checkout_fields(array $fields): array
	{
		// A rule of the booking (its phone comes from here), so any Rentiva line
		// keeps it; the shop's own orders keep their fields.
		if (! self::cart_has_booking()) {
			return $fields;
		}

		// --- ROW 1: Identity ---
		if (isset($fields['billing']['billing_first_name'])) {
			$fields['billing']['billing_first_name']['priority'] = 10;
			$fields['billing']['billing_first_name']['class']    = array( 'form-row-first' );
		}

		if (isset($fields['billing']['billing_last_name'])) {
			$fields['billing']['billing_last_name']['priority'] = 11;
			$fields['billing']['billing_last_name']['class']    = array( 'form-row-last' );
		}

		// --- ROW 2: Location Broad (Country + State/City) ---
		if (isset($fields['billing']['billing_country'])) {
			$fields['billing']['billing_country']['priority'] = 20;
			$fields['billing']['billing_country']['class']    = array( 'form-row-first' );
		}

		// State (City/Province in TR context often) - Right
		if (isset($fields['billing']['billing_state'])) {
			$fields['billing']['billing_state']['priority'] = 21;
			$fields['billing']['billing_state']['class']    = array( 'form-row-last' );
			$fields['billing']['billing_state']['label']    = self::booking_address_labels()['state'];
		}

		// --- ROW 3: Location Specific (City/District + Postcode) ---
		// City (District in TR context often) - Left
		if (isset($fields['billing']['billing_city'])) {
			$fields['billing']['billing_city']['priority'] = 30;
			$fields['billing']['billing_city']['class']    = array( 'form-row-first' );
			$fields['billing']['billing_city']['label']    = self::booking_address_labels()['city'];
		}

		if (isset($fields['billing']['billing_postcode'])) {
			$fields['billing']['billing_postcode']['priority'] = 31;
			$fields['billing']['billing_postcode']['class']    = array( 'form-row-last' );
		}

		// --- ROW 4: Street Details (Single Line) ---
		if (isset($fields['billing']['billing_address_1'])) {
			$fields['billing']['billing_address_1']['priority'] = 40;
			$fields['billing']['billing_address_1']['class']    = array( 'form-row-wide' );
		}

		// Remove Address 2 (Apartment) entirely
		if (isset($fields['billing']['billing_address_2'])) {
			unset($fields['billing']['billing_address_2']);
		}

		// --- ROW 5: Contact ---
		if (isset($fields['billing']['billing_phone'])) {
			$fields['billing']['billing_phone']['priority'] = 50;
			$fields['billing']['billing_phone']['class']    = array( 'form-row-first' );
			$fields['billing']['billing_phone']['required'] = true; // ⭐ FORCE REQUIRED
		}

		if (isset($fields['billing']['billing_email'])) {
			$fields['billing']['billing_email']['priority'] = 51;
			$fields['billing']['billing_email']['class']    = array( 'form-row-last' );
		}

		return $fields;
	}
	/**
	 * WC_Checkout::validate_posted_data() names a billing field "Billing %s" in
	 * its error notices ("Fatura Ad gerekli bir alandır."). A booking's checkout
	 * asks for the renter's details, not an invoice address, so on a cart with a
	 * booking the field's own label stands alone. Open from
	 * woocommerce_checkout_process (before validate_checkout()) to
	 * woocommerce_after_checkout_validation; the shop's own orders keep
	 * WooCommerce's wording.
	 */
	public static function open_field_label_window(): void
	{
		if (self::cart_has_booking()) {
			add_filter('gettext_with_context_woocommerce', array( self::class, 'bare_field_label' ), 10, 3);
		}
	}

	public static function close_field_label_window(): void
	{
		remove_filter('gettext_with_context_woocommerce', array( self::class, 'bare_field_label' ), 10);
	}

	/**
	 * @param mixed $translation The translated text.
	 * @param mixed $text        The source text.
	 * @param mixed $context     The context.
	 * @return mixed
	 */
	public static function bare_field_label($translation, $text, $context = '')
	{
		return 'Billing %s' === $text && 'checkout-validation' === $context ? '%s' : $translation;
	}

	/**
	 * Add "Return to Cart" link at checkout
	 */
	public static function add_return_to_cart_link(): void
	{
		// One rental: beside the "Your order" heading, placed by rvs-checkout.css.
		if (null !== self::rental_cart_data()) {
			self::print_return_to_cart_link();
		}
	}

	/**
	 * A mixed or transfer cart keeps the theme's checkout, which frames the
	 * heading and the order review as one box: the link goes inside the review,
	 * above its table, where no fragment refresh replaces it.
	 */
	public static function add_return_to_cart_link_in_review(): void
	{
		if (self::cart_has_booking() && null === self::rental_cart_data()) {
			self::print_return_to_cart_link();
		}
	}

	private static function print_return_to_cart_link(): void
	{
		echo '<div class="mhm-return-cart-wrapper"><a href="' . esc_url(wc_get_cart_url()) . '" class="mhm-return-cart-link"><span aria-hidden="true">&lsaquo;</span> ' . esc_html__('Return to Cart', 'mhm-rentiva') . '</a></div>';
	}
	/**
	 * 1. Inject a Custom VAT Row before the Order Total
	 */
	public static function add_custom_tax_row_checkout(): void
	{
		if (!isset(\WC()->cart) || null === self::rental_cart_data()) {
			return;
		}

		/** @var \WC_Cart $cart */
		$cart = \WC()->cart;

		// Prices shown without tax: WooCommerce's review-order.php draws the tax
		// rows itself, and a second set would count the tax twice on screen.
		if (! $cart->display_prices_including_tax()) {
			return;
		}

		// Get all tax totals dynamically to silence static analyzer
		$tax_totals = [];
		if (is_callable([ $cart, 'get_tax_totals' ])) {
			$tax_totals = call_user_func([ $cart, 'get_tax_totals' ]);
		}

		if (empty($tax_totals)) {
			return;
		}

		foreach ($tax_totals as $code => $tax) {
			// Use WooCommerce's native translation for consistency
			// Matches source text: (incl. tax)
			$label = $tax->label . ' ' . __('(incl. tax)', 'mhm-rentiva');
			?>
			<tr class="tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?> mhm-custom-tax-row">
				<th><?php echo esc_html($label); ?></th>
				<td><?php echo wp_kses_post($tax->formatted_amount); ?></td>
			</tr>
			<?php
		}
	}

	/**
	 * 2. Strip the "(includes tax)" suffix from the main Total HTML
	 *
	 * @param string $value The total html
	 * @return string Cleaned html
	 */
	public static function clean_checkout_total_html(string $value): string
	{
		// Only where add_custom_tax_row_checkout() says the same thing in a row:
		// on the cart page the suffix is the customer's only mention of tax.
		if (! function_exists('is_checkout') || ! \is_checkout() || null === self::rental_cart_data()) {
			return $value;
		}

		// The $value comes as "Price <small class='includes_tax'>...</small>"
		// We simply explode at the <small> tag and take the first part (the Price).
		$parts = explode('<small', $value);
		return $parts[0] ?? $value;
	}
}
