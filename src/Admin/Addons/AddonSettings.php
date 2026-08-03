<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Settings Class.
 *
 * @package MHMRentiva\Admin\Addons
 */





use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles settings for additional services.
 */
final class AddonSettings {


	public const PAGE = 'mhmrentiva_addon_settings';

	/**
	 * Register actions.
	 */
	public static function register(): void {
		// WordPress Settings API registration.
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'wp_ajax_mhmrentiva_create_default_addons', array( self::class, 'ajax_create_default_addons' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	/**
	 * Register WordPress Settings API.
	 */
	public static function register_settings(): void {
		// Register setting group.
		register_setting(
			'mhmrentiva_addon_settings',
			'mhmrentiva_addon_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_addon_settings_option' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	public static function defaults(): array {
		return array(
			'system_enabled' => '1',
			'show_prices'    => '1',
			'allow_multiple' => '1',
			'display_order'  => 'price_asc',
		);
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value.
	 * @return string Setting value.
	 */
	public static function get( string $key, $default_value = null ) {
		$settings = get_option( 'mhmrentiva_addon_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = array_merge( self::defaults(), $settings );
		$value    = array_key_exists( $key, $settings ) ? $settings[ $key ] : $default_value;

		// Convert null values to string.
		return null !== $value ? (string) $value : '';
	}

	/**
	 * Sanitize input data.
	 *
	 * @param array $input Input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize( array $input ): array {
		return SettingsSanitizer::sanitize_addon_settings_option( $input );
	}

	/**
	 * Render settings page content.
	 *
	 * @param bool $in_tab Whether rendered inside a tab.
	 */
	public static function render_page( bool $in_tab = false ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mhm-rentiva' ) );
		}

		// WordPress Settings API handles form submission automatically.
		// No manual POST processing needed.

		// Add wrapper for standalone page only.
		if ( ! $in_tab ) {
			echo '<div class="wrap mhm-rentiva-wrap">';
			echo '<h1>' . esc_html__( 'Additional Service Settings', 'mhm-rentiva' ) . '</h1>';
		}

		// Form wrapper - for standalone page only.
		if ( ! $in_tab ) {
			echo '<form method="post" action="options.php">';
			// WordPress Settings API handles nonce and hidden fields.
			settings_fields( 'mhmrentiva_addon_settings' );
		}

		// Form fields
		echo '<table class="form-table">';
		echo '<tbody>';

		// Additional Service System
		$system_enabled = self::get( 'system_enabled', '1' );
		echo '<tr>';
		echo '<th scope="row"><label for="system_enabled">' . esc_html__( 'Additional Service System', 'mhm-rentiva' ) . '</label></th>';
		echo '<td>';
		echo '<label><input type="checkbox" id="system_enabled" name="mhmrentiva_addon_settings[system_enabled]" value="1" ' . checked( $system_enabled, '1', false ) . '> ' . esc_html__( 'Enable additional service system', 'mhm-rentiva' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'When disabled, additional services are not shown in booking form.', 'mhm-rentiva' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Price Display
		$show_prices = self::get( 'show_prices', '1' );
		echo '<tr>';
		echo '<th scope="row"><label for="show_prices">' . esc_html__( 'Price Display', 'mhm-rentiva' ) . '</label></th>';
		echo '<td>';
		echo '<label><input type="checkbox" id="show_prices" name="mhmrentiva_addon_settings[show_prices]" value="1" ' . checked( $show_prices, '1', false ) . '> ' . esc_html__( 'Show additional service prices in booking form', 'mhm-rentiva' ) . '</label>';
		echo '</td>';
		echo '</tr>';

		// Multiple Selection
		$allow_multiple = self::get( 'allow_multiple', '1' );
		echo '<tr>';
		echo '<th scope="row"><label for="allow_multiple">' . esc_html__( 'Multiple Selection', 'mhm-rentiva' ) . '</label></th>';
		echo '<td>';
		echo '<label><input type="checkbox" id="allow_multiple" name="mhmrentiva_addon_settings[allow_multiple]" value="1" ' . checked( $allow_multiple, '1', false ) . '> ' . esc_html__( 'Allow multiple additional service selection', 'mhm-rentiva' ) . '</label>';
		echo '</td>';
		echo '</tr>';

		// Display Order
		$display_order = self::get( 'display_order', 'price_asc' );
		echo '<tr>';
		echo '<th scope="row"><label for="display_order">' . esc_html__( 'Display Order', 'mhm-rentiva' ) . '</label></th>';
		echo '<td>';
		echo '<select id="display_order" name="mhmrentiva_addon_settings[display_order]">';
		$options = array(
			'price_asc'  => __( 'Price ascending', 'mhm-rentiva' ),
			'price_desc' => __( 'Price descending', 'mhm-rentiva' ),
			'name_asc'   => __( 'Name A-Z', 'mhm-rentiva' ),
			'name_desc'  => __( 'Name Z-A', 'mhm-rentiva' ),
			'menu_order' => __( 'Menu order', 'mhm-rentiva' ),
		);
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $display_order, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

		// Submit button - for standalone page only (main page button is used within a tab)
		if ( ! $in_tab ) {
			submit_button( __( 'Save Settings', 'mhm-rentiva' ) );
			echo '</form>';
		}

		// Default additional services creation section (outside form)
		echo '<hr style="margin: 30px 0;">';
		echo '<h2>' . esc_html__( 'Default Additional Services', 'mhm-rentiva' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Automatically create default additional services for new installations.', 'mhm-rentiva' ) . '</p>';

		$existing_count = wp_count_posts( 'mhmrentiva_addon' )->publish;
		if ( $existing_count > 0 ) {
			/* translators: %d: existing additional services count. */
			echo '<p class="description">' . esc_html( sprintf( __( 'There are already %d additional services. Click the button below to create new default services.', 'mhm-rentiva' ), $existing_count ) ) . '</p>';
			echo '<button type="button" class="button" id="create-default-addons">' . esc_html__( 'Create Default Additional Services', 'mhm-rentiva' ) . '</button>';
		} else {
			echo '<p class="description">' . esc_html__( 'No additional services created yet. You can create default additional services with the button below.', 'mhm-rentiva' ) . '</p>';
			echo '<button type="button" class="button button-primary" id="create-default-addons">' . esc_html__( 'Create Default Additional Services', 'mhm-rentiva' ) . '</button>';
		}

		// Close wrapper for standalone page only
		if ( ! $in_tab ) {
			echo '</div>';
		}

		// Inline JavaScript removed - the click handler lives in
		// assets/js/admin/addon-settings.js, enqueued below.
	}

	/**
	 * Enqueue and localize the script that binds #create-default-addons.
	 *
	 * Gated on this screen's own hook suffix -- the same pattern
	 * About::enqueue_scripts() uses -- so the script and its data never load
	 * on other admin screens.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( 'mhm-rentiva_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'mhm-rentiva-addon-settings',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/addon-settings.js',
			array( 'jquery' ),
			MHMRENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-addon-settings',
			'mhmAddonSettings',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mhmrentiva_create_default_addons' ),
				'strings'  => array(
					'confirm_create' => __( 'Are you sure you want to create the default additional services?', 'mhm-rentiva' ),
					'creating'       => __( 'Creating...', 'mhm-rentiva' ),
					'error'          => __( 'An error occurred. Please try again.', 'mhm-rentiva' ),
					'create_default' => __( 'Create Default Additional Services', 'mhm-rentiva' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for creating default addons.
	 */
	public static function ajax_create_default_addons(): void {
		check_ajax_referer( 'mhmrentiva_create_default_addons', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'mhm-rentiva' ) ) );
			return;
		}

		// Make sure method exists.
		if ( method_exists( '\MHMRentiva\Admin\Addons\AddonManager', 'create_default_addons' ) ) {
			AddonManager::create_default_addons();
		}

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Default additional services created successfully.', 'mhm-rentiva' ),
			)
		);
	}
}
