<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Ajax;

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailAjaxHandler {

	/**
	 * Register AJAX actions
	 */
	public static function register(): void {
		add_action( 'wp_ajax_mhm_rentiva_preview_email_ajax', array( self::class, 'handle_preview_email' ) );
		add_action( 'wp_ajax_mhm_rentiva_send_test_email_ajax', array( self::class, 'handle_send_test_email' ) );
	}

	/**
	 * Handle email preview
	 */
	public static function handle_preview_email(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'mhm_email_preview_action', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'mhm-rentiva' ) );
		}

		try {
			$booking_id   = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
			$template_key = isset( $_POST['template_key'] ) ? sanitize_text_field( wp_unslash( $_POST['template_key'] ) ) : '';
			$new_status   = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';

			// If a booking ID is provided, verify it exists
			if ( $booking_id > 0 && get_post_type( $booking_id ) !== 'vehicle_booking' ) {
				wp_send_json_error( __( 'Booking not found.', 'mhm-rentiva' ) );
			}

			// Determine context builder arguments
			$context_status = $new_status !== '' ? 'booking_status_changed' : 'booking_created';

			// Use EmailTemplates::build_context which now supports mock data if booking_id=0
			$ctx = EmailTemplates::build_context( $template_key, $booking_id );

			// Standard compile
			$subject = Templates::compile_subject( $template_key, $ctx );

			// render_body ALREADY wraps with layout when needed (see Templates.php line 182-184)
			// Do NOT call wrapWithLayout again - it causes double-wrap and CSS leak!
			$full_html = Templates::render_body( $template_key, $ctx );

			wp_send_json_success(
				array(
					'subject' => $subject,
					'html'    => $full_html,
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error( 'Connection error: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle sending test email
	 */
	public static function handle_send_test_email(): void {
		// Verify nonce (Support both specific template test and general connection test)
		if (
			! check_ajax_referer( 'mhm_rentiva_send_template_test', 'nonce', false ) &&
			! check_ajax_referer( 'mhm_rentiva_send_test_email', 'nonce', false )
		) {
			wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'mhm-rentiva' ) );
		}

		$template_key = isset( $_POST['template_key'] ) ? sanitize_text_field( wp_unslash( $_POST['template_key'] ) ) : 'booking_created_admin';
		$to           = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$booking_id   = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		// Fallback for 'to' if empty (General Connection Test)
		if ( empty( $to ) ) {
			$to = \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_test_mode()
				? \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_test_address()
				: get_option( 'admin_email' );
		}

		if ( empty( $template_key ) || empty( $to ) ) {
			wp_send_json_error( __( 'Missing parameters.', 'mhm-rentiva' ) );
		}

		try {
			// Build context
			$ctx = EmailTemplates::build_context( $template_key, $booking_id );

			// Send
			$sent = Mailer::send( $template_key, $to, $ctx );

			if ( $sent ) {
				wp_send_json_success( __( 'Test email sent successfully!', 'mhm-rentiva' ) );
			} else {
				wp_send_json_error( __( 'Failed to send test email. Check server logs.', 'mhm-rentiva' ) );
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
