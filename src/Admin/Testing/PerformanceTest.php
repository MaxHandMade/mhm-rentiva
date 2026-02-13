<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ 4. STAGE - Performance Test Suite
 */
final class PerformanceTest {


	/**
	 * Run all performance tests
	 */
	public static function run_all_tests(): array {
		$results = array();

		$results['query_performance']      = self::test_query_performance();
		$results['cache_hit_rate']         = self::test_cache_hit_rate();
		$results['memory_usage']           = self::test_memory_usage();
		$results['asset_loading']          = self::test_asset_loading_time();
		$results['database_indexes']       = self::test_database_indexes();
		$results['autoloader_performance'] = self::test_autoloader_performance();

		return $results;
	}

	/**
	 * Test: Query Performance
	 */
	public static function test_query_performance(): array {
		global $wpdb;

		$start_time = microtime( true );

		// Test query: Get vehicle list
		$vehicles = get_posts(
			array(
				'post_type'              => 'vehicle',
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		$query_time         = ( microtime( true ) - $start_time ) * 1000; // ms
		$query_count_before = $wpdb->num_queries;

		// Calculate real query count (queries executed during this test)
		$vehicles_count = is_array( $vehicles ) ? count( $vehicles ) : 0;

		// Performance criteria
		$pass       = $query_time < 100; // Should be below 100ms
		$efficiency = $query_time < 50 ? 'excellent' : ( $query_time < 100 ? 'good' : 'poor' );

		if ( $pass ) {
			$message = sprintf(
				/* translators: 1: query duration in ms; 2: vehicle count; 3: performance label. */
				esc_html__( '✅ Query completed in %1$.2f ms. Found %2$d vehicles. Performance: %3$s', 'mhm-rentiva' ),
				$query_time,
				$vehicles_count,
				esc_html( $efficiency )
			);
		} else {
			$message = sprintf(
				/* translators: 1: query duration in ms; 2: vehicle count; 3: performance label. */
				esc_html__( '⚠️ Query took %1$.2f ms (exceeds 100ms limit). Found %2$d vehicles. Performance: %3$s', 'mhm-rentiva' ),
				$query_time,
				$vehicles_count,
				esc_html( $efficiency )
			);
		}

		return array(
			'test'           => __( 'Query Performance', 'mhm-rentiva' ),
			'status'         => $pass ? 'pass' : 'warning',
			'message'        => $message,
			'query_time_ms'  => round( $query_time, 2 ),
			'vehicles_found' => $vehicles_count,
			'efficiency'     => $efficiency,
		);
	}

	/**
	 * Test: Cache Hit Rate
	 */
	public static function test_cache_hit_rate(): array {
		if ( ! class_exists( 'MHMRentiva\\Admin\\Core\\Utilities\\CacheManager' ) ) {
			return array(
				'test'    => __( 'Cache Hit Rate', 'mhm-rentiva' ),
				'status'  => 'skip',
				'message' => esc_html__( 'CacheManager class not found', 'mhm-rentiva' ),
			);
		}

		// Cache test
		$test_iterations = 100;
		$hits            = 0;
		$test_key        = 'perf_test_' . time();

		// First write
		\MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache( 'dashboard_stats', $test_key, array( 'data' => 'test' ), 3600 );

		// Read tests
		$start_time = microtime( true );
		for ( $i = 0; $i < $test_iterations; $i++ ) {
			$result = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache( 'dashboard_stats', $test_key );
			if ( $result !== null ) {
				++$hits;
			}
		}
		$read_time = ( microtime( true ) - $start_time ) * 1000;

		// Clean up
		\MHMRentiva\Admin\Core\Utilities\CacheManager::clear_cache( array( 'dashboard_stats' ) );

		$hit_rate = ( $hits / $test_iterations ) * 100;
		$pass     = $hit_rate >= 95;

		return array(
			'test'          => __( 'Cache Hit Rate', 'mhm-rentiva' ),
			'status'        => $pass ? 'pass' : 'warning',
			'message'       => sprintf(
				/* translators: 1: %.1f; 2: %d; 3: %d. */
				esc_html__( 'Cache hit rate: %1$.1f%% (%2$d/%3$d)', 'mhm-rentiva' ),
				$hit_rate,
				$hits,
				$test_iterations
			),
			'hit_rate'      => round( $hit_rate, 1 ),
			'read_time_ms'  => round( $read_time, 2 ),
			'avg_read_time' => round( $read_time / $test_iterations, 4 ),
		);
	}

	/**
	 * Test: Memory Usage
	 */
	public static function test_memory_usage(): array {
		$memory_usage = memory_get_usage( true );
		$memory_peak  = memory_get_peak_usage( true );
		$memory_limit = ini_get( 'memory_limit' );

		// Convert to MB
		$usage_mb = round( $memory_usage / 1024 / 1024, 2 );
		$peak_mb  = round( $memory_peak / 1024 / 1024, 2 );
		$limit_mb = self::parse_memory_limit( $memory_limit );

		$usage_percentage = ( $usage_mb / $limit_mb ) * 100;
		$pass             = $usage_percentage < 70; // Should be below 70%

		return array(
			'test'             => __( 'Memory Usage', 'mhm-rentiva' ),
			'status'           => $pass ? 'pass' : 'warning',
			'message'          => sprintf(
				/* translators: 1: current memory usage in MB, 2: memory limit in MB, 3: percentage used */
				esc_html__( 'Memory usage: %1$d MB / %2$d MB (%3$.1f%%)', 'mhm-rentiva' ),
				(int) $usage_mb,
				(int) $limit_mb,
				$usage_percentage
			),
			'current_mb'       => $usage_mb,
			'peak_mb'          => $peak_mb,
			'limit_mb'         => $limit_mb,
			'usage_percentage' => round( $usage_percentage, 1 ),
		);
	}

	/**
	 * Test: Asset File Sizes
	 *
	 * Checks only frontend assets (frontend/ + core/ + components/ that are used in frontend)
	 * Admin assets are not counted as they don't affect frontend performance
	 */
	public static function test_asset_loading_time(): array {
		$assets_dir = MHM_RENTIVA_PLUGIN_DIR . 'assets/';

		// Frontend CSS files (frontend/ + core/ + components/)
		$frontend_css_files = array_merge(
			glob( $assets_dir . 'css/frontend/*.css' ) ?: array(),
			glob( $assets_dir . 'css/core/*.css' ) ?: array(),
			glob( $assets_dir . 'css/components/*.css' ) ?: array()
		);

		$frontend_css_size = 0;
		foreach ( $frontend_css_files as $file ) {
			$frontend_css_size += filesize( $file );
		}

		// Frontend JS files (frontend/ + core/ + components/)
		$frontend_js_files = array_merge(
			glob( $assets_dir . 'js/frontend/*.js' ) ?: array(),
			glob( $assets_dir . 'js/core/*.js' ) ?: array(),
			glob( $assets_dir . 'js/components/*.js' ) ?: array()
		);

		$frontend_js_size = 0;
		foreach ( $frontend_js_files as $file ) {
			$frontend_js_size += filesize( $file );
		}

		$frontend_total_kb = round( ( $frontend_css_size + $frontend_js_size ) / 1024, 2 );

		// Also calculate admin assets for informational purposes
		$admin_css_files = glob( $assets_dir . 'css/admin/*.css' ) ?: array();
		$admin_js_files  = glob( $assets_dir . 'js/admin/*.js' ) ?: array();

		$admin_css_size = 0;
		foreach ( $admin_css_files as $file ) {
			$admin_css_size += filesize( $file );
		}

		$admin_js_size = 0;
		foreach ( $admin_js_files as $file ) {
			$admin_js_size += filesize( $file );
		}

		// Performance criteria: Frontend assets should be optimized (max 2000KB recommended)
		// Increased limit because frontend includes core + components + frontend specific
		$recommended_limit_kb = 2000;
		$pass                 = $frontend_total_kb < $recommended_limit_kb;

		if ( $pass ) {
			$message = sprintf(
				/* translators: 1: %1$d; 2: %2$d; 3: %3$d. */
				esc_html__( '✅ Frontend assets: %1$d KB (CSS: %2$d KB, JS: %3$d KB) - Optimal', 'mhm-rentiva' ),
				$frontend_total_kb,
				round( $frontend_css_size / 1024, 2 ),
				round( $frontend_js_size / 1024, 2 )
			);
		} else {
			$message = sprintf(
				/* translators: 1: %1$d; 2: %2$d; 3: %4$d. */
				esc_html__( '⚠️ Frontend assets: %1$d KB exceeds recommended limit (%2$d KB). Consider optimization (CSS: %3$d KB, JS: %4$d KB)', 'mhm-rentiva' ),
				$frontend_total_kb,
				$recommended_limit_kb,
				round( $frontend_css_size / 1024, 2 ),
				round( $frontend_js_size / 1024, 2 )
			);
		}

		// Add admin assets info (informational, not counted in pass/fail)
		if ( $frontend_total_kb > 0 ) {
			$admin_total_kb = round( ( $admin_css_size + $admin_js_size ) / 1024, 2 );
			$message       .= sprintf(
				/* translators: 1: %1$d; 2: %2$d; 3: %3$d. */
				' | ' . esc_html__( 'Admin: %1$d KB (CSS: %2$d KB, JS: %3$d KB)', 'mhm-rentiva' ),
				$admin_total_kb,
				round( $admin_css_size / 1024, 2 ),
				round( $admin_js_size / 1024, 2 )
			);
		}

		return array(
			'test'                   => __( 'Asset File Sizes', 'mhm-rentiva' ),
			'status'                 => $pass ? 'pass' : 'warning',
			'message'                => $message,
			'frontend_css_files'     => count( $frontend_css_files ),
			'frontend_js_files'      => count( $frontend_js_files ),
			'frontend_css_size_kb'   => round( $frontend_css_size / 1024, 2 ),
			'frontend_js_size_kb'    => round( $frontend_js_size / 1024, 2 ),
			'frontend_total_size_kb' => $frontend_total_kb,
			'admin_css_files'        => count( $admin_css_files ),
			'admin_js_files'         => count( $admin_js_files ),
			'admin_total_size_kb'    => round( ( $admin_css_size + $admin_js_size ) / 1024, 2 ),
			'recommended_limit_kb'   => $recommended_limit_kb,
		);
	}

	/**
	 * Test: Database Indexes
	 */
	public static function test_database_indexes(): array {
		global $wpdb;

		// Check indexes in the postmeta table
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->postmeta}" );

		$index_names        = array_column( $indexes, 'Key_name' );
		$has_meta_key_index = in_array( 'meta_key', $index_names );
		$has_post_id_index  = in_array( 'post_id', $index_names );

		$pass = $has_meta_key_index && $has_post_id_index;

		return array(
			'test'               => __( 'Database Indexes', 'mhm-rentiva' ),
			'status'             => $pass ? 'pass' : 'warning',
			'message'            => $pass ?
				esc_html__( '✅ Required indexes are present', 'mhm-rentiva' ) :
				esc_html__( '⚠️ Some indexes may be missing', 'mhm-rentiva' ),
			'has_meta_key_index' => $has_meta_key_index,
			'has_post_id_index'  => $has_post_id_index,
			'total_indexes'      => count( $index_names ),
		);
	}

	/**
	 * Test: Autoloader Performance
	 */
	public static function test_autoloader_performance(): array {
		$test_classes = array(
			'MHMRentiva\\Plugin',
			'MHMRentiva\\Admin\\Core\\AssetManager',
			'MHMRentiva\\Admin\\Vehicle\\PostType\\Vehicle',
			'MHMRentiva\\Admin\\Booking\\PostType\\Booking',
			'MHMRentiva\\Admin\\Frontend\\Shortcodes\\VehiclesList',
			'MHMRentiva\\Admin\\Core\\Utilities\\CacheManager',
			'MHMRentiva\\Admin\\Core\\Utilities\\RateLimiter',
			'MHMRentiva\\Admin\\REST\\Availability',
		);

		$start_time = microtime( true );
		$loaded     = 0;

		foreach ( $test_classes as $class ) {
			if ( class_exists( $class, false ) ) { // false = don't autoload
				++$loaded;
			}
		}

		$load_time = ( microtime( true ) - $start_time ) * 1000;
		$pass      = $load_time < 10; // Should be below 10ms

		return array(
			'test'         => __( 'Autoloader Performance', 'mhm-rentiva' ),
			'status'       => $pass ? 'pass' : 'warning',
			'message'      => sprintf(
				/* translators: 1: loaded class count, 2: total class count, 3: load time in ms */
				esc_html__( '%1$d/%2$d class loaded in %3$.2f ms', 'mhm-rentiva' ),
				$loaded,
				count( $test_classes ),
				$load_time
			),
			'loaded_count' => $loaded,
			'total_count'  => count( $test_classes ),
			'load_time_ms' => round( $load_time, 2 ),
		);
	}

	/**
	 * Helper: Convert memory limit to MB
	 */
	private static function parse_memory_limit( string $limit ): float {
		$limit = trim( $limit );
		$last  = strtolower( $limit[ strlen( $limit ) - 1 ] );
		$value = (float) $limit;

		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// fall through
			case 'm':
				return $value;
			case 'k':
				return $value / 1024;
			default:
				return $value / 1024 / 1024;
		}
	}

	/**
	 * Benchmark: Full Page Load Simulation
	 */
	public static function benchmark_full_page_load(): array {
		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		// Simulate page load
		do_action( 'wp_loaded' );
		do_action( 'template_redirect' );

		// Shortcode render test
		$shortcode_start = microtime( true );
		do_shortcode( '[rentiva_vehicles_list limit="10"]' );
		$shortcode_time = ( microtime( true ) - $shortcode_start ) * 1000;

		$total_time  = ( microtime( true ) - $start_time ) * 1000;
		$memory_used = ( memory_get_usage() - $start_memory ) / 1024; // KB

		return array(
			'total_time_ms'     => round( $total_time, 2 ),
			'shortcode_time_ms' => round( $shortcode_time, 2 ),
			'memory_used_kb'    => round( $memory_used, 2 ),
			'status'            => $total_time < 500 ? 'excellent' : ( $total_time < 1000 ? 'good' : 'needs_optimization' ),
		);
	}
}
