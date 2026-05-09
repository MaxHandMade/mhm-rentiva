<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Reports;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\AssetManager;



/**
 * Charts class - Manages charts on the Reports page
 */
final class Charts {


	/**
	 * Generic chart renderer to reduce code duplication
	 *
	 * Uses printf to output HTML/JS within PHP context to ensure IDE stability.
	 */
	private static function render_chart(string $chart_type, string $start_date, string $end_date): void
	{
		$chart_id    = $chart_type . '-chart-' . uniqid();
		$init_method = 'init' . ucfirst($chart_type) . 'Chart';

		printf(
			'<canvas id="%s"></canvas>
			<script>
			jQuery(document).ready(function($) {
				if (typeof window.mhmRentivaCharts !== "undefined" && window.mhmRentivaCharts["%s"]) {
					window.mhmRentivaCharts["%s"]("%s", "%s", "%s");
				}
			});
			</script>',
			esc_attr($chart_id),
			esc_js($init_method),
			esc_js($init_method),
			esc_attr($chart_id),
			esc_js($start_date),
			esc_js($end_date)
		);
	}

	public static function render_revenue_chart(string $start_date, string $end_date): void
	{
		self::render_chart('revenue', $start_date, $end_date);
	}

	public static function render_bookings_chart(string $start_date, string $end_date): void
	{
		self::render_chart('bookings', $start_date, $end_date);
	}

	public static function render_vehicles_chart(string $start_date, string $end_date): void
	{
		self::render_chart('vehicles', $start_date, $end_date);
	}

	public static function render_customers_chart(string $start_date, string $end_date): void
	{
		self::render_chart('customers', $start_date, $end_date);
	}

	public static function render_booking_status_chart(string $start_date, string $end_date): void
	{
		self::render_chart('bookings', $start_date, $end_date);
	}
}
