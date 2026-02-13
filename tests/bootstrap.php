<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap file.
 *
 * @package Mhm_Rentiva
 */

$_tests_dir = getenv('WP_TESTS_DIR');

if (! $_tests_dir) {
	// Windows/XAMPP fallback: Check for local wordpress-develop repository
	$windows_local_dev = 'C:/xampp/htdocs/wordpress-develop/tests/phpunit';
	if (file_exists($windows_local_dev . '/includes/functions.php')) {
		$_tests_dir = $windows_local_dev;
	} else {
		$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
	}
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

/**
 * Resolve and override wp-tests-config with an isolated table prefix.
 *
 * This avoids noisy "table already exists / duplicate entry" messages when
 * multiple test runs share the same DB.
 */
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
		$updated_body = preg_replace(
			"/^\\$table_prefix\\s*=\\s*'[^']*';/m",
			"\$table_prefix = '" . addslashes($table_prefix) . "';",
			$config_body
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

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Parse env value to boolean.
 *
 * @param string $key Environment variable key.
 * @return bool|null Returns null when env is not set or invalid.
 */
function mhm_test_env_bool(string $key): ?bool
{
	$value = getenv($key);
	if (! is_string($value) || '' === trim($value)) {
		return null;
	}

	$parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	return is_bool($parsed) ? $parsed : null;
}

/**
 * Register optional legacy feature flags from test environment variables.
 */
function mhm_register_legacy_test_filters(): void
{
	$global = mhm_test_env_bool('MHM_TEST_LEGACY_FEATURES_ENABLED');
	if (is_bool($global)) {
		tests_add_filter(
			'mhm_rentiva_legacy_feature_enabled',
			static function (bool $enabled) use ($global): bool {
				return $global;
			}
		);
	}

	$feature_map = array(
		'setup_wizard'       => 'MHM_TEST_LEGACY_SETUP_WIZARD_ENABLED',
		'about_page'         => 'MHM_TEST_LEGACY_ABOUT_PAGE_ENABLED',
	);

	foreach ($feature_map as $feature => $env_key) {
		$value = mhm_test_env_bool($env_key);
		if (! is_bool($value)) {
			continue;
		}

		tests_add_filter(
			"mhm_rentiva_legacy_{$feature}_enabled",
			static function (bool $enabled) use ($value): bool {
				return $value;
			}
		);
	}
}

mhm_register_legacy_test_filters();

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
	require dirname(dirname(__FILE__)) . '/mhm-rentiva.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
