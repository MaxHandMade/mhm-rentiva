<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment Settings
 *
 * Handles payment configuration and WooCommerce integration status.
 *
 * @since 4.0.0
 */
final class PaymentSettings {

	public const SECTION_GENERAL = 'mhm_rentiva_general_payment_section';

	/**
	 * Get default settings for payment
	 *
	 * Note: This class serves as an informational page for WooCommerce integration status.
	 * All payment processing is delegated to WooCommerce (for frontend bookings).
	 * Payment-related settings (email notifications, rate limiting) are managed in:
	 * - EmailSettings.php (email confirmations)
	 * - MaintenanceSettings.php (rate limiting, security)
	 *
	 * @return array Empty array - no settings managed directly by this class.
	 */
	public static function get_default_settings(): array {
		return array();
	}

	/**
	 * Render the payment settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			// General Payment Information / WooCommerce Status
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_GENERAL );
		}
	}

	/**
	 * Register payment settings
	 *
	 * Registers the payment configuration section to WordPress Settings API.
	 * This method should be called during the 'admin_init' hook.
	 *
	 * Note: Typically invoked by the centralized SettingsManager class.
	 * Do not call this method directly unless you understand the plugin architecture.
	 *
	 * @hooked admin_init (via SettingsManager)
	 * @return void
	 */
	public static function register(): void {
		// FIX: Use centralized Page Slug constant
		$page_slug = SettingsCore::PAGE;

		add_settings_section(
			self::SECTION_GENERAL,
			__( 'Payment Configuration', 'mhm-rentiva' ),
			array( self::class, 'render_payment_section_description' ),
			$page_slug
		);
	}

	/**
	 * Render payment section description
	 */
	public static function render_payment_section_description(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-info inline">';
			echo '<p><strong>' . esc_html__( 'WooCommerce Integration Active', 'mhm-rentiva' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'All payment processing is currently handled securely by WooCommerce.', 'mhm-rentiva' ) . '</p>';

			$woo_settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
			echo '<p><a href="' . esc_url( $woo_settings_url ) . '" class="button button-primary">' . esc_html__( 'Manage Payment Gateways in WooCommerce', 'mhm-rentiva' ) . '</a></p>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-warning inline">';
			echo '<p><strong>' . esc_html__( 'WooCommerce Required', 'mhm-rentiva' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Please install and activate WooCommerce to accept payments.', 'mhm-rentiva' ) . '</p>';

			if ( current_user_can( 'install_plugins' ) ) {
				$install_url = admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
				echo '<p><a href="' . esc_url( $install_url ) . '" class="button button-primary">' . esc_html__( 'Install WooCommerce', 'mhm-rentiva' ) . '</a></p>';
			}
			echo '</div>';
		}
	}
}
