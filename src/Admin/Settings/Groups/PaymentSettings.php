<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

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

	public const SECTION_GENERAL = 'mhmrentiva_general_payment_section';

	/**
	 * Get default settings for payment
	 *
	 * Payment processing itself is delegated to WooCommerce. What this tab owns
	 * is the site-wide rental deposit: the rate a booking pays up front when the
	 * vehicle has no deposit rate of its own (DepositCalculator::resolve_rate()).
	 * The defaults keep the behaviour of an empty vehicle field from before this
	 * setting existed: a 10% deposit.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {
		return array(
			'mhmrentiva_rental_deposit_type' => 'percentage',
			'mhmrentiva_rental_deposit_rate' => 10,
		);
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

		SettingsHelper::select_field(
			$page_slug,
			'mhmrentiva_rental_deposit_type',
			__( 'Rental Payment Type', 'mhm-rentiva' ),
			array(
				'full_payment' => __( 'Full Payment Required', 'mhm-rentiva' ),
				'percentage'   => __( 'Deposit (Percentage)', 'mhm-rentiva' ),
			),
			__( 'How customers pay for rentals. A vehicle with its own deposit rate uses that rate instead of this setting.', 'mhm-rentiva' ),
			self::SECTION_GENERAL
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_rental_deposit_rate',
			__( 'Deposit Rate (%)', 'mhm-rentiva' ),
			1,
			100,
			__( 'Percentage of the rental total charged as a deposit when the vehicle has no deposit rate of its own.', 'mhm-rentiva' ),
			self::SECTION_GENERAL
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
