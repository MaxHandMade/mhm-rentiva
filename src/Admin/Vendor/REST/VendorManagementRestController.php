<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Vendor\PostType\VendorApplication;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use MHMRentiva\Admin\Vendor\VendorOnboardingController;

/**
 * REST endpoints for the Vendor Management admin SPA (Faz A).
 *
 * Routes:
 *   GET  /mhm-rentiva/v1/vendors/applications              → paginated pending list
 *   GET  /mhm-rentiva/v1/vendors/applications/(?P<id>\d+)  → application detail (IBAN masked)
 *   POST /mhm-rentiva/v1/vendors/applications/(?P<id>\d+)/approve
 *   POST /mhm-rentiva/v1/vendors/applications/(?P<id>\d+)/reject
 *   GET  /mhm-rentiva/v1/vendors/iban-requests             → pending IBAN change list
 *   POST /mhm-rentiva/v1/vendors/iban-requests/(?P<vendor_id>\d+)/approve
 *   POST /mhm-rentiva/v1/vendors/iban-requests/(?P<vendor_id>\d+)/reject
 *
 * Faz B routes (vendors list, commission, settings) are not included here.
 */
final class VendorManagementRestController {

	private const REST_NAMESPACE = 'mhm-rentiva/v1';
	private const APPS_BASE      = '/vendors/applications';
	private const IBAN_BASE      = '/vendors/iban-requests';

	public static function register(): void {
		if ( ! Mode::canUseVendorMarketplace() ) {
			return;
		}
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		$ns   = self::REST_NAMESPACE;
		$perm = array( self::class, 'check_permission' );

		// Applications list.
		register_rest_route( $ns, self::APPS_BASE, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_applications' ),
			'permission_callback' => $perm,
			'args'                => array(
				'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
				'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			),
		) );

		// Application detail.
		register_rest_route( $ns, self::APPS_BASE . '/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_application' ),
			'permission_callback' => $perm,
			'args'                => array(
				'id' => array( 'type' => 'integer', 'minimum' => 1, 'required' => true ),
			),
		) );

		// Approve application.
		register_rest_route( $ns, self::APPS_BASE . '/(?P<id>\d+)/approve', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'approve_application' ),
			'permission_callback' => $perm,
			'args'                => array(
				'id' => array( 'type' => 'integer', 'minimum' => 1, 'required' => true ),
			),
		) );

		// Reject application.
		register_rest_route( $ns, self::APPS_BASE . '/(?P<id>\d+)/reject', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'reject_application' ),
			'permission_callback' => $perm,
			'args'                => array(
				'id'     => array( 'type' => 'integer', 'minimum' => 1, 'required' => true ),
				'reason' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
			),
		) );

		// IBAN requests list.
		register_rest_route( $ns, self::IBAN_BASE, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_iban_requests' ),
			'permission_callback' => $perm,
		) );

		// Approve IBAN.
		register_rest_route( $ns, self::IBAN_BASE . '/(?P<vendor_id>\d+)/approve', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'approve_iban' ),
			'permission_callback' => $perm,
			'args'                => array(
				'vendor_id' => array( 'type' => 'integer', 'minimum' => 1, 'required' => true ),
			),
		) );

		// Reject IBAN.
		register_rest_route( $ns, self::IBAN_BASE . '/(?P<vendor_id>\d+)/reject', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'reject_iban' ),
			'permission_callback' => $perm,
			'args'                => array(
				'vendor_id' => array( 'type' => 'integer', 'minimum' => 1, 'required' => true ),
			),
		) );
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ---------------------------------------------------------------
	// Applications
	// ---------------------------------------------------------------

	public static function get_applications( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );

		$total_ids = get_posts( array(
			'post_type'      => VendorApplication::POST_TYPE,
			'post_status'    => VendorApplicationManager::STATUS_PENDING,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		$total = count( $total_ids );

		$posts = get_posts( array(
			'post_type'      => VendorApplication::POST_TYPE,
			'post_status'    => VendorApplicationManager::STATUS_PENDING,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$applications = array();
		foreach ( $posts as $post ) {
			$author           = get_userdata( (int) $post->post_author );
			$applications[] = array(
				'id'              => (int) $post->ID,
				'author_id'       => (int) $post->post_author,
				'applicant_name'  => $author instanceof \WP_User ? $author->display_name : '#' . $post->post_author,
				'applicant_email' => $author instanceof \WP_User ? $author->user_email : '',
				'city'            => (string) get_post_meta( $post->ID, '_vendor_city', true ),
				'applied_date'    => get_the_date( 'Y-m-d H:i:s', $post ),
				'applied_human'   => sprintf(
					/* translators: %s: human-readable time difference */
					__( '%s ago', 'mhm-rentiva' ),
					human_time_diff( (int) get_post_timestamp( $post ), time() )
				),
				'status'          => VendorApplicationManager::STATUS_PENDING,
			);
		}

		$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

		return new \WP_REST_Response( array(
			'applications' => $applications,
			'total'        => $total,
			'pages'        => $pages,
			'current_page' => $page,
		) );
	}

	public static function get_application( \WP_REST_Request $request ): \WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$app = get_post( $id );

		if ( ! $app || $app->post_type !== VendorApplication::POST_TYPE ) {
			return new \WP_REST_Response( array( 'code' => 'application_not_found' ), 404 );
		}

		$author   = get_userdata( (int) $app->post_author );
		$raw_iban = VendorApplicationManager::decrypt_iban(
			(string) get_post_meta( $id, '_vendor_iban', true )
		);
		$iban_masked = strlen( $raw_iban ) > 4
			? substr( $raw_iban, 0, 2 ) . '** **** ' . substr( $raw_iban, -4 )
			: '—';

		$doc_fields = array(
			'id'      => array( 'label' => __( 'ID Document', 'mhm-rentiva' ),       'meta' => '_vendor_doc_id' ),
			'license' => array( 'label' => __( "Driver's License", 'mhm-rentiva' ), 'meta' => '_vendor_doc_license' ),
			'address' => array( 'label' => __( 'Address Document', 'mhm-rentiva' ),  'meta' => '_vendor_doc_address' ),
		);
		$documents = array();
		foreach ( $doc_fields as $key => $field ) {
			$att_id          = (int) get_post_meta( $id, $field['meta'], true );
			$url             = $att_id ? wp_get_attachment_url( $att_id ) : null;
			$documents[ $key ] = array( 'label' => $field['label'], 'url' => $url ?: null );
		}

		return new \WP_REST_Response( array(
			'application' => array(
				'id'              => (int) $app->ID,
				'author_id'       => (int) $app->post_author,
				'applicant_name'  => $author instanceof \WP_User ? $author->display_name : '#' . $app->post_author,
				'applicant_email' => $author instanceof \WP_User ? $author->user_email : '',
				'phone'           => (string) get_post_meta( $id, '_vendor_phone', true ),
				'city'            => (string) get_post_meta( $id, '_vendor_city', true ),
				'bio'             => (string) get_post_meta( $id, '_vendor_profile_bio', true ),
				'account_holder'  => (string) get_post_meta( $id, '_vendor_account_holder', true ),
				'iban_masked'     => $iban_masked,
				'tax_office'      => (string) get_post_meta( $id, '_vendor_tax_office', true ),
				'tax_number'      => (string) get_post_meta( $id, '_vendor_tax_number', true ),
				'documents'       => $documents,
				'applied_date'    => get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $app ),
				'status'          => $app->post_status,
			),
		) );
	}

	public static function approve_application( \WP_REST_Request $request ): \WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$app = get_post( $id );

		if ( ! $app || $app->post_type !== VendorApplication::POST_TYPE ) {
			return new \WP_REST_Response( array( 'code' => 'application_not_found' ), 404 );
		}

		$result = VendorOnboardingController::approve( $id );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), 422 );
		}

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	public static function reject_application( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$reason = (string) $request->get_param( 'reason' );

		if ( '' === trim( $reason ) ) {
			return new \WP_REST_Response( array( 'code' => 'reason_required', 'message' => __( 'Rejection reason is required.', 'mhm-rentiva' ) ), 400 );
		}

		$app = get_post( $id );
		if ( ! $app || $app->post_type !== VendorApplication::POST_TYPE ) {
			return new \WP_REST_Response( array( 'code' => 'application_not_found' ), 404 );
		}

		$result = VendorOnboardingController::reject( $id, $reason );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), 422 );
		}

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	// ---------------------------------------------------------------
	// IBAN Requests
	// ---------------------------------------------------------------

	public static function get_iban_requests(): \WP_REST_Response {
		$vendors = get_users( array(
			'role'       => 'rentiva_vendor',
			'meta_key'   => '_rentiva_iban_change_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'number'     => 200,
		) );

		$requests = array();
		foreach ( $vendors as $vendor ) {
			$raw_current = VendorApplicationManager::decrypt_iban( (string) get_user_meta( $vendor->ID, '_rentiva_vendor_iban', true ) );
			$raw_pending = VendorApplicationManager::decrypt_iban( (string) get_user_meta( $vendor->ID, '_rentiva_pending_iban', true ) );

			$masked_current = strlen( $raw_current ) > 4
				? substr( $raw_current, 0, 2 ) . '******' . substr( $raw_current, -4 )
				: __( 'Not set', 'mhm-rentiva' );

			$masked_pending = strlen( $raw_pending ) > 8
				? substr( $raw_pending, 0, 4 ) . str_repeat( '*', max( 0, strlen( $raw_pending ) - 8 ) ) . substr( $raw_pending, -4 )
				: str_repeat( '*', strlen( $raw_pending ) );

			$requests[] = array(
				'vendor_id'           => (int) $vendor->ID,
				'vendor_name'         => $vendor->display_name,
				'vendor_email'        => $vendor->user_email,
				'current_iban_masked' => $masked_current,
				'pending_iban_masked' => $masked_pending,
			);
		}

		return new \WP_REST_Response( array( 'requests' => $requests, 'total' => count( $requests ) ) );
	}

	public static function approve_iban( \WP_REST_Request $request ): \WP_REST_Response {
		$vendor_id = (int) $request->get_param( 'vendor_id' );
		$pending   = (string) get_user_meta( $vendor_id, '_rentiva_pending_iban', true );

		if ( '' !== $pending ) {
			update_user_meta( $vendor_id, '_rentiva_vendor_iban', $pending );
		}

		delete_user_meta( $vendor_id, '_rentiva_pending_iban' );
		delete_user_meta( $vendor_id, '_rentiva_iban_change_status' );

		\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info(
			sprintf( 'Vendor #%d IBAN change approved by Admin #%d.', $vendor_id, get_current_user_id() ),
			array( 'vendor' => $vendor_id, 'action' => 'iban_change_approved' )
		);

		do_action( 'mhm_rentiva_iban_change_approved', $vendor_id );

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	public static function reject_iban( \WP_REST_Request $request ): \WP_REST_Response {
		$vendor_id = (int) $request->get_param( 'vendor_id' );

		delete_user_meta( $vendor_id, '_rentiva_pending_iban' );
		delete_user_meta( $vendor_id, '_rentiva_iban_change_status' );

		\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info(
			sprintf( 'Vendor #%d IBAN change rejected by Admin #%d.', $vendor_id, get_current_user_id() ),
			array( 'vendor' => $vendor_id, 'action' => 'iban_change_rejected' )
		);

		do_action( 'mhm_rentiva_iban_change_rejected', $vendor_id );

		return new \WP_REST_Response( array( 'success' => true ) );
	}
}
