<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About;

use MHMRentiva\Admin\Licensing\Mode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System information collection class
 */
final class SystemInfo {


	/**
	 * Get cached system information
	 */
	public static function get_cached_system_info(): array {
		// ✅ CACHE OPTIMIZATION - Centralized cache management
		$cached = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache( 'system_info' );

		if ( $cached === false ) {
			$cached = self::safe_get_system_info();
			\MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache( 'system_info', '', $cached );
		}

		return $cached;
	}

	/**
	 * Safe system information collection
	 */
	private static function safe_get_system_info(): array {
		try {
			return array(
				'wordpress' => self::get_wordpress_info(),
				'php'       => self::get_php_info(),
				'plugin'    => self::get_plugin_info(),
				'database'  => self::get_database_info(),
			);
		} catch ( \Exception $e ) {
			error_log( 'MHM Rentiva About Page Error: ' . $e->getMessage() );
			return array(
				'error'     => esc_html__( 'Error occurred while getting system information.', 'mhm-rentiva' ),
				'wordpress' => array(),
				'php'       => array(),
				'plugin'    => array(),
				'database'  => array(),
			);
		}
	}

	/**
	 * Get WordPress information
	 */
	private static function get_wordpress_info(): array {
		return array(
			'version'     => get_bloginfo( 'version' ),
			'language'    => get_locale(),
			'timezone'    => wp_timezone_string(),
			'site_url'    => get_site_url(),
			'admin_email' => get_option( 'admin_email' ),
			'multisite'   => is_multisite(),
		);
	}

	/**
	 * Get PHP information
	 */
	private static function get_php_info(): array {
		return array(
			'version'             => PHP_VERSION,
			'memory_limit'        => ini_get( 'memory_limit' ),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size'       => ini_get( 'post_max_size' ),
		);
	}

	/**
	 * Get plugin information
	 */
	private static function get_plugin_info(): array {
		return array(
			'version'        => MHM_RENTIVA_VERSION,
			'file_size'      => self::get_plugin_file_size(),
			'install_date'   => get_option( 'mhm_rentiva_install_date', esc_html__( 'Unknown', 'mhm-rentiva' ) ),
			'last_update'    => get_option( 'mhm_rentiva_last_update', esc_html__( 'Unknown', 'mhm-rentiva' ) ),
			'license_status' => Mode::isPro() ? esc_html__( 'Pro Active', 'mhm-rentiva' ) : esc_html__( 'Lite Version', 'mhm-rentiva' ),
		);
	}

	/**
	 * Get database information
	 */
	private static function get_database_info(): array {
		global $wpdb;

		return array(
			'version' => $wpdb->db_version(),
			'charset' => $wpdb->charset,
			'collate' => $wpdb->collate,
			'tables'  => self::get_plugin_tables(),
		);
	}

	/**
	 * Get plugin file size
	 */
	private static function get_plugin_file_size(): string {
		$plugin_dir = MHM_RENTIVA_PLUGIN_DIR;

		try {
			$size = self::calculate_directory_size( $plugin_dir );
			return size_format( $size, 2 );
		} catch ( \Exception $e ) {
			return esc_html__( 'Could not calculate', 'mhm-rentiva' );
		}
	}

	/**
	 * Calculate directory size
	 */
	private static function calculate_directory_size( string $directory ): int {
		$size = 0;

		if ( ! is_readable( $directory ) ) {
			return $size;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->isReadable() ) {
				$size += $file->getSize();
			}
		}

		return $size;
	}

	/**
	 * Get plugin tables
	 */
	private static function get_plugin_tables(): array {
		global $wpdb;

		$plugin_tables = array(
			'vehicle_booking' => $wpdb->prefix . 'posts', // In posts table as CPT
			'mhm_payment_log' => $wpdb->prefix . 'mhm_payment_log',
			'mhm_message'     => $wpdb->prefix . 'posts', // For messages
		);

		$tables_info = array();

		foreach ( $plugin_tables as $key => $table_name ) {
			try {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						'SHOW TABLES LIKE %s',
						$table_name
					)
				);

				if ( $exists ) {
					if ( 'mhm_payment_log' === $key ) {
						// Table name is from internal map, but still use %i if possible or ensure it's not user input.
						// In older MySQL, %i is not available, so we use esc_sql on a trusted constant.
						$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );
					} else {
						$post_type = 'vehicle_booking' === $key ? 'vehicle_booking' : ( 'mhm_message' === $key ? 'mhm_message' : '' );
						$count     = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT COUNT(*) FROM `{$table_name}` WHERE post_type = %s",
								$post_type
							)
						);
					}

					$size = $wpdb->get_var(
						$wpdb->prepare(
							'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                         FROM information_schema.TABLES
                         WHERE table_schema = DATABASE() AND table_name = %s',
							$table_name
						)
					);

					$tables_info[ $key ] = array(
						'name'   => $table_name,
						'exists' => true,
						'count'  => (int) ( $count ?? 0 ),
						'size'   => $size ? $size . ' MB' : '0 MB',
					);
				} else {
					$tables_info[ $key ] = array(
						'name'   => $table_name,
						'exists' => false,
						'count'  => 0,
						'size'   => '0 MB',
					);
				}
			} catch ( \Exception $e ) {
				$tables_info[ $key ] = array(
					'name'   => $table_name,
					'exists' => false,
					'count'  => 0,
					'size'   => esc_html__( 'Error', 'mhm-rentiva' ),
				);
			}
		}

		return $tables_info;
	}
}
