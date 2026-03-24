<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Cron Job Monitor tab
 *
 * Provides an interface to monitor, refresh, and manually trigger plugin-specific scheduled tasks.
 */
final class CronMonitorRenderer extends AbstractTabRenderer {


	public function __construct() {
		parent::__construct(
			__( 'Cron Job Monitor', 'mhm-rentiva' ),
			'cron-monitor'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_header_actions(): array {
		return array(
			array(
				'text'  => __( 'Refresh List', 'mhm-rentiva' ),
				'url'   => '#',
				'class' => 'button button-primary',
				'id'    => 'mhm-refresh-cron-list-btn',
				'icon'  => 'dashicons-update',
			),
			array(
				'text'  => __( 'Run Health Check', 'mhm-rentiva' ),
				'url'   => '#',
				'class' => 'button button-secondary',
				'id'    => 'mhm-test-cron-jobs-btn',
				'icon'  => 'dashicons-admin-tools',
			),
			$this->get_standard_reset_action(),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void {
		$this->enqueue_cron_assets();

		?>
		<div class="mhm-cron-monitor-container" style="margin-top: 20px;">


			<div id="mhm-cron-test-results" style="margin-bottom: 20px; display: none;"></div>
			<div id="mhm-cron-list" class="mhm-cron-list-table-wrapper">
				<div class="mhm-loading-spinner" style="text-align: center; padding: 40px;">
					<span class="dashicons dashicons-update spin"></span>
					<?php esc_html_e( 'Loading scheduled tasks...', 'mhm-rentiva' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue assets for cron monitoring
	 */
	private function enqueue_cron_assets(): void {
		$version = defined( 'MHM_RENTIVA_VERSION' ) ? (string) MHM_RENTIVA_VERSION : '1.0.0';
		$url     = defined( 'MHM_RENTIVA_PLUGIN_URL' ) ? (string) MHM_RENTIVA_PLUGIN_URL : '';

		wp_enqueue_script(
			'mhm-cron-monitor',
			esc_url( $url . 'assets/js/admin/cron-monitor.js' ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'mhm-cron-monitor',
			'mhm_cron_vars',
			array(
				'nonce'                         => wp_create_nonce( 'mhm_cron_monitor' ),
				'run_text'                      => __( 'Run Now', 'mhm-rentiva' ),
				'running_text'                  => __( 'Executing...', 'mhm-rentiva' ),
				'refresh_text'                  => __( 'Refresh List', 'mhm-rentiva' ),
				'loading_text'                  => __( 'Loading...', 'mhm-rentiva' ),
				'success_text'                  => __( 'Task executed successfully.', 'mhm-rentiva' ),
				'error_text'                    => __( 'Operation failed.', 'mhm-rentiva' ),
				'confirm_run_text'              => __( 'This will execute the scheduled task immediately. Continue?', 'mhm-rentiva' ),
				'hook_text'                     => __( 'Hook Name', 'mhm-rentiva' ),
				'name_text'                     => __( 'Internal Name', 'mhm-rentiva' ),
				'description_text'              => __( 'Task Description', 'mhm-rentiva' ),
				'schedule_text'                 => __( 'Recurrence', 'mhm-rentiva' ),
				'next_run_text'                 => __( 'Next Execution', 'mhm-rentiva' ),
				'status_text'                   => __( 'System Status', 'mhm-rentiva' ),
				'actions_text'                  => __( 'Actions', 'mhm-rentiva' ),
				'scheduled_text'                => __( 'Scheduled', 'mhm-rentiva' ),
				'not_scheduled_text'            => __( 'Idle', 'mhm-rentiva' ),
				'testing_text'                  => __( 'Running Health Check...', 'mhm-rentiva' ),
				'test_results_text'             => __( 'Health Report', 'mhm-rentiva' ),
				'active_text'                   => __( 'Active', 'mhm-rentiva' ),
				'registered_not_scheduled_text' => __( 'Pending Schedule', 'mhm-rentiva' ),
				'not_registered_text'           => __( 'Unregistered', 'mhm-rentiva' ),
				'hook_not_registered_text'      => __( 'Hook not found - execution aborted.', 'mhm-rentiva' ),
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function should_wrap_with_form(): bool {
		return false;
	}
}
