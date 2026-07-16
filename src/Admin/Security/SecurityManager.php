<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Security;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global Security Manager
 *
 * Handles IP whitelist, blacklist, and country restrictions across the entire site
 *
 * @since 4.0.0
 */
final class SecurityManager {


	/**
	 * Initialize security management
	 */
	public static function init(): void {
		// Hook into early request processing
		add_action( 'template_redirect', array( self::class, 'check_ip_access' ), 1 );
		add_action( 'wp_loaded', array( self::class, 'check_ip_access' ), 1 );
	}

	/**
	 * Check if current IP should be allowed access
	 */
	public static function check_ip_access(): void {
		// Skip for admin pages to prevent lockout
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$client_ip = self::get_client_ip();

		// Check blacklist first
		if ( self::is_ip_blacklisted( $client_ip ) ) {
			self::deny_access( __( 'Access denied: Your IP address is blocked.', 'mhm-rentiva' ) );
		}

		// Check whitelist if enabled
		if ( self::is_whitelist_enabled() ) {
			if ( ! self::is_ip_whitelisted( $client_ip ) ) {
				self::deny_access( __( 'Access denied: Your IP address is not authorized.', 'mhm-rentiva' ) );
			}
		}

		// Check country restriction
		if ( self::is_country_restriction_enabled() ) {
			if ( ! self::is_country_allowed( $client_ip ) ) {
				self::deny_access( __( 'Access denied: Your country is not authorized.', 'mhm-rentiva' ) );
			}
		}
	}

	/**
	 * Get client IP address
	 */
	private static function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( true === array_key_exists( $key, $_SERVER ) ) {
				$ip = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}
				$ip = trim( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		$remote_addr = sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		return '' !== $remote_addr ? $remote_addr : '0.0.0.0';
	}

	/**
	 * Check if IP blacklist is enabled
	 */
	private static function is_blacklist_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_ip_blacklist_enabled', '0' ) === '1';
	}

	/**
	 * Check if IP is blacklisted
	 */
	private static function is_ip_blacklisted( string $ip ): bool {
		if ( ! self::is_blacklist_enabled() ) {
			return false;
		}

		$blacklist = SettingsCore::get( 'mhm_rentiva_ip_blacklist', '' );
		if ( empty( $blacklist ) ) {
			return false;
		}

		$ips = self::parse_ip_list( $blacklist );

		foreach ( $ips as $blocked_ip ) {
			if ( self::match_ip( $ip, $blocked_ip ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if whitelist is enabled
	 */
	private static function is_whitelist_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_ip_whitelist_enabled', '0' ) === '1';
	}

	/**
	 * Check if IP is whitelisted
	 */
	private static function is_ip_whitelisted( string $ip ): bool {
		$whitelist = SettingsCore::get( 'mhm_rentiva_ip_whitelist', '' );
		if ( empty( $whitelist ) ) {
			return true; // Empty whitelist = allow all
		}

		$ips = self::parse_ip_list( $whitelist );

		foreach ( $ips as $allowed_ip ) {
			if ( self::match_ip( $ip, $allowed_ip ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if country restriction is enabled
	 */
	private static function is_country_restriction_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_country_restriction_enabled', '0' ) === '1';
	}

	/**
	 * Check if country is allowed.
	 *
	 * Fails OPEN when the country cannot be determined -- the long-standing
	 * behaviour, and the safe one: a restriction that guesses would lock
	 * legitimate visitors out of the whole site.
	 *
	 * Since Lite performs no third-party geolocation lookup (see
	 * get_country_from_ip()), the country is only known on Cloudflare-fronted
	 * sites. Everywhere else this check now always allows access, so the
	 * country restriction is effectively inert there rather than partially
	 * enforced.
	 */
	private static function is_country_allowed( string $ip ): bool {
		$allowed_countries = SettingsCore::get( 'mhm_rentiva_allowed_countries', '' );
		if ( empty( $allowed_countries ) ) {
			return true;
		}

		$country_code = self::get_country_from_ip( $ip );
		if ( empty( $country_code ) ) {
			return true; // Country undeterminable -- fail open.
		}

		$allowed = array_map( 'trim', explode( ',', strtoupper( $allowed_countries ) ) );
		return in_array( strtoupper( $country_code ), $allowed, true );
	}

	/**
	 * Get country code from IP, using only signals already present on the request.
	 *
	 * Lite resolves the country from the `CF-IPCountry` request header that a
	 * Cloudflare-fronted site already supplies. It performs NO geolocation
	 * lookup: the previous ip-api.com fallback sent the visitor's IP address to
	 * an unaffiliated third party, over plain HTTP, with no consent and no
	 * disclosure -- which is both a privacy defect and something WordPress.org
	 * review reliably rejects.
	 *
	 * When the header is absent the country is simply unknown (''), and
	 * is_country_allowed() already fails OPEN on an empty result, so access is
	 * granted rather than wrongly denied. The practical consequence is that the
	 * country restriction setting only enforces on sites behind Cloudflare; see
	 * the note on is_country_allowed().
	 *
	 * The IP argument is retained for signature stability and for any future
	 * local (non-networked) resolver.
	 *
	 * @param string $ip Client IP (currently unused -- no lookup is performed).
	 * @return string Uppercase ISO country code, or '' when undeterminable.
	 */
	private static function get_country_from_ip( string $ip ): string {
		unset( $ip );

		if ( isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$country = strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
			// Cloudflare sends 'XX' when it cannot determine the country (and
			// 'T1' for Tor); neither is a real country, so treat both as unknown
			// and let the caller fail open.
			if ( 'XX' === $country || 'T1' === $country || '' === $country ) {
				return '';
			}
			return $country;
		}

		return '';
	}

	/**
	 * Parse IP list from textarea input
	 */
	private static function parse_ip_list( string $list ): array {
		$lines = explode( "\n", $list );
		$ips   = array();

		foreach ( $lines as $line ) {
			$ip = trim( $line );
			if ( ! empty( $ip ) ) {
				$ips[] = $ip;
			}
		}

		return $ips;
	}

	/**
	 * Match IP against pattern (supports CIDR notation)
	 */
	private static function match_ip( string $ip, string $pattern ): bool {
		// Direct match
		if ( $ip === $pattern ) {
			return true;
		}

		// CIDR notation match
		if ( strpos( $pattern, '/' ) !== false ) {
			return self::match_cidr( $ip, $pattern );
		}

		return false;
	}

	/**
	 * Match IP against CIDR notation
	 */
	private static function match_cidr( string $ip, string $cidr ): bool {
		list($subnet, $mask) = explode( '/', $cidr );

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );

		if ( $ip_long === false || $subnet_long === false ) {
			return false;
		}

		$mask_long = -1 << ( 32 - (int) $mask );
		return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
	}

	/**
	 * Deny access and send appropriate response
	 */
	private static function deny_access( string $message ): void {
		if ( wp_doing_ajax() ) {
			wp_send_json_error(
				array(
					'message' => $message,
				)
			);
		} else {
			wp_die(
				esc_html( $message ),
				esc_html__( 'Access Denied', 'mhm-rentiva' ),
				array( 'response' => 403 )
			);
		}
	}
}
