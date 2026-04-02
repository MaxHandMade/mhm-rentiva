<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Provides dashboard navigation items by dashboard context.
 */
final class DashboardNavigation
{
	/**
	 * Get navigation items by context.
	 *
	 * @param string $context customer|vendor
	 * @return array<string, array<string, string>>
	 */
	public static function get_items(string $context): array
	{
		$map = array(
			'customer' => array(
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
				'messages'  => array(
					'label' => __('Messages', 'mhm-rentiva'),
					'icon'  => 'mail',
				),
			),
			'vendor'   => array(
				'overview' => array(
					'label' => __('Overview', 'mhm-rentiva'),
					'icon'  => 'dashboard',
				),
				'listings' => array(
					'label' => __('Listings', 'mhm-rentiva'),
					'icon'  => 'car',
				),
				'bookings' => array(
					'label' => __('Booking Requests', 'mhm-rentiva'),
					'icon'  => 'calendar',
				),
				'ledger'   => array(
					'label' => __('Ledger & Payouts', 'mhm-rentiva'),
					'icon'  => 'chart',
				),
				'settings' => array(
					'label' => __('Payment Settings', 'mhm-rentiva'),
					'icon'  => 'settings',
				),
				'messages'    => array(
					'label' => __('Messages', 'mhm-rentiva'),
					'icon'  => 'mail',
				),
				'reliability' => array(
					'label' => __('Reliability & Penalties', 'mhm-rentiva'),
					'icon'  => 'shield',
				),
			),
		);

		return $map[$context] ?? array();
	}
}
