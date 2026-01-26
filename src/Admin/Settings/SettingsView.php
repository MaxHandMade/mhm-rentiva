<?php

/**
 * Settings View Class
 *
 * @package MHMRentiva
 * @version 1.2.0
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsView
 *
 * Manages the admin settings interface and loads templates.
 */
final readonly class SettingsView {

	/**
	 * Render the settings page.
	 *
	 * @param string $current_tab Current active tab slug.
	 * @param array  $tabs        List of available tabs (slug => label).
	 * @param mixed  $renderer    The renderer instance for the current tab.
	 * @return void
	 */
	public static function render_settings_page( string $current_tab, array $tabs, $renderer = null ): void {
		// Use the plugin path constant for template loading.
		$template_file = MHM_RENTIVA_PLUGIN_DIR . 'templates/admin/settings-page.php';

		// Check file existence and load safely.
		if ( ! file_exists( $template_file ) ) {
			wp_die(
				esc_html__( 'Settings template file not found.', 'mhm-rentiva' )
			);
		}

		self::load_template(
			$template_file,
			array(
				'current_tab' => $current_tab,
				'tabs'        => $tabs,
				'renderer'    => $renderer,
			)
		);
	}

	/**
	 * Load template file and pass arguments.
	 *
	 * CRITICAL: Do not use extract(). Access variables in template
	 * using the $args['key'] format.
	 *
	 * @param string $template_path Full path to the template file.
	 * @param array  $args          Arguments to pass to the template.
	 * @return void
	 */
	private static function load_template( string $template_path, array $args ): void {
		/**
		 * Include the template file.
		 * Usage inside template:
		 * echo esc_html( $args['current_tab'] );
		 */
		include $template_path;
	}
}
