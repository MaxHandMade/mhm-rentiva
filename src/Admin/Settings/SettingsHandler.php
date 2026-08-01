<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\REST\Settings\RESTSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Handler Class
 *
 * Handles settings form submissions and action processing.
 * Separates logic from the view.
 *
 * @since 4.0.0
 */
final class SettingsHandler {


	/**
	 * Handle settings page actions
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// There is deliberately no routing read here. This used to copy $_POST and
		// $_GET wholesale to decide which branch to enter, which meant the request
		// was inspected in a scope that verifies nothing. Each handler below now
		// starts from its own trigger field and its own nonce and returns without
		// writing when either is absent, so every superglobal access in this class
		// sits in the same function as the verification that authorises it.
		self::handle_email_templates();
		self::handle_rest_settings();
		self::handle_reset_defaults();
	}

	/**
	 * Handle Reset Defaults Action
	 */
	private static function handle_reset_defaults(): void {
		// Trigger field first: without it this is an ordinary settings page load,
		// not a reset request, and nothing below applies.
		if ( 'true' !== sanitize_key( wp_unslash( $_GET['reset_defaults'] ?? '' ) ) ) {
			return;
		}

		$target_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );

		// 🔍 LOGGING: Start reset attempt
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::debug( 'Reset defaults attempt for tab: ' . ( '' !== $target_tab ? $target_tab : 'all' ) );
		}

		$nonce = sanitize_key( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhm_rentiva_reset_defaults' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::debug( 'Reset defaults FAILED at nonce verification phase. Nonce: ' . ( '' !== $nonce ? $nonce : 'missing' ) );
			}
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'mhm-rentiva' ) );
		}

		$view = sanitize_text_field( wp_unslash( $_GET['view'] ?? '' ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::debug( 'Executing reset for tab: ' . $target_tab );
		}

		// Execute reset via service (SRP compliant)
		$success = \MHMRentiva\Admin\Settings\Services\SettingsService::reset_defaults( $target_tab );

		// Smart Redirect
		$redirect_url = admin_url( 'admin.php?page=mhm-rentiva-settings' );
		if ( ! empty( $target_tab ) ) {
			$redirect_url = add_query_arg( 'tab', $target_tab, $redirect_url );
		}

		if ( '' !== $view ) {
			$redirect_url = add_query_arg( 'view', $view, $redirect_url );
		}

		$redirect_url = add_query_arg(
			array(
				'settings-updated' => 'true',
				'reset'            => $success ? 'success' : 'failed',
			),
			$redirect_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle Email Templates Save Action
	 */
	private static function handle_email_templates(): void {
		if ( 'save' !== sanitize_key( wp_unslash( $_POST['email_templates_action'] ?? '' ) ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, \MHMRentiva\Admin\Settings\Core\SettingsCore::GROUP . '-options' ) ) {
			return;
		}

		EmailTemplates::handle_save_templates();
		add_settings_error(
			'mhm_rentiva_messages',
			'email_templates_saved',
			__( 'Email templates saved successfully!', 'mhm-rentiva' ),
			'success'
		);
	}

	/**
	 * Handle REST Settings Save Action
	 */
	private static function handle_rest_settings(): void {
		if ( 'mhm_rentiva_rest_settings' !== sanitize_key( wp_unslash( $_POST['option_page'] ?? '' ) ) ) {
			return;
		}
		if ( 'update' !== sanitize_key( wp_unslash( $_POST['action'] ?? '' ) ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhm_rentiva_rest_settings-options' ) ) {
			return;
		}

		$request = VerifiedRequest::from( $_POST );
		if ( ! $request->has( 'mhm_rentiva_rest_settings' ) ) {
			return;
		}

		if ( \MHMRentiva\Admin\Settings\Services\SettingsService::save_rest_settings( $request->arr( 'mhm_rentiva_rest_settings' ) ) ) {
			add_settings_error(
				'mhm_rentiva_messages',
				'rest_settings_saved',
				__( 'REST API Settings saved successfully!', 'mhm-rentiva' ),
				'success'
			);
		}
	}
}
