<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * General Information Tab.
 *
 * @package MHMRentiva\Admin\About\Tabs
 */





use MHMRentiva\Admin\Core\Tabs\AbstractTab;



/**
 * General Information Tab
 */
final class GeneralTab extends AbstractTab {





	/**
	 * Get the tab ID.
	 *
	 * @return string Tab ID.
	 */
	protected static function get_tab_id(): string
	{
		return 'general';
	}

	/**
	 * Get the tab title.
	 *
	 * @return string Tab title.
	 */
	protected static function get_tab_title(): string
	{
		return __('General Information', 'mhm-rentiva');
	}

	/**
	 * Get the tab description.
	 *
	 * @return string Tab description.
	 */
	protected static function get_tab_description(): string
	{
		return __('General information and statistics about the plugin', 'mhm-rentiva');
	}

	/**
	 * Get the tab content.
	 *
	 * @param array $data Data for the tab.
	 * @return array Tab content configuration.
	 */
	protected static function get_tab_content(array $data = array()): array
	{
		// If no data is passed, get the system information.
		if (empty($data)) {
			$data = self::get_system_info();
		}

		// Try to get statistics from cache.
		$stats_cache_key = 'mhmrentiva_about_stats';
		$stats           = get_transient($stats_cache_key);

		if (false === $stats) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Value is cached in transient immediately below.
			$vehicle_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'vehicle',
					'publish'
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Value is cached in transient immediately below.
			$booking_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'vehicle_booking',
					'publish'
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Value is cached in transient immediately below.
			$customer_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta}
WHERE meta_key = %s AND meta_value != '' AND meta_value != '0'",
					'_mhmrentiva_customer_user_id'
				)
			);

			$stats = array(
				'vehicle'  => $vehicle_count,
				'booking'  => $booking_count,
				'customer' => $customer_count,
			);

			set_transient($stats_cache_key, $stats, 12 * HOUR_IN_SECONDS);
		}

		$vehicle_count  = $stats['vehicle'];
		$booking_count  = $stats['booking'];
		$customer_count = $stats['customer'];

		// Build plugin information content.
		$plugin_info   = array();
		$plugin_info[] = array(
			'type'     => 'key-value',
			'label'    => __('Name:', 'mhm-rentiva'),
			'value'    => __('MHM Rentiva', 'mhm-rentiva'),
			'data_key' => '',
		);
		$plugin_info[] = array(
			'type'     => 'key-value',
			'label'    => __('Version:', 'mhm-rentiva'),
			'value'    => 'v' . MHMRENTIVA_VERSION,
			'data_key' => '',
		);
		$plugin_info[] = array(
			'type'     => 'key-value',
			'label'    => __('Developer:', 'mhm-rentiva'),
			'value'    => __('MHM (MaxHandMade)', 'mhm-rentiva'),
			'data_key' => '',
		);
		$plugin_info[] = array(
			'type'     => 'key-value',
			'label'    => __('File Size:', 'mhm-rentiva'),
			'value'    => __('Calculating...', 'mhm-rentiva'),
			'data_key' => 'plugin.file_size',
		);

		return array(
			'title'       => self::get_tab_title(),
			'description' => self::get_tab_description(),
			'sections'    => array(
				array(
					'type'  => 'card',
					'cards' => array(
						array(
							'title'   => __('Plugin Information', 'mhm-rentiva'),
							'content' => $plugin_info,
						),
						array(
							'title'   => __('Compatibility', 'mhm-rentiva'),
							'content' => array(
								array(
									'type'     => 'key-value',
									'label'    => __('WordPress:', 'mhm-rentiva'),
									'value'    => get_bloginfo('version'),
									'data_key' => '',
									'suffix'   => '+',
								),
								array(
									'type'     => 'key-value',
									'label'    => __('PHP:', 'mhm-rentiva'),
									'value'    => PHP_VERSION,
									'data_key' => '',
									'suffix'   => '+',
								),
								array(
									'type'     => 'key-value',
									'label'    => __('MySQL:', 'mhm-rentiva'),
									'value'    => __('5.6+', 'mhm-rentiva'),
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __('Tested:', 'mhm-rentiva'),
									'value'    => __('WP 7.0, PHP 8.2', 'mhm-rentiva'),
									'data_key' => '',
								),
							),
						),
						array(
							'title'   => __('Statistics', 'mhm-rentiva'),
							'content' => array(
								array(
									'type'     => 'key-value',
									'label'    => __('Total Vehicles:', 'mhm-rentiva'),
									'value'    => (string) $vehicle_count,
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __('Total Bookings:', 'mhm-rentiva'),
									'value'    => (string) $booking_count,
									'data_key' => '',
								),
								array(
									'type'     => 'key-value',
									'label'    => __('Total Customers:', 'mhm-rentiva'),
									'value'    => (string) $customer_count,
									'data_key' => '',
								),
							),
						),
					),
				),

			),
		);
	}

	/**
	 * Returns structured general tab data for the REST endpoint.
	 */
	public static function get_data(): array
	{
		$stats_cache_key = 'mhmrentiva_about_stats';
		$stats           = get_transient( $stats_cache_key );

		if ( false === $stats ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Value is cached in transient immediately below.
			$vehicle_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'vehicle',
					'publish'
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Value is cached in transient immediately below.
			$booking_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'vehicle_booking',
					'publish'
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Value is cached in transient immediately below.
			$customer_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta}
WHERE meta_key = %s AND meta_value != '' AND meta_value != '0'",
					'_mhmrentiva_customer_user_id'
				)
			);

			$stats = array(
				'vehicle'  => $vehicle_count,
				'booking'  => $booking_count,
				'customer' => $customer_count,
			);
			set_transient( $stats_cache_key, $stats, 12 * HOUR_IN_SECONDS );
		}

		$plugin_info = array(
			array(
				'label' => __( 'Name', 'mhm-rentiva' ),
				'value' => __( 'MHM Rentiva', 'mhm-rentiva' ),
			),
			array(
				'label' => __( 'Version', 'mhm-rentiva' ),
				'value' => 'v' . MHMRENTIVA_VERSION,
			),
			array(
				'label' => __( 'Developer', 'mhm-rentiva' ),
				'value' => __( 'MHM (MaxHandMade)', 'mhm-rentiva' ),
			),
		);

		return array(
			'plugin_info'   => $plugin_info,
			'compatibility' => array(
				array(
					'label'  => __( 'WordPress', 'mhm-rentiva' ),
					'value'  => get_bloginfo( 'version' ),
					'suffix' => '+',
				),
				array(
					'label'  => __( 'PHP', 'mhm-rentiva' ),
					'value'  => PHP_VERSION,
					'suffix' => '+',
				),
				array(
					'label' => __( 'MySQL', 'mhm-rentiva' ),
					'value' => '5.6+',
				),
				array(
					'label' => __( 'Tested', 'mhm-rentiva' ),
					'value' => __( 'WP 7.0, PHP 8.2', 'mhm-rentiva' ),
				),
			),
			'stats'         => array(
				array(
					'label' => __( 'Total Vehicles', 'mhm-rentiva' ),
					'value' => (string) $stats['vehicle'],
				),
				array(
					'label' => __( 'Total Bookings', 'mhm-rentiva' ),
					'value' => (string) $stats['booking'],
				),
				array(
					'label' => __( 'Total Customers', 'mhm-rentiva' ),
					'value' => (string) $stats['customer'],
				),
			),
		);
	}
}
