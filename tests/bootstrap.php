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
$normalized_tests_dir = str_replace('\\', '/', (string) $_tests_dir);
$is_windows_local_dev = str_contains($normalized_tests_dir, 'wordpress-develop/tests/phpunit');
$is_ci = 'true' === strtolower((string) getenv('CI'));
$isolation_flag = strtolower((string) getenv('WP_TESTS_ISOLATE_DB'));
$isolation_explicitly_enabled = in_array($isolation_flag, array('1', 'true', 'yes'), true);
$should_isolate_db = ! $is_discovery_mode && ($is_ci || $isolation_explicitly_enabled);

if ($is_windows_local_dev) {
	$existing_config_path = getenv('WP_TESTS_CONFIG_FILE_PATH');
	if (is_string($existing_config_path) && str_contains(str_replace('\\', '/', $existing_config_path), '/Temp/')) {
		putenv('WP_TESTS_CONFIG_FILE_PATH');
	}
}

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
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
	require dirname(dirname(__FILE__)) . '/mhm-rentiva.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
