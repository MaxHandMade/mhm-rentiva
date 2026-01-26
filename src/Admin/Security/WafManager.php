<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Security;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Web Application Firewall (WAF) Manager
 *
 * Provides basic protection against SQL Injection (SQLi) and Cross-Site Scripting (XSS).
 *
 * @since 4.0.0
 */
final class WafManager
{


	/**
	 * Initialize WAF
	 */
	public static function init(): void
	{
		// Run early to intercept requests
		add_action('plugins_loaded', array(self::class, 'inspect_request'), 1);
	}

	/**
	 * Inspect incoming request data
	 */
	public static function inspect_request(): void
	{
		// Skip for admin users who can manage options, to prevent locking out admins
		// But still check if it's a front-end form submission from an unknown source if needed.
		// For safety, we skip if user is admin, assuming admins are trusted or handled by other mechanisms (nonces).
		// However, a compromised admin account could benefit from WAF.
		// For this implementation, we'll exclude admin pages but check POST requests on front-end.
		// Actually, let's just check non-admin requests or be very specific patterns.

		$is_admin_user = current_user_can('manage_options');
		if ($is_admin_user && is_admin()) {
			return;
		}

		$check_sqli = '1' === SettingsCore::get('mhm_rentiva_sql_injection_protection', '0');
		$check_xss  = '1' === SettingsCore::get('mhm_rentiva_xss_protection', '0');

		if (! $check_sqli && ! $check_xss) {
			return;
		}

		// Data sources to inspect
		$sources = array(
			'GET'     => $_GET,
			'POST'    => $_POST,
			'COOKIE'  => $_COOKIE,
			'REQUEST' => $_REQUEST,
		);

		foreach ($sources as $type => $data) {
			if (empty($data)) {
				continue;
			}
			// Recursive scan
			self::scan_data($data, $type, $check_sqli, $check_xss);
		}
	}

	/**
	 * Recursive scan of data array
	 */
	private static function scan_data(array $data, string $type, bool $check_sqli, bool $check_xss): void
	{
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				self::scan_data($value, $type, $check_sqli, $check_xss);
			} elseif (is_string($value)) {
				if ($check_sqli && self::detect_sqli($value)) {
					self::block_request('SQL Injection detected', $type, $key);
				}
				if ($check_xss && self::detect_xss($value)) {
					self::block_request('XSS attempt detected', $type, $key);
				}
			}
		}
	}

	/**
	 * Detect SQL Injection patterns
	 */
	private static function detect_sqli(string $value): bool
	{
		$value    = strtoupper(urldecode($value));
		$patterns = array(
			'UNION SELECT',
			'UNION ALL SELECT',
			'INFORMATION_SCHEMA',
			'CONCAT(',
			'CHAR(',
			'; DROP TABLE',
			'; UPDATE',
			'; DELETE FROM',
			' OR 1=1',
		);

		foreach ($patterns as $pattern) {
			if (strpos($value, $pattern) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect XSS patterns
	 */
	private static function detect_xss(string $value): bool
	{
		$value    = strtolower(urldecode($value));
		$patterns = array(
			'<script>',
			'javascript:',
			'onerror=',
			'onload=',
			'onmouseover=',
			'<iframe>',
			'<object>',
			'<embed>',
		);

		foreach ($patterns as $pattern) {
			if (strpos($value, $pattern) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Block the request and die
	 */
	private static function block_request(string $reason, string $type, string $key): void
	{
		// Log the event if logger exists.
		if (class_exists('\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger')) {
			/* translators: 1: reason, 2: context type, 3: parameter key */
			/* translators: 1: reason, 2: context type, 3: parameter key */
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning(
				sprintf(
					/* translators: 1: reason, 2: context type, 3: parameter key */
					__('WAF Blocked Request: %1$s in %2$s[%3$s]', 'mhm-rentiva'),
					$reason,
					$type,
					$key
				),
				array('ip' => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'))),
				'security'
			);
		} else {
			error_log(sprintf('MHM WAF Blocked: %s in %s[%s] IP: %s', $reason, $type, $key, sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'))));
		}

		wp_die(
			esc_html__('Security Violation Detected. Request Blocked.', 'mhm-rentiva'),
			esc_html__('Forbidden', 'mhm-rentiva'),
			array('response' => 403)
		);
	}
}
