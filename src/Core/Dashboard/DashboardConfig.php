<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Lite customer dashboard KPI configuration.
 */
final class DashboardConfig {

	/**
	 * @param string $context Dashboard context supplied by the neutral extension contract.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_kpis(string $context): array
	{
		$kpis = 'customer' === $context
			? array(
				'total_bookings'   => array(
					'label'      => __('Total Bookings', 'mhm-rentiva'),
					'metric'     => 'total_bookings',
					'trend'      => true,
					'icon'       => 'calendar',
					'meta'       => __('Last 7 Days vs Previous 7 Days', 'mhm-rentiva'),
					'trend_meta' => __('Last 7 Days vs Previous 7 Days', 'mhm-rentiva'),
				),
				'upcoming_pickups' => array(
					'label'  => __('Upcoming Pickups', 'mhm-rentiva'),
					'metric' => 'upcoming_pickups',
					'trend'  => false,
					'icon'   => 'briefcase',
					'meta'   => __('Next 7 Days vs Previous 7 Days', 'mhm-rentiva'),
				),
				'saved_favorites'  => array(
					'label'  => __('Saved Favorites', 'mhm-rentiva'),
					'metric' => 'saved_favorites',
					'trend'  => false,
					'icon'   => 'heart',
					'meta'   => __('Total Saved', 'mhm-rentiva'),
				),
			)
			: array();

		/**
		 * Filters customer dashboard KPI definitions.
		 *
		 * @param array<string, array<string, mixed>> $kpis KPI definitions.
		 * @param string                              $context Dashboard context.
		 */
		return apply_filters('mhmrentiva_dashboard_kpis', $kpis, $context);
	}
}
