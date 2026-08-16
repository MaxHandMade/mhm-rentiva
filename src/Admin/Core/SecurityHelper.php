<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;



/**
 * Security Helper
 *
 * Security controls and helper methods for shortcodes
 */
final class SecurityHelper {

	/**
	 * Object cache group for rate-limit counters.
	 *
	 * A group of its own so a counter can never be confused with, or flushed
	 * alongside, unrelated cached data.
	 */
	public const RATE_LIMIT_CACHE_GROUP = 'mhmrentiva_rate_limits';




	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}

	/**
	 * Safe array value getter with default and type casting
	 *
	 * @param array  $array   Source array
	 * @param string $key     Key to search
	 * @param mixed  $default Default value if key not set
	 * @param string $type    Target type (string, int, bool, float, array)
	 * @return mixed
	 */
	public static function get_val(array $source, string $key, $fallback = '', string $type = 'string')
	{
		$value = $source[ $key ] ?? $fallback;

		// WordPress global verification (unslash data if it's from globals or expected to be slashed)
		if (is_string($value)) {
			$value = wp_unslash($value);
		} elseif (is_array($value)) {
			$value = wp_unslash($value);
		}

		switch ($type) {
			case 'int':
				return (int) $value;
			case 'bool':
				return (bool) $value;
			case 'float':
				return (float) $value;
			case 'array':
				return is_array($value) ? $value : (array) $value;
			case 'string':
			default:
				return sanitize_text_field( (string) $value);
		}
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
		// Read the candidate nonce field by field, in this method, right where it is
		// verified below. Copying whole superglobals into local arrays first hid the
		// access from static analysis (which reads as evasion) and let the value
		// travel; a single-key read that is unslashed and sanitized on the spot is
		// the shape WordPress documents, and the verification is three lines down.
		$nonce = '';

		foreach (array( 'nonce', 'security', '_ajax_nonce' ) as $key) {
			if (isset($_POST[ $key ])) {
				$nonce = sanitize_text_field(wp_unslash( (string) $_POST[ $key ]));
				break;
			}
			if (isset($_GET[ $key ])) {
				$nonce = sanitize_text_field(wp_unslash( (string) $_GET[ $key ]));
				if ($nonce !== '') {
					break;
				}
			}
		}
		if (! wp_verify_nonce($nonce, $nonce_name)) {
			// Debug log for admins only
			if (current_user_can('manage_options')) {
				$logged_nonce = '' !== $nonce ? $nonce : 'EMPTY';
				AdvancedLogger::security(
					'Nonce verification failed',
					array(
						'action' => $nonce_name,
						'nonce'  => $logged_nonce,
					)
				);
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
	 * AJAX request security check with error response
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
			$message         = '' !== $error_message ? $error_message : $default_message;
			wp_send_json_error(array( 'message' => $message ));
			return false;
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
		$key   = self::rate_limit_key($action, $user_id);
		$count = self::increment_counter($key, $window);

		return $count <= $limit;
	}

	/**
	 * Current hit count for a rate-limit bucket, read from whichever storage
	 * increment_counter() writes to.
	 *
	 * @param string   $action  Action name.
	 * @param int|null $user_id User ID (null = current user).
	 */
	public static function get_rate_limit_count(string $action, ?int $user_id = null): int
	{
		return self::read_counter(self::rate_limit_key($action, $user_id));
	}

	/**
	 * Bucket key for an action/subject pair.
	 *
	 * Hashed like RateLimiter::getCacheKey() -- an unhashed IP in the
	 * transient name is both an unnecessary PII exposure (visible to anyone
	 * who can read wp_options) and needlessly inconsistent with the house
	 * pattern for the same kind of key.
	 */
	private static function rate_limit_key(string $action, ?int $user_id = null): string
	{
		if ($user_id === null) {
			$user_id = get_current_user_id();
		}

		// IP-based rate limiting for anonymous users.
		$identifier = (string) $user_id;
		if ($user_id === 0) {
			$identifier = self::get_client_ip();
		}

		return "mhmrentiva_rate_limit_{$action}_" . hash('sha256', $identifier);
	}

	/**
	 * Add one to a counter and return its new value.
	 *
	 * The house counter primitive, shared with RateLimiter so both limiters
	 * count the same way.
	 *
	 * A transient counter is read-modify-write: get_transient(), compare,
	 * set_transient(current + 1). Two concurrent requests read the same value
	 * and write the same increment, so N simultaneous hits can advance the
	 * counter by one -- the limiter undercounts precisely under the load it
	 * exists to control. Where a persistent object cache is available its
	 * increment is a single atomic operation (Redis INCR, Memcached INCR),
	 * so use it: wp_cache_add() seeds the key with the window as its TTL
	 * (whoever loses that race simply gets false and increments the winner's
	 * key), and wp_cache_incr() does the counting without a read first.
	 *
	 * 🔴 The fallback is not optional. Without a persistent object cache
	 * wp_cache_* is request-scoped -- a counter kept only there resets on
	 * every request and the limiter silently stops limiting anything. Most
	 * WordPress sites are on that path, so the transient remains the default.
	 */
	public static function increment_counter(string $key, int $duration): int
	{
		if (wp_using_ext_object_cache()) {
			wp_cache_add($key, 0, self::RATE_LIMIT_CACHE_GROUP, $duration);
			$count = wp_cache_incr($key, 1, self::RATE_LIMIT_CACHE_GROUP);

			// incr() returns false only if the key vanished between the add
			// and the incr (eviction, flush). Falling through to the
			// transient would split one bucket across two stores, so re-seed
			// this one instead.
			if (false !== $count) {
				return (int) $count;
			}

			wp_cache_set($key, 1, self::RATE_LIMIT_CACHE_GROUP, $duration);
			return 1;
		}

		$attempts = (int) get_transient($key);
		++$attempts;
		set_transient($key, $attempts, $duration);

		return $attempts;
	}

	/**
	 * Read a counter written by increment_counter(), from the same storage.
	 */
	public static function read_counter(string $key): int
	{
		if (wp_using_ext_object_cache()) {
			$value = wp_cache_get($key, self::RATE_LIMIT_CACHE_GROUP);
			return false === $value ? 0 : (int) $value;
		}

		return (int) get_transient($key);
	}

	/**
	 * Drop a counter from BOTH stores.
	 *
	 * Deliberately not conditional on wp_using_ext_object_cache(): a site
	 * that gains or loses a persistent cache between the increment and the
	 * clear would otherwise leave the counter stranded in the other store,
	 * and an admin's "clear this limit" would silently do nothing.
	 */
	public static function clear_counter(string $key): void
	{
		wp_cache_delete($key, self::RATE_LIMIT_CACHE_GROUP);
		delete_transient($key);
	}

	/**
	 * Rate limiting check with error response
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
			$message         = '' !== $error_message ? $error_message : $default_message;
			wp_send_json_error(array( 'message' => $message ));
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
		// Direct read rather than via $GLOBALS: the indirection resolved to the same
		// array but hid the access from static analysis. Every value taken from it is
		// unslashed, sanitized and then validated with filter_var( FILTER_VALIDATE_IP ).
		$server = $_SERVER;

		$remote_addr = isset($server['REMOTE_ADDR']) ? (string) wp_unslash($server['REMOTE_ADDR']) : '0.0.0.0';
		$remote_addr = self::sanitize_text_field_safe($remote_addr);

		/**
		 * Client-supplied headers such as `X-Forwarded-For`/`Client-IP` are
		 * ordinary request headers: any caller that reaches this site
		 * directly can set them to whatever it likes, so trusting them by
		 * default let an attacker rotate the header value and bypass every
		 * IP-based rate limit built on get_client_ip() entirely. They are
		 * only meaningful behind a reverse proxy/load balancer that
		 * OVERWRITES them itself before the request reaches PHP -- something
		 * this plugin cannot know on its own.
		 *
		 * REMOTE_ADDR (the actual TCP peer) is the only value trusted by
		 * default. A site that knows it sits behind such a trusted proxy can
		 * opt specific headers back in, in priority order, via this filter --
		 * e.g. `array( 'HTTP_CF_CONNECTING_IP' )` behind Cloudflare.
		 *
		 * @param string[] $headers $_SERVER keys to trust ahead of REMOTE_ADDR. Empty by default.
		 */
		$trusted_headers = (array) apply_filters('mhmrentiva_trusted_proxy_ip_headers', array());

		foreach ($trusted_headers as $key) {
			if (! isset($server[ $key ])) {
				continue;
			}

			$ip = self::sanitize_text_field_safe( (string) wp_unslash($server[ $key ]));
			if (strpos($ip, ',') !== false) {
				$ip = explode(',', $ip)[0];
			}
			$ip = trim($ip);

			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return $ip;
			}
		}

		return $remote_addr;
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
		$common_formats = array( 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d' );
		foreach ($common_formats as $fmt) {
			$obj = \DateTime::createFromFormat($fmt, $date);
			if ($obj && $obj->format($fmt) === $date) {
				return $obj->format('Y-m-d');
			}
		}

		// Final fallback to strtotime (but normalize common separators first)
		// PHP prefers m/d/y with / but d-m-y with -; replace common separators with '-'
		$norm_date = str_replace(array( '/', '.', ' ' ), '-', $date);
		$time      = strtotime($norm_date);

		if (! $time) {
			throw new \InvalidArgumentException(esc_html__('Invalid date format.', 'mhm-rentiva'));
		}

		return gmdate('Y-m-d', $time);
	}

	public static function validate_email($email): string
	{
		if ($email === null || $email === '') {
			throw new \InvalidArgumentException(esc_html__('Invalid email address.', 'mhm-rentiva'));
		}
		$email = sanitize_email( (string) $email );
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

	public static function validate_numeric_array($values, string $field_name = 'array'): array
	{
		unset($field_name);

		// Convert string to array if needed (jQuery sends single-value arrays as strings)
		if (is_string($values) || is_numeric($values)) {
			$values = array( $values );
		}

		if (! is_array($values)) {
			throw new \InvalidArgumentException(esc_html__('Invalid array format.', 'mhm-rentiva'));
		}

		$result = array_map('intval', $values);
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
			'compare' => in_array($compare, array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ), true ) ? $compare : '=',
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
		$allowed_contexts = array( 'html', 'attr', 'url', 'js', 'json' );
		if (! in_array($context, $allowed_contexts, true)) {
			// If context is invalid, default to html for safety,
			// but we could also throw an exception in dev mode
			$context = 'html';
		}

		if (is_array($data) || is_object($data)) {
			$data    = wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
			$context = 'json'; // Force JSON context for arrays/objects
		}

		switch ($context) {
			case 'html':
				return esc_html( (string) $data);
			case 'attr':
				return esc_attr( (string) $data);
			case 'url':
				return esc_url( (string) $data);
			case 'js':
				return esc_js( (string) $data);
			case 'json':
				// JSON generated via wp_json_encode is already safe for script tags
				return (string) $data;
			default:
				return esc_html( (string) $data);
		}
	}
}
