<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerManagementSettings {

	// Section Constants
	public const SECTION_COMMUNICATION = 'mhm_rentiva_customer_communication_section';

	/**
	 * Get default settings for customer management
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			'mhm_rentiva_customer_welcome_email'         => '1',
			'mhm_rentiva_customer_booking_notifications' => '1',
		);
	}

	/**
	 * Render the customer settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_COMMUNICATION );
		}
	}

	/**
	 * Register customer management settings
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		// --- SECTIONS ---
		add_settings_section( self::SECTION_COMMUNICATION, __( 'Customer Communication Settings', 'mhm-rentiva' ), fn() => print( '<p>' . esc_html__( 'Configure customer email notification settings.', 'mhm-rentiva' ) . '</p>' ), $page_slug );

		// --- COMMUNICATION FIELDS ---
		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_customer_welcome_email', __( 'Send Welcome Email', 'mhm-rentiva' ), __( 'Send a custom welcome email when a user registers via Rentiva forms (if used).', 'mhm-rentiva' ), self::SECTION_COMMUNICATION );
		SettingsHelper::checkbox_field( $page_slug, 'mhm_rentiva_customer_booking_notifications', __( 'Send Booking Notifications', 'mhm-rentiva' ), __( 'Send notifications to customers regarding their booking status updates.', 'mhm-rentiva' ), self::SECTION_COMMUNICATION );
	}
}
