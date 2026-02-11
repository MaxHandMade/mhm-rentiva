<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Booking Confirmation Shortcode
 *
 * Displays booking confirmation details
 *
 * @since 4.0.0
 */
final class BookingConfirmation extends AbstractShortcode
{


	/**
	 * Returns the shortcode tag
	 */
	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_booking_confirmation';
	}

	/**
	 * Returns the template file path
	 */
	protected static function get_template_path(): string
	{
		return 'shortcodes/booking-confirmation';
	}

	/**
	 * Returns default attributes for template
	 */
	protected static function get_default_attributes(): array
	{
		return array(
			'booking_id'   => '',
			'show_details' => '1',
			'show_actions' => '1',
			'class'        => '',
		);
	}

	/**
	 * Returns CSS filename
	 */
	protected static function get_css_filename(): string
	{
		return 'booking-confirmation.css';
	}

	/**
	 * Prepares template data
	 */
	protected static function prepare_template_data(array $atts): array
	{
		// Get booking_id from URL
		$booking_id = (int) ($atts['booking_id'] ?? 0);

		// Also check from URL parameter
		if (! $booking_id) {
			$booking_id = (int) (isset($_GET['booking_id']) ? wp_unslash($_GET['booking_id']) : 0);
		}

		if (! $booking_id) {
			return array(
				'error' => __('Invalid booking ID.', 'mhm-rentiva'),
				'atts'  => $atts,
			);
		}

		$booking = get_post($booking_id);
		if (! $booking || $booking->post_type !== 'vehicle_booking') {
			return array(
				'error' => __('Booking not found.', 'mhm-rentiva'),
				'atts'  => $atts,
			);
		}

		// Vehicle information
		$vehicle_id = (int) get_post_meta($booking_id, MetaKeys::BOOKING_VEHICLE_ID, true);
		$vehicle    = get_post($vehicle_id);

		// Booking dates and times
		$pickup_date  = (string) get_post_meta($booking_id, MetaKeys::BOOKING_PICKUP_DATE, true);
		$dropoff_date = (string) get_post_meta($booking_id, MetaKeys::BOOKING_DROPOFF_DATE, true);
		$pickup_time  = (string) get_post_meta($booking_id, MetaKeys::BOOKING_PICKUP_TIME, true);
		$dropoff_time = (string) get_post_meta($booking_id, MetaKeys::BOOKING_RETURN_TIME, true);

		// Pricing information
		$total_price      = get_post_meta($booking_id, MetaKeys::BOOKING_TOTAL_PRICE, true);
		$status           = (string) get_post_meta($booking_id, MetaKeys::BOOKING_STATUS, true);
		$payment_type     = (string) get_post_meta($booking_id, MetaKeys::BOOKING_PAYMENT_TYPE, true);
		$deposit_amount   = get_post_meta($booking_id, MetaKeys::BOOKING_DEPOSIT_AMOUNT, true);
		$remaining_amount = get_post_meta($booking_id, MetaKeys::BOOKING_REMAINING_AMOUNT, true);
		$selected_addons  = get_post_meta($booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, true);

		// Customer information
		$customer_first_name = (string) get_post_meta($booking_id, MetaKeys::BOOKING_CUSTOMER_FIRST_NAME, true);
		$customer_last_name  = (string) get_post_meta($booking_id, MetaKeys::BOOKING_CUSTOMER_LAST_NAME, true);
		$customer_name       = trim($customer_first_name . ' ' . $customer_last_name);
		$customer_email      = (string) get_post_meta($booking_id, MetaKeys::BOOKING_CUSTOMER_EMAIL, true);
		$customer_phone      = (string) get_post_meta($booking_id, MetaKeys::BOOKING_CUSTOMER_PHONE, true);

		// Payment information
		$payment_method = (string) get_post_meta($booking_id, MetaKeys::BOOKING_PAYMENT_METHOD, true);
		$payment_status = (string) get_post_meta($booking_id, MetaKeys::BOOKING_PAYMENT_STATUS, true);

		// Enhance data with WooCommerce Order details if available
		$order_id = (int) get_post_meta($booking_id, MetaKeys::BOOKING_WC_ORDER_ID, true);
		if (! $order_id) {
			$order_id = (int) get_post_meta($booking_id, '_mhm_woocommerce_order_id', true);
		}

		if ($order_id && function_exists('wc_get_order')) {
			$order = wc_get_order($order_id);
			if ($order instanceof \WC_Order) {
				/** @var \WC_Order $order */
				// Get correct payment method title (e.g., "Credit Card" instead of "stripe")
				if (method_exists($order, 'get_payment_method_title')) {
					$method_name          = 'get_payment_method_title';
					$payment_method_title = $order->$method_name();
					if (! empty($payment_method_title)) {
						$payment_method = (string) $payment_method_title;
					}
				}

				// Get customer details from billing address
				$billing_first_name = (string) (method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : '');
				$billing_last_name  = (string) (method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : '');
				$billing_phone      = (string) (method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : '');

				if (! empty($billing_first_name) || ! empty($billing_last_name)) {
					$customer_name       = trim($billing_first_name . ' ' . $billing_last_name);
					$customer_first_name = $billing_first_name;
					$customer_last_name  = $billing_last_name;
				}

				if (! empty($billing_phone)) {
					$customer_phone = $billing_phone;
				}
			}
		}

		// Fallback: If customer data is still missing, try to get from WP User (post author)
		if (empty($customer_phone) || empty($customer_name) || trim($customer_name) === html_entity_decode((string) get_the_author_meta('display_name', (int) $booking->post_author))) {
			$author_id = (int) $booking->post_author;
			if ($author_id > 0) {
				// Try to get billing phone from user meta (WooCommerce standard)
				if (empty($customer_phone)) {
					$customer_phone = (string) get_user_meta($author_id, 'billing_phone', true);
				}

				// If name looks like username or is empty, try to get real name
				$user_info = get_userdata($author_id);
				if ($user_info) {
					$u_first_name = (string) $user_info->first_name;
					$u_last_name  = (string) $user_info->last_name;

					if (! empty($u_first_name) || ! empty($u_last_name)) {
						// Use user profile name if available
						$customer_name       = trim($u_first_name . ' ' . $u_last_name);
						$customer_first_name = $u_first_name;
						$customer_last_name  = $u_last_name;
					} elseif (empty($customer_name)) {
						// Fallback to display name if completely empty
						$customer_name = (string) $user_info->display_name;
					}
				}
			}
		}

		// Fix "woocommerce" payment method label
		if (! empty($payment_method) && (strtolower($payment_method) === 'woocommerce' || strtolower($payment_method) === 'wc_payment_method')) {
			$payment_method = __('Online Payment', 'mhm-rentiva');
		}

		// Vehicle image
		$vehicle_image = (string) get_the_post_thumbnail_url($vehicle_id, 'medium');
		if (! $vehicle_image) {
			$vehicle_image = MHM_RENTIVA_PLUGIN_URL . 'assets/images/no-image.png';
		}

		// Currency symbol
		$currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

		return array(
			'booking'             => $booking,
			'booking_id'          => $booking_id,
			'vehicle'             => $vehicle,
			'vehicle_id'          => $vehicle_id,
			'vehicle_image'       => $vehicle_image,
			'pickup_date'         => $pickup_date,
			'dropoff_date'        => $dropoff_date,
			'pickup_time'         => $pickup_time,
			'dropoff_time'        => $dropoff_time,
			'total_price'         => $total_price,
			'status'              => $status,
			'payment_type'        => $payment_type,
			'deposit_amount'      => $deposit_amount,
			'remaining_amount'    => $remaining_amount,
			'selected_addons'     => $selected_addons,
			'customer_first_name' => $customer_first_name,
			'customer_last_name'  => $customer_last_name,
			'customer_name'       => $customer_name,
			'customer_email'      => $customer_email,
			'customer_phone'      => $customer_phone,
			'payment_method'      => $payment_method,
			'payment_status'      => $payment_status,
			'currency_symbol'     => $currency_symbol,
			'atts'                => $atts,
		);
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
	protected static function enqueue_assets(array $atts = []): void
	{
		// CSS
		wp_enqueue_style(
			'mhm-rentiva-booking-confirmation',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/booking-confirmation.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// JavaScript with cache busting
		wp_enqueue_script(
			'mhm-rentiva-booking-confirmation',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/booking-confirmation.js',
			array('jquery'),
			MHM_RENTIVA_VERSION . '.' . time() . '.' . wp_rand(1000, 9999),
			true
		);

		// Localize script
		wp_localize_script(
			'mhm-rentiva-booking-confirmation',
			'mhmRentivaBookingConfirmation',
			array(
				'ajaxUrl'      => admin_url('admin-ajax.php'),
				'nonce'        => wp_create_nonce('mhm_rentiva_booking_confirmation'),
				'dashboardUrl' => admin_url('admin.php?page=mhm-rentiva-dashboard'),
				'i18n'         => array(
					'print'                => __('Print', 'mhm-rentiva'),
					'download'             => __('Download', 'mhm-rentiva'),
					'share'                => __('Share', 'mhm-rentiva'),
					'back_to_dashboard'    => __('Back to Dashboard', 'mhm-rentiva'),
					'downloading'          => __('Downloading...', 'mhm-rentiva'),
					'download_success'     => __('Download completed', 'mhm-rentiva'),
					'download_error'       => __('An error occurred during download.', 'mhm-rentiva'),
					'print_error'          => __('An error occurred during printing.', 'mhm-rentiva'),
					'booking_id_not_found' => __('Booking ID not found.', 'mhm-rentiva'),
					'booking_confirmation' => __('Booking Confirmation', 'mhm-rentiva'),
					'view_booking_details' => __('View your booking details.', 'mhm-rentiva'),
					'share_error'          => __('An error occurred during sharing.', 'mhm-rentiva'),
					'link_copied'          => __('Link copied to clipboard!', 'mhm-rentiva'),
					'link_copy_failed'     => __('Link could not be copied.', 'mhm-rentiva'),
				),
			)
		);
	}


	/**
	 * Create booking confirmation URL
	 */
	public static function get_confirmation_url(int $booking_id): string
	{
		$confirmation_url = (string) ShortcodeUrlManager::get_page_url('rentiva_booking_confirmation');

		if (self::is_fallback_url($confirmation_url)) {
			ShortcodeUrlManager::clear_cache('rentiva_booking_confirmation');
			$confirmation_url = (string) ShortcodeUrlManager::get_page_url('rentiva_booking_confirmation');
		}

		return (string) add_query_arg(array('booking_id' => $booking_id), $confirmation_url);
	}

	private static function is_fallback_url(string $url): bool
	{
		$home_url  = trailingslashit(home_url('/'));
		$candidate = trailingslashit($url);

		if (ShortcodeUrlManager::page_exists('rentiva_booking_confirmation')) {
			return false;
		}

		return $candidate === $home_url;
	}

	/**
	 * Get booking confirmation URL as short URL
	 */
	public static function get_short_confirmation_url(int $booking_id): string
	{
		return (string) ShortcodeUrlManager::get_page_url('rentiva_booking_confirmation') . $booking_id . '/';
	}
}
