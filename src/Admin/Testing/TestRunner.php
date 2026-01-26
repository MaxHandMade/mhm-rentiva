<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ 4. STAGE - Test Runner (Runs All Tests)
 */
final class TestRunner {



	/**
	 * Run all test suites
	 */
	public static function run_all_suites(): array {
		$start_time = microtime( true );

		$results = array(
			'activation'  => ActivationTest::run_all_tests(),
			'security'    => SecurityTest::run_all_tests(),
			'functional'  => FunctionalTest::run_all_tests(),
			'performance' => PerformanceTest::run_all_tests(),
		);

		// Add IntegrationTest if available
		if ( class_exists( 'MHMRentiva\\Admin\\Testing\\IntegrationTest' ) ) {
			$results['integration'] = IntegrationTest::run_all_tests();
		}

		$execution_time = microtime( true ) - $start_time;

		// Analyze results
		$summary = self::analyze_results( $results );

		return array(
			'results'        => $results,
			'summary'        => $summary,
			'execution_time' => round( $execution_time, 3 ),
			'timestamp'      => current_time( 'mysql' ),
		);
	}

	/**
	 * Analyze test results
	 */
	public static function analyze_results( array $results ): array {
		$total_tests = 0;
		$passed      = 0;
		$failed      = 0;
		$warnings    = 0;
		$skipped     = 0;

		foreach ( $results as $suite => $tests ) {
			foreach ( $tests as $test ) {
				++$total_tests;

				switch ( $test['status'] ) {
					case 'pass':
						++$passed;
						break;
					case 'fail':
						++$failed;
						break;
					case 'warning':
						++$warnings;
						break;
					case 'skip':
						++$skipped;
						break;
				}
			}
		}

		$pass_rate      = $total_tests > 0 ? ( $passed / $total_tests ) * 100 : 0;
		$overall_status = self::determine_overall_status( $pass_rate, $failed );

		return array(
			'total_tests'    => $total_tests,
			'passed'         => $passed,
			'failed'         => $failed,
			'warnings'       => $warnings,
			'skipped'        => $skipped,
			'pass_rate'      => round( $pass_rate, 1 ),
			'overall_status' => $overall_status,
		);
	}

	/**
	 * Genel test durumunu belirle
	 */
	public static function determine_overall_status( float $pass_rate, int $failed ): string {
		if ( $failed > 0 ) {
			return 'fail';
		}

		if ( $pass_rate >= 95 ) {
			return 'excellent';
		} elseif ( $pass_rate >= 85 ) {
			return 'good';
		} elseif ( $pass_rate >= 70 ) {
			return 'acceptable';
		} else {
			return 'poor';
		}
	}

	/**
	 * Render test results as HTML
	 */
	public static function render_html_report( array $test_results ): string {
		$summary = $test_results['summary'];

		ob_start();
		?>
		<div class="mhm-test-report">
			<h1>🧪 <?php esc_html_e( 'MHM Rentiva - Test Report', 'mhm-rentiva' ); ?></h1>

			<!-- Summary -->
			<div class="test-summary test-summary-<?php echo esc_attr( $summary['overall_status'] ); ?>">
				<h2>📊 <?php esc_html_e( 'Summary', 'mhm-rentiva' ); ?></h2>
				<div class="summary-stats">
					<div class="stat">
						<span class="stat-label"><?php esc_html_e( 'Total Tests:', 'mhm-rentiva' ); ?></span>
						<span class="stat-value"><?php echo esc_html( $summary['total_tests'] ); ?></span>
					</div>
					<div class="stat stat-pass">
						<span class="stat-label"><?php esc_html_e( 'Successful:', 'mhm-rentiva' ); ?></span>
						<span class="stat-value"><?php echo esc_html( $summary['passed'] ); ?></span>
					</div>
					<div class="stat stat-fail">
						<span class="stat-label"><?php esc_html_e( 'Failed:', 'mhm-rentiva' ); ?></span>
						<span class="stat-value"><?php echo esc_html( $summary['failed'] ); ?></span>
					</div>
					<div class="stat stat-warning">
						<span class="stat-label"><?php esc_html_e( 'Warnings:', 'mhm-rentiva' ); ?></span>
						<span class="stat-value"><?php echo esc_html( $summary['warnings'] ); ?></span>
					</div>
					<div class="stat">
						<span class="stat-label"><?php esc_html_e( 'Success Rate:', 'mhm-rentiva' ); ?></span>
						<span class="stat-value"><?php echo esc_html( $summary['pass_rate'] ); ?>%</span>
					</div>
				</div>

				<div class="overall-status">
					<?php
					$status_icon = match ( $summary['overall_status'] ) {
						'excellent' => '🎉',
						'good' => '✅',
						'acceptable' => '⚠️',
						'poor' => '❌',
						'fail' => '🚨',
						default => '❓'
					};

					$status_text = match ( $summary['overall_status'] ) {
						'excellent' => __( 'Excellent', 'mhm-rentiva' ),
						'good' => __( 'Good', 'mhm-rentiva' ),
						'acceptable' => __( 'Acceptable', 'mhm-rentiva' ),
						'poor' => __( 'Poor', 'mhm-rentiva' ),
						'fail' => __( 'Failed', 'mhm-rentiva' ),
						default => __( 'Unknown', 'mhm-rentiva' )
					};
		?>
					<h3><?php echo esc_html( $status_icon ); ?> <?php esc_html_e( 'Overall Status:', 'mhm-rentiva' ); ?> <?php echo esc_html( $status_text ); ?></h3>
				</div>
			</div>

			<!-- Detailed Results -->
			<?php foreach ( $test_results['results'] as $suite_name => $suite_tests ) : ?>
				<div class="test-suite">
					<h2>📋 <?php echo esc_html( ucfirst( $suite_name ) ); ?> <?php esc_html_e( 'Tests', 'mhm-rentiva' ); ?></h2>

					<table class="widefat">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Test', 'mhm-rentiva' ); ?></th>
								<th><?php esc_html_e( 'Status', 'mhm-rentiva' ); ?></th>
								<th><?php esc_html_e( 'Message', 'mhm-rentiva' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $suite_tests as $test ) : ?>
								<tr class="test-row-<?php echo esc_attr( $test['status'] ); ?>">
									<td><strong><?php echo esc_html( $test['test'] ); ?></strong></td>
									<td>
										<span class="test-badge test-badge-<?php echo esc_attr( $test['status'] ); ?>">
											<?php echo esc_html( strtoupper( $test['status'] ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $test['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>

			<!-- Execution Info -->
			<div class="test-info">
				<p><strong>⏱️ <?php esc_html_e( 'Execution Time:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $test_results['execution_time'] ); ?> <?php esc_html_e( 'seconds', 'mhm-rentiva' ); ?></p>
				<p><strong>📅 <?php esc_html_e( 'Test Date:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $test_results['timestamp'] ); ?></p>
			</div>

			<style>
				.mhm-test-report {
					max-width: 1200px;
					margin: 20px auto;
					padding: 20px;
					background: #fff;
				}

				.test-summary {
					padding: 20px;
					margin: 20px 0;
					background: #f5f5f5;
					border-radius: 5px;
				}

				.test-summary-excellent {
					border-left: 4px solid #46b450;
				}

				.test-summary-good {
					border-left: 4px solid #00a0d2;
				}

				.test-summary-acceptable {
					border-left: 4px solid #ffb900;
				}

				.test-summary-poor,
				.test-summary-fail {
					border-left: 4px solid #dc3232;
				}

				.summary-stats {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
					gap: 15px;
					margin: 15px 0;
				}

				.stat {
					padding: 10px;
					background: #fff;
					border-radius: 3px;
					text-align: center;
				}

				.stat-pass {
					border-left: 3px solid #46b450;
				}

				.stat-fail {
					border-left: 3px solid #dc3232;
				}

				.stat-warning {
					border-left: 3px solid #ffb900;
				}

				.stat-label {
					display: block;
					font-size: 12px;
					color: #666;
					margin-bottom: 5px;
				}

				.stat-value {
					display: block;
					font-size: 24px;
					font-weight: bold;
				}

				.test-suite {
					margin: 30px 0;
				}

				.test-row-pass {
					background-color: #f0f8f0;
				}

				.test-row-fail {
					background-color: #fff0f0;
				}

				.test-row-warning {
					background-color: #fffbf0;
				}

				.test-row-skip {
					background-color: #f9f9f9;
				}

				.test-badge {
					display: inline-block;
					padding: 4px 10px;
					border-radius: 3px;
					font-size: 11px;
					font-weight: bold;
				}

				.test-badge-pass {
					background: #46b450;
					color: #fff;
				}

				.test-badge-fail {
					background: #dc3232;
					color: #fff;
				}

				.test-badge-warning {
					background: #ffb900;
					color: #000;
				}

				.test-badge-skip {
					background: #999;
					color: #fff;
				}

				.test-info {
					margin-top: 30px;
					padding: 15px;
					background: #f5f5f5;
					border-radius: 3px;
				}

				.overall-status {
					margin-top: 20px;
					text-align: center;
				}

				.overall-status h3 {
					font-size: 24px;
					margin: 0;
				}
			</style>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Return test results as JSON
	 */
	public static function get_json_report( array $test_results ): string {
		return wp_json_encode( $test_results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Save test results to file
	 */
	public static function save_report_to_file( array $test_results, string $format = 'html' ): string {
		$upload_dir  = wp_upload_dir();
		$reports_dir = trailingslashit( $upload_dir['basedir'] ) . 'mhm-rentiva-reports/';

		if ( ! file_exists( $reports_dir ) ) {
			wp_mkdir_p( $reports_dir );
		}

		$filename = 'test-report-' . gmdate( 'Y-m-d-H-i-s' ) . '.' . $format;
		$filepath = $reports_dir . $filename;

		if ( $format === 'html' ) {
			$content = self::render_html_report( $test_results );
		} else {
			$content = self::get_json_report( $test_results );
		}

		file_put_contents( $filepath, $content );

		return $filepath;
	}
}
