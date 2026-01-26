<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Monitoring;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Messaging system performance monitoring
 */
final class PerformanceMonitor
{

	private static array $timers            = array();
	private static array $queries           = array();
	private static array $memory_usage      = array();
	private static bool $monitoring_enabled = true;

	/**
	 * Start performance monitoring
	 */
	public static function start_monitoring(): void
	{
		self::$monitoring_enabled = true;
		self::start_timer('total_execution');
		self::record_memory_usage('start');

		// WordPress query monitoring
		add_filter('log_query_custom_data', array(self::class, 'log_query_data'));
	}

	/**
	 * Stop performance monitoring
	 */
	public static function stop_monitoring(): array
	{
		if (! self::$monitoring_enabled) {
			return array();
		}

		self::end_timer('total_execution');
		self::record_memory_usage('end');

		$results = array(
			'execution_time' => self::get_timer_result('total_execution'),
			'memory_usage'   => self::get_memory_usage(),
			'query_count'    => count(self::$queries),
			'query_time'     => array_sum(array_column(self::$queries, 'time')),
			'queries'        => self::$queries,
			'peak_memory'    => memory_get_peak_usage(true),
			'current_memory' => memory_get_usage(true),
		);

		self::log_performance_data($results);

		return $results;
	}

	/**
	 * Start timer
	 */
	public static function start_timer(string $name): void
	{
		if (! self::$monitoring_enabled) {
			return;
		}

		self::$timers[$name] = array(
			'start'    => microtime(true),
			'end'      => null,
			'duration' => null,
		);
	}

	/**
	 * End timer
	 */
	public static function end_timer(string $name): ?float
	{
		if (! self::$monitoring_enabled || ! isset(self::$timers[$name])) {
			return null;
		}

		$end_time = microtime(true);
		$duration = $end_time - self::$timers[$name]['start'];

		self::$timers[$name]['end']      = $end_time;
		self::$timers[$name]['duration'] = $duration;

		return $duration;
	}

	/**
	 * Get timer result
	 */
	public static function get_timer_result(string $name): ?float
	{
		return self::$timers[$name]['duration'] ?? null;
	}

	/**
	 * Record memory usage
	 */
	public static function record_memory_usage(string $stage): void
	{
		if (! self::$monitoring_enabled) {
			return;
		}

		self::$memory_usage[$stage] = array(
			'memory_usage' => memory_get_usage(true),
			'peak_memory'  => memory_get_peak_usage(true),
			'timestamp'    => microtime(true),
		);
	}

	/**
	 * Get memory usage data
	 */
	public static function get_memory_usage(): array
	{
		return self::$memory_usage;
	}

	/**
	 * Log query data
	 */
	public static function log_query_data(array $data): array
	{
		if (! self::$monitoring_enabled) {
			return $data;
		}

		$backtrace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		$query_source = self::find_query_source($backtrace);

		self::$queries[] = array(
			'query'     => $data['query'] ?? '',
			'time'      => $data['query_time'] ?? 0,
			'source'    => $query_source,
			'timestamp' => microtime(true),
		);

		return $data;
	}

	/**
	 * Find query source
	 */
	private static function find_query_source(array $backtrace): string
	{
		foreach ($backtrace as $trace) {
			if (isset($trace['file'])) {
				$file = $trace['file'];

				// Filter files related to MHM Messages
				if (strpos($file, 'mhm-rentiva') !== false && strpos($file, 'Messages') !== false) {
					return basename($file) . ':' . ($trace['line'] ?? 'unknown');
				}
			}
		}

		return 'unknown';
	}

	/**
	 * Log performance data
	 */
	private static function log_performance_data(array $data): void
	{
		if (! defined('WP_DEBUG') || ! WP_DEBUG) {
			return;
		}

		$log_entry = array(
			'timestamp'      => current_time('mysql'),
			'execution_time' => round($data['execution_time'] * 1000, 2) . 'ms',
			'memory_peak'    => size_format($data['peak_memory']),
			'memory_current' => size_format($data['current_memory']),
			'query_count'    => $data['query_count'],
			'query_time'     => round($data['query_time'] * 1000, 2) . 'ms',
			'slow_queries'   => self::find_slow_queries($data['queries']),
		);

		error_log('[MHM Messages Performance] ' . json_encode($log_entry));
	}

	/**
	 * Find slow queries
	 */
	private static function find_slow_queries(array $queries): array
	{
		$slow_threshold = 0.1; // 100ms
		$slow_queries   = array();

		foreach ($queries as $query) {
			if ($query['time'] > $slow_threshold) {
				$slow_queries[] = array(
					'time'   => round($query['time'] * 1000, 2) . 'ms',
					'source' => $query['source'],
					'query'  => substr($query['query'], 0, 100) . '...',
				);
			}
		}

		return $slow_queries;
	}

	/**
	 * Generate performance report
	 */
	public static function generate_performance_report(): array
	{
		$data = self::stop_monitoring();

		return array(
			'summary'         => array(
				'total_time'     => round($data['execution_time'] * 1000, 2) . 'ms',
				'memory_peak'    => size_format($data['peak_memory']),
				'query_count'    => $data['query_count'],
				'query_time'     => round($data['query_time'] * 1000, 2) . 'ms',
				'avg_query_time' => $data['query_count'] > 0 ? round(($data['query_time'] / $data['query_count']) * 1000, 2) . 'ms' : '0ms',
			),
			'recommendations' => self::generate_recommendations($data),
			'details'         => $data,
		);
	}

	/**
	 * Generate performance recommendations
	 */
	private static function generate_recommendations(array $data): array
	{
		$recommendations = array();

		// Slow query recommendations
		if ($data['query_count'] > 10) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => 'Too many database queries. Increase cache usage.',
				'action'  => 'Check if cache system is active',
			);
		}

		if ($data['query_time'] > 0.5) {
			$recommendations[] = array(
				'type'    => 'error',
				'message' => 'Database queries are too slow. Optimization needed.',
				'action'  => 'Optimize queries or add indexes',
			);
		}

		// Memory recommendations
		$memory_mb = $data['peak_memory'] / (1024 * 1024);
		if ($memory_mb > 64) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => 'High memory usage detected.',
				'action'  => 'Optimize memory usage',
			);
		}

		// General performance recommendations
		if ($data['execution_time'] > 1.0) {
			$recommendations[] = array(
				'type'    => 'warning',
				'message' => 'Page load time is slow.',
				'action'  => 'Do general performance optimization',
			);
		}

		return $recommendations;
	}

	/**
	 * Clear performance data
	 */
	public static function clear_performance_data(): void
	{
		self::$timers       = array();
		self::$queries      = array();
		self::$memory_usage = array();
	}

	/**
	 * Set monitoring enabled/disabled
	 */
	public static function set_monitoring_enabled(bool $enabled): void
	{
		self::$monitoring_enabled = $enabled;
	}

	/**
	 * Get performance statistics
	 */
	public static function get_performance_stats(): array
	{
		return array(
			'monitoring_enabled' => self::$monitoring_enabled,
			'active_timers'      => count(self::$timers),
			'query_count'        => count(self::$queries),
			'memory_usage'       => self::$memory_usage,
			'current_memory'     => memory_get_usage(true),
			'peak_memory'        => memory_get_peak_usage(true),
		);
	}

	/**
	 * AJAX performance endpoint
	 */
	public static function ajax_get_performance_data(): void
	{
		check_ajax_referer('mhm_messages_performance', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(esc_html__('Unauthorized access', 'mhm-rentiva'));
		}

		$report = self::generate_performance_report();
		wp_send_json_success($report);
	}

	/**
	 * Performance dashboard widget
	 */
	public static function render_performance_widget(): void
	{
		$stats = self::get_performance_stats();

?>
		<div class="mhm-performance-widget">
			<h4><?php esc_html_e('Message System Performance', 'mhm-rentiva'); ?></h4>

			<div class="performance-stats">
				<div class="stat-item">
					<span class="label"><?php esc_html_e('Active Timers:', 'mhm-rentiva'); ?></span>
					<span class="value"><?php echo esc_html($stats['active_timers']); ?></span>
				</div>

				<div class="stat-item">
					<span class="label"><?php esc_html_e('Query Count:', 'mhm-rentiva'); ?></span>
					<span class="value"><?php echo esc_html($stats['query_count']); ?></span>
				</div>

				<div class="stat-item">
					<span class="label"><?php esc_html_e('Memory Usage:', 'mhm-rentiva'); ?></span>
					<span class="value"><?php echo esc_html(size_format($stats['current_memory'])); ?></span>
				</div>

				<div class="stat-item">
					<span class="label"><?php esc_html_e('Peak Memory:', 'mhm-rentiva'); ?></span>
					<span class="value"><?php echo esc_html(size_format($stats['peak_memory'])); ?></span>
				</div>
			</div>

			<div class="performance-actions">
				<button type="button" class="button button-small" id="clear-performance-data-btn">
					<?php esc_html_e('Clear Data', 'mhm-rentiva'); ?>
				</button>

				<button type="button" class="button button-small" id="generate-report-btn">
					<?php esc_html_e('Generate Report', 'mhm-rentiva'); ?>
				</button>
			</div>
		</div>

		<!-- CSS will be moved to separate file -->

		<!-- JavaScript will be moved to separate file -->
<?php
	}
}
