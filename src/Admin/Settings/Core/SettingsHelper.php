<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

/**
 * Check if ABSPATH is defined to prevent direct access.
 */
if (! defined('ABSPATH')) {
	exit;
}

/**
 * SettingsHelper Class
 *
 * Provides static helper methods to render WordPress settings fields
 * and sanitize input data following 100/100 Gold Standards.
 *
 * @package MHMRentiva\Admin\Settings\Core
 */
final class SettingsHelper
{

	/**
	 * Settings option name constant for consistency.
	 */
	private const SETTINGS_KEY = 'mhm_rentiva_settings';

	/**
	 * Text field helper for settings.
	 *
	 * @param string $group   Settings group name.
	 * @param string $name    Field name/ID.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 */
	public static function text_field(string $group, string $name, string $label, string $section = '', string $description = '', string $placeholder = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $description, $placeholder) {
				$val = esc_attr((string) SettingsCore::get($name, ''));
				printf(
					'<input type="text" name="%s[%s]" class="regular-text" value="%s" placeholder="%s"/>',
					esc_attr(self::SETTINGS_KEY),
					esc_attr($name),
					esc_attr($val),
					esc_attr($placeholder)
				);
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * Checkbox field helper for settings.
	 *
	 * @param string $group       Settings group name.
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param string $description Optional description.
	 * @param string $section     Section ID.
	 */
	public static function checkbox_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $description) {
				$val = (string) SettingsCore::get($name, '0');

				// Fallback hidden field for unchecked state.
				printf('<input type="hidden" name="%s[%s]" value="0">', esc_attr(self::SETTINGS_KEY), esc_attr($name));

				echo '<label>';
				printf(
					'<input type="checkbox" name="%s[%s]" value="1" %s> %s',
					esc_attr(self::SETTINGS_KEY),
					esc_attr($name),
					checked('1', $val, false),
					esc_html($description)
				);
				echo '</label>';
			},
			$group,
			$section
		);
	}

	/**
	 * Select field helper for settings.
	 *
	 * @param string $group       Settings group name.
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param array  $options     Associative array of options (value => label).
	 * @param string $description Optional description.
	 * @param string $section     Section ID.
	 */
	public static function select_field(string $group, string $name, string $label, array $options, string $description = '', string $section = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $options, $description) {
				$val = (string) SettingsCore::get($name, '');
				printf('<select name="%s[%s]">', esc_attr(self::SETTINGS_KEY), esc_attr($name));
				foreach ($options as $value => $text) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr((string) $value),
						selected($val, (string) $value, false),
						esc_html((string) $text)
					);
				}
				echo '</select>';
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * Number field helper for settings.
	 */
	public static function number_field(string $group, string $name, string $label, int|float $min = 0, int|float $max = 999, string $description = '', string $section = '', int|float|null $step = null): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $min, $max, $description, $step) {
				$raw_val = SettingsCore::get($name, $min);
				$val     = is_numeric($raw_val) ? $raw_val : $min;

				// Auto-detect step if not provided
				if ($step === null) {
					$step = (is_float($min) || is_float($max) || is_float($val)) ? 0.1 : 1;
				}

				printf(
					'<input type="number" class="small-text" min="%s" max="%s" step="%s" name="%s[%s]" value="%s"/>',
					esc_attr((string) $min),
					esc_attr((string) $max),
					esc_attr((string) $step),
					esc_attr(self::SETTINGS_KEY),
					esc_attr($name),
					esc_attr((string) $val)
				);
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * Textarea field helper for settings.
	 */
	public static function textarea_field(string $group, string $name, string $label, int $rows = 5, string $description = '', string $section = '', string $placeholder = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $rows, $description, $placeholder) {
				$val = esc_textarea((string) SettingsCore::get($name, ''));
				printf(
					'<textarea name="%s[%s]" class="large-text code" rows="%d" placeholder="%s">%s</textarea>',
					esc_attr(self::SETTINGS_KEY),
					esc_attr($name),
					absint($rows),
					esc_attr($placeholder),
					esc_textarea($val)
				);
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * Email field helper for settings.
	 */
	public static function email_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $description) {
				$val = esc_attr((string) SettingsCore::get($name, ''));
				printf(
					'<input type="email" class="regular-text" name="%s[%s]" value="%s"/>',
					esc_attr(self::SETTINGS_KEY),
					esc_attr($name),
					esc_attr($val)
				);
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * URL field helper for settings.
	 */
	public static function url_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $description) {
				// FIXED: Now uses SettingsCore::get for consistency.
				$val = esc_url((string) SettingsCore::get($name, ''));
				printf(
					'<input type="url" class="regular-text" name="%s[%s]" value="%s"/>',
					esc_attr(self::SETTINGS_KEY),
					esc_attr($name),
					esc_url($val)
				);
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * Password field helper for settings.
	 */
	public static function password_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($name, $description) {
				// FIXED: Now uses SettingsCore::get for consistency.
				$val = esc_attr((string) SettingsCore::get($name, ''));
				printf(
					'<input type="password" class="regular-text" name="%s[%s]" value="%s"/>',
					esc_attr(self::SETTINGS_KEY),
					esc_attr($name),
					esc_attr($val)
				);
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * Readonly field helper.
	 */
	public static function readonly_field(string $group, string $name, string $label, string $value, string $description = '', string $section = ''): void
	{
		add_settings_field(
			$name,
			$label,
			static function () use ($value, $description) {
				printf(
					'<input type="text" class="regular-text" readonly value="%s" onclick="this.select();" />',
					esc_attr($value)
				);
				if ($description) {
					printf('<p class="description">%s</p>', esc_html($description));
				}
			},
			$group,
			$section
		);
	}

	/**
	 * Sanitization Hub using PHP 8 match expression.
	 */
	public static function sanitize_field(mixed $value, string $type = 'text'): mixed
	{
		if (null === $value || '' === $value) {
			return '';
		}

		return match ($type) {
			'email'    => sanitize_email((string) $value),
			'textarea' => sanitize_textarea_field((string) $value),
			'url'      => esc_url_raw((string) $value),
			'checkbox' => ('1' === $value || 1 === $value) ? '1' : '0',
			'integer'  => (int) $value,
			default    => sanitize_text_field((string) $value),
		};
	}

	/**
	 * Register a setting with safe callbacks.
	 */
	public static function register_setting(string $group, string $name, string $type = 'text'): void
	{
		register_setting(
			$group,
			$name,
			array(
				'type'              => 'string',
				'sanitize_callback' => static fn($val) => self::sanitize_field($val, $type),
			)
		);
	}

	/**
	 * Render radio buttons for enabled/disabled options.
	 */
	public static function render_radio_enabled(string $name, string $current_value, string $description = ''): void
	{
		printf(
			'<label><input type="radio" name="%1$s" value="1" %2$s> %3$s</label><br>',
			esc_attr($name),
			checked('1', $current_value, false),
			esc_html__('Enabled', 'mhm-rentiva')
		);
		printf(
			'<label><input type="radio" name="%1$s" value="0" %2$s> %3$s</label>',
			esc_attr($name),
			checked('0', $current_value, false),
			esc_html__('Disabled', 'mhm-rentiva')
		);

		if ($description) {
			printf('<p class="description">%s</p>', esc_html($description));
		}
	}
}
