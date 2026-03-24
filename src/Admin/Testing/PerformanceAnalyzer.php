<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance Analyzer
 *
 * Analyzes shortcode performance
 */
final class PerformanceAnalyzer {

	private static array $queries   = array();
	private static array $timing    = array();
	private static bool $monitoring = false;

	/**
	 * Register hooks
	 */
	public static function register(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'init', array( self::class, 'start_monitoring' ) );
			add_filter( 'query', array( self::class, 'log_query' ) );
		}
	}

	/**
	 * Start performance monitoring
	 */
	public static function start_monitoring(): void {
		self::$monitoring = true;
		self::$queries    = array();
		self::$timing     = array();
	}

	/**
	 * Stop performance monitoring
	 */
	public static function stop_monitoring(): array {
		self::$monitoring = false;

		return array(
			'queries'        => self::$queries,
			'timing'         => self::$timing,
			'total_queries'  => count( self::$queries ),
			'unique_queries' => count( array_unique( array_column( self::$queries, 'sql' ) ) ),
		);
	}

	/**
	 * Log database query
	 */
	public static function log_query( string $query ): string {
		if ( ! self::$monitoring ) {
			return $query;
		}

		self::$queries[] = array(
			'sql'    => $query,
			'time'   => microtime( true ),
			'source' => current_filter(),
		);

		return $query;
	}

	/**
	 * Start timing a shortcode
	 */
	public static function start_timing( string $shortcode ): void {
		self::$timing[ $shortcode ] = array(
			'start'    => microtime( true ),
			'end'      => null,
			'duration' => null,
		);
	}

	/**
	 * End timing a shortcode
	 */
	public static function end_timing( string $shortcode ): float {
		if ( ! isset( self::$timing[ $shortcode ] ) ) {
			return 0;
		}

		self::$timing[ $shortcode ]['end']      = microtime( true );
		self::$timing[ $shortcode ]['duration'] = self::$timing[ $shortcode ]['end'] - self::$timing[ $shortcode ]['start'];

		return self::$timing[ $shortcode ]['duration'];
	}

	/**
	 * Analyze shortcode performance
	 */
	public static function analyze_shortcode( string $shortcode, string $example ): array {
		self::start_monitoring();
		self::start_timing( $shortcode );

		$start_memory = memory_get_usage();
		$start_peak   = memory_get_peak_usage();

		try {
			$output  = do_shortcode( $example );
			$success = true;
			$error   = null;
		} catch ( Exception $e ) {
			$output  = '';
			$success = false;
			$error   = $e->getMessage();
		}

		$duration         = self::end_timing( $shortcode );
		$performance_data = self::stop_monitoring();

		$end_memory = memory_get_usage();
		$end_peak   = memory_get_peak_usage();

		return array(
			'shortcode'      => $shortcode,
			'success'        => $success,
			'error'          => $error,
			'duration'       => $duration,
			'memory_usage'   => $end_memory - $start_memory,
			'peak_memory'    => $end_peak - $start_peak,
			'output_size'    => strlen( $output ),
			'queries'        => $performance_data['total_queries'],
			'unique_queries' => $performance_data['unique_queries'],
			'query_details'  => $performance_data['queries'],
		);
	}

	/**
	 * Get performance recommendations
	 */
	public static function get_recommendations( array $analysis ): array {
		$recommendations = array();

		// Duration recommendations
		if ( $analysis['duration'] > 1.0 ) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %.3f placeholder. */
					esc_html__( 'Shortcode running very slow (%.3fs). Query optimization required.', 'mhm-rentiva' ),
					round( $analysis['duration'], 3 )
				),
			);
		} elseif ( $analysis['duration'] > 0.5 ) {
			$recommendations[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %.3f placeholder. */
					esc_html__( 'Shortcode running at medium speed (%.3fs). Improvement recommended.', 'mhm-rentiva' ),
					round( $analysis['duration'], 3 )
				),
			);
		}

		// Query recommendations
		if ( $analysis['queries'] > 10 ) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %d placeholder. */
					esc_html__( 'Too many database queries (%d). Check for N+1 problem.', 'mhm-rentiva' ),
					$analysis['queries']
				),
			);
		} elseif ( $analysis['queries'] > 5 ) {
			$recommendations[] = array(
				'type'    => 'info',
				/* translators: %d placeholder. */
				'message' => __( 'Medium level database queries (%d). Consider optimization.', 'mhm-rentiva' ),
			);
		}

		// Memory recommendations
		if ( $analysis['memory_usage'] > 1024 * 1024 ) { // 1MB
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %.2f placeholder. */
					esc_html__( 'High memory usage (%.2f MB). Check for memory leak.', 'mhm-rentiva' ),
					round( $analysis['memory_usage'] / 1024 / 1024, 2 )
				),
			);
		}

		// Output size recommendations
		if ( $analysis['output_size'] > 100000 ) { // 100KB
			$recommendations[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %.2f placeholder. */
					esc_html__( 'Large output size (%.2f KB). Consider lazy loading.', 'mhm-rentiva' ),
					round( $analysis['output_size'] / 1024, 2 )
				),
			);
		}

		return $recommendations;
	}

	/**
	 * Generate performance report
	 */
	public static function generate_performance_report( array $analyses ): array {
		$total_shortcodes = count( $analyses );
		$successful       = count( array_filter( $analyses, fn( $a ) => $a['success'] ) );
		$total_duration   = array_sum( array_column( $analyses, 'duration' ) );
		$total_queries    = array_sum( array_column( $analyses, 'queries' ) );
		$total_memory     = array_sum( array_column( $analyses, 'memory_usage' ) );

		$slow_shortcodes       = array_filter( $analyses, fn( $a ) => $a['duration'] > 0.5 );
		$high_query_shortcodes = array_filter( $analyses, fn( $a ) => $a['queries'] > 5 );

		return array(
			'summary'   => array(
				'total_shortcodes'      => $total_shortcodes,
				'successful_shortcodes' => $successful,
				'success_rate'          => $total_shortcodes > 0 ? round( ( $successful / $total_shortcodes ) * 100, 2 ) : 0,
				'total_duration'        => round( $total_duration, 3 ),
				'average_duration'      => $total_shortcodes > 0 ? round( $total_duration / $total_shortcodes, 3 ) : 0,
				'total_queries'         => $total_queries,
				'average_queries'       => $total_shortcodes > 0 ? round( $total_queries / $total_shortcodes, 2 ) : 0,
				'total_memory'          => round( $total_memory / 1024 / 1024, 2 ), // MB
			),
			'issues'    => array(
				'slow_shortcodes'       => count( $slow_shortcodes ),
				'high_query_shortcodes' => count( $high_query_shortcodes ),
			),
			'analyses'  => $analyses,
			'timestamp' => current_time( 'mysql' ),
		);
	}
}
