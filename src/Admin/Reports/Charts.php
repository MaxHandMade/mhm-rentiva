<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Reports;

use MHMRentiva\Admin\Core\AssetManager;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Charts class - Manages charts on the Reports page
 */
final class Charts
{

	/**
	 * Enqueues scripts
	 */
	public static function enqueue_scripts(): void
	{
		// ✅ Enqueue Chart.js library from local package (no CDN dependency)
		$chart_js_path    = MHM_RENTIVA_PLUGIN_URL . 'assets/js/vendor/chart.min.js';
		$chart_js_version = file_exists(MHM_RENTIVA_PLUGIN_DIR . 'assets/js/vendor/chart.min.js')
			? filemtime(MHM_RENTIVA_PLUGIN_DIR . 'assets/js/vendor/chart.min.js')
			: MHM_RENTIVA_VERSION;

		wp_enqueue_script(
			'chart-js',
			$chart_js_path,
			array(),
			$chart_js_version,
			true
		);

		// ✅ Enqueue External JavaScript file
		wp_enqueue_script(
			'mhm-reports-charts',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/reports-charts.js',
			array('jquery', 'chart-js'),
			MHM_RENTIVA_VERSION,
			true
		);

		// ✅ Dynamic settings with Localization
		wp_localize_script(
			'mhm-reports-charts',
			'mhmRentivaCharts',
			array(
				'ajax_url'       => admin_url('admin-ajax.php'),
				'nonce'          => wp_create_nonce('mhm_reports_nonce'),
				'locale'         => get_locale(),
				'currencySymbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
				'strings'        => array(
					'daily_revenue'     => __('Daily Revenue', 'mhm-rentiva'),
					'daily_bookings'    => __('Daily Bookings', 'mhm-rentiva'),
					'vip_customers'     => __('VIP Customers', 'mhm-rentiva'),
					'regular_customers' => __('Regular Customers', 'mhm-rentiva'),
					'new_customers'     => __('New Customers', 'mhm-rentiva'),
					'no_data'           => __('No data found', 'mhm-rentiva'),
					'error_loading'     => __('Error loading data', 'mhm-rentiva'),
				),
			)
		);
	}

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
