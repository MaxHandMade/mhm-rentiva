<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;



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
	public static function render_dashboard(array $atts = array()): string
	{
		return Templates::render('account/dashboard', self::get_dashboard_data($atts), true);
	}

	/**
	 * Get dashboard data
	 */
	public static function get_dashboard_data(array $atts = array()): array
	{
		$user = wp_get_current_user();

		// User bookings
		$bookings        = self::get_user_bookings($user->ID);
		$active_bookings = array_filter(
			$bookings,
			function ($booking) {
				$status = get_post_meta($booking->ID, '_mhm_status', true);
				return in_array($status, array('confirmed', 'in_progress'));
			}
		);

		$data = array(
			'user'                  => $user,
			'bookings_count'        => count($bookings),
			'active_bookings_count' => count($active_bookings),
			'recent_bookings'       => array_slice($bookings, 0, 5),
			'navigation'            => self::get_navigation(),
			'favorites'             => SettingsCore::get('mhm_rentiva_customer_favorites', '1'),
			'booking_history'       => SettingsCore::get('mhm_rentiva_customer_booking_history', '1'),
			'welcome_message'       => SettingsCore::get('mhm_rentiva_customer_dashboard_welcome', __('Welcome to your dashboard. From your account dashboard you can view your recent orders, manage your shipping and billing addresses, and edit your password and account details.', 'mhm-rentiva')),
		);

		return array('data' => $data);
	}

	/**
	 * Bookings render
	 */
	public static function render_bookings(array $atts = array()): string
	{
		return Templates::render('account/bookings', self::get_bookings_data($atts), true);
	}

	/**
	 * Get bookings data
	 */
	public static function get_bookings_data(array $atts = array()): array
	{
		$user = wp_get_current_user();

		$args = array(
			'limit'   => (int) ($atts['limit'] ?? 10),
			'status'  => self::sanitize_text_field_safe($atts['status'] ?? ''),
			'orderby' => self::sanitize_text_field_safe($atts['orderby'] ?? 'date'),
			'order'   => self::sanitize_text_field_safe($atts['order'] ?? 'DESC'),
		);

		$bookings = self::get_user_bookings($user->ID, $args);

		$data = array(
			'user'       => $user,
			'bookings'   => $bookings,
			'navigation' => (isset($atts['hide_nav']) && $atts['hide_nav']) ? array() : self::get_navigation(),
		);

		return array('data' => $data);
	}

	/**
	 * Favorites render
	 */
	public static function render_favorites(array $atts = array()): string
	{
		return Templates::render('account/favorites', self::get_favorites_data($atts), true);
	}

	/**
	 * Get favorites data
	 */
	public static function get_favorites_data(array $atts = array()): array
	{
		$user      = wp_get_current_user();
		$favorites = get_user_meta($user->ID, 'mhm_rentiva_favorites', true);

		if (! is_array($favorites) || empty($favorites)) {
			$favorites = array();
		}

		// Load Vehicles Grid CSS (for grid view)
		wp_enqueue_style(
			'mhm-rentiva-vehicles-grid',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vehicles-grid.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Ensure standardized card styles are available on My Account favorites endpoint.
		if (! wp_style_is('mhm-vehicle-card-css', 'registered')) {
			wp_register_style(
				'mhm-vehicle-card-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/vehicle-card.css',
				array('mhm-css-variables'),
				MHM_RENTIVA_VERSION
			);
		}
		wp_enqueue_style('mhm-vehicle-card-css');

		// Enqueue global interactions (v1.3.3)
		wp_enqueue_script('mhm-vehicle-interactions');

		// Localize (grid/list ortak)
		wp_localize_script(
			'mhm-rentiva-my-account',
			'mhmRentivaVehiclesList',
			array(
				'ajaxUrl'    => admin_url('admin-ajax.php'),
				'nonce'      => wp_create_nonce('mhm_rentiva_vehicles_list'),
				'bookingUrl' => ShortcodeUrlManager::get_page_url('rentiva_booking_form'),
				'loginUrl'   => wp_login_url(),
				'icons'      => array(
					'heart' => \MHMRentiva\Helpers\Icons::get('heart'),
				),
				'text'       => array(
					'loading'                => __('Loading...', 'mhm-rentiva'),
					'no_vehicles'            => __('No vehicles found', 'mhm-rentiva'),
					'error'                  => __('An error occurred', 'mhm-rentiva'),
					'book_now'               => __('Book Now', 'mhm-rentiva'),
					'view_details'           => __('View Details', 'mhm-rentiva'),
					'added_to_favorites'     => __('Added to favorites', 'mhm-rentiva'),
					'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
					'login_required'         => __('You must be logged in to add to favorites', 'mhm-rentiva'),
				),
			)
		);

		$data = array(
			'user'       => $user,
			'favorites'  => $favorites,
			'columns'    => (int) ($atts['columns'] ?? 3),
			'navigation' => (isset($atts['hide_nav']) && $atts['hide_nav']) ? array() : self::get_navigation(),
		);

		return array('data' => $data);
	}

	/**
	 * Payment History render
	 */
	public static function render_payment_history(array $atts = array()): string
	{
		return Templates::render('account/payment-history', self::get_payment_history_data($atts), true);
	}

	/**
	 * Get payment history data
	 */
	public static function get_payment_history_data(array $atts = array()): array
	{
		$user     = wp_get_current_user();
		$per_page = 10;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$result = self::get_user_payments($user->ID, $per_page, $page);

		$data = array(
			'user'         => $user,
			'payments'     => $result['items'],
			'total'        => $result['total'],
			'total_pages'  => $result['total_pages'],
			'current_page' => $result['current_page'],
			'navigation'   => (isset($atts['hide_nav']) && $atts['hide_nav']) ? array() : self::get_navigation(),
		);

		return array('data' => $data);
	}

	/**
	 * Account Details render (Removed)
	 *
	 * @see WooCommerce My Account
	 */
	/*
	public static function render_account_details(array $atts = []): string
	{
		// Removed as per request (redundant with WooCommerce)
		return '';
	}
	*/

	/**
	 * Messages render
	 */
	public static function render_messages(array $atts = array()): string
	{
		$data_res = self::get_messages_data($atts);
		if (isset($data_res['error'])) {
			return $data_res['error'];
		}
		return Templates::render('account/messages', $data_res, true);
	}

	/**
	 * Get messages data
	 */
	public static function get_messages_data(array $atts = array()): array
	{
		// Check if Messages feature is enabled
		if (
			! class_exists(\MHMRentiva\Admin\Licensing\Mode::class) ||
			! \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)
		) {
			return array(
				'error' => '<div class="mhm-rentiva-account-page"><div class="mhm-account-content"><p>' .
					__('Messages feature is available in Pro version.', 'mhm-rentiva') .
					'</p></div></div>',
			);
		}

		$user = wp_get_current_user();

		// Load CSS files
		wp_enqueue_style(
			'mhm-customer-messages',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/customer-messages.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// ⭐ Load JavaScript file (required for messages functionality)
		wp_enqueue_script(
			'mhm-rentiva-account-messages',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/account-messages.js',
			array('jquery'),
			MHM_RENTIVA_VERSION,
			true
		);

		// ⭐ Localize JavaScript (required for REST API calls)
		wp_localize_script(
			'mhm-rentiva-account-messages',
			'mhmRentivaMessages',
			array(
				'restUrl'       => rest_url('mhm-rentiva/v1/'),
				'restNonce'     => wp_create_nonce('wp_rest'),
				'customerEmail' => $user->user_email,
				'customerName'  => $user->display_name ?: $user->user_login,
				'i18n'          => array(
					'threadIdNotFound'   => __('Thread ID not found.', 'mhm-rentiva'),
					'confirmClose'       => __('Are you sure you want to close this conversation? You won\'t be able to send more messages.', 'mhm-rentiva'),
					'closing'            => __('Closing...', 'mhm-rentiva'),
					'messageClosed'      => __('Message closed successfully.', 'mhm-rentiva'),
					'closeFailed'        => __('Failed to close message.', 'mhm-rentiva'),
					'loadingMessages'    => __('Loading messages...', 'mhm-rentiva'),
					'noMessages'         => __('No messages found yet.', 'mhm-rentiva'),
					'loadFailed'         => __('Failed to load messages.', 'mhm-rentiva'),
					'loginRequired'      => __('Please login to access your messages.', 'mhm-rentiva'),
					'permissionDenied'   => __('You do not have permission to access messages.', 'mhm-rentiva'),
					'new'                => __('New', 'mhm-rentiva'),
					'customer'           => __('Customer', 'mhm-rentiva'),
					'administrator'      => __('Administrator', 'mhm-rentiva'),
					'loadingThread'      => __('Loading thread...', 'mhm-rentiva'),
					'threadLoadFailed'   => __('Failed to load thread.', 'mhm-rentiva'),
					'noMessagesFound'    => __('No messages found.', 'mhm-rentiva'),
					'closeMessage'       => __('Close Message', 'mhm-rentiva'),
					'conversationClosed' => __('This conversation is closed.', 'mhm-rentiva'),
					'replySent'          => __('Reply sent successfully.', 'mhm-rentiva'),
					'replyFailed'        => __('Failed to send reply.', 'mhm-rentiva'),
					'messageSent'        => __('Message sent successfully.', 'mhm-rentiva'),
					'messageFailed'      => __('Failed to send message.', 'mhm-rentiva'),
					'sending'            => __('Sending...', 'mhm-rentiva'),
					'sendMessage'        => __('Send Message', 'mhm-rentiva'),
					'sendReply'          => __('Send Reply', 'mhm-rentiva'),
					'cancel'             => __('Cancel', 'mhm-rentiva'),
					'backToMessages'     => __('Back to Messages', 'mhm-rentiva'),
					'newMessage'         => __('New Message', 'mhm-rentiva'),
					'yourReply'          => __('Your Reply:', 'mhm-rentiva'),
				),
			)
		);

		$data = array(
			'user'           => $user,
			'customer_email' => $user->user_email,
			'customer_name'  => $user->display_name ?: $user->user_login,
			'navigation'     => (isset($atts['hide_nav']) && $atts['hide_nav']) ? array() : self::get_navigation(),
		);

		return array('data' => $data);
	}



	/**
	 * Booking detail render
	 */
	/**
	 * Booking detail render
	 */
	public static function render_booking_detail(int $booking_id, bool $hide_nav = false): string
	{
		$data_res = self::get_booking_detail_data($booking_id, $hide_nav);
		if (isset($data_res['error'])) {
			return $data_res['error'];
		}
		return Templates::render('account/booking-detail', $data_res, true);
	}

	/**
	 * Get booking detail data
	 */
	public static function get_booking_detail_data(int $booking_id, bool $hide_nav = false): array
	{
		$booking = get_post($booking_id);

		if (! $booking || $booking->post_type !== 'vehicle_booking') {
			return array('error' => '<p>' . __('Booking not found.', 'mhm-rentiva') . '</p>');
		}

		// User check
		$user            = wp_get_current_user();
		$booking_user_id = get_post_meta($booking_id, '_mhm_customer_user_id', true);

		if ($booking_user_id != $user->ID && ! current_user_can('manage_options')) {
			return array('error' => '<p>' . __('You do not have permission to view this booking.', 'mhm-rentiva') . '</p>');
		}

		$navigation = $hide_nav ? array() : self::get_navigation();

		// If navigation is empty (e.g. WooCommerce integration), provide minimal nav for breadcrumbs
		if (empty($navigation)) {
			$dashboard_url = home_url('/');
			$bookings_url  = home_url('/');

			if (class_exists('WooCommerce') && function_exists('wc_get_endpoint_url')) {
				$my_account_url = wc_get_page_permalink('myaccount');
				$dashboard_url  = $my_account_url;
				$bookings_slug  = AccountController::get_endpoint_slug('bookings', 'rentiva-bookings');
				$bookings_url   = wc_get_endpoint_url($bookings_slug, '', $my_account_url);
			} else {
				$dashboard_url = AccountController::get_account_url();
				$bookings_url  = AccountController::get_booking_view_url($booking_id);
			}

			$navigation = array(
				'dashboard' => array('url' => $dashboard_url),
				'bookings'  => array('url' => $bookings_url),
			);
		}

		$data = array(
			'booking'       => $booking,
			'booking_id'    => $booking_id,
			'navigation'    => $navigation,
			'is_integrated' => $hide_nav,
		);

		return array('data' => $data);
	}

	/**
	 * Get navigation menu
	 * Returns empty array if WooCommerce My Account page is active (to avoid duplicate navigation)
	 */
	private static function get_navigation(): array
	{
		// ⭐ Don't show custom navigation if WooCommerce is active
		// WooCommerce provides its own navigation which is preferred
		if (class_exists('WooCommerce')) {
			return array();
		}

		// Main account page URL
		$base_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_my_account');

		return array(
			'dashboard'       => array(
				'title' => __('Dashboard', 'mhm-rentiva'),
				'url'   => $base_url,
				'icon'  => '📊',
			),
			'bookings'        => array(
				'title' => __('My Bookings', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'bookings', $base_url),
				'icon'  => '📅',
			),
			'favorites'       => array(
				'title' => __('Favorite Vehicles', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'favorites', $base_url),
				'icon'  => '❤️',
			),
			'payment-history' => array(
				'title' => __('Payment History', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'payment-history', $base_url),
				'icon'  => '💳',
			),
			'messages'        => array(
				'title' => __('Messages', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'messages', $base_url),
				'icon'  => '💬',
			),
			'edit-account'    => array(
				'title' => __('Account Details', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'edit-account', $base_url),
				'icon'  => '⚙️',
			),
			'logout'          => array(
				'title' => __('Logout', 'mhm-rentiva'),
				'url'   => wp_logout_url($base_url),
				'icon'  => '🚪',
			),
		);
	}

	/**
	 * Check if current page is WooCommerce My Account page
	 *
	 * @return bool True if WooCommerce My Account page is active
	 */
	private static function is_woocommerce_account_page(): bool
	{
		if (! class_exists('WooCommerce') || ! function_exists('wc_get_page_id')) {
			return false;
		}

		$account_page_id = wc_get_page_id('myaccount');
		if (! $account_page_id || $account_page_id <= 0) {
			return false;
		}

		// Check if we're on the WooCommerce My Account page
		return is_page($account_page_id) || is_account_page();
	}

	/**
	 * Get user bookings
	 */
	private static function get_user_bookings(int $user_id, array $args = array()): array
	{
		global $wpdb;

		$defaults = array(
			'limit'   => 10,
			'status'  => '',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$args = wp_parse_args($args, $defaults);

		// Meta query
		$meta_query = array(
			array(
				'key'     => '_mhm_customer_user_id',
				'value'   => $user_id,
				'compare' => '=',
			),
		);

		// Status filter
		if (! empty($args['status'])) {
			$meta_query[] = array(
				'key'     => '_mhm_status',
				'value'   => self::sanitize_text_field_safe($args['status']),
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['limit'],
			'orderby'        => self::sanitize_text_field_safe($args['orderby']),
			'order'          => self::sanitize_text_field_safe($args['order']),
			'meta_query'     => $meta_query,
		);

		$query = new \WP_Query($query_args);

		return $query->posts;
	}

	/**
	 * Get user payments
	 */
	private static function get_user_payments(int $user_id, int $per_page = 10, int $page = 1): array
	{
		// Get all user bookings
		$bookings = self::get_user_bookings($user_id, array('limit' => -1));

		$payments = array();

		foreach ($bookings as $booking) {
			$payment_status  = get_post_meta($booking->ID, '_mhm_payment_status', true);
			$payment_method  = get_post_meta($booking->ID, '_mhm_payment_method', true);
			$payment_gateway = get_post_meta($booking->ID, '_mhm_payment_gateway', true);
			$total_price     = (float) get_post_meta($booking->ID, '_mhm_total_price', true);
			$deposit_amount  = (float) get_post_meta($booking->ID, '_mhm_deposit_amount', true);
			$payment_type    = get_post_meta($booking->ID, '_mhm_payment_type', true);

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
			$pickup_date    = get_post_meta($booking->ID, '_mhm_pickup_date', true) ?: get_post_meta($booking->ID, '_booking_pickup_date', true);
			$pickup_time    = get_post_meta($booking->ID, '_mhm_start_time', true) ?: get_post_meta($booking->ID, '_mhm_pickup_time', true) ?: get_post_meta($booking->ID, '_booking_pickup_time', true);
			$date_str       = trim($pickup_date . ' ' . ($pickup_time ?: ''));
			$date_formatted = $date_str ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date_str)) : get_the_date('', $booking->ID);

			if ($payment_status) {
				// Translate Status
				$status_labels = array(
					'pending'   => __('Pending', 'mhm-rentiva'),
					'completed' => __('Completed', 'mhm-rentiva'),
					'cancelled' => __('Cancelled', 'mhm-rentiva'),
				);
				$status_label  = $status_labels[$payment_status] ?? ucfirst($payment_status);

				// Format Gateway/Method
				$woocommerce_method_title = '';

				// Try to get method title from WooCommerce Order if exists
				$wc_order_id = get_post_meta($booking->ID, '_mhm_woocommerce_order_id', true);
				if ($wc_order_id && function_exists('wc_get_order')) {
					$order = wc_get_order($wc_order_id);
					if ($order instanceof \WC_Order && method_exists($order, 'get_payment_method_title')) {
						$method                   = 'get_payment_method_title';
						$woocommerce_method_title = $order->$method();
					}
				}

				// If we found a real title, use it. Otherwise fallback to existing method or gateway
				if (! empty($woocommerce_method_title)) {
					$method_display = $woocommerce_method_title;
				} elseif (! empty($payment_method) && $payment_method !== 'manual') {
					$method_display = ucfirst($payment_method);
				} else {
					// Fallback to gateway name if method is empty
					$method_display = $payment_gateway === 'woocommerce' ? 'WooCommerce' : ucfirst($payment_gateway);
				}

				$payments[] = array(
					'booking_id'    => $booking->ID,
					'booking_title' => get_the_title($booking->ID),
					'date'          => $date_formatted,
				'sort_ts'       => $date_str ? strtotime($date_str) : 0,
					'status'        => $payment_status,
					'status_label'  => $status_label,
					'method'        => $method_display,
					// 'gateway' => $gateway_label, // Removed as requested
					'amount'        => $payment_type === 'deposit' ? $deposit_amount : $total_price,
					'total'         => $total_price,
					'type'          => $payment_type,
					'receipt'       => array(
						'attachment_id' => (int) get_post_meta($booking->ID, '_mhm_receipt_attachment_id', true),
						'status'        => get_post_meta($booking->ID, '_mhm_receipt_status', true),
						'url'           => ($id = (int) get_post_meta($booking->ID, '_mhm_receipt_attachment_id', true)) ? wp_get_attachment_url($id) : '',
					),
				);
			}
		}

		// Latest payments first
		usort(
			$payments,
			function ($a, $b) {
				return $b['sort_ts'] - $a['sort_ts'];
			}
		);

		$total  = count($payments);
		$offset = ($page - 1) * $per_page;

		return array(
			'items'        => array_slice($payments, $offset, $per_page),
			'total'        => $total,
			'total_pages'  => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}
}
