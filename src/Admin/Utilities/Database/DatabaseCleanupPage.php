<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Database;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ STAGE 3 - Database Cleanup Admin Page
 */
final class DatabaseCleanupPage
{


	public static function register(): void
	{
		// ✅ REMOVED: Menu page registration - now moved to Settings tab
		// Database cleanup is now available in Settings > Database Cleanup tab
		// Keep only AJAX handlers and assets enqueue
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_assets'));
		add_action('wp_ajax_mhm_analyze_database', array(self::class, 'ajax_analyze'));
		add_action('wp_ajax_mhm_cleanup_orphaned', array(self::class, 'ajax_cleanup_orphaned'));
		add_action('wp_ajax_mhm_cleanup_transients', array(self::class, 'ajax_cleanup_transients'));
		add_action('wp_ajax_mhm_optimize_autoload', array(self::class, 'ajax_optimize_autoload'));
		add_action('wp_ajax_mhm_optimize_tables', array(self::class, 'ajax_optimize_tables'));
		add_action('wp_ajax_mhm_cleanup_invalid_meta', array(self::class, 'ajax_cleanup_invalid_meta'));
		add_action('wp_ajax_mhm_list_backups', array(self::class, 'ajax_list_backups'));
		add_action('wp_ajax_mhm_download_backup', array(self::class, 'ajax_download_backup'));
		add_action('wp_ajax_mhm_restore_backup', array(self::class, 'ajax_restore_backup'));
		add_action('wp_ajax_mhm_delete_backup', array(self::class, 'ajax_delete_backup'));
		add_action('wp_ajax_mhm_create_full_backup', array(self::class, 'ajax_create_full_backup'));
		add_action('wp_ajax_mhm_list_full_backups', array(self::class, 'ajax_list_full_backups'));
		add_action('wp_ajax_mhm_download_full_backup', array(self::class, 'ajax_download_full_backup'));
		add_action('wp_ajax_mhm_restore_full_backup', array(self::class, 'ajax_restore_full_backup'));
		add_action('wp_ajax_mhm_delete_full_backup', array(self::class, 'ajax_delete_full_backup'));
		add_action('wp_ajax_mhm_repair_table', array(self::class, 'ajax_repair_table'));
		add_action('wp_ajax_mhm_cleanup_logs', array(self::class, 'ajax_cleanup_logs'));
	}

	/**
	 * Enqueue assets for the admin page
	 * Note: Assets are now enqueued in Settings::render_database_cleanup_page()
	 * This method is kept for backward compatibility but may not be called
	 */
	public static function enqueue_assets(string $hook): void
	{
		// Check if we're on the settings page with database-cleanup tab
		$screen = get_current_screen();
		if (! $screen || strpos($screen->id, 'mhm-rentiva-settings') === false) {
			return;
		}

		// Only enqueue if database-cleanup tab is active
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector for script enqueue gating.
		$current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
		if ($current_tab !== 'database-cleanup') {
			return;
		}

		wp_enqueue_style(
			'mhm-database-cleanup',
			esc_url(plugin_dir_url(dirname(__DIR__, 3)) . 'assets/css/admin/database-cleanup.css'),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'mhm-database-cleanup',
			esc_url(plugin_dir_url(dirname(__DIR__, 3)) . 'assets/js/admin/database-cleanup.js'),
			array('jquery'),
			'1.0.0',
			true
		);

		wp_localize_script(
			'mhm-database-cleanup',
			'mhm_db_cleanup_vars',
			array(
				'nonce'                  => wp_create_nonce('mhm_db_cleanup'),
				'analyzing_text'         => esc_html__('Analyzing...', 'mhm-rentiva'),
				'cleaning_text'          => esc_html__('Cleaning...', 'mhm-rentiva'),
				'optimizing_text'        => esc_html__('Optimizing...', 'mhm-rentiva'),
				'error_text'             => esc_html__('Error:', 'mhm-rentiva'),
				'success_text'           => esc_html__('Success:', 'mhm-rentiva'),
				'analyze_text'           => esc_html__('Analyze Database', 'mhm-rentiva'),
				'clean_orphaned_text'    => esc_html__('Clean Orphaned Meta', 'mhm-rentiva'),
				'clean_transients_text'  => esc_html__('Clean Expired Transients', 'mhm-rentiva'),
				'optimize_autoload_text' => esc_html__('Optimize Autoload', 'mhm-rentiva'),
				'optimize_tables_text'   => esc_html__('Optimize Tables', 'mhm-rentiva'),
				'confirm_orphaned_text'  => esc_html__('This will delete orphaned meta data. A backup will be created. Continue?', 'mhm-rentiva'),
				'confirm_tables_text'    => esc_html__('Table optimization may take several minutes. Continue?', 'mhm-rentiva'),
			)
		);
	}

	/**
	 * Render the admin page
	 */
	public static function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'mhm-rentiva'));
		}

?>
		<div class="wrap">
			<h1><?php esc_html_e('Database Management & Optimization', 'mhm-rentiva'); ?></h1>

			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e('Warning:', 'mhm-rentiva'); ?></strong>
					<?php esc_html_e('Database cleanup operations are irreversible. Automatic backup is created before each operation.', 'mhm-rentiva'); ?>
				</p>
			</div>

			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo DatabaseCleaner::render_cleanup_buttons();
			?>

			<div id="mhm-cleanup-results" style="margin-top: 20px;"></div>
		</div>
<?php
	}



	/**
	 * AJAX - Analyze database
	 */
	public static function ajax_analyze(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$analysis = DatabaseCleaner::analyze_database();
		$html     = DatabaseCleaner::render_cleanup_report($analysis);

		wp_send_json_success(
			array(
				'html'     => $html,
				'analysis' => $analysis,
			)
		);
	}

	/**
	 * AJAX - Cleanup orphaned meta
	 */
	public static function ajax_cleanup_orphaned(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$result = DatabaseCleaner::cleanup_orphaned_postmeta(false); // Execute cleanup

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: %1$d; 2: %2$s. */
					esc_html__('%1$d orphaned meta records cleaned. Backup table: %2$s', 'mhm-rentiva'),
					$result['deleted'] ?? 0,
					$result['backup_table'] ?? 'N/A'
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * AJAX - Cleanup expired transients
	 */
	public static function ajax_cleanup_transients(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$result = DatabaseCleaner::cleanup_expired_transients(false); // Execute cleanup

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %1$d placeholder. */
					esc_html__('%1$d expired transients cleaned.', 'mhm-rentiva'),
					$result['deleted'] ?? 0
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * AJAX - Optimize autoload
	 */
	public static function ajax_optimize_autoload(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$result = DatabaseCleaner::optimize_autoload_options(false); // Execute optimization

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: %1$d; 2: %2$s. */
					esc_html__('%1$d options optimized. Memory saved: %2$s', 'mhm-rentiva'),
					$result['updated'] ?? 0,
					size_format($result['memory_saved_bytes'] ?? 0)
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * AJAX - Optimize tables
	 */
	public static function ajax_optimize_tables(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$result = DatabaseCleaner::optimize_tables();

		$total_time = array_sum(array_column($result, 'execution_time_ms'));

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: %1$d; 2: %2$.2f. */
					esc_html__('%1$d tables optimized in %2$.2f seconds', 'mhm-rentiva'),
					count($result),
					$total_time / 1000
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * AJAX - Cleanup invalid meta keys
	 */
	public static function ajax_cleanup_invalid_meta(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$result = DatabaseCleaner::cleanup_invalid_meta_keys(false); // Execute cleanup

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: %1$d; 2: %2$s. */
					esc_html__('%1$d invalid meta records cleaned. Backup table: %2$s', 'mhm-rentiva'),
					$result['deleted'] ?? 0,
					$result['backup_table'] ?? 'N/A'
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * AJAX - List all backups
	 */
	public static function ajax_list_backups(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$backups = DatabaseCleaner::list_backups();

		wp_send_json_success(
			array(
				'backups' => $backups,
				'count'   => count($backups),
			)
		);
	}

	/**
	 * AJAX - Download backup as SQL file
	 */
	public static function ajax_download_backup(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_die(esc_html__('Invalid security nonce.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied', 'mhm-rentiva'));
		}

		$table_name = isset($_POST['table_name']) ? sanitize_text_field(wp_unslash($_POST['table_name'])) : '';

		if (empty($table_name)) {
			wp_die(esc_html__('Backup table name required', 'mhm-rentiva'));
		}

		$sql = DatabaseCleaner::export_backup_to_sql($table_name);

		if (empty($sql)) {
			wp_die(esc_html__('Failed to generate SQL export', 'mhm-rentiva'));
		}

		// Send file
		header('Content-Type: application/sql');
		header('Content-Disposition: attachment; filename="' . esc_attr($table_name) . '.sql"');
		header('Content-Length: ' . strlen($sql));

		echo $sql; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * AJAX - Restore backup
	 */
	public static function ajax_restore_backup(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$table_name = isset($_POST['table_name']) ? sanitize_text_field(wp_unslash($_POST['table_name'])) : '';

		if (empty($table_name)) {
			wp_send_json_error(esc_html__('Backup table name required', 'mhm-rentiva'));
		}

		$result = DatabaseCleaner::restore_backup($table_name);

		if ($result['success']) {
			wp_send_json_success(
				array(
					'message'      => $result['message'],
					'restored'     => $result['restored'] ?? 0,
					'target_table' => $result['target_table'] ?? '',
				)
			);
		} else {
			wp_send_json_error($result['message'] ?? esc_html__('Failed to restore backup', 'mhm-rentiva'));
		}
	}

	/**
	 * AJAX - Delete backup
	 */
	public static function ajax_delete_backup(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$table_name = isset($_POST['table_name']) ? sanitize_text_field(wp_unslash($_POST['table_name'])) : '';

		if (empty($table_name)) {
			wp_send_json_error(esc_html__('Backup table name required', 'mhm-rentiva'));
		}

		$result = DatabaseCleaner::delete_backup($table_name);

		if ($result['success']) {
			wp_send_json_success(
				array(
					'message' => $result['message'],
				)
			);
		} else {
			wp_send_json_error($result['message'] ?? esc_html__('Failed to delete backup', 'mhm-rentiva'));
		}
	}

	/**
	 * AJAX - Create full database backup
	 */
	public static function ajax_create_full_backup(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$result = DatabaseCleaner::create_full_backup();

		if ($result['success']) {
			wp_send_json_success(
				array(
					'message'      => $result['message'],
					'backup_name'  => $result['backup_name'] ?? '',
					'file_path'    => $result['file_path'] ?? '',
					'file_size_mb' => round(($result['file_size'] ?? 0) / 1024 / 1024, 2),
					'tables_count' => $result['tables_count'] ?? 0,
					'rows_count'   => $result['rows_count'] ?? 0,
				)
			);
		} else {
			wp_send_json_error($result['message'] ?? esc_html__('Failed to create backup', 'mhm-rentiva'));
		}
	}

	/**
	 * AJAX - List all full backups
	 */
	public static function ajax_list_full_backups(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$backups = DatabaseCleaner::list_full_backups();

		wp_send_json_success(
			array(
				'backups' => $backups,
				'count'   => count($backups),
			)
		);
	}

	/**
	 * AJAX - Download full backup file
	 */
	public static function ajax_download_full_backup(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_die(esc_html__('Invalid security nonce.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied', 'mhm-rentiva'));
		}

		$file_path = isset($_POST['file_path']) ? sanitize_text_field(wp_unslash($_POST['file_path'])) : '';

		if (empty($file_path) || ! file_exists($file_path)) {
			wp_die(esc_html__('Backup file not found', 'mhm-rentiva'));
		}

		// Verify it's in backup directory
		$backup_dir = WP_CONTENT_DIR . '/mhm-rentiva-backups';
		if (strpos(realpath($file_path), realpath($backup_dir)) !== 0) {
			wp_die(esc_html__('Invalid backup file path', 'mhm-rentiva'));
		}

		// Send file
		header('Content-Type: application/sql');
		header('Content-Disposition: attachment; filename="' . esc_attr(basename($file_path)) . '"');
		header('Content-Length: ' . filesize($file_path));

		// Initialize filesystem
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ($wp_filesystem->exists($file_path)) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Outputting SQL file content for download.
			echo $wp_filesystem->get_contents($file_path);
		} else {
			// Fallback if filesystem fails (should normally work)
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			readfile($file_path);
		}
		exit;
	}

	/**
	 * AJAX - Restore full backup
	 */
	public static function ajax_restore_full_backup(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$file_path = isset($_POST['file_path']) ? sanitize_text_field(wp_unslash($_POST['file_path'])) : '';

		if (empty($file_path)) {
			wp_send_json_error(esc_html__('Backup file path required', 'mhm-rentiva'));
		}

		// Verify it's in backup directory
		$backup_dir = WP_CONTENT_DIR . '/mhm-rentiva-backups';
		if (strpos(realpath($file_path), realpath($backup_dir)) !== 0) {
			wp_send_json_error(esc_html__('Invalid backup file path', 'mhm-rentiva'));
		}

		$result = DatabaseCleaner::restore_full_backup($file_path);

		if ($result['success']) {
			wp_send_json_success(
				array(
					'message'  => $result['message'],
					'executed' => $result['executed'] ?? 0,
				)
			);
		} else {
			wp_send_json_error($result['message'] ?? esc_html__('Failed to restore backup', 'mhm-rentiva'));
		}
	}

	/**
	 * AJAX - Delete full backup
	 */
	public static function ajax_delete_full_backup(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$backup_name = isset($_POST['backup_name']) ? sanitize_text_field(wp_unslash($_POST['backup_name'])) : '';

		if (empty($backup_name)) {
			wp_send_json_error(esc_html__('Backup name required', 'mhm-rentiva'));
		}

		$result = DatabaseCleaner::delete_full_backup($backup_name);

		if ($result['success']) {
			wp_send_json_success(
				array(
					'message' => $result['message'],
				)
			);
		} else {
			wp_send_json_error($result['message'] ?? esc_html__('Failed to delete backup', 'mhm-rentiva'));
		}
	}

	/**
	 * AJAX - Repair table
	 */
	public static function ajax_repair_table(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$table_name = isset($_POST['table_name']) ? sanitize_text_field(wp_unslash($_POST['table_name'])) : '';

		if (empty($table_name)) {
			wp_send_json_error(esc_html__('Table name required', 'mhm-rentiva'));
		}

		// Use DatabaseMigrator to create the table
		$success = \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_table($table_name);

		if ($success) {
			wp_send_json_success(
				array(
					'message' => esc_html__('Table repaired successfully', 'mhm-rentiva'),
				)
			);
		} else {
			wp_send_json_error(esc_html__('Failed to repair table or table definition not found', 'mhm-rentiva'));
		}
	}
	/**
	 * AJAX - Cleanup old logs
	 */
	public static function ajax_cleanup_logs(): void
	{
		if (! check_ajax_referer('mhm_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$results = DatabaseCleaner::cleanup_old_logs(30, false); // Execute cleanup

		$total_deleted = 0;
		foreach ($results as $table_result) {
			$total_deleted += ($table_result['deleted'] ?? 0);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d placeholder. */
					esc_html__('%d old log records cleaned.', 'mhm-rentiva'),
					$total_deleted
				),
				'result'  => $results,
			)
		);
	}
}
