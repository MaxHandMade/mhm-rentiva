<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Provides the Lite customer dashboard navigation.
 */
final class DashboardNavigation {

	/**
	 * @return array<string, array<string, string>>
	 */
	public static function get_items(string $context): array
	{
		$items = 'customer' === $context
			? array(
				'overview'  => array(
					'label' => __('Overview', 'mhm-rentiva'),
					'icon'  => 'dashboard',
				),
				'bookings'  => array(
					'label' => __('My Bookings', 'mhm-rentiva'),
					'icon'  => 'calendar',
				),
				'favorites' => array(
					'label' => __('Favorites', 'mhm-rentiva'),
					'icon'  => 'heart',
				),
			)
			: array();

		/**
		 * Filters Lite dashboard navigation without owning optional features.
		 *
		 * @param array<string, array<string, string>> $items   Navigation items.
		 * @param string                              $context Dashboard context.
		 */
		return apply_filters('mhmrentiva_dashboard_navigation_items', $items, $context);
	}
}
