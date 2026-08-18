<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Security\VerifiedRequest;
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
		add_action( 'wp_ajax_mhmrentiva_preview_email_ajax', array( self::class, 'handle_preview_email' ) );
		add_action( 'wp_ajax_mhmrentiva_send_test_email_ajax', array( self::class, 'handle_send_test_email' ) );
	}

	/**
	 * Handle email preview
	 */
	public static function handle_preview_email(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'mhmrentiva_email_preview_action', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'mhm-rentiva' ) );
		}

		$booking_id   = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$template_key = isset( $_POST['template_key'] ) ? sanitize_text_field( wp_unslash( $_POST['template_key'] ) ) : '';
		$new_status   = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';

		// If a booking ID is provided, verify it exists
		if ( $booking_id > 0 && get_post_type( $booking_id ) !== 'mhmrentiva_booking' ) {
			wp_send_json_error( __( 'Booking not found.', 'mhm-rentiva' ) );
		}

		/*
		 * Only the rendering can throw, so only the rendering is wrapped: a
		 * catch around wp_send_json_* swallows the wp_die() terminator and
		 * writes a second, contradictory document after the first.
		 */
		$subject   = '';
		$full_html = '';
		$error     = null;

		try {
			// Use EmailTemplates::build_context which now supports mock data if booking_id=0
			$ctx = EmailTemplates::build_context( $template_key, $booking_id );

			// Standard compile
			$subject = Templates::compile_subject( $template_key, $ctx );

			// render_body ALREADY wraps with layout when needed (see Templates.php line 182-184)
			// Do NOT call wrapWithLayout again - it causes double-wrap and CSS leak!
			$full_html = Templates::render_body( $template_key, $ctx );
		} catch ( \Throwable $e ) {
			$error = 'Connection error: ' . $e->getMessage();
		}

		if ( null !== $error ) {
			wp_send_json_error( $error );
		}

		wp_send_json_success(
			array(
				'subject' => $subject,
				'html'    => $full_html,
			)
		);
	}

	/**
	 * The test-send submission, or null when it carries neither of the two
	 * nonces that authorise it.
	 *
	 * The template test screen and the general connection test mint different
	 * actions against the same `nonce` field; each gets its own early return
	 * rather than being combined into one compound condition at the call site.
	 * The verified payload travels back with the verdict so the nonce check and
	 * the superglobal access stay in one scope.
	 */
	private static function verified_test_email_request(): ?VerifiedRequest {
		if ( false !== check_ajax_referer( 'mhmrentiva_send_template_test', 'nonce', false ) ) {
			return VerifiedRequest::from( $_POST );
		}

		if ( false !== check_ajax_referer( 'mhmrentiva_send_test_email', 'nonce', false ) ) {
			return VerifiedRequest::from( $_POST );
		}

		return null;
	}

	/**
	 * Handle sending test email
	 */
	public static function handle_send_test_email(): void {
		// Verify nonce (either the specific template test or the general connection
		// test). Fails closed when the nonce is missing entirely.
		$request = self::verified_test_email_request();
		if ( null === $request ) {
			wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'mhm-rentiva' ) );
		}

		$template_key = $request->text( 'template_key', 'booking_created_admin' );
		$to           = sanitize_email( (string) ( $request->raw( 'to' ) ?? '' ) );
		$booking_id   = $request->int( 'booking_id' );

		// Fallback for 'to' if empty (General Connection Test)
		if ( empty( $to ) ) {
			$to = \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_test_mode()
				? \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_test_address()
				: get_option( 'admin_email' );
		}

		if ( empty( $template_key ) || empty( $to ) ) {
			wp_send_json_error( __( 'Missing parameters.', 'mhm-rentiva' ) );
		}

		// Same shape as the preview handler above: the try wraps only the work
		// that can throw, and the answer is written once, outside it.
		$sent  = false;
		$error = null;

		try {
			// Build context
			$ctx = EmailTemplates::build_context( $template_key, $booking_id );

			// Send
			$sent = Mailer::send( $template_key, $to, $ctx );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		if ( null !== $error ) {
			wp_send_json_error( $error );
		}

		if ( $sent ) {
			wp_send_json_success( __( 'Test email sent successfully!', 'mhm-rentiva' ) );
		}

		wp_send_json_error( __( 'Failed to send test email. Check server logs.', 'mhm-rentiva' ) );
	}
}
