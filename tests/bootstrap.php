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
function mhm_is_test_discovery_mode(): bool
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
$is_discovery_mode = mhm_is_test_discovery_mode();
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
	add_filter('mhm_rentiva_skip_dependency_check', '__return_true', 1);
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
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
	require dirname(dirname(__FILE__)) . '/mhm-rentiva.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

/**
 * Force valid Tenant ID for all tests to satisfy v1.9 Orchestration requirements.
 */
tests_add_filter('muplugins_loaded', function () {
	add_filter('mhm_rentiva_filter_tenant_id', function () {
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
		'transfer_locations',
		'transfer_routes',
		'ratings',
		'queue',
		'report_queue',
		'message_logs',
		'notification_queue',
		'background_jobs',
	);

	foreach ($critical_tables as $table) {
		\MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_table($table);
	}

	// Ensure Default Tenants exist for Orchestration/Isolation tests
	global $wpdb;
	$tenants_table = $wpdb->prefix . 'mhm_rentiva_tenants';

	$seed_tenants = [1, 2];
	foreach ($seed_tenants as $tid) {
		$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tenants_table WHERE tenant_id = %d", $tid));
		if (!$exists) {
			$wpdb->insert($tenants_table, [
				'tenant_id' => $tid,
				'status' => 'ACTIVE',
				'subscription_plan' => ($tid === 1) ? 'pro' : 'basic',
				'quota_payouts_limit' => 1000,
				'quota_ledger_entries_limit' => 10000,
				'quota_risk_events_limit' => 500,
				'created_at' => current_time('mysql')
			]);
		}
	}
}, 20);

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
