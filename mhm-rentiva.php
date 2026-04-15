<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.
/*
 * Plugin Name:       MHM Rentiva
 * Plugin URI:        https://maxhandmade.com/urun/mhm-rentiva/
 * Description:       MHM Rentiva is a powerful and flexible vehicle rental management plugin with secure WooCommerce integration for all frontend bookings.
 * Version:           4.26.5
 * Requires at least: 6.7
 * Tested up to:      6.9
 * Requires PHP:      8.1
 * Author:            MHM Development Team
 * Author URI:        https://maxhandmade.com/
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mhm-rentiva
 * Domain Path:       /languages
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}




/**
 * Safe sanitize text field that handles null values
 */
function mhm_rentiva_sanitize_text_field_safe($value)
{
	// Use central Sanitizer if available (PSR-4 autoloader might not be ready yet in some hooks)
	if (class_exists('MHMRentiva\Admin\Core\Helpers\Sanitizer')) {
		return \MHMRentiva\Admin\Core\Helpers\Sanitizer::text_field_safe($value);
	}

	// Fallback implementation
	if ($value === null) {
		return '';
	}
	if ($value === '') {
		return '';
	}
	if (! is_string($value) && ! is_numeric($value)) {
		return '';
	}
	return sanitize_text_field($value);
}

/**
 * Get the display ID for a booking (WooCommerce order ID if available, otherwise booking post ID).
 * Use this everywhere a booking reference number is shown to users.
 *
 * @param int $booking_id Booking post ID.
 * @return int Display ID (WC order ID preferred).
 */
function mhm_rentiva_get_display_id(int $booking_id): int
{
	$order_id = (int) get_post_meta($booking_id, '_mhm_woocommerce_order_id', true);
	return $order_id ?: $booking_id;
}

// Define Version (Updated via build script)
define('MHM_RENTIVA_VERSION', '4.26.5');

// PHP version check
if (version_compare(PHP_VERSION, '8.1', '<')) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: detected PHP version number. */
				esc_html__('MHM Rentiva plugin requires PHP 8.1 or higher. Your version: %s', 'mhm-rentiva'),
				esc_html(PHP_VERSION)
			);
			echo '</p></div>';
		}
	);
	return;
}

// Version constant
if (! defined('MHM_RENTIVA_DISABLE_CACHE')) {
	define('MHM_RENTIVA_DISABLE_CACHE', false);
}


// Plugin file constant
if (! defined('MHM_RENTIVA_PLUGIN_FILE')) {
	define('MHM_RENTIVA_PLUGIN_FILE', __FILE__);
}

// Plugin URL constant
if (! defined('MHM_RENTIVA_PLUGIN_URL')) {
	define('MHM_RENTIVA_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Plugin PATH constant
if (! defined('MHM_RENTIVA_PLUGIN_PATH')) {
	define('MHM_RENTIVA_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

// Plugin directory constant
if (! defined('MHM_RENTIVA_PLUGIN_DIR')) {
	define('MHM_RENTIVA_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Developer mode now works only with automatic detection (for security)

// Advanced PSR-4 autoloader (MHMRentiva\* -> /src)
spl_autoload_register(
	function ($class_name) {
		if (strpos($class_name, 'MHMRentiva\\') !== 0) {
			return;
		}

		// Ensure AbstractShortcode is loaded first for shortcode classes
		if (
			strpos($class_name, 'MHMRentiva\\Admin\\Frontend\\Shortcodes\\') === 0 &&
			$class_name !== 'MHMRentiva\\Admin\\Frontend\\Shortcodes\\AbstractShortcode' &&
			! class_exists('MHMRentiva\\Admin\\Frontend\\Shortcodes\\AbstractShortcode')
		) {

			$abstract_path = __DIR__ . '/src/Admin/Frontend/Shortcodes/Core/AbstractShortcode.php';
			if (file_exists($abstract_path)) {
				require_once $abstract_path;
			}
		}

		// Convert namespace to file path
		$relative = str_replace(array('MHMRentiva\\', '\\'), array('', '/'), $class_name) . '.php';
		$path     = __DIR__ . '/src/' . $relative;

		// Load file if exists
		if (file_exists($path)) {
			require_once $path;
			return;
		}

		// Log for files not following PSR-4 (only when absolutely necessary)
		// Note: Some old classes may be in different namespaces, this is normal
		// Only logged when there's a real problem (e.g., plugin doesn't work)
	}
);

// Central bootstrap - ALL registrations are done in Plugin.php
// Priority -10: Load BEFORE AJAX requests
add_action(
	'plugins_loaded',
	function () {
		// Check if already bootstrapped
		static $bootstrapped = false;
		if ($bootstrapped) {
			return;
		}

		if (class_exists('MHMRentiva\\Plugin')) {
			try {
				\MHMRentiva\Plugin::bootstrap();
				$bootstrapped = true;
			} catch (Exception $e) {
				add_action(
					'admin_notices',
					function () use ($e) {
						echo '<div class="notice notice-error">
            <p>';
						echo esc_html__('MHM Rentiva plugin error on startup: ', 'mhm-rentiva') . esc_html($e->getMessage());
						echo '</p>
        </div>';
					}
				);
			}
		} else {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error">
            <p>';
					echo esc_html__('MHM Rentiva plugin failed to load. Please reinstall the plugin.', 'mhm-rentiva');
					echo '</p>
        </div>';
				}
			);
		}
	},
	-10
); // Priority -10: Load very early (critical for AJAX)

/**
 * Version drift migration trigger.
 *
 * Fires on every request after bootstrap. Compares the plugin file constant
 * (MHM_RENTIVA_VERSION) against the stored option (mhm_rentiva_plugin_version).
 * If they differ, run_migrations() is invoked so schema changes from an update
 * are applied even when the user installs via manual upload / FTP / wp-cli —
 * paths that never trigger the activation hook.
 *
 * DatabaseMigrator is idempotent (guarded by mhm_rentiva_db_version) so this
 * is safe to call on every version bump with no duplicate work.
 */
add_action(
	'plugins_loaded',
	function () {
		if (! is_admin() && ! wp_doing_cron() && ! (defined('WP_CLI') && WP_CLI)) {
			// Only check on admin / cron / cli to avoid front-end overhead.
			return;
		}

		$stored_version = get_option('mhm_rentiva_plugin_version', '');

		if ($stored_version === MHM_RENTIVA_VERSION) {
			return;
		}

		if (class_exists('MHMRentiva\\Admin\\Core\\Utilities\\DatabaseMigrator')) {
			\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations();
			update_option('mhm_rentiva_plugin_version', MHM_RENTIVA_VERSION);
		}
	},
	20
);

/**
 * Single site activation operations
 */
function mhm_rentiva_single_site_activation()
{
	// Register CPT and taxonomy
	if (class_exists('MHMRentiva\\Admin\\Vehicle\\PostType\\Vehicle')) {
		\MHMRentiva\Admin\Vehicle\PostType\Vehicle::register();
	}
	if (class_exists('MHMRentiva\\Admin\\Vehicle\\Taxonomies\\VehicleCategory')) {
		\MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::register();
	}

	// Register Customer role
	if (class_exists('MHMRentiva\\Plugin')) {
		\MHMRentiva\Plugin::register_customer_role();
	}

	// Refresh permalinks
	flush_rewrite_rules();

	// Create all database tables
	if (class_exists('MHMRentiva\Admin\Core\Utilities\DatabaseMigrator')) {
		// Run migrations to ensure all indexes and tables are up to date
		\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations();

		// Force create specific tables that might not be in migrations yet
		$critical_tables = array(
			'payment_log',
			'sessions',
			'transfer_locations',
			'transfer_routes',
			'ratings',
			'queue',
			'report_queue',
			'message_logs',
			'notification_queue',
		);

		foreach ($critical_tables as $table) {
			\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_table($table);
		}
	}

	// Trigger setup wizard redirect on new installations
	update_option('mhm_rentiva_setup_redirect', '1');

	// Seed plugin version so version drift hook does not fire on fresh install.
	update_option('mhm_rentiva_plugin_version', MHM_RENTIVA_VERSION);
}

// Activation hook - CPT and taxonomy registration + rewrite flush + Multisite support
register_activation_hook(
	__FILE__,
	function () {
		// PHP version check
		if (version_compare(PHP_VERSION, '8.1', '<')) {
			wp_die(esc_html__('MHM Rentiva plugin requires PHP 8.1 or higher.', 'mhm-rentiva'));
		}

		// Check for WooCommerce dependency (production default, filterable for tests).
		$skip_dependency_check = (bool) apply_filters('mhm_rentiva_skip_dependency_check', false);
		if (! $skip_dependency_check && ! class_exists('WooCommerce')) {
			wp_die(
				sprintf(
					/* translators: %s: Plugin name. */
					esc_html__('%s requires WooCommerce to be installed and active.', 'mhm-rentiva'),
					'MHM Rentiva'
				),
				esc_html__('Plugin Dependency Check', 'mhm-rentiva'),
				array('back_link' => true)
			);
		}

		if (is_multisite()) {
			// Network-wide activation
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Activation hooks are triggered by WordPress core without custom nonces in this context.
			if (isset($_GET['networkwide']) && '1' === sanitize_text_field(wp_unslash($_GET['networkwide']))) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Activation hooks are triggered by WordPress core without custom nonces in this context.

				// Fetch blog IDs using get_sites() instead of direct database query
				$blog_ids = wp_cache_get('mhm_rentiva_network_blogs');
				if (false === $blog_ids) {
					$sites    = get_sites(array('public' => 1));
					$blog_ids = array();
					foreach ($sites as $site) {
						$blog_ids[] = $site->blog_id;
					}
					wp_cache_set('mhm_rentiva_network_blogs', $blog_ids, '', 3600);
				}

				if (! empty($blog_ids)) {
					foreach ($blog_ids as $blog_id) {
						switch_to_blog((int) $blog_id);
						mhm_rentiva_single_site_activation();
						restore_current_blog();
					}
				}
				return;
			}
		}

		// Single site activation
		mhm_rentiva_single_site_activation();
	}
);

// Runtime dependency check
add_action(
	'admin_notices',
	function () {
		if (! class_exists('WooCommerce')) {
			echo '<div class="notice notice-error">
            <p>';
			printf(
				/* translators: %s: Plugin name. */
				esc_html__('%s requires WooCommerce to be installed and active. Please install WooCommerce to use this plugin.', 'mhm-rentiva'),
				'<strong>MHM Rentiva</strong>'
			);
			echo '</p>
        </div>';
		}
	}
);

// When new blog is created in Multisite
add_action(
	'wpmu_new_blog',
	function ($blog_id) {
		if (is_plugin_active_for_network('mhm-rentiva/mhm-rentiva.php')) {
			switch_to_blog($blog_id);
			mhm_rentiva_single_site_activation();
			restore_current_blog();
		}
	},
	10,
	1
);

// Register ShortcodeServiceProvider (Singleton)
if (class_exists('MHMRentiva\\Admin\\Core\\ShortcodeServiceProvider')) {
	\MHMRentiva\Admin\Core\ShortcodeServiceProvider::register();
}

// Initialize Blocks
if (class_exists('MHMRentiva\\Blocks\\BlockRegistry')) {
	\MHMRentiva\Blocks\BlockRegistry::init();
}

// Deactivation hook - rewrite flush + license cron cleanup
register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
		// Clean license cron job
		if (class_exists('MHMRentiva\\Admin\\Licensing\\LicenseManager')) {
			\MHMRentiva\Admin\Licensing\LicenseManager::deactivatePluginHook();
		}

		// Clean log maintenance cron job
		if (class_exists('MHMRentiva\\Admin\\Core\\Utilities\\LogMaintenanceScheduler')) {
			\MHMRentiva\Admin\Core\Utilities\LogMaintenanceScheduler::deactivate();
		}
	}
);
