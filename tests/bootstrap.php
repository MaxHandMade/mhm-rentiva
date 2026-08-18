<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * @package Mhm_Rentiva
 */

$_tests_dir = getenv('WP_TESTS_DIR');

if (! $_tests_dir) {
	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if (false !== $_phpunit_polyfills_path) {
	define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if (! file_exists("{$_tests_dir}/includes/functions.php")) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit(1);
}

// Test-only cryptographic seed constants for audit key generation paths.
if (! defined('AUTH_KEY')) {
	define('AUTH_KEY', 'unit-test-auth-key');
}

if (! defined('SECURE_AUTH_SALT')) {
	define('SECURE_AUTH_SALT', 'unit-test-secure-auth-salt');
}

/**
 * Detect whether PHPUnit is running in test discovery mode.
 *
 * @return bool
 */
function mhmrentiva_is_test_discovery_mode(): bool
{
	$argv = $_SERVER['argv'] ?? array();
	if (! is_array($argv)) {
		return false;
	}

	foreach ($argv as $arg) {
		if (! is_string($arg)) {
			continue;
		}

		if (str_starts_with($arg, '--list-tests')) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve and override wp-tests-config with an isolated table prefix.
 *
 * This avoids noisy "table already exists / duplicate entry" messages when
 * multiple test runs share the same DB.
 */
$is_discovery_mode = mhmrentiva_is_test_discovery_mode();
$is_ci = 'true' === strtolower((string) getenv('CI'));
$isolation_flag = strtolower((string) getenv('WP_TESTS_ISOLATE_DB'));
$isolation_explicitly_enabled = in_array($isolation_flag, array('1', 'true', 'yes'), true);
$should_isolate_db = ! $is_discovery_mode && ($is_ci || $isolation_explicitly_enabled);

if ($should_isolate_db) {
	$table_prefix = getenv('WP_TESTS_TABLE_PREFIX');
	if (! is_string($table_prefix) || '' === trim($table_prefix)) {
		$run_id = getenv('MHM_TEST_RUN_ID');
		if (! is_string($run_id) || '' === trim($run_id)) {
			$run_id = substr(hash('sha256', (string) microtime(true) . '-' . (string) getmypid()), 0, 8);
		}
		$table_prefix = 'wptests_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $run_id) . '_';
	}

	$config_path = getenv('WP_TESTS_CONFIG_FILE_PATH');
	if (! is_string($config_path) || '' === trim($config_path)) {
		$config_candidates = array(
			dirname($_tests_dir) . '/wp-tests-config.php',
			dirname($_tests_dir, 2) . '/wp-tests-config.php',
			dirname($_tests_dir, 3) . '/wp-tests-config.php',
		);
		foreach ($config_candidates as $candidate) {
			if (is_readable($candidate)) {
				$config_path = $candidate;
				break;
			}
		}
	}

	if (is_string($config_path) && '' !== trim($config_path) && is_readable($config_path)) {
		$config_body = file_get_contents($config_path);
		if (is_string($config_body) && '' !== $config_body) {
			$wp_core_dir = getenv('WP_CORE_DIR');
			if (! is_string($wp_core_dir) || '' === trim($wp_core_dir)) {
				$wp_core_dir = dirname($_tests_dir, 2) . '/src';
			}
			$wp_core_dir = rtrim(str_replace('\\', '/', (string) $wp_core_dir), '/');

			$updated_body = preg_replace(
				"/define\\(\\s*'ABSPATH'\\s*,\\s*.+?\\);/m",
				"define('ABSPATH', '" . addslashes($wp_core_dir) . "/');",
				$config_body,
				1
			);

			if (! is_string($updated_body) || '' === $updated_body) {
				$updated_body = $config_body;
			}

			$updated_body = preg_replace(
				"/^\\$table_prefix\\s*=\\s*'[^']*';/m",
				"\$table_prefix = '" . addslashes($table_prefix) . "';",
				$updated_body
			);

			if (is_string($updated_body) && '' !== $updated_body) {
				$tmp_config = rtrim((string) sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'mhm-rentiva-wp-tests-config-' . md5($table_prefix) . '.php';
				file_put_contents($tmp_config, $updated_body);
				putenv('WP_TESTS_CONFIG_FILE_PATH=' . $tmp_config);
				if (! defined('WP_TESTS_CONFIG_FILE_PATH')) {
					define('WP_TESTS_CONFIG_FILE_PATH', $tmp_config);
				}
			}
		}
	}
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Allow activation hook dependency guard bypass in test runtime only.
 *
 * This keeps production dependency checks intact while permitting
 * deterministic plugin lifecycle activation tests in CI.
 */
tests_add_filter('muplugins_loaded', static function () {
	add_filter('mhmrentiva_skip_dependency_check', '__return_true', 1);
}, 1);

/**
 * Suppress WP auto-update checks during tests.
 *
 * wp_version_check() and wp_maybe_auto_update() fire via admin_init during
 * WP_Ajax_UnitTestCase::_handleAjax(). In a Docker environment without
 * internet access, the HTTP request fails and WP calls trigger_error().
 * PHPUnit (convertNoticesToExceptions="true") converts the notice to a
 * PHPUnit Error, causing non-deterministic failures in AJAX tests.
 *
 * Returning a "recently checked" transient object triggers wp_version_check()'s
 * built-in early-return guard (timeout not yet expired) so no HTTP request
 * or trigger_error() is ever issued.
 */
tests_add_filter('muplugins_loaded', static function () {
	$fresh = static function () {
		$obj               = new \stdClass();
		$obj->last_checked = time();
		$obj->updates      = array();
		return $obj;
	};
	add_filter('pre_site_transient_update_core',    $fresh, 1);
	add_filter('pre_site_transient_update_plugins', $fresh, 1);
	add_filter('pre_site_transient_update_themes',  $fresh, 1);
}, 5);

/**
 * Locate WooCommerce, or return null when it is genuinely absent.
 *
 * Two environments, one shape. Locally WP_TESTS_DIR's config points ABSPATH at
 * the dev site's core tree, so WooCommerce is already on disk beside this
 * plugin. In CI, install-wp-tests.sh builds a clean tree at /tmp/wordpress and
 * the workflow downloads WooCommerce into its plugins directory. Both end up at
 * ABSPATH/wp-content/plugins/woocommerce, so one lookup covers both; WC_PLUGIN_DIR
 * stays available as an override for a layout neither anticipates.
 *
 * @return string|null Absolute path to woocommerce.php, or null.
 */
function mhmrentiva_locate_woocommerce(): ?string
{
	$candidates = array();

	$override = getenv('WC_PLUGIN_DIR');
	if (is_string($override) && '' !== trim($override)) {
		$candidates[] = rtrim($override, '/') . '/woocommerce.php';
	}

	// Sibling of the plugin under test.
	$candidates[] = dirname(__DIR__, 2) . '/woocommerce/woocommerce.php';

	if (defined('ABSPATH')) {
		$candidates[] = rtrim((string) ABSPATH, '/') . '/wp-content/plugins/woocommerce/woocommerce.php';
	}

	foreach ($candidates as $candidate) {
		if (is_readable($candidate)) {
			return $candidate;
		}
	}

	return null;
}

/**
 * Load WooCommerce ahead of the plugin under test.
 *
 * Priority 5, so mhm-rentiva (priority 10) sees the same world it sees in
 * production: 26 of its source files branch on WooCommerce being present, and a
 * suite that loads them without it measures the absent branch every time.
 *
 * Deliberately silent when WooCommerce is missing rather than fatal: the suite
 * still has to run for someone checking out this repo without a WooCommerce
 * checkout beside it. WooCommerceTestEnvironmentTest is what turns that silence
 * into a visible failure, so the absence is reported once, by name, instead of
 * as five skipped files nobody reads.
 */
tests_add_filter('muplugins_loaded', static function () {
	$woocommerce = mhmrentiva_locate_woocommerce();

	if (null === $woocommerce) {
		return;
	}

	require_once $woocommerce;
}, 5);

/**
 * Install WooCommerce's schema into the test database.
 *
 * Loading the plugin is not installing it. Without this, the first test to
 * touch an order fails on a missing table rather than on its own assertion --
 * and HPOS in particular keeps orders in tables that only WC_Install creates.
 *
 * setup_theme runs after plugins are loaded and before the first test case, and
 * it is where WooCommerce's own suite installs itself.
 */
tests_add_filter('setup_theme', static function () {
	if (! class_exists('WC_Install')) {
		return;
	}

	// The dev site runs HPOS (measured 2026-08-18). Declare it BEFORE install so
	// the order tables are created and the store is selected in one pass; a
	// suite in the other storage mode would prove nothing about the code paths
	// WooCommerceBridge actually takes in production.
	update_option('woocommerce_feature_custom_order_tables_enabled', 'yes');
	update_option('woocommerce_custom_orders_table_enabled', 'yes');

	\WC_Install::install();

	// Capabilities are registered during install; WP caches roles before that.
	$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	wp_roles();
});

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
	require dirname(dirname(__FILE__)) . '/mhm-rentiva.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

/**
 * Pin LicenseServerPublicKey::resource() to the test fixture public PEM.
 *
 * The embedded LicenseServerPublicKey::PEM constant ships the production
 * public key (swapped in at release time). The fixture-bound suite signs
 * test tokens with the paired fixture PRIVATE key — those signatures only
 * verify against the fixture PUBLIC key. Without this override, the Mode
 * → FeatureTokenVerifier → openssl_verify chain would reject every
 * fixture-signed token after the production swap.
 *
 * Priority 12 runs after _manually_load_plugin (priority 10) so the
 * autoloader has the LicenseServerPublicKey class registered.
 */
tests_add_filter('muplugins_loaded', static function () {
	if (! class_exists(\MHMRentiva\Admin\Licensing\LicenseServerPublicKey::class)) {
		return;
	}

	$fixturePath = __DIR__ . '/fixtures/test-rsa-public.pem';
	if (! is_readable($fixturePath)) {
		return;
	}

	\MHMRentiva\Admin\Licensing\LicenseServerPublicKey::injectForTesting(
		(string) file_get_contents($fixturePath)
	);
}, 12);

/**
 * Force valid Tenant ID for all tests to satisfy v1.9 Orchestration requirements.
 */
tests_add_filter('muplugins_loaded', function () {
	add_filter('mhmrentiva_filter_tenant_id', function () {
		return 1;
	}, 1);
}, 11);

/**
 * Run plugin installation (DB table creation) after plugin is loaded.
 *
 * Priority 20 ensures this runs after _manually_load_plugin (priority 10),
 * so DatabaseMigrator class is available. This replicates the activation hook
 * behaviour so the test DB matches the production activation surface exactly.
 */
tests_add_filter('muplugins_loaded', function () {
	if (! class_exists(\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::class)) {
		return;
	}

	\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations();

	$critical_tables = array(
		'payment_log',
		'sessions',
		'ratings',
		'queue',
		'message_logs',
	);

	foreach ($critical_tables as $table) {
		\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_table($table);
	}

}, 20);

/**
 * Autoload shared test support classes.
 *
 * PHPUnit only loads files matching the suite's `*Test.php` suffix, so traits
 * and helpers under tests/Support/ are invisible to it. This plugin has no
 * composer autoload map, so rather than adding one (and a composer dump
 * requirement for anyone running the suite) the support namespace gets its own
 * small loader, scoped so it can never resolve a production class.
 */
spl_autoload_register(static function (string $class): void {
	$separator = chr(92);
	$prefix    = 'MHMRentiva' . $separator . 'Tests' . $separator . 'Support' . $separator;

	if (! str_starts_with($class, $prefix)) {
		return;
	}

	$relative = str_replace($separator, '/', substr($class, strlen($prefix)));
	$path     = __DIR__ . '/Support/' . $relative . '.php';

	if (is_readable($path)) {
		require_once $path;
	}
});

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
