<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce/capability checks are enforced in each dispatched action handler.
		$post = wp_unslash( $_POST );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query flags are validated in handler-specific branches.
		$get = wp_unslash( $_GET );

		// Modern Dispatcher using PHP 8.2 match
		match ( true ) {
			isset( $post['email_templates_action'] ) && $post['email_templates_action'] === 'save' => self::handle_email_templates(),
			isset( $post['option_page'] ) && $post['option_page'] === 'mhm_rentiva_rest_settings' => self::handle_rest_settings(),
			isset( $get['reset_defaults'] ) && $get['reset_defaults'] === 'true' => self::handle_reset_defaults(),
			default => null,
		};
	}

	/**
	 * Handle Reset Defaults Action
	 */
	private static function handle_reset_defaults(): void {
		$get = wp_unslash( $_GET );

		// 🔍 LOGGING: Start reset attempt
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::debug( 'Reset defaults attempt for tab: ' . ( $get['tab'] ?? 'all' ) );
		}

		if (
			! isset( $get['reset_defaults'] ) ||
			$get['reset_defaults'] !== 'true' ||
			! isset( $get['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_key( $get['_wpnonce'] ), 'mhm_rentiva_reset_defaults' )
		) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::debug( 'Reset defaults FAILED at nonce verification phase. Nonce: ' . ( $get['_wpnonce'] ?? 'missing' ) );
			}
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'mhm-rentiva' ) );
		}

		$target_tab = sanitize_key( $get['tab'] ?? '' );
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

		if ( isset( $get['view'] ) ) {
			$redirect_url = add_query_arg( 'view', sanitize_text_field( $get['view'] ), $redirect_url );
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
		$post = wp_unslash( $_POST );

		if (
			isset( $post['email_templates_action'] ) &&
			sanitize_key( $post['email_templates_action'] ) === 'save' &&
			isset( $post['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_text_field( $post['_wpnonce'] ), \MHMRentiva\Admin\Settings\Core\SettingsCore::GROUP . '-options' )
		) {
			EmailTemplates::handle_save_templates();
			add_settings_error(
				'mhm_rentiva_messages',
				'email_templates_saved',
				__( 'Email templates saved successfully!', 'mhm-rentiva' ),
				'success'
			);
		}
	}

	/**
	 * Handle REST Settings Save Action
	 */
	private static function handle_rest_settings(): void {
		$post = wp_unslash( $_POST );

		if (
			isset( $post['option_page'] ) &&
			$post['option_page'] === 'mhm_rentiva_rest_settings' &&
			isset( $post['action'] ) &&
			$post['action'] === 'update' &&
			isset( $post['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_text_field( $post['_wpnonce'] ), 'mhm_rentiva_rest_settings-options' )
		) {
			if ( isset( $post['mhm_rentiva_rest_settings'] ) && is_array( $post['mhm_rentiva_rest_settings'] ) ) {
				$success = \MHMRentiva\Admin\Settings\Services\SettingsService::save_rest_settings( $post['mhm_rentiva_rest_settings'] );

				if ( $success ) {
					add_settings_error(
						'mhm_rentiva_messages',
						'rest_settings_saved',
						__( 'REST API Settings saved successfully!', 'mhm-rentiva' ),
						'success'
					);
				}
			}
		}
	}
}
