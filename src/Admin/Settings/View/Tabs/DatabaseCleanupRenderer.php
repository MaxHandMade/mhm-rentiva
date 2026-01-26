<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Database Cleanup tab
 *
 * Manages database maintenance, table optimization, and recovery backups.
 * Adheres to WordPress security standards for irreversible operations.
 */
final class DatabaseCleanupRenderer extends AbstractTabRenderer {

	public function __construct() {
		parent::__construct(
			__( 'Database Cleanup', 'mhm-rentiva' ),
			'database-cleanup'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void {
		// Dependency check
		if ( ! class_exists( '\MHMRentiva\Admin\Core\Utilities\DatabaseCleaner' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Database maintenance engine not found.', 'mhm-rentiva' ) . '</p></div>';
			return;
		}

		$this->enqueue_cleanup_assets();

		?>
		<div class="mhm-settings-tab-header">
			<div class="mhm-settings-title-group">
				<h2><?php echo esc_html( $this->label ); ?></h2>
				<p class="description"><?php esc_html_e( 'Optimize storage and ensure database integrity by removing orphaned metadata and expired logs.', 'mhm-rentiva' ); ?></p>
			</div>

			<div class="mhm-settings-header-actions">
				<a href="https://maxhandmade.github.io/mhm-rentiva-docs/" target="_blank" class="button button-secondary mhm-docs-btn">
					<span class="dashicons dashicons-book-alt"></span>
					<?php esc_html_e( 'Documentation', 'mhm-rentiva' ); ?>
				</a>
			</div>
		</div>
		<hr class="wp-header-end">

		<div class="mhm-database-cleanup-page" style="margin-top: 20px;">
			<div class="notice notice-warning inline" style="margin-bottom: 25px; padding: 15px;">
				<p>
					<span class="dashicons dashicons-warning" style="color: #dba617; margin-right: 5px;"></span>
					<strong><?php esc_html_e( 'Critical Usage:', 'mhm-rentiva' ); ?></strong>
					<?php esc_html_e( 'Cleanup operations are irreversible. An automated snapshot will be captured before modifications proceed.', 'mhm-rentiva' ); ?>
				</p>
			</div>

			<div class="mhm-cleanup-dashboard" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<?php
				// Handled via separate logic class for high performance
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::render_cleanup_buttons();
				?>
				<div id="mhm-cleanup-results" style="margin-top: 15px;"></div>
			</div>

			<!-- Full Database Backup Section -->
			<div id="mhm-full-backup-section" class="mhm-settings-card" style="margin-top: 40px; background: #fff; border: 1px solid #ccd0d4; padding: 25px; border-radius: 4px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'System Snapshot (Full Backup)', 'mhm-rentiva' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Create a complete archive of all rental-related tables including Posts, Meta, and Custom Definitions.', 'mhm-rentiva' ); ?></p>

				<div class="notice notice-info inline" style="margin: 15px 0;">
					<p>
						<span class="dashicons dashicons-lock" style="color: #2271b1; vertical-align: middle;"></span>
						<strong><?php esc_html_e( 'Secure Storage:', 'mhm-rentiva' ); ?></strong>
						<?php esc_html_e( 'Archives are stored in protected directories with restricted web access, adhering to WP application standards.', 'mhm-rentiva' ); ?>
					</p>
				</div>

				<div class="mhm-backup-actions" style="margin-top: 20px;">
					<button type="button" class="button button-primary button-large" id="mhm-create-full-backup-btn">
						<span class="dashicons dashicons-database-add"></span>
						<?php esc_html_e( 'Initialize Snapshot', 'mhm-rentiva' ); ?>
					</button>

					<button type="button" class="button button-large" id="mhm-refresh-full-backups-btn" style="margin-left: 10px;">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Sync History', 'mhm-rentiva' ); ?>
					</button>
				</div>

				<div id="mhm-full-backup-list" class="mhm-history-list" style="margin-top: 25px;"></div>
			</div>

			<!-- Cleanup Backup Management Section -->
			<div id="mhm-backup-management" class="mhm-settings-card" style="margin-top: 30px; background: #fff; border: 1px solid #ccd0d4; padding: 25px; border-radius: 4px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Incremental Cleanup Backups', 'mhm-rentiva' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Manage and restore localized data clusters captured during recent maintenance routines.', 'mhm-rentiva' ); ?></p>

				<button type="button" class="button button-large" id="mhm-refresh-backups-btn" style="margin-top: 15px;">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Sync Maintenance Logs', 'mhm-rentiva' ); ?>
				</button>

				<div id="mhm-backup-list" class="mhm-history-list" style="margin-top: 25px;"></div>
			</div>
		</div>
		<?php
	}

	private function enqueue_cleanup_assets(): void {
		$url     = defined( 'MHM_RENTIVA_PLUGIN_URL' ) ? (string) MHM_RENTIVA_PLUGIN_URL : '';
		$version = defined( 'MHM_RENTIVA_VERSION' ) ? (string) MHM_RENTIVA_VERSION : '1.0.0';

		wp_enqueue_style( 'mhm-database-cleanup', esc_url( $url . 'assets/css/admin/database-cleanup.css' ), array(), $version );
		wp_enqueue_script( 'mhm-database-cleanup', esc_url( $url . 'assets/js/admin/database-cleanup.js' ), array( 'jquery' ), $version, true );

		wp_localize_script(
			'mhm-database-cleanup',
			'mhm_db_cleanup_vars',
			array(
				'nonce'                            => wp_create_nonce( 'mhm_db_cleanup' ),
				'analyzing_text'                   => __( 'Inspecting Data...', 'mhm-rentiva' ),
				'cleaning_text'                    => __( 'Purging Records...', 'mhm-rentiva' ),
				'optimizing_text'                  => __( 'Optimizing Index...', 'mhm-rentiva' ),
				'error_text'                       => __( 'Operation Aborted.', 'mhm-rentiva' ),
				'success_text'                     => __( 'Task Successful.', 'mhm-rentiva' ),
				'analyze_text'                     => __( 'Analyze Integrity', 'mhm-rentiva' ),
				'clean_orphaned_text'              => __( 'Purge Orphaned Meta', 'mhm-rentiva' ),
				'clean_transients_text'            => __( 'Clear System Cache', 'mhm-rentiva' ),
				'optimize_autoload_text'           => __( 'Optimize Autoload', 'mhm-rentiva' ),
				'optimize_tables_text'             => __( 'Compact Tables', 'mhm-rentiva' ),
				'clean_invalid_meta_text'          => __( 'Invalidate', 'mhm-rentiva' ),
				'confirm_orphaned_text'            => __( 'Commit to purging orphaned meta? A snapshot will be created.', 'mhm-rentiva' ),
				'confirm_tables_text'              => __( 'Table compaction may impact short-term performance. Continue?', 'mhm-rentiva' ),
				'confirm_invalid_meta_text'        => __( 'Purge invalid keys? A snapshot will be created.', 'mhm-rentiva' ),
				'confirm_restore_text'             => __( 'Initiate data restoration from snapshot?', 'mhm-rentiva' ),
				'confirm_delete_backup_text'       => __( 'Permanently discard this backup? This is irreversible.', 'mhm-rentiva' ),
				'purge_logs_text'                  => __( 'Purge Old Logs', 'mhm-rentiva' ),
				'confirm_old_logs_text'            => __( 'Purge all logs and queue entries older than 30 days? This action is irreversible.', 'mhm-rentiva' ),
				'loading_text'                     => __( 'Synchronizing...', 'mhm-rentiva' ),
				'refresh_text'                     => __( 'Refresh Log', 'mhm-rentiva' ),
				'restoring_text'                   => __( 'Restoring Matrix...', 'mhm-rentiva' ),
				'deleting_text'                    => __( 'Discarding File...', 'mhm-rentiva' ),
				'backup_table_text'                => __( 'Target Resource', 'mhm-rentiva' ),
				'backup_type_text'                 => __( 'Context', 'mhm-rentiva' ),
				'backup_date_text'                 => __( 'Timestamp', 'mhm-rentiva' ),
				'backup_rows_text'                 => __( 'Record Count', 'mhm-rentiva' ),
				'backup_size_text'                 => __( 'Payload Size', 'mhm-rentiva' ),
				'actions_text'                     => __( 'Command', 'mhm-rentiva' ),
				'download_text'                    => __( 'Export', 'mhm-rentiva' ),
				'restore_text'                     => __( 'Rollback', 'mhm-rentiva' ),
				'delete_text'                      => __( 'Purge', 'mhm-rentiva' ),
				'no_backups_text'                  => __( 'No archived snapshots found.', 'mhm-rentiva' ),
				'type_invalid_meta_text'           => __( 'Metadata Integrity', 'mhm-rentiva' ),
				'type_orphaned_meta_text'          => __( 'Orphaned Cluster', 'mhm-rentiva' ),
				'type_custom_text'                 => __( 'Custom Backup', 'mhm-rentiva' ),
				'create_full_backup_text'          => __( 'Initialize Snapshot', 'mhm-rentiva' ),
				'confirm_create_full_backup_text'  => __( 'Generate complete system snapshot? Proceed with caution.', 'mhm-rentiva' ),
				'creating_backup_text'             => __( 'Streaming Snapshot...', 'mhm-rentiva' ),
				'confirm_restore_full_backup_text' => __( 'WARNING: Full restoration will overwrite current live data. Proceed with matrix rollback?', 'mhm-rentiva' ),
				'backup_name_text'                 => __( 'Archive Identity', 'mhm-rentiva' ),
				'backup_tables_text'               => __( 'Table Manifest', 'mhm-rentiva' ),
				'backup_status_text'               => __( 'Verified Status', 'mhm-rentiva' ),
				'file_exists_text'                 => __( 'Online', 'mhm-rentiva' ),
				'file_missing_text'                => __( 'Offline/Missing', 'mhm-rentiva' ),
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
