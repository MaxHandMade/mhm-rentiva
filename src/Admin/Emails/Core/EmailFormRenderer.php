<?php

namespace MHMRentiva\Admin\Emails\Core;

if (! defined('ABSPATH')) {
	exit;
}

class EmailFormRenderer
{

	/**
	 * Render email form
	 *
	 * @param string $title Form title
	 * @param string $description Form description
	 * @param array  $fields Form fields
	 */
	public static function render_form(string $title, string $description, array $fields): void
	{
		echo '<h3>' . esc_html($title) . '</h3>';
		echo '<p class="description">' . esc_html($description) . '</p>';

		echo '<table class="form-table">';

		foreach ($fields as $field) {
			self::render_field($field);
		}

		echo '</table>';
	}

	/**
	 * Render form field
	 *
	 * @param array $field Field definition
	 */
	private static function render_field(array $field): void
	{
		$type        = $field['type'] ?? 'text';
		$name        = $field['name'] ?? '';
		$label       = $field['label'] ?? '';
		$description = $field['description'] ?? '';
		$value       = $field['value'] ?? '';
		$required    = $field['required'] ?? false;
		$placeholder = $field['placeholder'] ?? '';
		$rows        = $field['rows'] ?? 3;

		echo '<tr>';
		echo '<th scope="row">';

		if ($type !== 'checkbox') {
			echo '<label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
		} else {
			echo esc_html($label);
		}

		echo '</th>';
		echo '<td>';

		switch ($type) {
			case 'checkbox':
				$checked = ($value === '1' || $value === 1 || $value === true) ? 'checked="checked"' : '';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $checked . '> ';
				echo esc_html($label) . '</label>';
				break;

			case 'email':
				echo '<input type="email" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" ';
				echo 'value="' . esc_attr($value) . '" class="regular-text"';
				if ($required) {
					echo ' required';
				}
				if ($placeholder) {
					echo ' placeholder="' . esc_attr($placeholder) . '"';
				}
				echo ' />';
				break;

			case 'number':
				echo '<input type="number" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" ';
				echo 'value="' . esc_attr($value) . '" class="small-text"';
				if (isset($field['min'])) {
					echo ' min="' . esc_attr($field['min']) . '"';
				}
				if (isset($field['max'])) {
					echo ' max="' . esc_attr($field['max']) . '"';
				}
				if ($required) {
					echo ' required';
				}
				echo ' />';
				break;

			case 'textarea':
				echo '<textarea id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" ';
				echo 'class="large-text code" rows="' . esc_attr($rows) . '"';
				if ($required) {
					echo ' required';
				}
				if ($placeholder) {
					echo ' placeholder="' . esc_attr($placeholder) . '"';
				}
				echo '>' . esc_textarea($value) . '</textarea>';
				break;

			case 'select':
				echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="regular-text">';
				foreach ($field['options'] as $option_value => $option_label) {
					$selected = ($value === $option_value) ? 'selected="selected"' : '';
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>';
					echo esc_html($option_label) . '</option>';
				}
				echo '</select>';
				break;

			default: // text
				echo '<input type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" ';
				echo 'value="' . esc_attr($value) . '" class="regular-text"';
				if ($required) {
					echo ' required';
				}
				if ($placeholder) {
					echo ' placeholder="' . esc_attr($placeholder) . '"';
				}
				echo ' />';
				break;
		}

		if ($description) {
			echo '<p class="description">' . esc_html($description) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Get email settings
	 *
	 * @param string $option_name Option name
	 * @param mixed  $default Default value
	 * @return mixed Option value
	 */
	public static function get_option(string $option_name, $default = '')
	{
		return get_option($option_name, $default);
	}

	/**
	 * Check checkbox value
	 *
	 * @param string $option_name Option name
	 * @return bool Is checked
	 */
	public static function is_checked(string $option_name): bool
	{
		$value = get_option($option_name, '0');
		return ($value === '1' || $value === 1 || $value === true);
	}
}
