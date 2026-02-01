<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Security Helper
 *
 * Security controls and helper methods for shortcodes
 */
final class SecurityHelper
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
	 * AJAX request security check
	 *
	 * @param string $nonce_name Nonce name
	 * @param string $capability Required capability (default: 'read')
	 * @return bool Security check successful
	 */
	public static function verify_ajax_request(string $nonce_name, string $capability = 'read'): bool
	{
		// Nonce check (accept common keys and both POST/GET)
		$nonce = '';
		foreach (array('nonce', 'security', '_ajax_nonce') as $key) {
			if (isset($_POST[$key])) {
				$nonce = self::sanitize_text_field_safe(wp_unslash($_POST[$key]));
				break;
			}
			if (isset($_GET[$key]) && $nonce === '') {
				$nonce = self::sanitize_text_field_safe(wp_unslash($_GET[$key]));
			}
		}
		if (! wp_verify_nonce($nonce, $nonce_name)) {
			// Debug log for admins only
			if (current_user_can('manage_options')) {
				error_log('[MHM SecurityHelper] Nonce verification failed for action ' . $nonce_name . ' with nonce: ' . ($nonce ?: 'EMPTY'));
			}
			return false;
		}

		// Capability check (only for logged in users)
		if (is_user_logged_in() && ! current_user_can($capability)) {
			return false;
		}

		return true;
	}

	/**
	 * AJAX request security check ve hata response'u
	 *
	 * @param string $nonce_name Nonce name
	 * @param string $capability Required capability
	 * @param string $error_message Error message
	 * @return bool Security check successful
	 */
	public static function verify_ajax_request_or_die(string $nonce_name, string $capability = 'read', string $error_message = ''): bool
	{
		if (! self::verify_ajax_request($nonce_name, $capability)) {
			$default_message = __('Security check failed.', 'mhm-rentiva');
			wp_send_json_error(array('message' => $error_message ?: $default_message));
			return false; // Bu satır çalışmaz ama IDE uyarısını önler
		}

		return true;
	}

	/**
	 * Rate limiting check
	 *
	 * @param string   $action Action name
	 * @param int      $limit Limit count
	 * @param int      $window Time window (seconds)
	 * @param int|null $user_id User ID (null = current user)
	 * @return bool Rate limit exceeded
	 */
	public static function check_rate_limit(string $action, int $limit = 10, int $window = 300, ?int $user_id = null): bool
	{
		if ($user_id === null) {
			$user_id = get_current_user_id();
		}

		// IP-based rate limiting for anonymous users
		if ($user_id === 0) {
			$user_id = self::get_client_ip();
		}

		$key      = "mhm_rate_limit_{$action}_{$user_id}";
		$attempts = get_transient($key) ?: 0;

		if ($attempts >= $limit) {
			return false; // Rate limit exceeded
		}

		set_transient($key, $attempts + 1, $window);
		return true; // Rate limit not exceeded
	}

	/**
	 * Rate limiting check ve hata response'u
	 *
	 * @param string $action Action name
	 * @param int    $limit Limit count
	 * @param int    $window Time window
	 * @param string $error_message Error message
	 * @return bool Rate limit exceeded
	 */
	public static function check_rate_limit_or_die(string $action, int $limit = 10, int $window = 300, string $error_message = ''): bool
	{
		if (! self::check_rate_limit($action, $limit, $window)) {
			$default_message = __('Too many requests. Please wait.', 'mhm-rentiva');
			wp_send_json_error(array('message' => $error_message ?: $default_message));
			return false;
		}

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address
	 */
	public static function get_client_ip(): string
	{
		$ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

		foreach ($ip_keys as $key) {
			if (array_key_exists($key, $_SERVER) === true) {
				$ip = self::sanitize_text_field_safe(wp_unslash($_SERVER[$key]));
				if (strpos($ip, ',') !== false) {
					$ip = explode(',', $ip)[0];
				}
				$ip = trim($ip);

				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return $ip;
				}
			}
		}

		return self::sanitize_text_field_safe(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
	}

	/**
	 * Input validation helpers
	 */
	public static function validate_vehicle_id($id): int
	{
		$id = intval($id);
		if ($id <= 0) {
			throw new \InvalidArgumentException(esc_html__('Invalid vehicle ID.', 'mhm-rentiva'));
		}
		return $id;
	}

	public static function validate_date($date): string
	{
		$date = self::sanitize_text_field_safe($date);
		if (empty($date)) {
			throw new \InvalidArgumentException(esc_html__('Invalid date format.', 'mhm-rentiva'));
		}

		// Try ISO format first (preferred)
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return $date;
		}

		// Try current WordPress date format fallback
		static $wp_format = null;
		if (null === $wp_format) {
			$wp_format = get_option('date_format', 'd/m/Y');
		}

		$date_obj = \DateTime::createFromFormat($wp_format, $date);
		if ($date_obj) {
			return $date_obj->format('Y-m-d');
		}

		// Common formats fallback
		$common_formats = array('d/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d');
		foreach ($common_formats as $fmt) {
			$obj = \DateTime::createFromFormat($fmt, $date);
			if ($obj && $obj->format($fmt) === $date) {
				return $obj->format('Y-m-d');
			}
		}

		// Final fallback to strtotime (but normalize separators first)
		// PHP prefers m/d/y with / but d-m-y with -
		$norm_date = str_replace('/', '-', $date);
		$time = strtotime($norm_date);

		if (! $time) {
			throw new \InvalidArgumentException(esc_html__('Invalid date format.', 'mhm-rentiva'));
		}

		return date('Y-m-d', $time);
	}

	public static function validate_email($email): string
	{
		if ($email === null || $email === '') {
			throw new \InvalidArgumentException(esc_html__('Invalid email address.', 'mhm-rentiva'));
		}
		$email = sanitize_email((string) ($email ?: ''));
		if (empty($email) || ! is_email($email)) {
			throw new \InvalidArgumentException(esc_html__('Invalid email address.', 'mhm-rentiva'));
		}
		return $email;
	}

	public static function validate_phone($phone): string
	{
		$phone = self::sanitize_text_field_safe($phone);

		// Empty phone is accepted (optional field)
		if (empty($phone)) {
			return '';
		}

		// Simple phone number validation
		if (! preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
			throw new \InvalidArgumentException(esc_html__('Invalid phone number.', 'mhm-rentiva'));
		}
		return $phone;
	}

	public static function validate_numeric_array($array, string $field_name = 'array'): array
	{
		// Convert string to array if needed (jQuery sends single-value arrays as strings)
		if (is_string($array) || is_numeric($array)) {
			$array = array($array);
		}

		if (! is_array($array)) {
			throw new \InvalidArgumentException(esc_html__('Invalid array format.', 'mhm-rentiva'));
		}

		$result = array_map('intval', $array);
		$result = array_filter(
			$result,
			function ($value) {
				return $value > 0;
			}
		);

		return array_values($result);
	}

	/**
	 * Return safe error message
	 *
	 * @param string $message Error message
	 * @param bool   $debug_mode In debug mode
	 * @return string Safe error message
	 */
	public static function get_safe_error_message(string $message, bool $debug_mode = false): string
	{
		if ($debug_mode && current_user_can('manage_options')) {
			return esc_html($message);
		}

		// General error message in production
		return __('An error occurred during the operation.', 'mhm-rentiva');
	}

	/**
	 * Safe meta query for SQL injection protection
	 *
	 * @param string $meta_key Meta key
	 * @param mixed  $meta_value Meta value
	 * @param string $compare Comparison operator
	 * @return array Safe meta query array
	 */
	public static function safe_meta_query(string $meta_key, $meta_value, string $compare = '='): array
	{
		return array(
			'key'     => sanitize_key($meta_key),
			'value'   => $meta_value,
			'compare' => in_array($compare, array('=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS')) ? $compare : '=',
		);
	}

	/**
	 * Safe output for XSS protection
	 *
	 * @param mixed  $data Output data
	 * @param string $context Output context (html, attr, url, js, json)
	 * @return string Safe output
	 */
	public static function safe_output($data, string $context = 'html'): string
	{
		// Context validation
		$allowed_contexts = array('html', 'attr', 'url', 'js', 'json');
		if (! in_array($context, $allowed_contexts, true)) {
			// If context is invalid, default to html for safety, 
			// but we could also throw an exception in dev mode
			$context = 'html';
		}

		if (is_array($data) || is_object($data)) {
			$data = wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
			$context = 'json'; // Force JSON context for arrays/objects
		}

		switch ($context) {
			case 'html':
				return esc_html((string) $data);
			case 'attr':
				return esc_attr((string) $data);
			case 'url':
				return esc_url((string) $data);
			case 'js':
				return esc_js((string) $data);
			case 'json':
				// JSON generated via wp_json_encode is already safe for script tags
				return (string) $data;
			default:
				return esc_html((string) $data);
		}
	}
}
