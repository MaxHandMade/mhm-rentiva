<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

use MHMRentiva\Admin\Settings\Settings;

if (! defined('ABSPATH')) {
	exit;
}

final class Mailer
{

	/**
	 * Booking data provider instance
	 *
	 * @var BookingDataProviderInterface|null
	 */
	private static ?BookingDataProviderInterface $booking_data_provider = null;

	/**
	 * Get booking data provider instance
	 *
	 * Uses filter hook to allow addons to provide custom implementations
	 *
	 * @return BookingDataProviderInterface Booking data provider
	 */
	private static function get_booking_data_provider(): BookingDataProviderInterface
	{
		if (self::$booking_data_provider === null) {
			/**
			 * Filter: Allow addons to provide custom booking data provider
			 *
			 * @param BookingDataProviderInterface|null $provider Booking data provider instance
			 * @return BookingDataProviderInterface Modified booking data provider instance
			 *
			 * @example
			 * add_filter('mhm_rentiva_booking_data_provider', function($provider) {
			 *     if (class_exists('MyCustomBookingProvider')) {
			 *         return new MyCustomBookingProvider();
			 *     }
			 *     return $provider;
			 * });
			 */
			$provider = apply_filters('mhm_rentiva_booking_data_provider', null);

			if ($provider instanceof BookingDataProviderInterface) {
				self::$booking_data_provider = $provider;
			} else {
				// Default: Use BookingQueryHelperAdapter
				self::$booking_data_provider = new BookingQueryHelperAdapter();
			}
		}

		return self::$booking_data_provider;
	}
	public static function register(): void
	{
		// Placeholder for future hooks
	}

	/**
	 * Central email sending method
	 *
	 * @param string $key Template key
	 * @param string $to Recipient email
	 * @param array  $context Template context
	 * @param array  $headers Additional headers
	 * @return bool Success status
	 */
	public static function send(string $key, string $to, array $context = array(), array $headers = array()): bool
	{
		if (empty($to) || ! is_email($to)) {
			return false;
		}

		// Check if email sending is enabled
		if (! \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_send_enabled()) {
			return false;
		}

		// Customer communication preferences check
		if (isset($context['customer']['email']) && $context['customer']['email'] === $to) {
			// Get user ID from email
			$user = get_user_by('email', $to);
			if ($user) {
				// Welcome email check - both global setting and user preference
				if ($key === 'welcome') {
					$global_enabled = self::is_welcome_email_enabled();
					$user_enabled   = get_user_meta($user->ID, 'mhm_welcome_email', true);
					if (! $global_enabled || $user_enabled === '0') {
						return false;
					}
				}

				// Booking notification check - both global setting and user preference
				if (in_array($key, array('booking_confirmation', 'booking_reminder', 'booking_cancellation'))) {
					$global_enabled = self::is_booking_notifications_enabled();
					$user_enabled   = get_user_meta($user->ID, 'mhm_booking_notifications', true);
					if (! $global_enabled || $user_enabled === '0') {
						return false;
					}
				}

				// Marketing email check - both global setting and user preference
				if ($key === 'marketing') {
					$global_enabled = self::is_marketing_emails_enabled();
					$user_enabled   = get_user_meta($user->ID, 'mhm_marketing_emails', true);
					if (! $global_enabled || $user_enabled === '0') {
						return false;
					}
				}
			} else {
				// User not found, check only global settings
				if ($key === 'welcome' && ! self::is_welcome_email_enabled()) {
					return false;
				}

				if (in_array($key, array('booking_confirmation', 'booking_reminder', 'booking_cancellation')) && ! self::is_booking_notifications_enabled()) {
					return false;
				}

				if ($key === 'marketing' && ! self::is_marketing_emails_enabled()) {
					return false;
				}
			}
		}

		// If in test mode, send to test address
		if (\MHMRentiva\Admin\Settings\Groups\EmailSettings::is_test_mode()) {
			$to = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_test_address();
		}

		$subject = Templates::compile_subject($key, $context);
		$body    = Templates::render_body($key, $context);
		$headers = self::normalize_headers($headers);

		// Default headers
		$from_name    = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_name();
		$from_address = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_address();

		$default_headers = array(
			'From: ' . $from_name . ' <' . $from_address . '>',
			'Content-Type: text/html; charset=UTF-8',
		);

		$headers = array_merge($default_headers, $headers);
		$headers = array_unique($headers);

		$ok = wp_mail($to, $subject, $body, $headers);

		// Action hook for logging/monitoring
		do_action('mhm_rentiva_email_sent', $key, $to, $ok, $subject, $context);

		return $ok;
	}

	/**
	 * Booking email sending helper
	 *
	 * @param string $template_key Template key
	 * @param int    $booking_id Booking ID
	 * @param string $recipient_type 'customer' or 'admin'
	 * @param array  $additional_context Additional context data
	 * @return bool Success status
	 */
	public static function sendBookingEmail(string $template_key, int $booking_id, string $recipient_type, array $additional_context = array()): bool
	{
		$booking_data = self::getBookingContext($booking_id);
		if (! $booking_data) {
			return false;
		}

		$context = array_merge($booking_data, $additional_context);

		if ($recipient_type === 'customer') {
			$email = $context['customer']['email'] ?? '';
			if (empty($email)) {
				return false;
			}
			return self::send($template_key, $email, $context);
		} elseif ($recipient_type === 'admin') {
			$email = get_option('admin_email');
			if (empty($email)) {
				return false;
			}
			return self::send($template_key, $email, $context);
		}

		return false;
	}

	/**
	 * Prepare booking context data
	 *
	 * @param int $booking_id Booking ID
	 * @return array|null Booking context data
	 */
	private static function getBookingContext(int $booking_id): ?array
	{
		if ($booking_id <= 0) {
			return null;
		}

		$post = get_post($booking_id);
		if (! $post || $post->post_type !== 'vehicle_booking') {
			return null;
		}

		// ⭐ Get data using interface-based provider (loose coupling)
		$provider        = self::get_booking_data_provider();
		$customer_info   = $provider->getBookingCustomerInfo($booking_id);
		$vehicle_info    = $provider->getBookingVehicleInfo($booking_id);
		$date_info       = $provider->getBookingDateInfo($booking_id);
		$payment_status  = $provider->getBookingPaymentStatus($booking_id);
		$payment_gateway = $provider->getBookingPaymentGateway($booking_id);
		$total_price     = $provider->getBookingTotalPrice($booking_id);

		// Get payment information
		$payment_type     = get_post_meta($booking_id, '_mhm_payment_type', true);
		$payment_method   = get_post_meta($booking_id, '_mhm_payment_method', true);
		$deposit_amount   = get_post_meta($booking_id, '_mhm_deposit_amount', true);
		$remaining_amount = get_post_meta($booking_id, '_mhm_remaining_amount', true);
		$payment_deadline = get_post_meta($booking_id, '_mhm_payment_deadline', true);

		return array(
			'booking'  => array(
				'id'               => $booking_id,
				'title'            => $post->post_title,
				'status'           => $post->post_status,
				'payment_status'   => $payment_status,
				'payment_gateway'  => $payment_gateway,
				'total_price'      => $total_price,
				'pickup_date'      => $date_info['pickup_date'] ?? '',
				'return_date'      => $date_info['return_date'] ?? '',
				'rental_days'      => $date_info['rental_days'] ?? 0,
				'payment_type'     => $payment_type,
				'payment_method'   => $payment_method,
				'deposit_amount'   => $deposit_amount,
				'remaining_amount' => $remaining_amount,
				'payment_deadline' => $payment_deadline,
			),
			'customer' => array(
				'name'       => $customer_info['first_name'] . ' ' . $customer_info['last_name'],
				'first_name' => $customer_info['first_name'],
				'last_name'  => $customer_info['last_name'],
				'email'      => $customer_info['email'],
				'phone'      => $customer_info['phone'],
			),
			'vehicle'  => array(
				'id'             => $vehicle_info['id'] ?? 0,
				'title'          => $vehicle_info['title'] ?? '',
				'price_per_day'  => $vehicle_info['price_per_day'] ?? 0,
				'featured_image' => $vehicle_info['featured_image'] ?? '',
			),
			'site'     => array(
				'name'        => get_bloginfo('name'),
				'url'         => home_url('/'),
				'admin_email' => get_option('admin_email'),
			),
		);
	}

	/**
	 * Prepare message context data
	 *
	 * @param int $message_id Message ID
	 * @return array|null Message context data
	 */
	public static function getMessageContext(int $message_id): ?array
	{
		if ($message_id <= 0) {
			return null;
		}

		$post = get_post($message_id);
		if (! $post || $post->post_type !== 'mhm_message') {
			return null;
		}

		$customer_name  = get_post_meta($message_id, '_mhm_customer_name', true);
		$customer_email = get_post_meta($message_id, '_mhm_customer_email', true);
		$category       = get_post_meta($message_id, '_mhm_message_category', true);
		$status         = get_post_meta($message_id, '_mhm_message_status', true);
		$thread_id      = get_post_meta($message_id, '_mhm_thread_id', true);

		return array(
			'message'  => array(
				'id'        => $message_id,
				'subject'   => $post->post_title,
				'content'   => $post->post_content,
				'date'      => $post->post_date,
				'category'  => $category,
				'status'    => $status,
				'thread_id' => $thread_id,
			),
			'customer' => array(
				'name'  => $customer_name ?: __('Anonymous', 'mhm-rentiva'),
				'email' => $customer_email,
			),
			'site'     => array(
				'name'        => get_bloginfo('name'),
				'url'         => home_url('/'),
				'admin_email' => get_option('admin_email'),
			),
		);
	}

	/**
	 * Normalize headers
	 *
	 * @param array $headers Headers array
	 * @return array Normalized headers
	 */
	private static function normalize_headers(array $headers): array
	{
		$out = array();
		foreach ($headers as $h) {
			if (! is_string($h)) {
				continue;
			}
			$h = trim($h);
			if ($h !== '') {
				$out[] = $h;
			}
		}
		return $out;
	}

	/**
	 * Email sending statistics - optimized version
	 *
	 * @param string $key Template key
	 * @param int    $days Days to look back
	 * @return array Statistics
	 */
	public static function getEmailStats(string $key = '', int $days = 30): array
	{
		// Create cache key
		$cache_key = 'email_stats_' . md5($key . '_' . $days);

		// Check cache
		$cached_stats = get_transient($cache_key);
		if ($cached_stats !== false) {
			return $cached_stats;
		}

		// Check if logging is enabled
		if (! \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_log_enabled()) {
			$stats = array(
				'total_sent'   => 0,
				'successful'   => 0,
				'failed'       => 0,
				'success_rate' => 0,
			);

			// 5 minutes cache
			set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
			return $stats;
		}

		// ⭐ Using WP_Query instead of raw SQL for better maintainability
		$date_threshold = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

		// Build meta query
		$meta_query = array(
			array(
				'key'     => '_mhm_email_status',
				'compare' => 'EXISTS',
			),
		);

		// Filter by template key if provided
		if (! empty($key)) {
			$meta_query[] = array(
				'key'     => '_mhm_email_key',
				'value'   => $key,
				'compare' => 'LIKE',
			);
		}

		// Query for all emails in date range
		$query = new \WP_Query(
			array(
				'post_type'      => 'mhm_email_log',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'after'     => $date_threshold,
						'inclusive' => true,
					),
				),
				'meta_query'     => $meta_query,
				'no_found_rows'  => false,
			)
		);

		// Calculate statistics from results
		$total_sent = $query->found_posts ?? 0;
		$successful = 0;
		$failed     = 0;

		if ($query->have_posts()) {
			foreach ($query->posts as $post_id) {
				$status = get_post_meta($post_id, '_mhm_email_status', true);
				if ($status === 'success') {
					++$successful;
				} elseif ($status === 'failed') {
					++$failed;
				}
			}
		}

		$stats = (object) array(
			'total_sent' => $total_sent,
			'successful' => $successful,
			'failed'     => $failed,
		);

		$final_stats = array(
			'total_sent'   => (int) $stats->total_sent,
			'successful'   => (int) $stats->successful,
			'failed'       => (int) $stats->failed,
			'success_rate' => $stats->total_sent > 0 ? round(($stats->successful / $stats->total_sent) * 100, 2) : 0,
		);

		// 15 minutes cache
		set_transient($cache_key, $final_stats, 15 * MINUTE_IN_SECONDS);

		return $final_stats;
	}

	/**
	 * Customer communication settings helper methods
	 */
	private static function is_welcome_email_enabled(): bool
	{
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_welcome_email', '1') === '1';
	}

	private static function is_booking_notifications_enabled(): bool
	{
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_booking_notifications', '1') === '1';
	}

	private static function is_marketing_emails_enabled(): bool
	{
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_marketing_emails', '0') === '1';
	}
}
