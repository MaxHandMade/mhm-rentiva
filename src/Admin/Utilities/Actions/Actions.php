<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Actions;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.





use MHMRentiva\Admin\PostTypes\Maintenance\LogRetention;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Payment\Refunds\Service as RefundService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



final class Actions {


	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	public static function register(): void {
		add_action( 'admin_post_mhm_rentiva_purge_logs', array( self::class, 'purge_logs' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_action( 'admin_post_mhm_rentiva_refund_booking', array( self::class, 'refund_booking' ) );
	}

	public static function refund_booking(): void {
		// Nonce first, so every read below happens in an already-verified scope.
		check_admin_referer( 'mhm_rentiva_refund_booking' );

		$bid = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;

		// ✅ SECURITY: Granular permission control
		if ( ! self::checkGranularPermission( 'refund_booking', $bid ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		$amount = isset( $_POST['amount_kurus'] ) ? absint( wp_unslash( $_POST['amount_kurus'] ) ) : 0;
		$reason = isset( $_POST['reason'] ) && ! is_array( $_POST['reason'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['reason'] ) )
			: '';
		$res    = RefundService::process( $bid, $amount, $reason );
		wp_safe_redirect( self::notice_url( $res, get_edit_post_link( $bid, '' ) ?: admin_url( 'edit.php?post_type=vehicle_booking' ) ) );
		exit;
	}

	public static function purge_logs(): void {
		// ✅ SECURITY: Granular permission control
		if ( ! self::checkGranularPermission( 'purge_logs' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}
		check_admin_referer( 'mhm_rentiva_purge_logs' );

		$days = isset( $_POST['days'] )
			? absint( wp_unslash( $_POST['days'] ) )
			: (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_log_retention_days', 30 );
		if ( $days <= 0 ) {
			$days = 30;
		}
		$limit   = (int) apply_filters( 'mhm_rentiva_log_purge_limit_manual', 1000 );
		$deleted = LogRetention::purge( $days, $limit );

		$ref = wp_get_referer();
		if ( ! $ref ) {
			$ref = admin_url( 'options-general.php' );
		}
		$url = self::notice_url(
			array(
				'mhm_purged'      => '1',
				'mhm_purge_count' => (string) (int) $deleted,
			),
			$ref
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Nonce action for the one-shot result params this class puts on its own
	 * post-action redirects.
	 */
	private const NOTICE_NONCE_ACTION = 'mhm_rentiva_action_notice';

	/**
	 * Query arg carrying the notice nonce.
	 */
	private const NOTICE_NONCE_ARG = 'mhm_rentiva_notice_nonce';

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
		$refund = isset( $_GET['mhm_refund'] ) && ! is_array( $_GET['mhm_refund'] )
			? sanitize_text_field( wp_unslash( (string) $_GET['mhm_refund'] ) )
			: '';
		if ( '' !== $refund ) {
			$ok   = $refund === '1';
			$msg  = isset( $_GET['mhm_refund_msg'] ) && ! is_array( $_GET['mhm_refund_msg'] )
				? sanitize_text_field( wp_unslash( (string) $_GET['mhm_refund_msg'] ) )
				: '';
			$type = $ok ? 'success' : 'error';
			$base = $ok ? esc_html__( 'Refund processed.', 'mhm-rentiva' ) : esc_html__( 'Refund failed.', 'mhm-rentiva' );
			$full = $msg ? $base . ' ' . $msg : $base;
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $full ) . '</p></div>';
		}

		$purged = isset( $_GET['mhm_purged'] ) && ! is_array( $_GET['mhm_purged'] )
			? sanitize_text_field( wp_unslash( (string) $_GET['mhm_purged'] ) )
			: '';
		if ( '1' !== $purged ) {
			return;
		}
		$count = isset( $_GET['mhm_purge_count'] ) ? absint( wp_unslash( $_GET['mhm_purge_count'] ) ) : 0;
		/* translators: %d: number of deleted records */
		echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d old records deleted.', 'mhm-rentiva' ), (int) $count ) . '</p></div>';
	}



	/**
	 * ✅ SECURITY: Granular permission control
	 *
	 * @param string   $action Action type
	 * @param int|null $resource_id Resource ID (optional)
	 * @return bool Permission granted?
	 */
	private static function checkGranularPermission( string $action, ?int $resource_id = null ): bool {
		$user = wp_get_current_user();

		switch ( $action ) {
			case 'refund_booking':
				// Only admin or booking owner
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}

				if ( $resource_id ) {
					$booking_user_id = (int) get_post_meta( $resource_id, '_mhm_user_id', true );
					return $booking_user_id === $user->ID;
				}

				return false;

			case 'purge_logs':
				// Only super admin
				return current_user_can( 'manage_options' );

			case 'view_booking':
				// Admin, booking owner or authorized staff
				if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
					return true;
				}

				if ( $resource_id ) {
					$booking_user_id = (int) get_post_meta( $resource_id, '_mhm_user_id', true );
					return $booking_user_id === $user->ID;
				}

				return false;

			case 'edit_booking':
				// Only admin and authorized staff
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'delete_booking':
				// Only super admin
				return current_user_can( 'manage_options' );

			case 'export_data':
				// Admin and authorized staff
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'manage_settings':
				// Only super admin
				return current_user_can( 'manage_options' );

			case 'view_reports':
				// Admin and authorized staff
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'manage_payments':
				// Only admin and authorized staff
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'view_customers':
				// Admin, authorized staff and booking owner
				if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
					return true;
				}

				if ( $resource_id ) {
					$booking_user_id = (int) get_post_meta( $resource_id, '_mhm_user_id', true );
					return $booking_user_id === $user->ID;
				}

				return false;

			case 'create_my_account':
				// Admin and authorized staff
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			default:
				// Default: manage_options permission required
				return current_user_can( 'manage_options' );
		}
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
			$restricted_caps = array( 'delete_booking', 'manage_settings', 'purge_logs' );
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
				$booking_user_id = (int) get_post_meta( $resource_id, '_mhm_user_id', true );
				return $booking_user_id === $user->ID;
			}
		}

		return false;
	}
}
