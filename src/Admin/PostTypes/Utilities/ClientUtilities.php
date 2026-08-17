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
	 * Get client IP address securely.
	 *
	 * Delegates to the single house resolver. This method used to walk seven
	 * client-supplied proxy headers (CF-Connecting-IP, Client-IP,
	 * X-Forwarded-For, ...) ahead of REMOTE_ADDR and return the first one that
	 * parsed as an IP -- the same defect closed in SecurityHelper and
	 * RateLimiter, in a third copy.
	 *
	 * What it costs here is not a rate limit -- nothing throttles on this
	 * value. Its callers are AdvancedLogger's `ip_address` field and its
	 * security-event context, i.e. this plugin's own audit trail: any caller
	 * could write any IP it chose into the record a site owner reads after an
	 * incident, and pin its own requests on someone else.
	 *
	 * SecurityHelper::get_client_ip() trusts REMOTE_ADDR by default and lets a
	 * site behind a real reverse proxy opt specific headers back in through
	 * the `mhmrentiva_trusted_proxy_ip_headers` filter. Delegating keeps that
	 * decision in one place instead of three.
	 */
	public static function get_client_ip(): string
	{
		return \MHMRentiva\Admin\Core\SecurityHelper::get_client_ip();
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
