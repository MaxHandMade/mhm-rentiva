<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Monitoring manager intentionally performs bounded health and queue queries.

namespace MHMRentiva\Admin\Messages\Monitoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance and log monitoring manager
 */
final class MonitoringManager {


	private static bool $initialized = false;

	/**
	 * Start monitoring system
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		// Start logger
		MessageLogger::init();

		// Start performance monitor
		add_action( 'wp_ajax_mhm_get_performance_report', array( PerformanceMonitor::class, 'ajax_get_performance_data' ) );
		add_action( 'wp_ajax_mhm_clear_performance_data', array( self::class, 'ajax_clear_performance_data' ) );

		// Add dashboard widgets
		add_action( 'wp_dashboard_setup', array( self::class, 'add_dashboard_widgets' ) );

		// Add admin menu
		add_action( 'admin_menu', array( self::class, 'add_admin_menu' ) );

		// Start performance monitoring
		add_action( 'init', array( self::class, 'start_performance_monitoring' ), 1 );

		// Check system health
		add_action( 'wp_ajax_mhm_get_system_health', array( self::class, 'ajax_get_system_health' ) );

		self::$initialized = true;
	}

	/**
	 * Start performance monitoring
	 */
	public static function start_performance_monitoring(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug check for conditional monitoring.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( is_admin() && '' !== $page && strpos( $page, 'mhm-rentiva' ) !== false ) {
			PerformanceMonitor::start_monitoring();
		}
	}

	/**
	 * Add dashboard widgets
	 */
	public static function add_dashboard_widgets(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'mhm_messages_performance_widget',
			__( 'Message System Performance', 'mhm-rentiva' ),
			array( PerformanceMonitor::class, 'render_performance_widget' )
		);

		wp_add_dashboard_widget(
			'mhm_messages_logs_widget',
			__( 'Message System Logs', 'mhm-rentiva' ),
			array( MessageLogger::class, 'render_log_widget' )
		);
	}

	/**
	 * Add admin menu
	 */
	public static function add_admin_menu(): void {
		add_submenu_page(
			'mhm-rentiva-messages',
			__( 'System Monitoring', 'mhm-rentiva' ),
			__( 'System Monitoring', 'mhm-rentiva' ),
			'manage_options',
			'mhm-rentiva-messages-monitoring',
			array( self::class, 'render_monitoring_page' )
		);

		add_submenu_page(
			'mhm-rentiva-messages',
			__( 'Log View', 'mhm-rentiva' ),
			__( 'Log View', 'mhm-rentiva' ),
			'manage_options',
			'mhm-rentiva-messages-logs',
			array( self::class, 'render_logs_page' )
		);
	}

	/**
	 * Monitoring page render
	 */
	public static function render_monitoring_page(): void {
		$performance_stats = PerformanceMonitor::get_performance_stats();
		$log_stats         = MessageLogger::get_log_stats();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Message System Monitoring', 'mhm-rentiva' ); ?></h1>

			<div class="mhm-monitoring-dashboard">
				<div class="monitoring-cards">
					<!-- Performance Card -->
					<div class="monitoring-card">
						<div class="card-header">
							<h3><?php esc_html_e( 'Performance Status', 'mhm-rentiva' ); ?></h3>
							<button type="button" class="button button-small" id="refresh-performance-btn">
								<?php esc_html_e( 'Refresh', 'mhm-rentiva' ); ?>
							</button>
						</div>

						<div class="card-content">
							<div class="stat-grid">
								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Active Timer:', 'mhm-rentiva' ); ?></span>
									<span class="stat-value"><?php echo esc_html( $performance_stats['active_timers'] ); ?></span>
								</div>

								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Query Count:', 'mhm-rentiva' ); ?></span>
									<span class="stat-value"><?php echo esc_html( $performance_stats['query_count'] ); ?></span>
								</div>

								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Memory Usage:', 'mhm-rentiva' ); ?></span>
									<span class="stat-value"><?php echo esc_html( size_format( $performance_stats['current_memory'] ) ); ?></span>
								</div>

								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Peak Memory:', 'mhm-rentiva' ); ?></span>
									<span class="stat-value"><?php echo esc_html( size_format( $performance_stats['peak_memory'] ) ); ?></span>
								</div>
							</div>
						</div>

						<div class="card-actions">
							<button type="button" class="button" id="generate-performance-report-btn">
								<?php esc_html_e( 'Generate Detailed Report', 'mhm-rentiva' ); ?>
							</button>
						</div>
					</div>

					<!-- Log Card -->
					<div class="monitoring-card">
						<div class="card-header">
							<h3><?php esc_html_e( 'Log Status', 'mhm-rentiva' ); ?></h3>
							<button type="button" class="button button-small" id="refresh-log-data-btn">
								<?php esc_html_e( 'Refresh', 'mhm-rentiva' ); ?>
							</button>
						</div>

						<div class="card-content">
							<div class="stat-grid">
								<div class="stat-item">
									<span class="stat-label"><?php esc_html_e( 'Total Logs:', 'mhm-rentiva' ); ?></span>
									<span class="stat-value"><?php echo esc_html( number_format( $log_stats['total_logs'] ) ); ?></span>
								</div>

								<?php foreach ( $log_stats['level_stats'] as $level_stat ) : ?>
									<div class="stat-item">
										<span class="stat-label">
											<?php echo esc_html( strtoupper( $level_stat['level'] ) . ':' ); ?>
										</span>
										<span class="stat-value log-count-<?php echo esc_attr( $level_stat['level'] ); ?>">
											<?php echo esc_html( $level_stat['count'] ); ?>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="card-actions">
							<button type="button" class="button" id="view-logs-btn">
								<?php esc_html_e( 'View Logs', 'mhm-rentiva' ); ?>
							</button>
						</div>
					</div>

					<!-- System Health Card -->
					<div class="monitoring-card">
						<div class="card-header">
							<h3><?php esc_html_e( 'System Health', 'mhm-rentiva' ); ?></h3>
							<button type="button" class="button button-small" id="check-system-health-btn">
								<?php esc_html_e( 'Check', 'mhm-rentiva' ); ?>
							</button>
						</div>

						<div class="card-content">
							<div id="system-health-content">
								<p><?php esc_html_e( 'Checking system health...', 'mhm-rentiva' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- Performance Charts -->
				<div class="monitoring-section">
					<h3><?php esc_html_e( 'Performance Trends', 'mhm-rentiva' ); ?></h3>
					<div class="chart-container">
						<canvas id="performance-chart" width="400" height="200"></canvas>
					</div>
				</div>

				<!-- System Recommendations -->
				<div class="monitoring-section">
					<h3><?php esc_html_e( 'System Recommendations', 'mhm-rentiva' ); ?></h3>
					<div id="system-recommendations">
						<p><?php esc_html_e( 'Loading recommendations...', 'mhm-rentiva' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- CSS will be moved to a separate file -->

		<!-- JavaScript will be moved to a separate file -->
		<?php
	}

	/**
	 * Log page render
	 */
	public static function render_logs_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Message System Logs', 'mhm-rentiva' ); ?></h1>

			<div class="mhm-logs-page">
				<!-- Log Filters -->
				<div class="log-filters">
					<form id="log-filters-form">
						<div class="filter-row">
							<div class="filter-group">
								<label for="log-level"><?php esc_html_e( 'Log Level:', 'mhm-rentiva' ); ?></label>
								<select id="log-level" name="level">
									<option value=""><?php esc_html_e( 'All', 'mhm-rentiva' ); ?></option>
									<option value="debug"><?php esc_html_e( 'Debug', 'mhm-rentiva' ); ?></option>
									<option value="info"><?php esc_html_e( 'Info', 'mhm-rentiva' ); ?></option>
									<option value="warning"><?php esc_html_e( 'Warning', 'mhm-rentiva' ); ?></option>
									<option value="error"><?php esc_html_e( 'Error', 'mhm-rentiva' ); ?></option>
									<option value="critical"><?php esc_html_e( 'Critical', 'mhm-rentiva' ); ?></option>
								</select>
							</div>

							<div class="filter-group">
								<label for="log-search"><?php esc_html_e( 'Search:', 'mhm-rentiva' ); ?></label>
								<input type="text" id="log-search" name="search" placeholder="<?php esc_attr_e( 'Message or context search...', 'mhm-rentiva' ); ?>">
							</div>

							<div class="filter-group">
								<button type="submit" class="button button-primary">
									<?php esc_html_e( 'Filter', 'mhm-rentiva' ); ?>
								</button>

								<button type="button" class="button" id="clear-log-filters-btn">
									<?php esc_html_e( 'Clear', 'mhm-rentiva' ); ?>
								</button>
							</div>
						</div>
					</form>
				</div>

				<!-- Log List -->
				<div class="log-list-container">
					<div id="log-list">
						<p><?php esc_html_e( 'Loading logs...', 'mhm-rentiva' ); ?></p>
					</div>

					<div class="log-pagination">
						<button type="button" class="button" id="prev-page" disabled>
							<?php esc_html_e( 'Previous', 'mhm-rentiva' ); ?>
						</button>

						<span id="page-info"><?php esc_html_e( 'Page 1', 'mhm-rentiva' ); ?></span>

						<button type="button" class="button" id="next-page">
							<?php esc_html_e( 'Next', 'mhm-rentiva' ); ?>
						</button>
					</div>
				</div>

				<!-- Log Operations -->
				<div class="log-actions">
					<button type="button" class="button" id="clear-old-logs-btn">
						<?php esc_html_e( 'Clear Logs Older Than 7 Days', 'mhm-rentiva' ); ?>
					</button>

					<button type="button" class="button" id="export-logs-btn">
						<?php esc_html_e( 'Export Logs', 'mhm-rentiva' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- JavaScript will be moved to a separate file -->
		<?php
	}

	/**
	 * AJAX - Performance data clear
	 */
	public static function ajax_clear_performance_data(): void {
		check_ajax_referer( 'mhm_messages_performance', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access', 'mhm-rentiva' ) );
		}

		PerformanceMonitor::clear_performance_data();
		wp_send_json_success( esc_html__( 'Performance data cleared', 'mhm-rentiva' ) );
	}

	/**
	 * AJAX - Check system health
	 */
	public static function ajax_get_system_health(): void {
		check_ajax_referer( 'mhm_messages_performance', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access', 'mhm-rentiva' ) );
		}

		$checks = array();

		// WordPress version check
		global $wp_version;
		if ( version_compare( $wp_version, '5.0', '>=' ) ) {
			$checks[] = array(
				'status'  => 'ok',
				/* translators: %s: WordPress version */
				'message' => sprintf( /* translators: %s: WordPress version */__( 'WordPress version is compatible: %s', 'mhm-rentiva' ), $wp_version ),
			);
		} else {
			$checks[] = array(
				'status'  => 'warning',
				/* translators: %s: WordPress version */
				'message' => sprintf( /* translators: %s: WordPress version */__( 'WordPress version is outdated: %s (5.0+ recommended)', 'mhm-rentiva' ), $wp_version ),
			);
		}

		// PHP version check
		if ( version_compare( PHP_VERSION, '7.4', '>=' ) ) {
			$checks[] = array(
				'status'  => 'ok',
				/* translators: %s: PHP version */
				'message' => sprintf( /* translators: %s: PHP version */__( 'PHP version is compatible: %s', 'mhm-rentiva' ), PHP_VERSION ),
			);
		} else {
			$checks[] = array(
				'status'  => 'warning',
				/* translators: %s: PHP version */
				'message' => sprintf( /* translators: %s: PHP version */__( 'PHP version is outdated: %s (7.4+ recommended)', 'mhm-rentiva' ), PHP_VERSION ),
			);
		}

		// Memory limit check
		$memory_limit = ini_get( 'memory_limit' );
		$memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
		if ( $memory_bytes >= 256 * 1024 * 1024 ) { // 256MB
			$checks[] = array(
				'status'  => 'ok',
				/* translators: %s: memory limit */
				'message' => sprintf( /* translators: %s: memory limit */__( 'Memory limit is sufficient: %s', 'mhm-rentiva' ), $memory_limit ),
			);
		} else {
			$checks[] = array(
				'status'  => 'warning',
				/* translators: %s: memory limit */
				'message' => sprintf( /* translators: %s: memory limit */__( 'Memory limit is low: %s (256MB+ recommended)', 'mhm-rentiva' ), $memory_limit ),
			);
		}

		// Database connection check
		global $wpdb;
		// WPCS: ignore WordPress.DB.PreparedSQL.NotPrepared -- Constant query.
		$db_check = $wpdb->get_var( 'SELECT 1' );
		if ( $db_check === '1' ) {
			$checks[] = array(
				'status'  => 'ok',
				'message' => esc_html__( 'Database connection active', 'mhm-rentiva' ),
			);
		} else {
			$checks[] = array(
				'status'  => 'error',
				'message' => esc_html__( 'Database connection issue', 'mhm-rentiva' ),
			);
		}

		// Plugin files check
		$plugin_files = array(
			'src/Admin/Messages/Settings/MessagesSettings.php',
			'src/Admin/Messages/Core/MessageCache.php',
			'src/Admin/Messages/Core/MessageQueryHelper.php',
		);

		foreach ( $plugin_files as $file ) {
			$file_path = MHM_RENTIVA_PLUGIN_PATH . $file;
			if ( file_exists( $file_path ) ) {
				$checks[] = array(
					'status'  => 'ok',
					/* translators: %s: filename */
					'message' => sprintf( /* translators: %s: filename */__( 'File exists: %s', 'mhm-rentiva' ), basename( $file ) ),
				);
			} else {
				$checks[] = array(
					'status'  => 'error',
					/* translators: %s: filename */
					'message' => sprintf( /* translators: %s: filename */__( 'File not found: %s', 'mhm-rentiva' ), basename( $file ) ),
				);
			}
		}

		// Cache status check
		if ( class_exists( 'MHM\\Rentiva\\Admin\\Messages\\Core\\MessageCache' ) ) {
			$checks[] = array(
				'status'  => 'ok',
				'message' => esc_html__( 'Cache system active', 'mhm-rentiva' ),
			);
		} else {
			$checks[] = array(
				'status'  => 'error',
				'message' => esc_html__( 'Cache system not found', 'mhm-rentiva' ),
			);
		}

		wp_send_json_success( array( 'checks' => $checks ) );
	}
}
