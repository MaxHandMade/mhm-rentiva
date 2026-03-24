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
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Licensing\LicenseManager;



/**
 * General Information Tab
 */
final class GeneralTab extends AbstractTab
{




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
		$stats_cache_key = 'mhm_rentiva_about_stats';
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
					"SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN (
SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
WHERE meta_key = %s AND meta_value != ''
)",
					'_booking_customer_id'
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

		// Get license information.
		$license_manager   = LicenseManager::instance();
		$license_data      = $license_manager->get();
		$is_pro            = Mode::isPro();
		$has_license_key   = ! empty($license_data['key']);
		$has_activation_id = ! empty($license_data['activation_id']);
		$is_pro_active     = $is_pro && $has_license_key && $has_activation_id;

		// Build license information content.
		$license_info   = array();
		$license_info[] = array(
			'type'     => 'key-value',
			'label'    => __('Name:', 'mhm-rentiva'),
			'value'    => __('MHM Rentiva', 'mhm-rentiva'),
			'data_key' => '',
		);
		$license_info[] = array(
			'type'     => 'key-value',
			'label'    => __('Version:', 'mhm-rentiva'),
			'value'    => 'v' . MHM_RENTIVA_VERSION,
			'data_key' => '',
		);
		$license_info[] = array(
			'type'     => 'key-value',
			'label'    => __('Developer:', 'mhm-rentiva'),
			'value'    => __('MHM (MaxHandMade)', 'mhm-rentiva'),
			'data_key' => '',
		);
		$license_info[] = array(
			'type'     => 'key-value',
			'label'    => __('License:', 'mhm-rentiva'),
			'value'    => $is_pro ? __('Pro', 'mhm-rentiva') : __('Lite', 'mhm-rentiva'),
			'data_key' => '',
		);

		// Add license dates if Pro is active.
		if ($is_pro_active) {
			// Activation date (use last_check_at as activation date if available, otherwise use current time).
			$activation_timestamp = ! empty($license_data['last_check_at']) ? (int) $license_data['last_check_at'] : time();
			$activation_date      = date_i18n(get_option('date_format'), $activation_timestamp);
			$license_info[]       = array(
				'type'     => 'key-value',
				'label'    => __('Activated On:', 'mhm-rentiva'),
				'value'    => $activation_date,
				'data_key' => '',
			);

			// Expiry date.
			if (isset($license_data['expires_at']) && ! empty($license_data['expires_at'])) {
				$expires_timestamp = is_numeric($license_data['expires_at']) ? (int) $license_data['expires_at'] : strtotime($license_data['expires_at']);
				$expires_date      = date_i18n(get_option('date_format'), $expires_timestamp);
				$is_expired        = $expires_timestamp < time();

				// Calculate days remaining.
				$current_time   = time();
				$days_remaining = $is_expired ? 0 : (int) floor(($expires_timestamp - $current_time) / DAY_IN_SECONDS);

				$expires_value = $expires_date;
				if (! $is_expired) {
					if (0 === $days_remaining) {
						$expires_value .= ' (' . __('Expires today', 'mhm-rentiva') . ')';
					} elseif (1 === $days_remaining) {
						$expires_value .= ' (' . __('1 day remaining', 'mhm-rentiva') . ')';
					} else {
						$expires_value .= ' (' . sprintf(
							/* translators: %d: number of days. */
							__('%d days remaining', 'mhm-rentiva'),
							$days_remaining
						) . ')';
					}
				} else {
					$expires_value .= ' (' . __('Expired', 'mhm-rentiva') . ')';
				}

				$license_info[] = array(
					'type'     => 'key-value',
					'label'    => __('Expires On:', 'mhm-rentiva'),
					'value'    => $expires_value,
					'data_key' => '',
				);
			}
		}

		$license_info[] = array(
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
							'content' => $license_info,
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
									'value'    => __('WP 6.6, PHP 8.2', 'mhm-rentiva'),
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
								array(
									'type'     => 'key-value',
									'label'    => __('Active License:', 'mhm-rentiva'),
									'value'    => Mode::isPro() ? __('Pro Active', 'mhm-rentiva') : __('Lite Version', 'mhm-rentiva'),
									'data_key' => '',
								),
							),
						),
					),
				),

			),
		);
	}
}
