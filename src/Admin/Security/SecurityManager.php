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
 * Handles IP whitelist and blacklist checks.
 *
 * The country restriction that used to live here was REMOVED, along with its
 * settings. It resolved the visitor's country by sending their IP address to
 * ip-api.com; once that third-party lookup was removed on privacy grounds the
 * only remaining signal was Cloudflare's CF-IPCountry request header, which
 * exists solely on Cloudflare-fronted sites. Everywhere else the check fell
 * through to its fail-open branch and silently admitted everyone -- while the
 * settings screen still displayed "Enable Country Restriction" as active.
 *
 * A security setting that does not enforce is worse than no setting: it makes a
 * promise the code does not keep, and the site owner stops looking for a real
 * control. So the setting is gone rather than conditionally displayed.
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

		// No country restriction: see the class docblock.
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
