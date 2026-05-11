<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportRepository;
use MHMRentiva\Admin\VendorReport\Core\VendorReportStatus;

/**
 * REST endpoints for the Vendor Reports admin SPA (Faz 6).
 *
 * Routes:
 *   GET /mhm-rentiva/v1/vendor-reports           → paginated list with filters
 *   GET /mhm-rentiva/v1/vendor-reports/(?P<id>\d+) → single report detail
 *
 * Status mutations remain in VendorReportsAdminPage admin-post handlers (unchanged).
 */
final class VendorReportsController {

	private const REST_NAMESPACE = 'mhm-rentiva/v1';
	private const BASE           = '/vendor-reports';

	public static function register(): void {
		if ( ! Mode::canUseVendorMarketplace() ) {
			return;
		}
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		$ns   = self::REST_NAMESPACE;
		$base = self::BASE;
		$perm = array( self::class, 'check_permission' );

		register_rest_route(
			$ns,
			$base,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_list' ),
				'permission_callback' => $perm,
				'args'                => array(
					'status'       => array(
						'type'              => 'string',
						'default'           => 'open',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $v ) {
							return in_array( $v, array_merge( array( 'all' ), VendorReportStatus::all() ), true );
						},
					),
					'context_type' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $v ) {
							return '' === $v || VendorReportContext::is_valid( $v );
						},
					),
					'per_page'     => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'         => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			$base . '/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_detail' ),
				'permission_callback' => $perm,
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
				),
			)
		);
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function get_list( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$table = $wpdb->prefix . 'mhm_rentiva_vendor_reports';

		$status       = (string) $request->get_param( 'status' );
		$context_type = (string) $request->get_param( 'context_type' );
		$per_page     = (int) $request->get_param( 'per_page' );
		$page         = (int) $request->get_param( 'page' );
		$offset       = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( 'all' !== $status && VendorReportStatus::is_valid( $status ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		if ( '' !== $context_type && VendorReportContext::is_valid( $context_type ) ) {
			$where[]  = 'context_type = %s';
			$params[] = $context_type;
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table is {prefix}+constant suffix; $where_sql composed from %s/%d placeholders bound via prepare; array_merge() satisfies placeholders but PHPCS cannot statically count them.
		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params )
			);
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					array_merge( $params, array( $per_page, $offset ) )
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$rows  = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset )
			);
		}
		// phpcs:enable

		$rows    = is_array( $rows ) ? $rows : array();
		$reports = array();

		foreach ( $rows as $row ) {
			$vendor      = get_userdata( (int) $row->vendor_id );
			$vendor_name = $vendor instanceof \WP_User
				? $vendor->display_name . ' (#' . (int) $row->vendor_id . ')'
				: '#' . (int) $row->vendor_id;
			$status_val  = (string) $row->status;
			$context_val = (string) $row->context_type;
			$created_ts  = strtotime( (string) $row->created_at );

			$reports[] = array(
				'id'            => (int) $row->id,
				'vendor_id'     => (int) $row->vendor_id,
				'vendor_name'   => $vendor_name,
				'context_type'  => $context_val,
				'context_label' => self::context_label( $context_val ),
				'context_id'    => $row->context_id,
				'title'         => (string) $row->title,
				'status'        => $status_val,
				'status_label'  => self::status_label( $status_val ),
				'is_terminal'   => VendorReportStatus::is_terminal( $status_val ),
				'created_at'    => (string) $row->created_at,
				'created_human' => sprintf(
					/* translators: %s: human-readable time difference */
					__( '%s ago', 'mhm-rentiva' ),
					human_time_diff( $created_ts, time() )
				),
			);
		}

		$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

		return new \WP_REST_Response(
			array(
				'reports'      => $reports,
				'total'        => $total,
				'pages'        => $pages,
				'current_page' => $page,
			)
		);
	}

	public static function get_detail( \WP_REST_Request $request ): \WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$row = VendorReportRepository::find( $id );

		if ( null === $row ) {
			return new \WP_REST_Response( array( 'code' => 'report_not_found' ), 404 );
		}

		$vendor       = get_userdata( (int) $row->vendor_id );
		$vendor_name  = $vendor instanceof \WP_User ? $vendor->display_name : '#' . (int) $row->vendor_id;
		$vendor_email = $vendor instanceof \WP_User ? $vendor->user_email : '';
		$status_val   = (string) $row->status;
		$context_val  = (string) $row->context_type;
		$created_ts   = strtotime( (string) $row->created_at );

		return new \WP_REST_Response(
			array(
				'report' => array(
					'id'            => (int) $row->id,
					'vendor_id'     => (int) $row->vendor_id,
					'vendor_name'   => $vendor_name,
					'vendor_email'  => $vendor_email,
					'context_type'  => $context_val,
					'context_label' => self::context_label( $context_val ),
					'context_id'    => $row->context_id,
					'title'         => (string) $row->title,
					'description'   => (string) $row->description,
					'status'        => $status_val,
					'status_label'  => self::status_label( $status_val ),
					'is_terminal'   => VendorReportStatus::is_terminal( $status_val ),
					'admin_note'    => $row->admin_note,
					'created_at'    => (string) $row->created_at,
					'created_human' => sprintf(
						/* translators: %s: human-readable time difference */
						__( '%s ago', 'mhm-rentiva' ),
						human_time_diff( $created_ts, time() )
					),
					'updated_at'    => (string) $row->updated_at,
					'resolved_at'   => $row->resolved_at,
				),
			)
		);
	}

	private static function status_label( string $status ): string {
		switch ( $status ) {
			case VendorReportStatus::OPEN:
				return __( 'Open', 'mhm-rentiva' );
			case VendorReportStatus::IN_REVIEW:
				return __( 'In Review', 'mhm-rentiva' );
			case VendorReportStatus::RESOLVED:
				return __( 'Resolved', 'mhm-rentiva' );
			case VendorReportStatus::REJECTED:
				return __( 'Rejected', 'mhm-rentiva' );
			default:
				return ucfirst( $status );
		}
	}

	private static function context_label( string $context ): string {
		switch ( $context ) {
			case VendorReportContext::BOOKING:
				return __( 'Booking', 'mhm-rentiva' );
			case VendorReportContext::VEHICLE:
				return __( 'Vehicle', 'mhm-rentiva' );
			case VendorReportContext::VEHICLE_ACTION:
				return __( 'Vehicle action', 'mhm-rentiva' );
			case VendorReportContext::PENALTY:
				return __( 'Penalty appeal', 'mhm-rentiva' );
			case VendorReportContext::GENERAL:
				return __( 'General', 'mhm-rentiva' );
			default:
				return ucfirst( $context );
		}
	}
}
