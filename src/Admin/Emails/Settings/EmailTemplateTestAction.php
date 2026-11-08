<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Settings;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\Utilities\SiteLocaleString;
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

		// The whole composition -- the booking values and the status names as
		// well as the render -- in the SITE's language, like the AJAX test send
		// and the preview: the operator's profile language is not the
		// customer's. A test of the template the operator picked: sent even if
		// its "Enabled" box is unticked (Mailer::send_test()).
		$ok = SiteLocaleString::in_site_locale(
			static function () use ( $template_key, $to, $booking_id, $new_status ): bool {
				return Mailer::send_test( $template_key, $to, self::test_context( $template_key, $booking_id, $new_status ) );
			}
		);
		self::redirect( $ok ? 'success' : 'failed' );
	}

	/**
	 * Context for a test send of $template_key, built from $booking_id when the
	 * operator picked one. Runs inside the site-locale switch.
	 */
	private static function test_context( string $template_key, int $booking_id, string $new_status ): array {
		$context = array();
		if ( $booking_id > 0 ) {
			$context = self::buildBookingContext( $booking_id );
			if ( $template_key === 'booking_status_changed_customer' || $template_key === 'booking_status_changed_admin' ) {
				$old = (string) ( $context['booking']['status'] ?? 'pending' );
				$new = $new_status !== '' ? $new_status : 'confirmed';

				$context['status_change'] = array(
					'old_status'       => $old,
					'new_status'       => $new,
					'old_status_label' => Status::get_label( $old ),
					'new_status_label' => Status::get_label( $new ),
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

		return $context;
	}

	/**
	 * The context the real e-mail is built from, so a test send prints what
	 * the customer reads (site date and time format, salutation); this used to
	 * be a copy with raw dates and no salutation (Fable #178 D3).
	 */
	private static function buildBookingContext( int $booking_id ): array {
		return Mailer::getBookingContext( $booking_id ) ?? array();
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
