<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Settings;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ REST SETTINGS - Dynamic REST API Settings
 */
final class RESTSettings {


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
			'api'           => array(
				'version'              => 'v1',
				'base_namespace'       => 'mhm-rentiva/v1',
				'cors_enabled'         => true,
				'cors_origins'         => array(),
				'request_logging'      => true,
				'response_compression' => true,
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
				$value = $value[ $k ];
			} else {
				// Fallback to default settings
				$full_defaults = self::get_default_settings();
				$val           = $full_defaults;
				foreach ($keys as $dk) {
					if (is_array($val) && array_key_exists($dk, $val)) {
						$val = $val[ $dk ];
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
	public static function get_api_settings(): array
	{
		return self::get_setting('api', array());
	}
	public static function register(): void
	{
		register_setting(
			'mhm_rentiva_rest_settings',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
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
				'default_limit'  => max(1, (int) ( $rl['default_limit'] ?? 60 )),
				'default_window' => max(1, (int) ( $rl['default_window'] ?? 60 )),
				'strict_limit'   => max(1, (int) ( $rl['strict_limit'] ?? 10 )),
				'strict_window'  => max(1, (int) ( $rl['strict_window'] ?? 60 )),
				'burst_limit'    => max(1, (int) ( $rl['burst_limit'] ?? 100 )),
				'burst_window'   => max(1, (int) ( $rl['burst_window'] ?? 300 )),
			);
		}

		return $sanitized;
	}

	/**
	 * Render all settings fields
	 */
	public static function render_settings_section(): void
	{
		$rate = self::get_rate_limit_settings();

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

		echo '</table>';
	}
}
