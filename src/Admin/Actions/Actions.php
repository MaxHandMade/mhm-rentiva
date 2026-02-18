<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.

/**
 * Actions management class.
 *
 * @package MHMRentiva\Admin\Actions
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Actions;

use MHMRentiva\Admin\PostTypes\Maintenance\LogRetention;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Payment\Refunds\Service as RefundService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles various administrative actions.
 */
final class Actions {



	/**
	 * Register action hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_mhm_rentiva_purge_logs', array( self::class, 'purge_logs' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_action( 'admin_post_mhm_rentiva_refund_booking', array( self::class, 'refund_booking' ) );
		add_action( 'wp_ajax_mhm_rentiva_create_my_account_page', array( self::class, 'create_my_account_page' ) );
	}

	/**
	 * Refund a booking.
	 */
	public static function refund_booking(): void {
		$bid = self::post_int( 'booking_id' );

		if ( ! self::check_granular_permission( 'refund_booking', $bid ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		check_admin_referer( 'mhm_rentiva_refund_booking' );
		$amount  = self::post_int( 'amount_kurus' );
		$reason  = self::post_text( 'reason' );
		$res     = RefundService::process( $bid, $amount, $reason );
		$ref_url = get_edit_post_link( $bid, '' );
		if ( ! $ref_url ) {
			$ref_url = admin_url( 'edit.php?post_type=vehicle_booking' );
		}
		$notice_args = array_merge(
			$res,
			array(
				'mhm_notice_nonce' => wp_create_nonce( 'mhm_rentiva_notice' ),
			)
		);
		wp_safe_redirect( add_query_arg( $notice_args, $ref_url ) );
		exit;
	}

	/**
	 * Purge old log records.
	 */
	public static function purge_logs(): void {
		if ( ! self::check_granular_permission( 'purge_logs' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}
		check_admin_referer( 'mhm_rentiva_purge_logs' );

		$days = self::post_int( 'days', (int) get_option( 'mhm_rentiva_log_retention_days', 90 ) );
		if ( $days <= 0 ) {
			$days = 90;
		}
		$limit   = (int) apply_filters( 'mhm_rentiva_log_purge_limit_manual', 1000 );
		$deleted = LogRetention::purge( $days, $limit );

		$ref = wp_get_referer();
		if ( ! $ref ) {
			$ref = admin_url( 'options-general.php' );
		}
		$url = add_query_arg(
			array(
				'mhm_purged'       => '1',
				'mhm_purge_count'  => (int) $deleted,
				'mhm_notice_nonce' => wp_create_nonce( 'mhm_rentiva_notice' ),
			),
			$ref
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Display administrative notices.
	 */
	public static function notices(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! self::has_valid_notice_nonce() ) {
			return;
		}
		// Refund result.
		$refund_status = self::get_text( 'mhm_refund' );
		if ( '' !== $refund_status ) {
			$ok   = '1' === $refund_status;
			$msg  = self::get_text( 'mhm_refund_msg' );
			$type = $ok ? 'success' : 'error';
			$base = $ok ? __( 'Refund processed.', 'mhm-rentiva' ) : __( 'Refund failed.', 'mhm-rentiva' );
			$full = $msg ? $base . ' ' . $msg : $base;
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible">
		<p>' . esc_html( $full ) . '</p>
	</div>';
		}

		if ( '1' !== self::get_text( 'mhm_purged' ) ) {
			return;
		}
		$count = self::get_int( 'mhm_purge_count' );
		echo '<div class="notice notice-success is-dismissible">
			<p>' .
			/* translators: %d: number of old records deleted. */
			sprintf( esc_html__( '%d old records deleted.', 'mhm-rentiva' ), (int) $count ) .
			'</p>
		</div>';
	}

	/**
	 * Verify notice nonce when action query vars are present.
	 */
	private static function has_valid_notice_nonce(): bool {
		$has_notice_params = '' !== self::get_text( 'mhm_refund' ) || '1' === self::get_text( 'mhm_purged' );
		if ( ! $has_notice_params ) {
			return true;
		}

		$nonce = self::get_text( 'mhm_notice_nonce' );
		if ( '' === $nonce ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, 'mhm_rentiva_notice' );
	}

	/**
	 * Granular permission check.
	 *
	 * @param string   $action Action type.
	 * @param int|null $resource_id Resource ID (optional).
	 * @return bool Permission granted?
	 */
	private static function check_granular_permission( string $action, ?int $resource_id = null ): bool {
		$user = wp_get_current_user();

		switch ( $action ) {
			case 'refund_booking':
				// Only admin or booking owner
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}

				if ( $resource_id ) {
					return self::user_owns_booking( $user->ID, $resource_id );
				}

				return false;

			case 'purge_logs':
				// Only super admin
				return current_user_can( 'manage_options' );

			case 'view_booking':
				// Admin, booking owner or authorized personnel
				if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
					return true;
				}

				if ( $resource_id ) {
					return self::user_owns_booking( $user->ID, $resource_id );
				}

				return false;

			case 'edit_booking':
				// Only admin and authorized personnel
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'delete_booking':
				// Only super admin
				return current_user_can( 'manage_options' );

			case 'export_data':
				// Admin and authorized personnel
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'manage_settings':
				// Only super admin
				return current_user_can( 'manage_options' );

			case 'view_reports':
				// Admin and authorized personnel
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'manage_payments':
				// Only admin and authorized personnel
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			case 'view_customers':
				// Admin, authorized personnel and booking owner
				if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
					return true;
				}

				if ( $resource_id ) {
					return self::user_owns_booking( $user->ID, $resource_id );
				}

				return false;

			case 'create_my_account':
				// Admin and authorized personnel
				return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );

			default:
				// Default: manage_options capability required
				return current_user_can( 'manage_options' );
		}
	}

	/**
	 * Audit log for permission checks.
	 *
	 * @param string   $action Action type.
	 * @param bool     $granted Permission granted?
	 * @param int|null $resource_id Resource ID.
	 */
	private static function log_permission_check( string $action, bool $granted, ?int $resource_id = null ): void {
		if ( class_exists( AdvancedLogger::class ) ) {
			AdvancedLogger::info(
				__( 'Permission check', 'mhm-rentiva' ),
				array(
					'action'      => $action,
					'granted'     => $granted,
					'resource_id' => $resource_id,
					'user_id'     => get_current_user_id(),
					'user_caps'   => wp_get_current_user()->allcaps,
					'ip_address'  => self::get_client_ip(),
					'user_agent'  => self::get_user_agent(),
				),
				AdvancedLogger::CATEGORY_SECURITY
			);
		}
	}

	/**
	 * Get client IP address safely
	 *
	 * @return string Client IP address
	 */
	private static function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return 'unknown';
	}

	/**
	 * Get user agent safely
	 *
	 * @return string User agent string
	 */
	private static function get_user_agent(): string {
		return ! empty( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: 'unknown';
	}

	/**
	 * Get booking user ID with caching
	 *
	 * @param int $booking_id Booking ID
	 * @return int User ID
	 */
	private static function get_booking_user_id( int $booking_id ): int {
		static $cache = array();

		if ( ! isset( $cache[ $booking_id ] ) ) {
			$cache[ $booking_id ] = (int) get_post_meta( $booking_id, '_mhm_user_id', true );
		}

		return $cache[ $booking_id ];
	}

	/**
	 * Check if user owns the booking.
	 *
	 * @param int $user_id User ID.
	 * @param int $booking_id Booking ID.
	 * @return bool User owns booking?.
	 */
	private static function user_owns_booking( int $user_id, int $booking_id ): bool {
		return self::get_booking_user_id( $booking_id ) === $user_id;
	}

	/**
	 * Read an integer from POST.
	 *
	 * @param string $key POST key.
	 * @param int    $default Default value.
	 * @return int
	 */
	private static function post_int( string $key, int $fallback = 0 ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by caller actions before using this helper.
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by caller actions before using this helper.
		return absint( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Read sanitized text from POST.
	 *
	 * @param string $key POST key.
	 * @param string $default Default value.
	 * @return string
	 */
	private static function post_text( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by caller actions before using this helper.
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by caller actions before using this helper.
		return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
	}

	/**
	 * Read sanitized text from GET.
	 *
	 * @param string $key GET key.
	 * @param string $default Default value.
	 * @return string
	 */
	private static function get_text( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query parameter.
		if ( ! isset( $_GET[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query parameter.
		return sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
	}

	/**
	 * Read an integer from GET.
	 *
	 * @param string $key GET key.
	 * @param int    $default Default value.
	 * @return int
	 */
	private static function get_int( string $key, int $fallback = 0 ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query parameter.
		if ( ! isset( $_GET[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice query parameter.
		return absint( wp_unslash( $_GET[ $key ] ) );
	}
}