<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ CLIENT UTILITIES - Central Client Information Class
 *
 * Centralizes client information for all PostTypes classes
 */
final class ClientUtilities {


	/**
	 * Get client IP address securely
	 *
	 * With proxy and load balancer support
	 */
	public static function get_client_ip(): string
	{
		// Check proxy headers
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',           // Proxy
			'HTTP_X_FORWARDED_FOR',     // Load balancer
			'HTTP_X_FORWARDED',         // Proxy
			'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
			'HTTP_FORWARDED_FOR',       // Proxy
			'HTTP_FORWARDED',           // Proxy
			'REMOTE_ADDR',               // Direct connection
		);

		foreach ($ip_headers as $header) {
			if (! empty($_SERVER[ $header ])) {
				$ip = sanitize_text_field(wp_unslash($_SERVER[ $header ]));

				// X-Forwarded-For can contain multiple IPs (comma separated)
				if (strpos($ip, ',') !== false) {
					$ips = explode(',', $ip);
					$ip  = trim($ips[0]);
				}

				// Validate IP address
				if (self::is_valid_ip($ip)) {
					return $ip;
				}
			}
		}

		return 'unknown';
	}

	/**
	 * Get user agent securely
	 */
	public static function get_user_agent(): string
	{
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) );
		}

		return 'unknown';
	}

	/**
	 * Get referer securely
	 */
	public static function get_referer(): string
	{
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			return esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) );
		}

		return '';
	}

	/**
	 * Get client info collectively
	 */
	public static function get_client_info(): array
	{
		$request_uri    = '';
		$request_method = 'GET';

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is unslashed then escaped with esc_url_raw() on output.
			$request_uri = wp_unslash( (string) $_SERVER['REQUEST_URI'] );
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is unslashed then sanitized with sanitize_text_field() on output.
			$request_method = wp_unslash( (string) $_SERVER['REQUEST_METHOD'] );
		}

		return array(
			'ip_address'     => self::get_client_ip(),
			'user_agent'     => self::get_user_agent(),
			'referer'        => self::get_referer(),
			'timestamp'      => current_time('mysql'),
			'request_uri'    => esc_url_raw($request_uri),
			'request_method' => sanitize_text_field($request_method),
		);
	}

	/**
	 * Check if IP address is valid
	 */
	private static function is_valid_ip(string $ip): bool
	{
		// IPv4 and IPv6 support
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			return true;
		}

		// Private IPs are acceptable (for local development)
		if (filter_var($ip, FILTER_VALIDATE_IP)) {
			return true;
		}

		return false;
	}

	/**
	 * Mask IP address for privacy
	 *
	 * @param string $ip IP address
	 * @param int    $mask_last_octets How many octets to mask from the end (default: 1)
	 */
	public static function mask_ip(string $ip, int $mask_last_octets = 1): string
	{
		if ($ip === 'unknown') {
			return $ip;
		}

		$parts = explode('.', $ip);
		if (count($parts) !== 4) {
			return $ip; // IPv6 or invalid format
		}

		for ($i = count($parts) - $mask_last_octets; $i < count($parts); $i++) {
			$parts[ $i ] = 'xxx';
		}

		return implode('.', $parts);
	}

	/**
	 * Get client location info (IP based)
	 *
	 * @return array ['country' => string, 'region' => string, 'city' => string]
	 */
	public static function get_client_location(): array
	{
		$ip = self::get_client_ip();

		if ($ip === 'unknown' || self::is_private_ip($ip)) {
			return array(
				'country' => 'unknown',
				'region'  => 'unknown',
				'city'    => 'unknown',
			);
		}

		// Use Geobytes API (free)
		$response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city");

		if (is_wp_error($response)) {
			return array(
				'country' => 'unknown',
				'region'  => 'unknown',
				'city'    => 'unknown',
			);
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		if ($data['status'] === 'success') {
			return array(
				'country' => sanitize_text_field($data['country'] ?? 'unknown'),
				'region'  => sanitize_text_field($data['regionName'] ?? 'unknown'),
				'city'    => sanitize_text_field($data['city'] ?? 'unknown'),
			);
		}

		return array(
			'country' => 'unknown',
			'region'  => 'unknown',
			'city'    => 'unknown',
		);
	}

	/**
	 * Check if IP address is private
	 */
	private static function is_private_ip(string $ip): bool
	{
		return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
	}

	/**
	 * Detect bot
	 */
	public static function is_bot(): bool
	{
		$user_agent = strtolower(self::get_user_agent());

		$bot_patterns = array(
			'bot',
			'crawler',
			'spider',
			'scraper',
			'facebook',
			'twitter',
			'googlebot',
			'bingbot',
			'slurp',
			'duckduckbot',
			'baiduspider',
			'yandexbot',
			'sogou',
			'exabot',
			'facebot',
			'ia_archiver',
		);

		foreach ($bot_patterns as $pattern) {
			if (strpos($user_agent, $pattern) !== false) {
				return true;
			}
		}

		return false;
	}
}
