<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Settings;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ REST SETTINGS - Dynamic REST API Settings
 */
final class RESTSettings
{

	public const OPTION_NAME = 'mhm_rentiva_rest_settings';

	/**
	 * Default settings
	 */
	public static function get_default_settings(): array
	{
		return array(
			'rate_limiting' => array(
				'enabled'        => true,
				'default_limit'  => 60,
				'default_window' => 60,
				'strict_limit'   => 10,
				'strict_window'  => 60,
				'burst_limit'    => 100,
				'burst_window'   => 300,
			),
			'tokens'        => array(
				'default_expiry_hours'   => 24,
				'max_expiry_hours'       => 168,
				'refresh_enabled'        => true,
				'auto_refresh_threshold' => 2,
			),
			'security'      => array(
				'require_https'         => true,
				'ip_whitelist_enabled'  => false,
				'ip_whitelist'          => array(),
				'ip_blacklist_enabled'  => true,
				'ip_blacklist'          => array(),
				'user_agent_validation' => true,
				'blocked_user_agents'   => array('curl', 'wget', 'python', 'bot', 'spider', 'crawler'),
			),
			'api'           => array(
				'version'              => 'v1',
				'base_namespace'       => 'mhm-rentiva/v1',
				'cors_enabled'         => true,
				'cors_origins'         => array(),
				'request_logging'      => true,
				'response_compression' => true,
			),
			'cache'         => array(
				'enabled'               => true,
				'duration_seconds'      => 300,
				'long_duration_seconds' => 1800,
				'cache_headers'         => true,
				'etag_enabled'          => true,
			),
			'development'   => array(
				'debug_mode'           => false,
				'cors_all_origins'     => false,
				'rate_limit_bypass'    => false,
				'security_bypass'      => false,
				'verbose_logging'      => false,
				'auto_enable_on_debug' => true,
			),
		);
	}

	/**
	 * Get setting with nested key support (e.g. 'rate_limiting.enabled')
	 */
	public static function get_setting(string $key, $default = null)
	{
		$settings = get_option(self::OPTION_NAME);

		if (false === $settings || ! is_array($settings)) {
			$settings = self::get_default_settings();
		}

		$keys  = explode('.', $key);
		$value = $settings;

		foreach ($keys as $k) {
			if (is_array($value) && array_key_exists($k, $value)) {
				$value = $value[$k];
			} else {
				// Fallback to default settings
				$full_defaults = self::get_default_settings();
				$val           = $full_defaults;
				foreach ($keys as $dk) {
					if (is_array($val) && array_key_exists($dk, $val)) {
						$val = $val[$dk];
					} else {
						return $default;
					}
				}
				return $val;
			}
		}

		return $value;
	}

	public static function get_rate_limit_settings(): array
	{
		return self::get_setting('rate_limiting', array());
	}
	public static function get_token_settings(): array
	{
		return self::get_setting('tokens', array());
	}
	public static function get_security_settings(): array
	{
		return self::get_setting('security', array());
	}
	public static function get_api_settings(): array
	{
		return self::get_setting('api', array());
	}
	public static function get_cache_settings(): array
	{
		return self::get_setting('cache', array());
	}
	public static function get_development_settings(): array
	{
		return self::get_setting('development', array());
	}

	public static function is_wp_debug_enabled(): bool
	{
		return defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
	}

	public static function is_development_mode(): bool
	{
		$dev = self::get_development_settings();
		return ($dev['debug_mode'] ?? false) || self::is_wp_debug_enabled();
	}

	public static function register(): void
	{
		register_setting(
			'mhm_rentiva_rest_settings',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array(self::class, 'sanitize_settings'),
				'default'           => self::get_default_settings(),
			)
		);
	}

	public static function reset_to_defaults(): bool
	{
		return update_option(self::OPTION_NAME, self::get_default_settings()) !== false;
	}

	/**
	 * Sanitize all REST settings
	 */
	public static function sanitize_settings(array $input): array
	{
		$defaults  = self::get_default_settings();
		$sanitized = $defaults;

		// 1. Rate Limiting
		if (isset($input['rate_limiting']) && is_array($input['rate_limiting'])) {
			$rl                         = $input['rate_limiting'];
			$sanitized['rate_limiting'] = array(
				'enabled'        => ! empty($rl['enabled']),
				'default_limit'  => max(1, (int) ($rl['default_limit'] ?? 60)),
				'default_window' => max(1, (int) ($rl['default_window'] ?? 60)),
				'strict_limit'   => max(1, (int) ($rl['strict_limit'] ?? 10)),
				'strict_window'  => max(1, (int) ($rl['strict_window'] ?? 60)),
				'burst_limit'    => max(1, (int) ($rl['burst_limit'] ?? 100)),
				'burst_window'   => max(1, (int) ($rl['burst_window'] ?? 300)),
			);
		}

		// 2. Token Settings
		if (isset($input['tokens']) && is_array($input['tokens'])) {
			$tokens              = $input['tokens'];
			$sanitized['tokens'] = array(
				'default_expiry_hours'   => max(1, min(168, (int) ($tokens['default_expiry_hours'] ?? 24))),
				'max_expiry_hours'       => 168,
				'refresh_enabled'        => ! empty($tokens['refresh_enabled']),
				'auto_refresh_threshold' => 2,
			);
		}

		// 3. Security Settings
		if (isset($input['security']) && is_array($input['security'])) {
			$sec = $input['security'];

			// Helper to convert list-like input to array
			$to_array = function ($val) {
				if (is_array($val)) {
					return $val;
				}
				if (is_string($val)) {
					return array_filter(array_map('trim', explode(',', $val)));
				}
				return array();
			};

			$ip_whitelist = $to_array($sec['ip_whitelist'] ?? array());
			$ip_blacklist = $to_array($sec['ip_blacklist'] ?? array());

			$sanitized['security'] = array(
				'require_https'         => ! empty($sec['require_https']),
				'ip_whitelist_enabled'  => ! empty($sec['ip_whitelist_enabled']),
				'ip_whitelist'          => array_map('sanitize_text_field', (array) $ip_whitelist),
				'ip_blacklist_enabled'  => ! empty($sec['ip_blacklist_enabled']),
				'ip_blacklist'          => array_map('sanitize_text_field', (array) $ip_blacklist),
				'user_agent_validation' => ! empty($sec['user_agent_validation']),
				'blocked_user_agents'   => $defaults['security']['blocked_user_agents'],
			);
		}

		// 4. Cache Settings
		if (isset($input['cache']) && is_array($input['cache'])) {
			$cache              = $input['cache'];
			$sanitized['cache'] = array(
				'enabled'               => ! empty($cache['enabled']),
				'duration_seconds'      => max(60, (int) ($cache['duration_seconds'] ?? 300)),
				'long_duration_seconds' => 1800,
				'cache_headers'         => true,
				'etag_enabled'          => true,
			);
		}

		// 5. Development Settings
		if (isset($input['development']) && is_array($input['development'])) {
			$dev                      = $input['development'];
			$sanitized['development'] = array(
				'debug_mode'           => ! empty($dev['debug_mode']),
				'cors_all_origins'     => ! empty($dev['cors_all_origins']),
				'rate_limit_bypass'    => ! empty($dev['rate_limit_bypass']),
				'security_bypass'      => ! empty($dev['security_bypass']),
				'verbose_logging'      => ! empty($dev['verbose_logging']),
				'auto_enable_on_debug' => ! empty($dev['auto_enable_on_debug']),
			);
		}

		return $sanitized;
	}

	/**
	 * Render all settings fields
	 */
	public static function render_settings_section(): void
	{
		$rate   = self::get_rate_limit_settings();
		$tokens = self::get_token_settings();
		$sec    = self::get_security_settings();
		$cache  = self::get_cache_settings();
		$dev    = self::get_development_settings();

		echo '<table class="form-table">';

		// --- RATE LIMITING ---
		echo '<tr><th scope="row">' . esc_html__('Rate Limiting', 'mhm-rentiva') . '</th><td>';
		echo '<input type="hidden" name="mhm_rentiva_rest_settings[rate_limiting][enabled]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[rate_limiting][enabled]" value="1" ' . checked($rate['enabled'] ?? false, true, false) . '> ' . esc_html__('Enable API Rate Limiting', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Prevents API abuse by limiting request frequency.', 'mhm-rentiva') . '</p><br>';

		echo '<label for="rest_default_limit">' . esc_html__('General Request Limit', 'mhm-rentiva') . '</label><br>';
		echo '<input type="number" id="rest_default_limit" name="mhm_rentiva_rest_settings[rate_limiting][default_limit]" value="' . esc_attr($rate['default_limit']) . '" min="1" max="1000" style="width: 100px;">';
		echo '<p class="description">' . esc_html__('Max requests per minute for authenticated users.', 'mhm-rentiva') . '</p><br>';

		echo '<label for="rest_strict_limit">' . esc_html__('Public Request Limit', 'mhm-rentiva') . '</label><br>';
		echo '<input type="number" id="rest_strict_limit" name="mhm_rentiva_rest_settings[rate_limiting][strict_limit]" value="' . esc_attr($rate['strict_limit']) . '" min="1" max="100" style="width: 100px;">';
		echo '<p class="description">' . esc_html__('Max requests per minute for public (anonymous) visitors.', 'mhm-rentiva') . '</p>';
		echo '</td></tr>';

		// --- TOKEN SETTINGS ---
		echo '<tr><th scope="row">' . esc_html__('Token Configuration', 'mhm-rentiva') . '</th><td>';
		echo '<label for="rest_token_expiry">' . esc_html__('Token Duration (Hours)', 'mhm-rentiva') . '</label><br>';
		echo '<input type="number" id="rest_token_expiry" name="mhm_rentiva_rest_settings[tokens][default_expiry_hours]" value="' . esc_attr($tokens['default_expiry_hours']) . '" min="1" max="168" style="width: 100px;">';
		echo '<p class="description">' . esc_html__('How long the issued API tokens remain valid.', 'mhm-rentiva') . '</p><br>';

		echo '<input type="hidden" name="mhm_rentiva_rest_settings[tokens][refresh_enabled]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[tokens][refresh_enabled]" value="1" ' . checked($tokens['refresh_enabled'] ?? false, true, false) . '> ' . esc_html__('Allow Token Refresh', 'mhm-rentiva') . '</label>';
		echo '</td></tr>';

		// --- SECURITY ---
		echo '<tr><th scope="row">' . esc_html__('Security Settings', 'mhm-rentiva') . '</th><td>';
		echo '<input type="hidden" name="mhm_rentiva_rest_settings[security][require_https]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[security][require_https]" value="1" ' . checked($sec['require_https'] ?? false, true, false) . '> ' . esc_html__('Mandatory HTTPS', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Require SSL encryption for all API communication.', 'mhm-rentiva') . '</p><br>';

		echo '<input type="hidden" name="mhm_rentiva_rest_settings[security][user_agent_validation]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[security][user_agent_validation]" value="1" ' . checked($sec['user_agent_validation'] ?? false, true, false) . '> ' . esc_html__('User Agent Filter', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Blocks known bots and scraping tools (curl, wget, bot etc.).', 'mhm-rentiva') . '</p><br>';

		echo '<label for="rest_ip_whitelist">' . esc_html__('IP Whitelist', 'mhm-rentiva') . '</label><br>';
		echo '<textarea id="rest_ip_whitelist" name="mhm_rentiva_rest_settings[security][ip_whitelist]" rows="2" cols="50" class="regular-text" placeholder="1.2.3.4, 5.6.7.8">' . esc_textarea(implode(', ', (array) ($sec['ip_whitelist'] ?? array()))) . '</textarea>';
		echo '<p class="description">' . esc_html__('Comma separated list of allowed IP addresses.', 'mhm-rentiva') . '</p>';
		echo '</td></tr>';

		// --- CACHE ---
		echo '<tr><th scope="row">' . esc_html__('API Caching', 'mhm-rentiva') . '</th><td>';
		echo '<input type="hidden" name="mhm_rentiva_rest_settings[cache][enabled]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[cache][enabled]" value="1" ' . checked($cache['enabled'] ?? false, true, false) . '> ' . esc_html__('Enable Caching', 'mhm-rentiva') . '</label><br><br>';
		echo '<label for="rest_cache_duration">' . esc_html__('Cache Life (Seconds)', 'mhm-rentiva') . '</label><br>';
		echo '<input type="number" id="rest_cache_duration" name="mhm_rentiva_rest_settings[cache][duration_seconds]" value="' . esc_attr($cache['duration_seconds']) . '" min="60" max="3600" style="width: 100px;">';
		echo '</td></tr>';

		// --- DEVELOPER MODE ---
		echo '<tr><th scope="row">' . esc_html__('Development Diagnostics', 'mhm-rentiva') . '</th><td>';
		echo '<input type="hidden" name="mhm_rentiva_rest_settings[development][debug_mode]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[development][debug_mode]" value="1" ' . checked($dev['debug_mode'] ?? false, true, false) . '> ' . esc_html__('Debug Operations', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Show detailed error messages and performance traces. (Turn off on live sites).', 'mhm-rentiva') . '</p><br>';

		echo '<input type="hidden" name="mhm_rentiva_rest_settings[development][cors_all_origins]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_rest_settings[development][cors_all_origins]" value="1" ' . checked($dev['cors_all_origins'] ?? false, true, false) . '> ' . esc_html__('Allow Global CORS (*)', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Permit API requests from any domain (Development only).', 'mhm-rentiva') . '</p>';
		echo '</td></tr>';

		echo '</table>';
	}

	/**
	 * AJAX Handlers
	 */
	public static function ajax_create_api_key(): void
	{
		if (! check_ajax_referer('mhm_rest_api_keys_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'mhm-rentiva')));
		}
		$name  = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
		$perms = isset($_POST['permissions']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['permissions'])) : array('read');
		$key   = \MHMRentiva\Admin\REST\APIKeyManager::create_api_key($name, $perms);
		$key ? wp_send_json_success(array('key' => $key)) : wp_send_json_error();
	}

	public static function ajax_list_api_keys(): void
	{
		if (! check_ajax_referer('mhm_rest_api_keys_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'mhm-rentiva')));
		}
		wp_send_json_success(array('keys' => \MHMRentiva\Admin\REST\APIKeyManager::list_api_keys()));
	}

	public static function ajax_revoke_api_key(): void
	{
		if (! check_ajax_referer('mhm_rest_api_keys_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'mhm-rentiva')));
		}
		$key_id = isset($_POST['key_id']) ? sanitize_text_field(wp_unslash((string) $_POST['key_id'])) : '';
		\MHMRentiva\Admin\REST\APIKeyManager::revoke_api_key($key_id) ? wp_send_json_success() : wp_send_json_error();
	}

	public static function ajax_delete_api_key(): void
	{
		if (! check_ajax_referer('mhm_rest_api_keys_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'mhm-rentiva')));
		}
		$key_id = isset($_POST['key_id']) ? sanitize_text_field(wp_unslash((string) $_POST['key_id'])) : '';
		\MHMRentiva\Admin\REST\APIKeyManager::delete_api_key($key_id) ? wp_send_json_success() : wp_send_json_error();
	}

	public static function ajax_list_endpoints(): void
	{
		if (! check_ajax_referer('mhm_rest_api_keys_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Insufficient permissions.', 'mhm-rentiva')));
		}
		wp_send_json_success(
			array(
				'endpoints' => \MHMRentiva\Admin\REST\EndpointListHelper::get_all_endpoints(),
				'namespace' => self::get_setting('api.base_namespace', 'mhm-rentiva/v1'),
			)
		);
	}
}
