<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\MetaBoxes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Abstract MetaBox Base Class
 *
 * Central base class for WordPress MetaBox classes.
 * Eliminates common functions and structural repetition.
 *
 * @abstract
 */
abstract class AbstractMetaBox {



	/**
	 * Safe sanitize text field that handles null values
	 */
	protected static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Abstract methods - Must be implemented in subclasses
	 */
	abstract protected static function get_post_type(): string;
	abstract protected static function get_meta_box_id(): string;
	abstract protected static function get_title(): string;
	abstract protected static function get_fields(): array;

	/**
	 * Register meta box
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( static::class, 'add_meta_boxes' ) );
		add_action( 'save_post', array( static::class, 'save_meta' ), 10, 2 );

		// Load scripts (if any)
		if ( method_exists( static::class, 'enqueue_scripts' ) ) {
			add_action( 'admin_enqueue_scripts', array( static::class, 'enqueue_scripts' ) );
		}
	}

	/**
	 * Add meta box
	 */
	public static function add_meta_boxes(): void {
		$fields = static::get_fields();

		foreach ( $fields as $field_id => $field_config ) {
			$config = static::get_meta_box_config( $field_id, $field_config );

			add_meta_box(
				$config['id'],
				$config['title'],
				array( static::class, 'render_meta_box' ),
				static::get_post_type(),
				$config['context'],
				$config['priority'],
				$config['callback_args']
			);
		}
	}

	/**
	 * Meta box configuration
	 */
	protected static function get_meta_box_config( string $field_id, array $field_config ): array {
		return array(
			'id'            => $field_id,
			'title'         => $field_config['title'] ?? static::get_title(),
			'context'       => $field_config['context'] ?? 'normal',
			'priority'      => $field_config['priority'] ?? 'default',
			'callback_args' => $field_config['callback_args'] ?? null,
		);
	}

	/**
	 * Render meta box
	 */
	public static function render_meta_box( \WP_Post $post, array $args = array() ): void {
		$meta_box_id = $args['id'] ?? static::get_meta_box_id();
		$fields      = static::get_fields();

		if ( ! isset( $fields[ $meta_box_id ] ) ) {
			return;
		}

		$field_config = $fields[ $meta_box_id ];
		$nonce_name   = static::get_nonce_name( $meta_box_id );

		// Nonce field
		wp_nonce_field( $nonce_name, $nonce_name );

		// Render template
		if ( isset( $field_config['template'] ) && method_exists( static::class, $field_config['template'] ) ) {
			call_user_func( array( static::class, $field_config['template'] ), $post, $field_config );
		} else {
			static::render_default_template( $post, $field_config, $meta_box_id );
		}
	}

	/**
	 * Default template render
	 */
	protected static function render_default_template( \WP_Post $post, array $field_config, string $meta_box_id ): void {
		echo '<table class="form-table">';
		echo '<tbody>';

		foreach ( $field_config['fields'] ?? array() as $field_key => $field ) {
			static::render_field( $post, $field_key, $field );
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render field
	 */
	protected static function render_field( \WP_Post $post, string $field_key, array $field ): void {
		$value       = get_post_meta( $post->ID, $field_key, true );
		$field_type  = $field['type'] ?? 'text';
		$label       = $field['label'] ?? $field_key;
		$description = $field['description'] ?? '';
		$required    = $field['required'] ?? false;

		echo '<tr>';
		echo '<th scope="row">';
		echo '<label for="' . esc_attr( $field_key ) . '">' . esc_html( $label );
		if ( $required ) {
			echo ' <span class="required">*</span>';
		}
		echo '</label>';
		echo '</th>';
		echo '<td>';

		switch ( $field_type ) {
			case 'text':
				static::render_text_field( $field_key, $value, $field );
				break;
			case 'number':
				static::render_number_field( $field_key, $value, $field );
				break;
			case 'email':
				static::render_email_field( $field_key, $value, $field );
				break;
			case 'url':
				static::render_url_field( $field_key, $value, $field );
				break;
			case 'textarea':
				static::render_textarea_field( $field_key, $value, $field );
				break;
			case 'checkbox':
				static::render_checkbox_field( $field_key, $value, $field );
				break;
			case 'select':
				static::render_select_field( $field_key, $value, $field );
				break;
			case 'radio':
				static::render_radio_field( $field_key, $value, $field );
				break;
			default:
				static::render_text_field( $field_key, $value, $field );
		}

		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Text field render
	 */
	protected static function render_text_field( string $field_key, $value, array $field ): void {
		$class       = $field['class'] ?? 'regular-text';
		$placeholder = $field['placeholder'] ?? '';

		echo '<input type="text" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
	}

	/**
	 * Number field render
	 */
	protected static function render_number_field( string $field_key, $value, array $field ): void {
		$min   = $field['min'] ?? '';
		$max   = $field['max'] ?? '';
		$step  = $field['step'] ?? '';
		$class = $field['class'] ?? 'small-text';

		echo '<input type="number" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '"';

		if ( $min !== '' ) {
			echo ' min="' . esc_attr( $min ) . '"';
		}
		if ( $max !== '' ) {
			echo ' max="' . esc_attr( $max ) . '"';
		}
		if ( $step !== '' ) {
			echo ' step="' . esc_attr( $step ) . '"';
		}

		echo ' />';
	}

	/**
	 * Email field render
	 */
	protected static function render_email_field( string $field_key, $value, array $field ): void {
		$class = $field['class'] ?? 'regular-text';

		echo '<input type="email" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
	}

	/**
	 * URL field render
	 */
	protected static function render_url_field( string $field_key, $value, array $field ): void {
		$class = $field['class'] ?? 'regular-text';

		echo '<input type="url" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
	}

	/**
	 * Textarea field render
	 */
	protected static function render_textarea_field( string $field_key, $value, array $field ): void {
		$rows  = $field['rows'] ?? 4;
		$cols  = $field['cols'] ?? 50;
		$class = $field['class'] ?? 'large-text';

		echo '<textarea id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" rows="' . esc_attr( $rows ) . '" cols="' . esc_attr( $cols ) . '" class="' . esc_attr( $class ) . '">' . esc_textarea( $value ) . '</textarea>';
	}

	/**
	 * Checkbox field render
	 */
	protected static function render_checkbox_field( string $field_key, $value, array $field ): void {
		$label_text = $field['label_text'] ?? $field['label'] ?? '';
		$checked    = checked( $value, '1', false );

		echo '<label>';
		echo '<input type="checkbox" id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" value="1" ' . wp_kses_post( $checked ) . '> ';
		echo esc_html( $label_text );
		echo '</label>';
	}

	/**
	 * Select field render
	 */
	protected static function render_select_field( string $field_key, $value, array $field ): void {
		$options = $field['options'] ?? array();
		$class   = $field['class'] ?? '';

		echo '<select id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '" class="' . esc_attr( $class ) . '">';

		foreach ( $options as $option_value => $option_label ) {
			$selected = selected( $value, $option_value, false );
			echo '<option value="' . esc_attr( $option_value ) . '" ' . wp_kses_post( $selected ) . '>' . esc_html( $option_label ) . '</option>';
		}

		echo '</select>';
	}

	/**
	 * Radio field render
	 */
	protected static function render_radio_field( string $field_key, $value, array $field ): void {
		$options = $field['options'] ?? array();

		foreach ( $options as $option_value => $option_label ) {
			$checked = checked( $value, $option_value, false );
			echo '<label>';
			echo '<input type="radio" name="' . esc_attr( $field_key ) . '" value="' . esc_attr( $option_value ) . '" ' . wp_kses_post( $checked ) . '> ';
			echo esc_html( $option_label );
			echo '</label><br>';
		}
	}

	/**
	 * Save meta
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		// Post type check
		if ( $post->post_type !== static::get_post_type() ) {
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Autosave and revision check
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$fields = static::get_fields();

		foreach ( $fields as $meta_box_id => $field_config ) {
			$nonce_name = static::get_nonce_name( $meta_box_id );

			// Nonce check
			if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_name ) ) {
				continue;
			}

			// Custom save handler
			if ( isset( $field_config['save_handler'] ) && method_exists( static::class, $field_config['save_handler'] ) ) {
				call_user_func( array( static::class, $field_config['save_handler'] ), $post_id, $field_config );
			} else {
				// Default save
				static::save_fields( $post_id, $field_config );
			}
		}
	}

	/**
	 * Default field save
	 */
	protected static function save_fields( int $post_id, array $field_config ): void {
		foreach ( $field_config['fields'] ?? array() as $field_key => $field ) {
			static::save_field( $post_id, $field_key, $field );
		}
	}

	/**
	 * Save single field
	 */
	protected static function save_field( int $post_id, string $field_key, array $field ): void {
		$field_type        = $field['type'] ?? 'text';
		$sanitize_callback = $field['sanitize_callback'] ?? null;

		if ( ! isset( $_POST[ $field_key ] ) ) {
			// Special case for checkbox
			if ( $field_type === 'checkbox' ) {
				delete_post_meta( $post_id, $field_key );
			}
			return;
		}

		$value = $_POST[ $field_key ];

		// Null check
		if ( $value === null ) {
			$value = '';
		}

		// Sanitize
		if ( $sanitize_callback && is_callable( $sanitize_callback ) ) {
			// Check to avoid passing null to sanitize_callback
			$value = ( $value === null || $value === '' ) ? '' : call_user_func( $sanitize_callback, $value );
		} else {
			$value = static::sanitize_value( $value, $field_type, $field );
		}

		// Save
		if ( $field_type === 'checkbox' ) {
			update_post_meta( $post_id, $field_key, '1' );
		} else {
			update_post_meta( $post_id, $field_key, $value );
		}
	}

	/**
	 * Sanitize value
	 */
	protected static function sanitize_value( $value, string $field_type, array $field ): string {
		// Move null check to the beginning
		if ( $value === null ) {
			return '';
		}

		switch ( $field_type ) {
			case 'email':
				return sanitize_email( (string) ( $value ?: '' ) );
			case 'url':
				return esc_url_raw( $value ?: '' );
			case 'textarea':
				return sanitize_textarea_field( (string) ( $value ?: '' ) );
			case 'number':
				return static::sanitize_text_field_safe( $value ); // WordPress number sanitization
			default:
				return static::sanitize_text_field_safe( $value );
		}
	}

	/**
	 * Create nonce name
	 */
	protected static function get_nonce_name( string $meta_box_id ): string {
		return static::get_post_type() . '_' . $meta_box_id . '_nonce';
	}

	/**
	 * Helper: Get meta value
	 */
	protected static function get_meta_value( int $post_id, string $meta_key, $default = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return $value !== '' ? $value : $default;
	}

	/**
	 * Helper: Get meta values in bulk
	 */
	protected static function get_meta_values( int $post_id, array $meta_keys ): array {
		$values = array();
		foreach ( $meta_keys as $key ) {
			$values[ $key ] = get_post_meta( $post_id, $key, true );
		}
		return $values;
	}

	/**
	 * Helper: Save meta values in bulk
	 */
	protected static function save_meta_values( int $post_id, array $meta_data ): void {
		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Helper: Delete meta values in bulk
	 */
	protected static function delete_meta_values( int $post_id, array $meta_keys ): void {
		foreach ( $meta_keys as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	/**
	 * Helper: Custom field render (overridable)
	 */
	protected static function render_custom_field( \WP_Post $post, string $field_key, array $field ): void {
		// Can be overridden in subclasses
		static::render_field( $post, $field_key, $field );
	}

	/**
	 * Helper: Custom field save (overridable)
	 */
	protected static function save_custom_field( int $post_id, string $field_key, array $field ): void {
		// Can be overridden in subclasses
		static::save_field( $post_id, $field_key, $field );
	}
}
