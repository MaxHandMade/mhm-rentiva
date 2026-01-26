<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security Settings Group
 *
 * Comprehensive security settings including rate limiting, IP control, and security rules.
 * Refactored for modularity and high performance.
 *
 * @since 4.0.0
 */
final class SecuritySettings {

	public const SECTION_IP_CONTROL     = 'mhm_rentiva_ip_control_section';
	public const SECTION_SECURITY_RULES = 'mhm_rentiva_security_rules_section';
	public const SECTION_AUTHENTICATION = 'mhm_rentiva_authentication_section';

	/**
	 * Get default settings for security.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			// IP Control
			'mhm_rentiva_ip_whitelist_enabled'           => '0',
			'mhm_rentiva_ip_whitelist'                   => '',
			'mhm_rentiva_ip_blacklist_enabled'           => '1',
			'mhm_rentiva_ip_blacklist'                   => '',
			'mhm_rentiva_country_restriction_enabled'    => '0',
			'mhm_rentiva_allowed_countries'              => '',

			// Security Rules
			'mhm_rentiva_brute_force_protection'         => '1',
			'mhm_rentiva_max_login_attempts'             => 5,
			'mhm_rentiva_login_lockout_duration'         => 30,
			'mhm_rentiva_sql_injection_protection'       => '1',
			'mhm_rentiva_xss_protection'                 => '1',
			'mhm_rentiva_csrf_protection'                => '1',

			// Rate Limiting
			'mhm_rentiva_rate_limit_enabled'             => '1',
			'mhm_rentiva_rate_limit_block_duration'      => 15,
			'mhm_rentiva_rate_limit_requests_per_minute' => 60,
			'mhm_rentiva_rate_limit_booking_per_minute'  => 5,
			'mhm_rentiva_rate_limit_payment_per_minute'  => 3,
		);
	}

	/**
	 * Render the security settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_IP_CONTROL );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_SECURITY_RULES );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_AUTHENTICATION );
		}
	}

	/**
	 * Register settings with WordPress.
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		// 1. IP Control Section
		add_settings_section(
			self::SECTION_IP_CONTROL,
			__( 'IP Control & Firewall', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Manage IP whitelists, blacklists and country-based restrictions.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_ip_whitelist_enabled', __( 'Enable IP Whitelist', 'mhm-rentiva' ), __( 'Restrictions are bypassed for these trusted IPs.', 'mhm-rentiva' ), self::SECTION_IP_CONTROL );
		SettingsHelper::textarea_field( $page_slug, 'mhm_rentiva_ip_whitelist', __( 'Whitelisted IPs', 'mhm-rentiva' ), 5, __( 'One IP per line.', 'mhm-rentiva' ), self::SECTION_IP_CONTROL, 'e.g. 192.168.1.1' );
		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_ip_blacklist_enabled', __( 'Enable IP Blacklist', 'mhm-rentiva' ), __( 'Traffic from these IPs is completely blocked.', 'mhm-rentiva' ), self::SECTION_IP_CONTROL );
		SettingsHelper::textarea_field( $page_slug, 'mhm_rentiva_ip_blacklist', __( 'Blacklisted IPs', 'mhm-rentiva' ), 5, __( 'One IP per line.', 'mhm-rentiva' ), self::SECTION_IP_CONTROL, 'e.g. 123.123.123.123' );
		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_country_restriction_enabled', __( 'Enable Country Restriction', 'mhm-rentiva' ), __( 'Block access from countries not in the allowed list.', 'mhm-rentiva' ), self::SECTION_IP_CONTROL );
		SettingsHelper::text_field( $page_slug, 'mhm_rentiva_allowed_countries', __( 'Allowed Countries (Codes)', 'mhm-rentiva' ), self::SECTION_IP_CONTROL, __( 'Comma separated 2-letter codes. Ex: TR, US', 'mhm-rentiva' ), 'e.g. TR, US, GB' );

		// 2. Security Rules Section
		add_settings_section(
			self::SECTION_SECURITY_RULES,
			__( 'Advanced Protection Rules', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure specialized security headers and injection protection.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_brute_force_protection', __( 'Brute Force Protection', 'mhm-rentiva' ), __( 'Monitors and blocks repeated failed login attempts.', 'mhm-rentiva' ), self::SECTION_SECURITY_RULES );
		SettingsHelper::number_field( $page_slug, 'mhm_rentiva_max_login_attempts', __( 'Max Login Attempts', 'mhm-rentiva' ), 1, 50, __( 'Incorrect attempts allowed before lockout.', 'mhm-rentiva' ), self::SECTION_SECURITY_RULES );
		SettingsHelper::number_field( $page_slug, 'mhm_rentiva_login_lockout_duration', __( 'Lockout Duration (min)', 'mhm-rentiva' ), 1, 1440, __( 'How long a blocked user must wait.', 'mhm-rentiva' ), self::SECTION_SECURITY_RULES );
		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_sql_injection_protection', __( 'SQL Injection Protection', 'mhm-rentiva' ), __( 'Filters malicious SQL patterns in requests.', 'mhm-rentiva' ), self::SECTION_SECURITY_RULES );
		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_xss_protection', __( 'XSS Protection', 'mhm-rentiva' ), __( 'Sanitizes inputs against cross-site scripting.', 'mhm-rentiva' ), self::SECTION_SECURITY_RULES );
		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_csrf_protection', __( 'CSRF Protection', 'mhm-rentiva' ), __( 'Validates request origins to prevent forgery.', 'mhm-rentiva' ), self::SECTION_SECURITY_RULES );

		// 3. Authentication & Rate Limiting Section
		add_settings_section(
			self::SECTION_AUTHENTICATION,
			__( 'Traffic limits & Request Control', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure request thresholds and automated activity blocking.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_rate_limit_enabled', __( 'Enable Rate Limiting', 'mhm-rentiva' ), __( 'Limits the frequency of requests to prevent abuse.', 'mhm-rentiva' ), self::SECTION_AUTHENTICATION );
		SettingsHelper::number_field( $page_slug, 'mhm_rentiva_rate_limit_block_duration', __( 'Rate Limit Block Duration', 'mhm-rentiva' ), 1, 1440, __( 'Block duration for abusive IPs (min).', 'mhm-rentiva' ), self::SECTION_AUTHENTICATION );
		SettingsHelper::number_field( $page_slug, 'mhm_rentiva_rate_limit_requests_per_minute', __( 'Global Request Limit', 'mhm-rentiva' ), 1, 1000, __( 'Max requests per minute for any visitor.', 'mhm-rentiva' ), self::SECTION_AUTHENTICATION );
		SettingsHelper::number_field( $page_slug, 'mhm_rentiva_rate_limit_booking_per_minute', __( 'Booking Request Limit', 'mhm-rentiva' ), 1, 100, __( 'Max booking attempts per minute.', 'mhm-rentiva' ), self::SECTION_AUTHENTICATION );
		SettingsHelper::number_field( $page_slug, 'mhm_rentiva_rate_limit_payment_per_minute', __( 'Payment Request Limit', 'mhm-rentiva' ), 1, 100, __( 'Max payment attempts per minute.', 'mhm-rentiva' ), self::SECTION_AUTHENTICATION );
	}

	/**
	 * IP Control Getters
	 */
	public static function is_ip_whitelist_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_ip_whitelist_enabled' ) === '1';
	}

	public static function get_ip_whitelist(): string {
		return (string) SettingsCore::get( 'mhm_rentiva_ip_whitelist', '' );
	}

	public static function is_ip_blacklist_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_ip_blacklist_enabled' ) === '1';
	}

	public static function get_ip_blacklist(): string {
		return (string) SettingsCore::get( 'mhm_rentiva_ip_blacklist', '' );
	}

	public static function is_country_restriction_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_country_restriction_enabled' ) === '1';
	}

	public static function get_allowed_countries(): string {
		return (string) SettingsCore::get( 'mhm_rentiva_allowed_countries', '' );
	}

	/**
	 * Security Rules Getters
	 */
	public static function is_brute_force_protection_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_brute_force_protection' ) === '1';
	}

	public static function get_max_login_attempts(): int {
		return (int) SettingsCore::get( 'mhm_rentiva_max_login_attempts', 5 );
	}

	public static function get_login_lockout_duration(): int {
		return (int) SettingsCore::get( 'mhm_rentiva_login_lockout_duration', 30 );
	}

	public static function is_sql_injection_protection_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_sql_injection_protection' ) === '1';
	}

	public static function is_xss_protection_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_xss_protection' ) === '1';
	}

	public static function is_csrf_protection_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_csrf_protection' ) === '1';
	}
}
