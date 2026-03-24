<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Dashboard KPI configuration by context.
 */
final class DashboardConfig
{
	/**
	 * Return KPI configuration per context.
	 *
	 * @param string $context customer|vendor
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_kpis(string $context): array
	{
		$map = array(
			'customer' => array(
				'total_bookings'  => array(
					'label'     => __('Total Bookings', 'mhm-rentiva'),
					'metric'    => 'total_bookings',
					'trend'     => true,
					'icon'      => 'calendar',
					'meta'      => __('Last 7 Days vs Previous 7 Days', 'mhm-rentiva'),
					'trend_meta' => __('Last 7 Days vs Previous 7 Days', 'mhm-rentiva'),
				),
				'upcoming_pickups' => array(
					'label'     => __('Upcoming Pickups', 'mhm-rentiva'),
					'metric'    => 'upcoming_pickups',
					'trend'     => false,
					'icon'      => 'briefcase',
					'meta'      => __('Next 7 Days vs Previous 7 Days', 'mhm-rentiva'),
				),
				'unread_messages'  => array(
					'label'     => __('Unread Messages', 'mhm-rentiva'),
					'metric'    => 'unread_messages',
					'trend'     => false,
					'icon'      => 'mail',
					'meta'      => __('Last 7 Days vs Previous 7 Days', 'mhm-rentiva'),
				),
				'saved_favorites'  => array(
					'label'     => __('Saved Favorites', 'mhm-rentiva'),
					'metric'    => 'saved_favorites',
					'trend'     => false,
					'icon'      => 'heart',
					'meta'      => __('Total Saved', 'mhm-rentiva'),
				),
			),
			'vendor'   => array(
				'active_listings' => array(
					'label'     => __('Active Listings', 'mhm-rentiva'),
					'metric'    => 'active_listings',
					'trend'     => false,
					'icon'      => 'car',
					'meta'      => __('Current Inventory', 'mhm-rentiva'),
				),
				'revenue_7d' => array(
					'label'     => __('Revenue (7 Days)', 'mhm-rentiva'),
					'metric'    => 'revenue_7d',
					'trend'     => true,
					'icon'      => 'chart',
					'meta'      => __('Rolling Window', 'mhm-rentiva'),
					'trend_meta' => __('Last 7 Days vs Previous 7 Days', 'mhm-rentiva'),
				),
				'pending_requests' => array(
					'label'     => __('Pending Requests', 'mhm-rentiva'),
					'metric'    => 'pending_requests',
					'trend'     => false,
					'icon'      => 'calendar',
					'meta'      => __('Pending Approval', 'mhm-rentiva'),
				),
				'upcoming_rentals' => array(
					'label'     => __('Upcoming Rentals', 'mhm-rentiva'),
					'metric'    => 'upcoming_rentals',
					'trend'     => false,
					'icon'      => 'briefcase',
					'meta'      => __('Next 7 Days', 'mhm-rentiva'),
				),
				'available_balance' => array(
					'label'     => __('Available Balance', 'mhm-rentiva'),
					'metric'    => 'available_balance',
					'trend'     => false,
					'icon'      => 'wallet',
					'meta'      => __('Cleared & Ready for Payout', 'mhm-rentiva'),
				),
				'pending_balance' => array(
					'label'     => __('Pending Balance', 'mhm-rentiva'),
					'metric'    => 'pending_balance',
					'trend'     => false,
					'icon'      => 'clock',
					'meta'      => __('Awaiting Completion', 'mhm-rentiva'),
				),
				'total_paid_out' => array(
					'label'     => __('Total Paid Out', 'mhm-rentiva'),
					'metric'    => 'total_paid_out',
					'trend'     => false,
					'icon'      => 'check-circle',
					'meta'      => __('Lifetime Withdrawals', 'mhm-rentiva'),
				),
				'occupancy_rate' => array(
					'label'     => __('Occupancy %', 'mhm-rentiva'),
					'metric'    => 'occupancy_rate',
					'trend'     => false,
					'icon'      => 'calendar',
					'meta'      => __('Avg Window Occupancy', 'mhm-rentiva'),
				),
				'cancellation_rate' => array(
					'label'     => __('Cancellation Rate', 'mhm-rentiva'),
					'metric'    => 'cancellation_rate',
					'trend'     => false,
					'icon'      => 'clock',
					'meta'      => __('Cancelled Bookings %', 'mhm-rentiva'),
				),
			),
		);

		$kpis = $map[$context] ?? array();

		/**
		 * Filters the dashboard KPIs for a specific context.
		 *
		 * @param array<string, array<string, mixed>> $kpis Default KPI configuration mapping.
		 */
		return apply_filters("mhm_rentiva_dashboard_kpi_{$context}", $kpis);
	}
}
