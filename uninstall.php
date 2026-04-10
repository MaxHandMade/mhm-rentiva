<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.
/**
 * Fired when the plugin is uninstalled.
 *
 * This file is automatically executed by WordPress when the plugin is deleted
 * from the Plugins page (Plugins > Installed Plugins > Delete).
 *
 * @package MHM_Rentiva
 * @since   1.0.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user wants to clean data on uninstall
$settings           = get_option( 'mhm_rentiva_settings', array() );
if ( ! is_array( $settings ) ) {
	$settings = array();
}
$clean_on_uninstall = isset( $settings['mhm_rentiva_clean_data_on_uninstall'] ) && $settings['mhm_rentiva_clean_data_on_uninstall'] === '1';

// If user hasn't enabled this option, exit without cleaning
if ( ! $clean_on_uninstall ) {
	return;
}

// Load WordPress database functions
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Load Uninstaller class if available
if ( file_exists( __DIR__ . '/src/Admin/Utilities/Uninstall/Uninstaller.php' ) ) {
	require_once __DIR__ . '/src/Admin/Utilities/Uninstall/Uninstaller.php';

	// Perform uninstall (delete backups as well)
	if ( class_exists( 'MHMRentiva\Admin\Utilities\Uninstall\Uninstaller' ) ) {
		// Skip permission check in uninstall context
		// Directly call uninstall logic
		\MHMRentiva\Admin\Utilities\Uninstall\Uninstaller::uninstall_direct( true );
	}
}