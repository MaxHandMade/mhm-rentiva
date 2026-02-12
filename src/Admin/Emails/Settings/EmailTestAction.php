<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Settings;

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailTestAction {


	public static function register(): void {
		add_action( 'admin_post_mhm_rentiva_send_test_email', array( self::class, 'handle' ) );
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mhm-rentiva' ), 403 );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mhm_rentiva_send_test_email' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'mhm-rentiva' ), 403 );
		}

		$to = EmailSettings::is_test_mode() ? EmailSettings::get_test_address() : get_option( 'admin_email' );

		$ok = Mailer::send(
			'booking_created_admin',
			$to,
			array(
				'booking' => array(
					'id'    => 0,
					'title' => __( 'Test Email', 'mhm-rentiva' ),
				),
				'site'    => array(
					'name' => get_bloginfo( 'name' ),
					'url'  => home_url( '/' ),
				),
			)
		);

		$redirect = add_query_arg(
			array(
				'page'           => 'mhm-rentiva-settings',
				'tab'            => 'email-settings',
				'mhm_email_test' => $ok ? 'success' : 'failed',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
