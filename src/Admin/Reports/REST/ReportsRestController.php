<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Reports\REST;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Report queries are bounded aggregate queries in admin context.

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Reports\BusinessLogic\BookingReport;
use MHMRentiva\Admin\Reports\BusinessLogic\CustomerReport;
use MHMRentiva\Admin\Reports\BusinessLogic\RevenueReport;
use MHMRentiva\Admin\Vehicle\Reports\VehicleReport;

final class ReportsRestController {
	private const ALLOWED_TABS = array( 'overview', 'revenue', 'bookings', 'vehicles', 'customers' );

	public static function register_routes(): void
	{
		register_rest_route(
			'mhm-rentiva/v1',
			'/reports',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_reports' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'tab'        => array(
						'type'              => 'string',
						'default'           => 'overview',
						'sanitize_callback' => 'sanitize_key',
					),
					'start_date' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_date'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public static function get_reports( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error
	{
		$tab        = (string) ( $request->get_param( 'tab' ) ?? 'overview' );
		$start_date = (string) ( $request->get_param( 'start_date' ) ?? '' );
		$end_date   = (string) ( $request->get_param( 'end_date' ) ?? '' );

		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( empty( $end_date ) ) {
			$end_date = gmdate( 'Y-m-d' );
		}

		if ( ! self::is_valid_date( $start_date ) ) {
			return new \WP_Error( 'invalid_start_date', __( 'Invalid start_date format. Expected YYYY-MM-DD.', 'mhm-rentiva' ), array( 'status' => 400 ) );
		}
		if ( ! self::is_valid_date( $end_date ) ) {
			return new \WP_Error( 'invalid_end_date', __( 'Invalid end_date format. Expected YYYY-MM-DD.', 'mhm-rentiva' ), array( 'status' => 400 ) );
		}
		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			return new \WP_Error( 'invalid_date_range', __( 'start_date cannot be after end_date.', 'mhm-rentiva' ), array( 'status' => 400 ) );
		}

		if ( ! Mode::canUseAdvancedReports() ) {
			$max_days  = Mode::reportsMaxRangeDays();
			$date_diff = ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS;
			if ( $date_diff > $max_days ) {
				return new \WP_Error( 'lite_range_exceeded', __( 'Date range exceeds the Lite plan limit.', 'mhm-rentiva' ), array( 'status' => 403 ) );
			}
		}

		if ( ! in_array( $tab, self::ALLOWED_TABS, true ) ) {
			return new \WP_Error( 'invalid_tab', __( 'Unknown report tab.', 'mhm-rentiva' ), array( 'status' => 400 ) );
		}

		$data = self::fetch_tab_data( $tab, $start_date, $end_date );

		return new \WP_REST_Response(
			array(
				'tab'        => $tab,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'data'       => $data,
			),
			200
		);
	}

	private static function is_valid_date( string $date ): bool
	{
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && (bool) strtotime( $date );
	}

	private static function fetch_tab_data( string $tab, string $start_date, string $end_date ): array
	{
		if ( 'overview' === $tab ) {
			return self::fetch_overview( $start_date, $end_date );
		}

		return match ( $tab ) {
			'revenue'   => RevenueReport::get_data( $start_date, $end_date ),
			'bookings'  => BookingReport::get_data( $start_date, $end_date ),
			'vehicles'  => VehicleReport::get_data( $start_date, $end_date ),
			'customers' => CustomerReport::get_data( $start_date, $end_date ),
			default     => array(),
		};
	}

	private static function fetch_overview( string $start_date, string $end_date ): array
	{
		$cache_key = 'mhm_rentiva_overview_' . md5( $start_date . $end_date );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
		$data = array(
			'revenue'   => RevenueReport::get_data( $start_date, $end_date ),
			'bookings'  => BookingReport::get_data( $start_date, $end_date ),
			'vehicles'  => VehicleReport::get_data( $start_date, $end_date ),
			'customers' => CustomerReport::get_data( $start_date, $end_date ),
		);
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );
		return $data;
	}
}
