<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.

declare(strict_types=1);

namespace MHMRentiva\Admin\Reports\BusinessLogic;

use MHMRentiva\Admin\Reports\Repository\ReportRepository;
use MHMRentiva\Admin\Utilities\Export\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RevenueReport {


	public static function get_data( string $start_date, string $end_date ): array {
		global $wpdb;

		$cache_key = 'mhm_revenue_report_' . md5( $start_date . $end_date );
		$data      = get_transient( $cache_key );

		if ( $data === false ) {
			// âœ… OPTIMIZED QUERY - Daily revenue data (COMPLETED AND CONFIRMED ONLY)
			$daily_revenue = ReportRepository::get_daily_revenue_data( $start_date, $end_date );

			// âœ… OPTIMIZED QUERY - Payment method distribution
			$payment_methods = ReportRepository::get_payment_method_distribution( $start_date, $end_date );

			// Monthly comparison
			$monthly_comparison = ReportRepository::get_monthly_revenue_comparison( $start_date, $end_date );

			// Calculate total revenue
			$total_revenue = array_sum( array_column( $daily_revenue, 'revenue' ) );

			// Format payment methods
			foreach ( $payment_methods as &$method ) {
				$method->method_label = self::get_payment_method_label( $method->method );
				$method->percentage   = $total_revenue > 0 ? round( ( $method->revenue / $total_revenue ) * 100, 1 ) : 0;
			}

			$data = array(
				'daily'      => $daily_revenue,
				'methods'    => $payment_methods,
				'monthly'    => $monthly_comparison,
				'total'      => $total_revenue,
				'avg_daily'  => count( $daily_revenue ) > 0 ? $total_revenue / count( $daily_revenue ) : 0,
				'date_range' => array(
					'start' => $start_date,
					'end'   => $end_date,
					'days'  => ( strtotime( $end_date ) - strtotime( $start_date ) ) / ( 60 * 60 * 24 ) + 1,
				),
			);

			set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
		}

		return $data;
	}

	public static function get_payment_method_label( string $method ): string {
		// Base payment method labels
		$labels = array(
			'offline'     => __( 'Bank Transfer', 'mhm-rentiva' ),
			'system'      => __( 'System', 'mhm-rentiva' ),
			'my_account'  => __( 'My Account', 'mhm-rentiva' ),
			'woocommerce' => __( 'WooCommerce', 'mhm-rentiva' ),
			'stripe'      => __( 'Stripe', 'mhm-rentiva' ),
			'paypal'      => __( 'PayPal', 'mhm-rentiva' ),
		);

		/**
		 * Filter: Allow addons and payment gateways to add custom payment method labels
		 *
		 * @param array<string, string> $labels Payment method key => Label mapping
		 * @param string                $method Current payment method being labeled
		 * @return array Modified labels array
		 *
		 * @example
		 * add_filter('mhm_rentiva_payment_method_labels', function($labels, $method) {
		 *     $labels['custom_gateway'] = __('Custom Payment Gateway', 'my-plugin');
		 *     return $labels;
		 * }, 10, 2);
		 */
		$labels = apply_filters( 'mhm_rentiva_payment_method_labels', $labels, $method );

		// If label exists, return it; otherwise return formatted method name
		if ( isset( $labels[ $method ] ) ) {
			return $labels[ $method ];
		}

		/**
		 * Filter: Allow custom formatting for unknown payment methods
		 *
		 * @param string $formatted_label Default formatted label (ucfirst)
		 * @param string $method         Payment method key
		 * @return string Modified label
		 */
		return apply_filters( 'mhm_rentiva_payment_method_label_unknown', ucfirst( str_replace( '_', ' ', $method ) ), $method );
	}

	public static function get_revenue_by_period( string $start_date, string $end_date, string $period = 'daily' ): array {
		global $wpdb;

		$data = ReportRepository::get_revenue_by_period( $start_date, $end_date, $period );

		return $data;
	}

	public static function get_revenue_trends( int $days = 30 ): array {
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', (int) strtotime( "-{$days} days" ) );

		$current_period = self::get_revenue_by_period( $start_date, $end_date, 'daily' );

		// Comparison with previous period
		$prev_start      = gmdate( 'Y-m-d', (int) strtotime( "-{$days} days", (int) strtotime( $start_date ) ) );
		$prev_end        = $start_date;
		$previous_period = self::get_revenue_by_period( $prev_start, $prev_end, 'daily' );

		$current_total  = array_sum( array_column( $current_period, 'revenue' ) );
		$previous_total = array_sum( array_column( $previous_period, 'revenue' ) );

		$change_percent = 0;
		if ( $previous_total > 0 ) {
			$change_percent = round( ( ( $current_total - $previous_total ) / $previous_total ) * 100, 1 );
		}

		return array(
			'current_period'  => $current_period,
			'previous_period' => $previous_period,
			'current_total'   => $current_total,
			'previous_total'  => $previous_total,
			'change_percent'  => $change_percent,
			'trend'           => $change_percent > 0 ? 'up' : ( $change_percent < 0 ? 'down' : 'stable' ),
		);
	}

	public static function get_top_revenue_sources( string $start_date, string $end_date, int $limit = 10 ): array {
		global $wpdb;

		// Vehicle based revenue
		$vehicle_revenue = ReportRepository::get_top_revenue_sources( $start_date, $end_date, $limit );

		// Add vehicle titles
		foreach ( $vehicle_revenue as &$vehicle ) {
			$vehicle->vehicle_title = get_the_title( $vehicle->vehicle_id ) ?: __( 'Unknown Vehicle', 'mhm-rentiva' );
		}

		return $vehicle_revenue;
	}

	public static function export_revenue_data( string $start_date, string $end_date, string $format = 'csv' ): void {
		$data = self::get_data( $start_date, $end_date );

		$filename = sprintf( 'mhm-rentiva-revenue-%s-%s', $start_date, $end_date );

		$export_data = array();

		// Add header
		$export_data[] = array(
			__( 'Date', 'mhm-rentiva' ),
			__( 'Revenue', 'mhm-rentiva' ),
			__( 'Booking Count', 'mhm-rentiva' ),
		);

		// Add daily data
		foreach ( $data['daily'] as $day ) {
			$export_data[] = array(
				$day->date,
				number_format( $day->revenue, 2, '.', '' ),
				'1', // Assume 1 record for each day
			);
		}

		Export::export_data( $export_data, $filename, $format );
	}
}