<?php
/*
 * Plugin Name:       MHM Rentiva
 * Plugin URI:        https://wpalemi.com/rentiva/
 * Description:       MHM Rentiva is a powerful and flexible vehicle rental management plugin with secure WooCommerce integration for all frontend bookings.
 * Version:           6.0.6
 * Requires at least: 6.7
 * Tested up to:      7.0
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            MHM Development Team
 * Author URI:        https://wpalemi.com/
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
function mhmrentiva_sanitize_text_field_safe($value)
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
function mhmrentiva_get_display_id(int $booking_id): int
{
	$order_id = (int) get_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', true);
	return $order_id ? $order_id : $booking_id;
}

/**
 * Render a standard admin error notice for bootstrap/dependency failures.
 *
 * @param string $message Notice body text.
 */
function mhmrentiva_render_admin_error_notice(string $message): void
{
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html($message)
	);
}

/**
 * Single uppercase initial letter for a local (non-Gravatar) avatar
 * placeholder. Used by every template that renders a reviewer/customer/user
 * avatar so the trim()+substr() logic can't drift between call sites.
 *
 * @param string $name Full display name to take the initial from.
 * @return string Single uppercase letter, or '' if $name has no content.
 */
function mhmrentiva_initial_avatar_letter(string $name): string
{
	return mb_strtoupper(mb_substr(trim($name), 0, 1, 'UTF-8'), 'UTF-8');
}

// Define Version (Updated via build script)
define('MHMRENTIVA_VERSION', '6.0.6');

// PHP version check
if (version_compare(PHP_VERSION, '8.1', '<')) {
	add_action(
		'admin_notices',
		function () {
			mhmrentiva_render_admin_error_notice(
				sprintf(
					/* translators: %s: detected PHP version number. */
					__('MHM Rentiva plugin requires PHP 8.1 or higher. Your version: %s', 'mhm-rentiva'),
					PHP_VERSION
				)
			);
		}
	);
	return;
}

// mbstring check.
//
// The plugin calls mb_strtoupper/mb_substr/mb_strlen unconditionally in five
// places, one of which is a visitor-facing AJAX path (review submission), and
// the shipped text is Turkish, where byte-wise string handling is wrong rather
// than merely approximate. WordPress lists mbstring as highly recommended but
// does not require it, so "WordPress runs, therefore mbstring exists" is not a
// guarantee the code may rely on -- without this check the failure mode is a
// fatal "Call to undefined function mb_strlen()" in front of a customer.
if (! extension_loaded('mbstring')) {
	add_action(
		'admin_notices',
		function () {
			mhmrentiva_render_admin_error_notice(
				__('MHM Rentiva requires the PHP mbstring extension, which is not enabled on this server. Please ask your host to enable it.', 'mhm-rentiva')
			);
		}
	);
	return;
}

// Version constant
if (! defined('MHMRENTIVA_DISABLE_CACHE')) {
	define('MHMRENTIVA_DISABLE_CACHE', false);
}


// Plugin file constant
if (! defined('MHMRENTIVA_PLUGIN_FILE')) {
	define('MHMRENTIVA_PLUGIN_FILE', __FILE__);
}

// Plugin URL constant
if (! defined('MHMRENTIVA_PLUGIN_URL')) {
	define('MHMRENTIVA_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Plugin PATH constant
if (! defined('MHMRENTIVA_PLUGIN_PATH')) {
	define('MHMRENTIVA_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

// Plugin directory constant
if (! defined('MHMRENTIVA_PLUGIN_DIR')) {
	define('MHMRENTIVA_PLUGIN_DIR', plugin_dir_path(__FILE__));
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
			$class_name !== 'MHMRentiva\\Admin\\Frontend\\Shortcodes\\Core\\AbstractShortcode' &&
			! class_exists('MHMRentiva\\Admin\\Frontend\\Shortcodes\\Core\\AbstractShortcode')
		) {

			$abstract_path = __DIR__ . '/src/Admin/Frontend/Shortcodes/Core/AbstractShortcode.php';
			if (file_exists($abstract_path)) {
				require_once $abstract_path;
			}
		}

		// Convert namespace to file path
		$relative = str_replace( array( 'MHMRentiva\\', '\\' ), array( '', '/' ), $class_name ) . '.php';
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

// Register this plugin's bundled copy of ui-core. The highest version across
// all plugins that bundle it wins at plugins_loaded priority 0.
$mhmrentiva_uicore_register_file = __DIR__ . '/vendor/mhm/ui-core/register.php';

if ( file_exists( $mhmrentiva_uicore_register_file ) ) {
	require_once $mhmrentiva_uicore_register_file;
	mhmuicore_register(
		'0.2.0',
		__DIR__ . '/vendor/mhm/ui-core/bootstrap.php'
	);
}

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
						mhmrentiva_render_admin_error_notice(
							sprintf(
								/* translators: %s: startup exception message. */
								__('MHM Rentiva plugin error on startup: %s', 'mhm-rentiva'),
								$e->getMessage()
							)
						);
					}
				);
			}
		} else {
			add_action(
				'admin_notices',
				function () {
					mhmrentiva_render_admin_error_notice(
						__('MHM Rentiva plugin failed to load. Please reinstall the plugin.', 'mhm-rentiva')
					);
				}
			);
		}
	},
	-10
); // Priority -10: Load very early (critical for AJAX)

/**
 * Migration trigger (two independent lanes).
 *
 * Fires on every admin / cron / cli request after bootstrap. The work is
 * split into two lanes so that a single misstep on one lane cannot block
 * the other.
 *
 *  Lane A — schema migrations driven by the stored version stamp.
 *    Runs `DatabaseMigrator::run_migrations()` only when the plugin file
 *    constant (MHMRENTIVA_VERSION) differs from the stored option
 *    (mhmrentiva_plugin_version, falling back once to its pre-6.0.0 name).
 *    DatabaseMigrator is itself idempotent (guarded by mhmrentiva_db_version),
 *    so re-running on every drift is cheap; the outer check just skips the
 *    call entirely on steady state.
 *
 *  Lane B — one-time data cleanups that carry their own per-migration flag
 *    option. These MUST run outside the version-drift guard. Reason:
 *    `mhmrentiva_single_site_activation()` stamps the version BEFORE the
 *    next `plugins_loaded` fires, so a ZIP-replace upgrade (the most common
 *    deploy path) lands with `stored_version === MHMRENTIVA_VERSION`
 *    already true. The drift check would short-circuit and the new data
 *    cleanup would never run — which is exactly the v4.27.2 →
 *    migrate_clean_test_pollution → never-executed bug reported on
 *    mhmrentiva.com. Each migration checks its own flag option and returns
 *    immediately once done, so the overhead on repeat admin loads is a
 *    single `get_option()` call per migration.
 */
add_action(
	'plugins_loaded',
	function () {
		if (! is_admin() && ! wp_doing_cron() && ! ( defined('WP_CLI') && WP_CLI )) {
			// Only check on admin / cron / cli to avoid front-end overhead.
			return;
		}

		// Lane A — schema drift.
		//
		// The stamp option was itself renamed in 6.0.0, so on the upgrade that
		// carries the rename the new name does not exist yet and the value is
		// still under the old one. Reading only the new name would return '',
		// which happens to differ from MHMRENTIVA_VERSION and so would still
		// fire the migration -- but it would fire it again on the NEXT upgrade
		// too, because the old row would sit there forever shadowing nothing.
		// The legacy row is adopted once, here, at the line that reads it; that
		// is Lane A's own gate, not one of Lane B's flag-guarded cleanups.
		$stored_version = get_option( 'mhmrentiva_plugin_version', '' );
		if ('' === $stored_version) {
			// PrefixMigrationMap::BOOTSTRAP_FALLBACK_ALLOWLIST -- deliberately
			// exempt from the rename sweep, because recognising a pre-6.0.0
			// install is the whole job of this literal.
			$legacy_version = get_option( 'mhm_rentiva_plugin_version', '' );
			if ('' !== $legacy_version) {
				add_option('mhmrentiva_plugin_version', $legacy_version, '', true);
				delete_option('mhm_rentiva_plugin_version');
				$stored_version = $legacy_version;
			}
		}

		if ($stored_version !== MHMRENTIVA_VERSION && class_exists('MHMRentiva\\Admin\\Core\\Utilities\\DatabaseMigrator')) {
			\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations();
			// This stamp tracks the PLUGIN CODE version, not migration
			// completion -- it is written whether run_migrations() finished
			// or returned early to retry a still-failing step later.
			// mhmrentiva_db_version (written by run_migrations() itself,
			// bounded by DatabaseMigrator::INDEX_CLEANUP_MAX_ATTEMPTS) is the
			// only source of truth for "did the schema migration finish",
			// and Plugin.php's unconditional admin_init hook -- not this
			// lane -- is what retries it. The two stamps are deliberately
			// independent so that neither can shadow the other's retry.
			update_option('mhmrentiva_plugin_version', MHMRENTIVA_VERSION);
		}

		// Lane B — one-time data cleanups.
		// Each migration is idempotent via its own flag option; calling them
		// on every admin request after steady state is a cheap no-op.
		//
		// v4.27.1 — legacy installs persisted translated field labels into
		// wp_options, where they outranked live __() calls after a locale
		// switch. Flag: mhmrentiva_v4271_labels_migrated.
		if (class_exists('MHMRentiva\\Admin\\Vehicle\\Meta\\VehicleMeta')) {
			\MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::migrate_remove_auto_populated_labels();
		}

		// v4.27.2 — Settings Testing "Run All Diagnostics" could leak
		// '1' / '0' test payloads into free-text, email, URL and currency
		// fields inside mhmrentiva_settings. Flag:
		// mhmrentiva_v4272_test_pollution_cleaned.
		if (class_exists('MHMRentiva\\Admin\\Settings\\Core\\SettingsCore')) {
			\MHMRentiva\Admin\Settings\Core\SettingsCore::migrate_clean_test_pollution();
		}

		// v4.64.1 — second pass of the cleanup above. Installs where the
		// v4.27.2 flag was already stamped "done" before the pollution
		// actually happened (e.g. the ajax_save_dark_mode() clobber bug,
		// fixed in the same release) never got re-cleaned. Flag:
		// mhmrentiva_v4641_test_pollution_recleaned.
		if (class_exists('MHMRentiva\\Admin\\Settings\\Core\\SettingsCore')) {
			\MHMRentiva\Admin\Settings\Core\SettingsCore::migrate_reclean_test_pollution();
		}
	},
	20
);

/**
 * Single site activation operations.
 *
 * The optional callables are narrow test seams. Production callers pass
 * nothing and therefore use the real migrator and table creator.
 *
 * @param callable():bool|null       $migration_runner Database migration runner.
 * @param callable(string):bool|null $table_creator    Required-table creator.
 */
function mhmrentiva_single_site_activation(?callable $migration_runner = null, ?callable $table_creator = null)
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
		$migration_runner ??= array( \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::class, 'run_migrations' );
		$table_creator    ??= array( \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::class, 'create_table' );

		// Run migrations to ensure all indexes and tables are up to date
		$database_ready = (bool) $migration_runner();

		// Force-create only the tables required by the Lite runtime. Add-on
		// tables remain owned by their class-gated migrations.
		$critical_tables = array(
			'payment_log',
			'sessions',
			'ratings',
			'queue',
			'message_logs',
		);

		foreach ($critical_tables as $table) {
			if (! $table_creator($table)) {
				$database_ready = false;
				break;
			}
		}

		if (! $database_ready) {
			wp_die(
				esc_html__('MHM Rentiva could not create its required database tables. The plugin was not activated. Check database permissions and try again.', 'mhm-rentiva'),
				esc_html__('Database Setup Failed', 'mhm-rentiva'),
				array(
					'back_link' => true,
					'response'  => 500,
				)
			);
		}
	}

	// Trigger setup wizard redirect on new installations
	update_option('mhmrentiva_setup_redirect', '1');

	// Seed plugin version so version drift hook does not fire on fresh install.
	update_option('mhmrentiva_plugin_version', MHMRENTIVA_VERSION);
}

// Activation hook - CPT and taxonomy registration + rewrite flush + Multisite support
register_activation_hook(
	__FILE__,
	/**
	 * @param bool $network_wide Whether the plugin is being activated for every
	 *                           site in the network. WordPress passes this to
	 *                           every activation callback
	 *                           (wp-admin/includes/plugin.php:
	 *                           `do_action( "activate_{$plugin}", $network_wide )`),
	 *                           and it is authoritative: activate_plugin() also
	 *                           forces it true for network-only plugins, a case
	 *                           the `?networkwide=1` URL parameter this used to
	 *                           read does not cover.
	 */
	function ( $network_wide = false ) {
		// PHP version check
		if (version_compare(PHP_VERSION, '8.1', '<')) {
			wp_die(esc_html__('MHM Rentiva plugin requires PHP 8.1 or higher.', 'mhm-rentiva'));
		}

		// Check for WooCommerce dependency (production default, filterable for tests).
		$skip_dependency_check = (bool) apply_filters('mhmrentiva_skip_dependency_check', false);
		if (! $skip_dependency_check && ! class_exists('WooCommerce')) {
			wp_die(
				sprintf(
					/* translators: %s: Plugin name. */
					esc_html__('%s requires WooCommerce to be installed and active.', 'mhm-rentiva'),
					'MHM Rentiva'
				),
				esc_html__('Plugin Dependency Check', 'mhm-rentiva'),
				array( 'back_link' => true )
			);
		}

		if (is_multisite()) {
			// Network-wide activation
			if ($network_wide) {

				// Fetch blog IDs using get_sites() instead of direct database query
				$blog_ids = wp_cache_get('mhmrentiva_network_blogs');
				if (false === $blog_ids) {
					$sites    = get_sites( array( 'public' => 1 ) );
					$blog_ids = array();
					foreach ($sites as $site) {
						$blog_ids[] = $site->blog_id;
					}
					wp_cache_set('mhmrentiva_network_blogs', $blog_ids, '', 3600);
				}

				if (! empty($blog_ids)) {
					foreach ($blog_ids as $blog_id) {
						switch_to_blog( (int) $blog_id );
						mhmrentiva_single_site_activation();
						restore_current_blog();
					}
				}
				return;
			}
		}

		// Single site activation
		mhmrentiva_single_site_activation();
	}
);

// Runtime dependency check
add_action(
	'admin_notices',
	function () {
		if (! class_exists('WooCommerce')) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: Plugin name. */
						__( '%s requires WooCommerce to be installed and active. Please install WooCommerce to use this plugin.', 'mhm-rentiva' ),
						'<strong>' . esc_html__( 'MHM Rentiva', 'mhm-rentiva' ) . '</strong>'
					),
					array( 'strong' => array() )
				)
			);
		}
	}
);

// When new blog is created in Multisite
add_action(
	'wpmu_new_blog',
	function ($blog_id) {
		if (is_plugin_active_for_network('mhm-rentiva/mhm-rentiva.php')) {
			switch_to_blog($blog_id);
			mhmrentiva_single_site_activation();
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

/*
 * WooCommerce feature compatibility.
 *
 * WooCommerce asks every plugin that touches orders to say which of its newer
 * storage/UI features it works with. A plugin that says nothing is listed as
 * INCOMPATIBLE on WooCommerce > Settings > Advanced > Features, and the site
 * owner is warned by name when they try to turn High-Performance Order Storage
 * on -- which has been the default for new installs since WooCommerce 8.2.
 *
 * Declaring it is not a promise made lightly; it is true of this plugin:
 *   - orders are read through wc_get_order(), never through wp_posts SQL or a
 *     WP_Query on 'shop_order';
 *   - order meta is written through the order object, so it lands in whichever
 *     store is active;
 *   - no order is created or updated with wp_insert_post()/wp_update_post().
 * If any of those stop being true, this declaration has to be removed in the
 * same change -- an untrue compatibility claim is worse than none.
 *
 * `before_woocommerce_init` is the only hook that fires early enough for the
 * declaration to be recorded, and the class_exists() guard keeps this harmless
 * on a WooCommerce version that predates FeaturesUtil.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if (! class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);

		/*
		 * Block-based cart/checkout: declared INCOMPATIBLE, deliberately.
		 *
		 * WooCommerce files an undeclared plugin as "uncertain", not "incompatible";
		 * both are surfaced when someone tries to switch checkout over, but they are
		 * not the same claim. This one is measured.
		 *
		 * Visible surfaces that simply would not render, all printed from
		 * classic-checkout hooks the block checkout never runs:
		 *   woocommerce_review_order_before_payment      -> payment-type selector
		 *   woocommerce_review_order_before_order_total  -> custom tax row
		 *   woocommerce_checkout_before_order_review     -> return-to-cart link
		 *
		 * And the part that matters more than any of those, because it fails without
		 * showing anything:
		 *   woocommerce_checkout_process        -> availability validation. This is
		 *       what stops two customers buying the same vehicle for the same dates.
		 *       Block checkout does not run it, so that window is open.
		 *   woocommerce_checkout_order_processed -> booking creation, leaving only
		 *       the woocommerce_thankyou fallback.
		 *   woocommerce_checkout_fields / _update_order_meta -> not run by the
		 *       Store API either.
		 *
		 * So "false" is not conservatism, it is the accurate answer. Making it true
		 * means porting these to the Additional Checkout Fields API and a Store API
		 * integration -- a feature, not a flag.
		 */
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
	}
);

// Deactivation hook - clear this plugin's scheduled events, then flush rewrites.
//
// A deactivated plugin's cron events keep their rows in the cron array and keep
// coming due forever with no callback listening, so WordPress re-queues them on
// every request for the life of the site. Reactivation reschedules them: every
// scheduler here is guarded by wp_next_scheduled(), so this is symmetric, not
// destructive. The hook list is the uninstaller's, so the two paths cannot
// drift apart.
register_deactivation_hook(
	__FILE__,
	function () {
		if ( class_exists( '\MHMRentiva\Admin\Utilities\Uninstall\Uninstaller' ) ) {
			foreach ( \MHMRentiva\Admin\Utilities\Uninstall\Uninstaller::plugin_cron_hooks() as $mhmrentiva_hook ) {
				wp_unschedule_hook( $mhmrentiva_hook );
			}
		}

		flush_rewrite_rules();
	}
);
