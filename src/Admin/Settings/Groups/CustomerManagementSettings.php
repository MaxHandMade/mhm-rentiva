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

final class CustomerManagementSettings {

	/**
	 * Get default settings for customer management
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array();
	}

	/**
	 * Render the customer settings section
	 */
	public static function render_settings_section(): void {
		echo '<p>' . esc_html__( 'Customer management settings are currently configured in the Email Configuration tab.', 'mhm-rentiva' ) . '</p>';
	}

	/**
	 * Register customer management settings
	 */
	public static function register(): void {
		// Fields moved to EmailSettings (Email Configuration tab).
	}
}
