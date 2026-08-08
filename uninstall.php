<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- This is a procedural script, not a class or a template: WordPress loads it directly (see the WP_UNINSTALL_PLUGIN guard below), so its local variables ($retired_indexes_path, $settings, $clean_on_uninstall) are file-scope by construction, not global state meant for another file to read. There is no hook or template naming in this file for the sniff's "legacy naming kept" rationale to describe.
/**
 * Fired when the plugin is uninstalled.
 *
 * This file is automatically executed by WordPress when the plugin is deleted
 * from the Plugins page (Plugins > Installed Plugins > Delete).
 *
 * @package MHM_Rentiva
 * @since   1.0.0
 */

declare(strict_types=1);

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * WordPress includes this file with the plugin NOT loaded, so the PSR-4
 * autoloader registered in mhm-rentiva.php does not exist here. Requiring
 * Uninstaller.php alone is not enough: anything it reaches for -- today
 * DatabaseCleaner, tomorrow whatever else -- has to resolve, or deletion fatals
 * partway through and every later step (table drops, cron clearing, taxonomy
 * cleanup) silently never runs, leaving behind exactly the data the site owner
 * asked to be removed. Registering the namespace here fixes the class of bug
 * rather than one instance of it.
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( strpos( $class_name, 'MHMRentiva\\' ) !== 0 ) {
			return;
		}

		$relative = str_replace( array( 'MHMRentiva\\', '\\' ), array( '', '/' ), $class_name ) . '.php';
		$path     = __DIR__ . '/src/' . $relative;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Retire the core-table index surface unconditionally.
 *
 * These are structural indexes on WordPress's OWN tables
 * (wp_posts/wp_postmeta/wp_usermeta), not this plugin's data -- so unlike
 * everything gated behind $clean_on_uninstall below, they are removed
 * regardless of that opt-in. Required directly rather than left to the
 * spl_autoload_register() above: RetiredIndexes.php has zero dependency on
 * the rest of the plugin by design (see its class docblock), so this
 * cleanup cannot fail here for a reason unrelated to the DROP INDEX itself.
 *
 * Guarded with file_exists(), same as the autoloader block above and for
 * the same reason: a missing file must not fatal the entire uninstall
 * before the settings read, the table drops, the cron clearing, or the
 * taxonomy cleanup below ever get a turn. Leaving one set of retired
 * indexes behind is strictly better than leaving all of the user's data
 * behind.
 */
$retired_indexes_path  = __DIR__ . '/src/Admin/Core/Utilities/RetiredIndexes.php';
$retired_index_cleanup = array(
	'dropped' => array(),
	'skipped' => array(),
	'failed'  => array(),
);
if ( file_exists( $retired_indexes_path ) ) {
	require_once $retired_indexes_path;
	global $wpdb;
	$retired_index_cleanup = \MHMRentiva\Admin\Core\Utilities\RetiredIndexes::drop( $wpdb );
}

// Check if user wants to clean data on uninstall
$settings = get_option( 'mhmrentiva_settings', array() );
if ( ! is_array( $settings ) ) {
	$settings = array();
}
$clean_on_uninstall = isset( $settings['mhmrentiva_clean_data_on_uninstall'] ) && $settings['mhmrentiva_clean_data_on_uninstall'] === '1';

// If user hasn't enabled this option, exit without cleaning
if ( ! $clean_on_uninstall ) {
	return;
}

// Load Uninstaller class if available
if ( file_exists( __DIR__ . '/src/Admin/Utilities/Uninstall/Uninstaller.php' ) ) {
	require_once __DIR__ . '/src/Admin/Utilities/Uninstall/Uninstaller.php';

	// Perform uninstall (delete backups as well)
	if ( class_exists( 'MHMRentiva\Admin\Utilities\Uninstall\Uninstaller' ) ) {
		// Skip permission check in uninstall context
		// Directly call uninstall logic
		\MHMRentiva\Admin\Utilities\Uninstall\Uninstaller::uninstall_direct( true, $retired_index_cleanup );
	}
}
