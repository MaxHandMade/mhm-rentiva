<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Actions;

if (!defined('ABSPATH')) {
    exit;
}






use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Payment\Refunds\Service as RefundService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



final class Actions {


	public static function register(): void {
		// admin_post_mhmrentiva_purge_logs -> purge_logs() was removed:
		// zero shipped nonce producer and zero consumer anywhere.
		//
		// refund_booking() and the refund branch of notices() are now in the
		// same position and are kept deliberately, not by oversight. a5c35a61
		// deleted BookingRefundMetaBox's nested <form> -- invalid HTML that
		// broke the booking edit screen's own Update button -- and with it the
		// only wp_create_nonce( 'mhmrentiva_refund_booking' ) in the tree. With
		// zero producers no request can pass the check_admin_referer() on the
		// first line of refund_booking(), so the handler and the notices it
		// feeds are unreachable rather than unprotected. Whether to delete them
		// or give them a new producer is a product decision this slice did not
		// take; notice_url()/notices()/the NOTICE_NONCE_* constants below stay
		// with them.
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_action( 'admin_post_mhmrentiva_refund_booking', array( self::class, 'refund_booking' ) );
	}

	public static function refund_booking(): void {
		// Nonce first, so every read below happens in an already-verified scope.
		check_admin_referer( 'mhmrentiva_refund_booking' );

		$bid = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;

		// ✅ SECURITY: Granular permission control
		$booking = get_post( $bid );
		if ( ! $booking || 'mhmrentiva_booking' !== $booking->post_type ) {
			wp_die( esc_html__( 'Invalid booking.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'edit_post', $bid ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		$amount = isset( $_POST['amount_kurus'] ) ? absint( wp_unslash( $_POST['amount_kurus'] ) ) : 0;
		$reason = isset( $_POST['reason'] ) && ! is_array( $_POST['reason'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['reason'] ) )
			: '';
		$res    = RefundService::process( $bid, $amount, $reason );
		wp_safe_redirect( self::notice_url( $res, get_edit_post_link( $bid, '' ) ?: admin_url( 'edit.php?post_type=mhmrentiva_booking' ) ) );
		exit;
	}

	// purge_logs() was removed with its admin_post_mhmrentiva_purge_logs
	// registration above.

	/**
	 * Nonce action for the one-shot result params this class puts on its own
	 * post-action redirects.
	 */
	private const NOTICE_NONCE_ACTION = 'mhmrentiva_action_notice';

	/**
	 * Query arg carrying the notice nonce.
	 */
	private const NOTICE_NONCE_ARG = 'mhmrentiva_notice_nonce';

	/**
	 * Sign a redirect that carries result params for notices().
	 *
	 * The params below are not navigation: they are a one-shot report from an
	 * action this class just performed. Signing them means notices() can verify
	 * the nonce in the same scope it reads them -- so the reads are provably
	 * authorised on the line, no annotation needed -- and a crafted link cannot
	 * make someone's admin display "Refund processed."
	 *
	 * @param array<string,string> $args Result params.
	 * @param string               $url  Redirect target.
	 */
	private static function notice_url( array $args, string $url ): string {
		$args[ self::NOTICE_NONCE_ARG ] = wp_create_nonce( self::NOTICE_NONCE_ACTION );
		return add_query_arg( $args, $url );
	}

	public static function notices(): void {
		if ( ! is_admin() ) {
			return;
		}

		$nonce = isset( $_GET[ self::NOTICE_NONCE_ARG ] ) && ! is_array( $_GET[ self::NOTICE_NONCE_ARG ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ self::NOTICE_NONCE_ARG ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::NOTICE_NONCE_ACTION ) ) {
			return;
		}

		// Refund result
		$refund = isset( $_GET['mhmrentiva_refund'] ) && ! is_array( $_GET['mhmrentiva_refund'] )
			? sanitize_text_field( wp_unslash( (string) $_GET['mhmrentiva_refund'] ) )
			: '';
		if ( '' !== $refund ) {
			$ok   = $refund === '1';
			$msg  = isset( $_GET['mhmrentiva_refund_msg'] ) && ! is_array( $_GET['mhmrentiva_refund_msg'] )
				? sanitize_text_field( wp_unslash( (string) $_GET['mhmrentiva_refund_msg'] ) )
				: '';
			$type = $ok ? 'success' : 'error';
			$base = $ok ? esc_html__( 'Refund processed.', 'mhm-rentiva' ) : esc_html__( 'Refund failed.', 'mhm-rentiva' );
			$full = $msg ? $base . ' ' . $msg : $base;
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $full ) . '</p></div>';
		}

		$purged = isset( $_GET['mhmrentiva_purged'] ) && ! is_array( $_GET['mhmrentiva_purged'] )
			? sanitize_text_field( wp_unslash( (string) $_GET['mhmrentiva_purged'] ) )
			: '';
		if ( '1' !== $purged ) {
			return;
		}
		$count = isset( $_GET['mhmrentiva_purge_count'] ) ? absint( wp_unslash( $_GET['mhmrentiva_purge_count'] ) ) : 0;
		/* translators: %d: number of deleted records */
		echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d old records deleted.', 'mhm-rentiva' ), (int) $count ) . '</p></div>';
	}



	/**
	 * ✅ SECURITY: Audit log for permission checks
	 *
	 * @param string   $action Action type
	 * @param bool     $granted Permission granted?
	 * @param int|null $resource_id Resource ID
	 */
	private static function logPermissionCheck( string $action, bool $granted, ?int $resource_id = null ): void {
		if ( class_exists( AdvancedLogger::class ) ) {
			AdvancedLogger::info(
				__( 'Permission check', 'mhm-rentiva' ),
				array(
					'action'      => $action,
					'granted'     => $granted,
					'resource_id' => $resource_id,
					'user_id'     => get_current_user_id(),
					'user_caps'   => wp_get_current_user()->allcaps,
					'ip_address'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '',
					'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				),
				AdvancedLogger::CATEGORY_SECURITY
			);
		}
	}

	/**
	 * ✅ SECURITY: Role-based access control
	 *
	 * @param string   $capability Required capability
	 * @param int|null $resource_id Resource ID
	 * @return bool Access granted?
	 */
	private static function checkRoleBasedAccess( string $capability, ?int $resource_id = null ): bool {
		$user = wp_get_current_user();

		// Super Admin - full access
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Editor - most access except sensitive operations
		if ( current_user_can( 'edit_posts' ) ) {
			$restricted_caps = array( 'delete_booking', 'manage_settings' );
			return ! in_array( $capability, $restricted_caps, true );
		}

		// Author - limited access
		if ( current_user_can( 'edit_published_posts' ) ) {
			$allowed_caps = array( 'view_booking', 'view_customers' );
			return in_array( $capability, $allowed_caps, true );
		}

		// Subscriber - very limited access (own bookings only)
		if ( current_user_can( 'read' ) ) {
			if ( $resource_id && in_array( $capability, array( 'view_booking', 'view_customers' ), true ) ) {
				$booking_user_id = (int) get_post_meta( $resource_id, '_mhmrentiva_user_id', true );
				return $booking_user_id === $user->ID;
			}
		}

		return false;
	}
}
