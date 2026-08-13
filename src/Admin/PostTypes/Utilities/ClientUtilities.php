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
	 * This is consumed by AdvancedLogger for the 'security' log category
	 * (and general logging), i.e. it is a forensic/audit field, not a
	 * decision -- nothing in this plugin bans, throttles, or blocks on the
	 * value it returns. It used to walk X-Forwarded-For/Client-IP/etc.
	 * ahead of REMOTE_ADDR regardless, which meant every visitor could set
	 * the IP this plugin's own security log recorded for them -- worthless,
	 * or actively misleading (framing another address), as a forensic
	 * record. REMOTE_ADDR (the real TCP peer) is now the only value trusted
	 * by default, matching the house pattern in
	 * SecurityHelper::get_client_ip(); a site that knows it sits behind a
	 * proxy that overwrites these headers itself can opt specific ones back
	 * in via the same `mhmrentiva_trusted_proxy_ip_headers` filter, so the
	 * rate limiter, the security helper and this log field all agree on the
	 * real client once configured.
	 *
	 * Deliberate behavior change: previously-logged `_mhmrentiva_log_ip_address`
	 * values could be a spoofed header; new log entries record REMOTE_ADDR
	 * unless the filter above is wired up.
	 */
	public static function get_client_ip(): string
	{
		$remote_addr = isset($_SERVER['REMOTE_ADDR'])
			? sanitize_text_field(wp_unslash( (string) $_SERVER['REMOTE_ADDR']))
			: '';

		/** @param string[] $headers $_SERVER keys to trust ahead of REMOTE_ADDR. Empty by default. */
		$trusted_headers = (array) apply_filters('mhmrentiva_trusted_proxy_ip_headers', array());

		foreach ($trusted_headers as $header) {
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

		return '' !== $remote_addr ? $remote_addr : 'unknown';
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
		// Cleaned on the line that reads the superglobal, so the returned array
		// never carries a raw value and the check is verifiable where it happens.
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) )
			: 'GET';

		return array(
			'ip_address'     => self::get_client_ip(),
			'user_agent'     => self::get_user_agent(),
			'referer'        => self::get_referer(),
			'timestamp'      => current_time('mysql'),
			'request_uri'    => $request_uri,
			'request_method' => $request_method,
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
		$total = count($parts);
		if ($total !== 4) {
			return $ip; // IPv6 or invalid format
		}

		for ($i = $total - $mask_last_octets; $i < $total; $i++) {
			$parts[ $i ] = 'xxx';
		}

		return implode('.', $parts);
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
