<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Database;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;



/**
 * ✅ STAGE 3 - Database Cleanup Admin Page
 */
final class DatabaseCleanupPage {



	public static function register(): void
	{
		// ✅ REMOVED: Menu page registration - now moved to Settings tab
		// Database cleanup is now available in Settings > Database Cleanup tab
		// Keep only AJAX handlers (enqueue_assets() was removed -- see below)
		add_action('wp_ajax_mhmrentiva_analyze_database', array( self::class, 'ajax_analyze' ));
		add_action('wp_ajax_mhmrentiva_cleanup_orphaned', array( self::class, 'ajax_cleanup_orphaned' ));
		add_action('wp_ajax_mhmrentiva_cleanup_transients', array( self::class, 'ajax_cleanup_transients' ));
		add_action('wp_ajax_mhmrentiva_optimize_autoload', array( self::class, 'ajax_optimize_autoload' ));
		add_action('wp_ajax_mhmrentiva_optimize_tables', array( self::class, 'ajax_optimize_tables' ));
		add_action('wp_ajax_mhmrentiva_cleanup_invalid_meta', array( self::class, 'ajax_cleanup_invalid_meta' ));
		add_action('wp_ajax_mhmrentiva_list_backups', array( self::class, 'ajax_list_backups' ));
		add_action('wp_ajax_mhmrentiva_download_backup', array( self::class, 'ajax_download_backup' ));
		add_action('wp_ajax_mhmrentiva_restore_backup', array( self::class, 'ajax_restore_backup' ));
		add_action('wp_ajax_mhmrentiva_delete_backup', array( self::class, 'ajax_delete_backup' ));
		add_action('wp_ajax_mhmrentiva_create_full_backup', array( self::class, 'ajax_create_full_backup' ));
		add_action('wp_ajax_mhmrentiva_list_full_backups', array( self::class, 'ajax_list_full_backups' ));
		add_action('wp_ajax_mhmrentiva_download_full_backup', array( self::class, 'ajax_download_full_backup' ));
		add_action('wp_ajax_mhmrentiva_delete_full_backup', array( self::class, 'ajax_delete_full_backup' ));
		add_action('wp_ajax_mhmrentiva_repair_table', array( self::class, 'ajax_repair_table' ));
		add_action('wp_ajax_mhmrentiva_cleanup_logs', array( self::class, 'ajax_cleanup_logs' ));
	}

	// enqueue_assets() was removed (WP.org T8 Görev 13, row 26-27): its own
	// docblock already said "kept for backward compatibility but may not be
	// called" -- confirmed dead. DatabaseCleanupRenderer::enqueue_cleanup_assets()
	// (called from render(), only when the database-cleanup tab actually
	// renders via TabRendererRegistry) enqueues the same 'mhm-rentiva-database-cleanup'
	// handle with a materially larger wp_localize_script payload that
	// database-cleanup.js's live code depends on (e.g. clean_invalid_meta_text,
	// restoring_text, type_invalid_meta_text -- all absent from this method's
	// smaller 14-key payload). admin_enqueue_scripts (this method's hook)
	// always fires before the page body renders, so wp_localize_script()'s
	// last-call-wins overwrite meant the renderer's payload always won
	// whenever both fired, and this method did nothing on every other tab --
	// dead weight either way. Zero test coverage referenced this method
	// (confirmed before deletion); the $_GET['tab'] read it carried is gone.

	// render_page() was removed (WP.org T8 Görev 10b, row D6): register()'s
	// own comment already said menu-page registration moved to the Settings
	// tab, and this method had zero remaining caller (confirmed: not
	// add_menu_page/add_submenu_page'd anywhere). The 15 wp_ajax_* handlers
	// below survive -- database-cleanup.js calls them live from the
	// reachable Settings > Database Cleanup tab.

	/**
	 * AJAX - Analyze database
	 */
	public static function ajax_analyze(): void
	{
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
		}

		$result = DatabaseCleaner::cleanup_invalid_meta_keys(false); // Execute cleanup

		// The cleaner refuses to run when it cannot identify live custom-field
		// meta. Report that as a failure so the admin sees why nothing happened,
		// rather than a success message reading "0 records cleaned".
		if (! empty($result['aborted'])) {
			wp_send_json_error(
				array(
					'message' => __('Cleanup cancelled: the vehicle custom field definitions could not be read, so custom field data cannot be told apart from stale meta. Nothing was deleted.', 'mhm-rentiva'),
					'result'  => $result,
				)
			);
		}

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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
	 * Write an already-composed download payload to the HTTP response body.
	 *
	 * The callers below serve file bodies (Content-Type: application/sql,
	 * Content-Disposition: attachment), not markup, so no escaping function
	 * applies -- esc_html() on a SQL dump returns a corrupted dump. `echo $var`
	 * is nevertheless the wrong construct for a binary response body: it invites
	 * the reader to ask which escaping applies, when the honest answer is none.
	 * Writing the bytes straight to the response stream says what is happening.
	 *
	 * php://output is the HTTP response body, not a file, so WP_Filesystem does
	 * not apply -- WPCS agrees and exempts this exact stream in
	 * AlternativeFunctionsSniff's $allowed_local_streams. This is the same
	 * shape CustomerExporter::handle() uses for its CSV.
	 *
	 * file_put_contents(), not fopen() + fwrite(): the sniff's exemption is
	 * keyed on the FILENAME argument, so it clears file_put_contents/fopen/
	 * readfile when that argument is a local data stream, but it has no way to
	 * trace a handle back to its fopen() and therefore flags every fwrite()
	 * unconditionally (file_system_operations_fwrite, an ERROR under WP.org's
	 * ruleset). The first draft of this method traded an EscapeOutput error for
	 * an AlternativeFunctions one; the gate caught it.
	 *
	 * @param string $bytes Raw payload; written verbatim.
	 */
	private static function send_download_body(string $bytes): void
	{
		file_put_contents('php://output', $bytes);
	}

	/**
	 * AJAX - Download backup as SQL file
	 */
	public static function ajax_download_backup(): void
	{
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
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

		self::send_download_body($sql);
		exit;
	}

	/**
	 * AJAX - Restore backup
	 */
	public static function ajax_restore_backup(): void
	{
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
		}

		$result = DatabaseCleaner::create_full_backup();

		if ($result['success']) {
			wp_send_json_success(
				array(
					'message'      => $result['message'],
					'backup_name'  => $result['backup_name'] ?? '',
					'file_path'    => $result['file_path'] ?? '',
					'file_size_mb' => round(( $result['file_size'] ?? 0 ) / 1024 / 1024, 2),
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_die(esc_html__('Invalid security nonce.', 'mhm-rentiva'));
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied', 'mhm-rentiva'));
		}

		$file_path = isset($_POST['file_path']) ? sanitize_text_field(wp_unslash($_POST['file_path'])) : '';

		if (empty($file_path) || ! file_exists($file_path)) {
			wp_die(esc_html__('Backup file not found', 'mhm-rentiva'));
		}

		// Verify it's in a backup directory. The check lives on DatabaseCleaner so
		// every entry point shares one definition of "inside the backup directory"
		// -- including the legacy wp-content location, whose files are still listed.
		if (! \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::is_backup_file($file_path)) {
			wp_die(esc_html__('Invalid backup file path', 'mhm-rentiva'));
		}

		// Initialize filesystem
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Single read path. The former readfile() fallback could not add anything:
		// the file's existence is already established by the file_exists() check
		// above, and dropping to a raw filesystem call on the one host where
		// WP_Filesystem cannot read is exactly the case WP_Filesystem exists for.
		//
		// The read happens BEFORE any header() call on purpose. It used to sit
		// after them, so its wp_die() failure path ran the default die handler
		// on a response already committed to Content-Type: application/sql --
		// the same collision that made CustomerExporter's CSV button emit an
		// unusable HTTP 500. With the read first, the failure path is a normal
		// HTML error page and the success path is a clean file response.
		$contents = $wp_filesystem->get_contents($file_path);
		if (false === $contents) {
			wp_die(esc_html__('Backup file could not be read.', 'mhm-rentiva'));
		}

		// Send file. Content-Length comes from the bytes actually about to be
		// written, not from filesize() -- the two can disagree when the reader
		// is a remote WP_Filesystem transport, and a short/over-long
		// Content-Length truncates or hangs the download.
		header('Content-Type: application/sql');
		header('Content-Disposition: attachment; filename="' . esc_attr(basename($file_path)) . '"');
		header('Content-Length: ' . strlen($contents));

		self::send_download_body($contents);
		exit;
	}

	/**
	 * AJAX - Delete full backup
	 */
	public static function ajax_delete_full_backup(): void
	{
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
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
		if (! check_ajax_referer('mhmrentiva_db_cleanup', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
		}

		$results = DatabaseCleaner::cleanup_old_logs(30, false); // Execute cleanup

		$total_deleted = 0;
		foreach ($results as $table_result) {
			$total_deleted += ( $table_result['deleted'] ?? 0 );
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
