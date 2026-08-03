<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Settings;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailTemplateTestAction {


	public static function register(): void {
		add_action( 'admin_post_mhmrentiva_send_template_test', array( self::class, 'handle' ) );
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mhm-rentiva' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_send_template_test' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'mhm-rentiva' ), 403 );
		}

		$template_key = isset( $_POST['template_key'] ) ? sanitize_text_field( wp_unslash( $_POST['template_key'] ) ) : '';
		$to           = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$booking_id   = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
		$new_status   = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';

		if ( $to === '' ) {
			// Default to test address if test mode, otherwise admin email
			$to = EmailSettings::is_test_mode() ? EmailSettings::get_test_address() : get_option( 'admin_email' );
		}

		if ( $template_key === '' || ! is_email( $to ) ) {
			self::redirect( 'failed' );
		}

		// Build context
		$context = array();
		if ( $booking_id > 0 ) {
			$context = self::buildBookingContext( $booking_id );
			if ( $template_key === 'booking_status_changed_customer' || $template_key === 'booking_status_changed_admin' ) {
				$context['status_change'] = array(
					'old_status'       => $context['booking']['status'] ?? 'pending',
					'new_status'       => $new_status !== '' ? $new_status : 'confirmed',
					'old_status_label' => $context['booking']['status'] ?? 'pending',
					'new_status_label' => $new_status !== '' ? $new_status : 'confirmed',
				);
			}
		}

		// Ensure site context exists
		if ( ! isset( $context['site'] ) ) {
			$context['site'] = array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url( '/' ),
				'admin_email' => get_option( 'admin_email' ),
			);
		}

		$ok = Mailer::send( $template_key, $to, $context );
		self::redirect( $ok ? 'success' : 'failed' );
	}

	private static function buildBookingContext( int $booking_id ): array {
		$post = get_post( $booking_id );
		if ( ! $post || $post->post_type !== 'mhmrentiva_booking' ) {
			return array();
		}

		if ( ! class_exists( '\\MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper' ) ) {
			return array();
		}
		$customer_info   = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( $booking_id );
		$vehicle_info    = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingVehicleInfo( $booking_id );
		$date_info       = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingDateInfo( $booking_id );
		$payment_status  = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingPaymentStatus( $booking_id );
		$payment_gateway = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingPaymentGateway( $booking_id );
		$total_price     = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingTotalPrice( $booking_id );

		return array(
			'booking'  => array(
				'id'              => $booking_id,
				'title'           => $post->post_title,
				'status'          => $post->post_status,
				'payment_status'  => $payment_status,
				'payment_gateway' => $payment_gateway,
				'total_price'     => $total_price,
				'pickup_date'     => $date_info['pickup_date'] ?? '',
				'return_date'     => $date_info['return_date'] ?? '',
				'rental_days'     => $date_info['rental_days'] ?? 0,
			),
			'customer' => array(
				'name'       => trim( ( $customer_info['first_name'] ?? '' ) . ' ' . ( $customer_info['last_name'] ?? '' ) ),
				'first_name' => $customer_info['first_name'] ?? '',
				'last_name'  => $customer_info['last_name'] ?? '',
				'email'      => $customer_info['email'] ?? '',
				'phone'      => $customer_info['phone'] ?? '',
			),
			'vehicle'  => array(
				'id'             => $vehicle_info['id'] ?? 0,
				'title'          => $vehicle_info['title'] ?? '',
				'price_per_day'  => $vehicle_info['price_per_day'] ?? 0,
				'featured_image' => $vehicle_info['featured_image'] ?? '',
			),
		);
	}

	/**
	 * Transient key holding this user's pending send-test result.
	 */
	private static function status_key(): string {
		return 'mhmrentiva_template_test_' . get_current_user_id();
	}

	/**
	 * Request-level cache, so the three screens that render this notice all see
	 * the same value even though the transient is consumed on first read.
	 *
	 * @var string|null
	 */
	private static $status_cache = null;

	/**
	 * Read and consume this user's pending send-test result.
	 *
	 * Returns '' when there is nothing pending. Replaces a
	 * `?mhmrentiva_template_test=` round trip through the URL, which survived
	 * refreshes and re-showed a stale notice.
	 */
	public static function take_status(): string {
		if ( null !== self::$status_cache ) {
			return self::$status_cache;
		}

		$status = get_transient( self::status_key() );
		if ( false === $status ) {
			self::$status_cache = '';
			return '';
		}

		delete_transient( self::status_key() );
		self::$status_cache = (string) $status;

		return self::$status_cache;
	}

	private static function redirect( string $status ): void {
		set_transient( self::status_key(), $status, MINUTE_IN_SECONDS );

		// `type` is fixed rather than carried over: this handler is reached by a
		// POST to a bare admin-post.php URL (see the detached form built in
		// assets/js/admin/email-templates.js), so the $_GET['type'] this used to
		// read was never populated and always fell through to this same default.
		$url = add_query_arg(
			array(
				'page' => 'mhm-rentiva-settings',
				'tab'  => 'email-templates',
				'type' => 'booking_notifications',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
