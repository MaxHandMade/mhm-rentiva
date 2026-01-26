<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

use MHMRentiva\Admin\Core\Tabs\AbstractTab;
use MHMRentiva\Admin\Licensing\Mode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System Information Tab
 */
final class SystemTab extends AbstractTab {

	protected static function get_tab_id(): string {
		return 'system';
	}

	protected static function get_tab_title(): string {
		return __( 'System Information', 'mhm-rentiva' );
	}

	protected static function get_tab_description(): string {
		return __( 'WordPress, PHP, MySQL and plugin system information', 'mhm-rentiva' );
	}

	protected static function get_tab_content( array $data = array() ): array {
		// If no data is passed, get the system information
		if ( empty( $data ) ) {
			$data = self::get_system_info();
		}

		return array(
			'title'       => self::get_tab_title(),
			'description' => self::get_tab_description(),
			'sections'    => array(
				array(
					'type'  => 'card',
					'cards' => array(
						array(
							'title'   => __( 'WordPress Information', 'mhm-rentiva' ),
							'content' => array(
								array(
									'type'     => 'key-value',
									'label'    => __( 'Version:', 'mhm-rentiva' ),
									'value'    => get_bloginfo( 'version' ),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Language:', 'mhm-rentiva' ),
									'value'    => get_locale(),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Timezone:', 'mhm-rentiva' ),
									'value'    => wp_timezone_string(),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Site URL:', 'mhm-rentiva' ),
									'value'    => get_site_url(),
									'data_key' => '',
								),
								array(
									'type'  => 'boolean',
									'label' => __( 'Multisite:', 'mhm-rentiva' ),
									'value' => is_multisite(),
								),
							),
						),
						array(
							'title'   => __( 'PHP Information', 'mhm-rentiva' ),
							'content' => array(
								array(
									'type'     => 'key-value',
									'label'    => __( 'Version:', 'mhm-rentiva' ),
									'value'    => PHP_VERSION,
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Memory Limit:', 'mhm-rentiva' ),
									'value'    => ini_get( 'memory_limit' ),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Max Execution Time:', 'mhm-rentiva' ),
									'value'    => ini_get( 'max_execution_time' ) . 's',
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Upload Max Size:', 'mhm-rentiva' ),
									'value'    => ini_get( 'upload_max_filesize' ),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Post Max Size:', 'mhm-rentiva' ),
									'value'    => ini_get( 'post_max_size' ),
									'data_key' => '',
								),
							),
						),
						array(
							'title'   => __( 'Plugin Information', 'mhm-rentiva' ),
							'content' => array(
								array(
									'type'     => 'key-value',
									'label'    => __( 'Version:', 'mhm-rentiva' ),
									'value'    => 'v' . MHM_RENTIVA_VERSION,
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'File Size:', 'mhm-rentiva' ),
									'value'    => __( 'Calculating...', 'mhm-rentiva' ),
									'data_key' => 'plugin.file_size',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Installation Date:', 'mhm-rentiva' ),
									'value'    => self::get_formatted_date_option( 'mhm_rentiva_installed' ),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Last Update:', 'mhm-rentiva' ),
									'value'    => self::get_formatted_date_option( 'mhm_rentiva_last_update' ),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'License Status:', 'mhm-rentiva' ),
									'value'    => Mode::isPro() ? __( 'Active', 'mhm-rentiva' ) : __( 'Lite Version', 'mhm-rentiva' ),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'License Expiry:', 'mhm-rentiva' ),
									'value'    => \MHMRentiva\Admin\Licensing\LicenseManager::instance()->getExpiryDate(),
									'data_key' => '',
								),
							),
						),
						array(
							'title'   => __( 'Database Information', 'mhm-rentiva' ),
							'content' => array(
								array(
									'type'     => 'key-value',
									'label'    => __( 'MySQL Version:', 'mhm-rentiva' ),
									'value'    => __( 'Unknown', 'mhm-rentiva' ),
									'data_key' => 'database.version',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Charset:', 'mhm-rentiva' ),
									'value'    => __( 'Unknown', 'mhm-rentiva' ),
									'data_key' => 'database.charset',
								),
								array(
									'type'     => 'key-value',
									'label'    => __( 'Collate:', 'mhm-rentiva' ),
									'value'    => __( 'Unknown', 'mhm-rentiva' ),
									'data_key' => 'database.collate',
								),
							),
						),
					),
				),
				array(
					'type'          => 'custom',
					'custom_render' => array( self::class, 'render_error_notice' ),
				),
			),
		);
	}

	/**
	 * Helper to get or set and return formatted date option
	 */
	private static function get_formatted_date_option( string $key ): string {
		$date = get_option( $key );
		if ( empty( $date ) ) {
			$date = current_time( 'mysql' );
			update_option( $key, $date );
		}
		return date_i18n( get_option( 'date_format' ), strtotime( $date ) );
	}

	/**
	 * Error notice render
	 */
	public static function render_error_notice( array $section, array $data = array() ): void {
		$system_info = self::get_system_info();
		if ( isset( $system_info['error'] ) ) {
			self::render_notice( $system_info['error'], 'error' );
		}
	}
}
