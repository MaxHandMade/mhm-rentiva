<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Database Migration Manager
 *
 * Automatically creates critical indexes for performance optimization
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Migration/DDL routines intentionally execute controlled schema and maintenance SQL against known WordPress tables.
final class DatabaseMigrator {





	/**
	 * Migration version
	 *
	 * Bump this when a new schema-creating migration is added so that
	 * `version_compare()` triggers `run_migrations()` on existing installs.
	 */
	private const CURRENT_VERSION = '3.11.0';

	/**
	 * Sanitize DB table identifiers to a strict whitelist.
	 */
	private static function sanitize_table_identifier(string $table): string
	{
		return preg_replace('/[^A-Za-z0-9_]/', '', $table) ?? '';
	}

	/**
	 * Run all pending migrations
	 */
	public static function run_migrations(): void
	{
		$current_version = get_option('mhm_rentiva_db_version', '1.0.0');

		if (version_compare($current_version, self::CURRENT_VERSION, '<')) {
			self::create_transfer_tables(); // VIP Transfer Tables
			self::create_table('notification_queue');

			// Payout governance schema (the `payout_audit` table + the seven
			// `mhm_rentiva_*` capabilities added to the administrator role)
			// belongs to Pro's GovernanceService -- the class that actually
			// reads/writes the audit trail and enforces those capabilities.
			// Gated on its presence rather than a registration check for the same
			// reason as the Ledger cluster below: this is a question of which
			// FILES this build ships, not which features are registered as
			// active. A registration gate would also skip the schema on a Pro
			// install whose extension is not yet activated, and since
			// run_migrations() is version-gated it would not re-run after
			// activation -- leaving Pro with a half-built schema.
			if (class_exists(\MHMRentiva\Core\Financial\GovernanceService::class)) {
				self::create_table('mhm_rentiva_payout_audit');
				self::register_governance_capabilities();
			}

			// Financial / ledger-audit schema. Every table in this cluster --
			// `ledger`, `commission_policy`, and the `key_registry` that holds the
			// ledger-signing keys -- belongs to Pro; Lite ships no class that reads
			// or writes any of them (Ledger, CommissionResolver, KeyPairManager and
			// KeyRegistryRepository all moved to Pro). Creating them anyway left
			// dead schema in every Lite install.
			//
			// Gated on LedgerMigration (the seam that owns the cluster's primary
			// table) rather than on a registration check: this is a question about
			// which FILES this build ships, not which features are registered as
			// active. A registration gate would also skip the schema on a Pro
			// install whose extension is not yet activated, and since
			// run_migrations() is version-gated it would not re-run after
			// activation -- leaving Pro with a half-built schema. Mirrors
			// create_transfer_tables()'s class_exists() gate below.
			if (class_exists(\MHMRentiva\Core\Database\Migrations\LedgerMigration::class)) {
				\MHMRentiva\Core\Database\Migrations\LedgerMigration::create_table();
				self::create_key_registry_table();

				if (class_exists(\MHMRentiva\Core\Database\Migrations\CommissionPolicyMigration::class)) {
					\MHMRentiva\Core\Database\Migrations\CommissionPolicyMigration::create_table();
				}

				// Adds tenant_id to ledger / key_registry / payout_audit, so it can
				// only run once those exist. In Lite it ALTERed three absent tables,
				// failing three times per upgrade.
				if (class_exists(\MHMRentiva\Core\Database\Migrations\MultiTenantMigration::class)) {
					\MHMRentiva\Core\Database\Migrations\MultiTenantMigration::run();
				}
			}
			// SaaS Control Plane scaffolding removed (#4 cleanup) — drop its dead tables.
			self::drop_orchestration_tables();
			// Geo-blocking removed — drop the option that promised it was ON.
			self::delete_dead_country_restriction_option();
			// Vendor Reports / Appeals (v4.35.0)
			if (class_exists(\MHMRentiva\Core\Database\Migrations\VendorReportsMigration::class)) {
				\MHMRentiva\Core\Database\Migrations\VendorReportsMigration::create_table();
			}
			self::add_performance_indexes();
			self::optimize_existing_indexes();
			self::add_missing_indexes();
			self::cleanup_orphan_data();
			self::migrate_standalone_settings();
			self::migrate_vehicle_lifecycle_status();

			// Update version in database
			update_option('mhm_rentiva_db_version', self::CURRENT_VERSION);

			// Log migration
			if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info(
					'Database migration completed',
					array(
						'from_version'  => $current_version,
						'to_version'    => self::CURRENT_VERSION,
						'indexes_added' => true,
					),
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
				);
			}
		}
	}

	/**
	 * Add critical performance indexes
	 */
	private static function add_performance_indexes(): void
	{
		global $wpdb;

		$indexes = array(
			// 1. Composite index for status queries
			'idx_mhm_status_lookup'    => "CREATE INDEX idx_mhm_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)",

			// 2. Timestamp index for date range queries
			'idx_mhm_timestamp_range'  => "CREATE INDEX idx_mhm_timestamp_range ON {$wpdb->postmeta} (post_id, meta_key(50), meta_value(20))",

			// 3. Index for vehicle booking lookups
			'idx_mhm_vehicle_bookings' => "CREATE INDEX idx_mhm_vehicle_bookings ON {$wpdb->postmeta} (meta_value(20), post_id)",

			// 4. Index for post date queries
			'idx_posts_date_type'      => "CREATE INDEX idx_posts_date_type ON {$wpdb->posts} (post_date, post_type(20), post_status(20))",

			// 5. Index for booking meta queries
			'idx_mhm_booking_meta'     => "CREATE INDEX idx_mhm_booking_meta ON {$wpdb->postmeta} (meta_key(50), post_id, meta_value(50))",

			// 6. Index for customer email lookups
			'idx_mhm_customer_email'   => "CREATE INDEX idx_mhm_customer_email ON {$wpdb->postmeta} (meta_key(50), meta_value(100))",

			// 7. Index for price range queries
			'idx_mhm_price_range'      => "CREATE INDEX idx_mhm_price_range ON {$wpdb->postmeta} (meta_key(50), meta_value(20))",

			// 8. Index for combined booking lookup
			'idx_mhm_booking_combined' => "CREATE INDEX idx_mhm_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))",
		);

		foreach ($indexes as $index_name => $sql) {
			try {
				$table = ( strpos($index_name, 'idx_posts_') === 0 ) ? $wpdb->posts : $wpdb->postmeta;

				if (self::index_exists($table, $index_name)) {
					continue;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: DDL statement using system prefix, strictly server-side.
				$result = $wpdb->query($sql);
				if ($result === false) {
					self::log_index_error($sql, (string) $wpdb->last_error);
				}
			} catch (\Exception $e) {
				self::log_index_error($sql, $e->getMessage());
			}
		}
	}

	/**
	 * Optimize existing indexes
	 */
	private static function optimize_existing_indexes(): void
	{
		global $wpdb;

		// Run index analysis
		$analysis_queries = array(
			"ANALYZE TABLE {$wpdb->posts}",
			"ANALYZE TABLE {$wpdb->postmeta}",
		);

		foreach ($analysis_queries as $sql) {
			try {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: DDL statement using system prefix, strictly server-side.
				$wpdb->query($sql);
			} catch (\Exception $e) {
				self::log_index_error($sql, $e->getMessage());
			}
		}
	}

	/**
	 * Detect and add missing indexes
	 */
	private static function add_missing_indexes(): void
	{
		global $wpdb;

		// Detect missing indexes
		$missing_indexes = self::detect_missing_indexes();

		foreach ($missing_indexes as $index_name => $index_sql) {
			try {
				if (self::index_exists($wpdb->postmeta, $index_name)) {
					continue;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL is generated internally from vetted table/meta constants.
				$result = $wpdb->query($index_sql);
				if ($result === false) {
					self::log_index_error($index_sql, (string) $wpdb->last_error);
				}
			} catch (\Exception $e) {
				self::log_index_error($index_sql, $e->getMessage());
			}
		}
	}

	/**
	 * Detect missing metadata indexes
	 */
	private static function detect_missing_indexes(): array
	{
		global $wpdb;

		$missing_indexes = array();

		// Special indexes for MHM Rentiva specific meta keys
		$mhm_meta_keys = array(
			'_mhm_status',
			'_mhm_vehicle_id',
			'_mhm_start_ts',
			'_mhm_end_ts',
			'_mhm_total_price',
			'_mhm_contact_email',
			'_mhm_contact_name',
			'_mhm_customer_id',
		);

		foreach ($mhm_meta_keys as $meta_key) {
			// Create a specific index for each meta key
			$index_name                     = 'idx_mhm_' . str_replace('_mhm_', '', $meta_key);
			$missing_indexes[ $index_name ] = "CREATE INDEX {$index_name} ON {$wpdb->postmeta} (meta_key(50), meta_value(50), post_id)";
		}

		return $missing_indexes;
	}

	/**
	 * Check index status
	 */
	public static function check_index_status(): array
	{
		global $wpdb;

		$status = array(
			'total_indexes'     => 0,
			'mhm_indexes'       => 0,
			'performance_score' => 0,
			'missing_indexes'   => array(),
			'recommendations'   => array(),
		);

		try {
			// Posts tablosu indexleri
			$posts_indexes            = $wpdb->get_results("SHOW INDEX FROM {$wpdb->posts}");
			$status['total_indexes'] += count($posts_indexes);

			// Postmeta table indexes
			$postmeta_indexes         = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}");
			$status['total_indexes'] += count($postmeta_indexes);

			// Count MHM Rentiva indexes
			foreach ($postmeta_indexes as $index) {
				if (strpos($index->Key_name, 'idx_mhm_') === 0) {
					++$status['mhm_indexes'];
				}
			}

			// Calculate performance score
			$status['performance_score'] = min(100, ( $status['mhm_indexes'] / 8 ) * 100);

			// Recommendations
			if ($status['mhm_indexes'] < 5) {
				$status['recommendations'][] = 'More MHM Rentiva indexes should be added';
			}

			if ($status['performance_score'] < 70) {
				$status['recommendations'][] = 'Database performance should be optimized';
			}
		} catch (\Exception $e) {
			$status['error'] = $e->getMessage();
		}

		return $status;
	}

	/**
	 * Index performans testi
	 */
	public static function test_index_performance(): array
	{
		global $wpdb;

		$results = array();

		// Test query'leri
		$test_queries = array(
			'status_lookup'    => "
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_status' 
                AND meta_value = 'confirmed'
            ",
			'date_range'       => "
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_start_ts' 
                AND meta_value > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ",
			'vehicle_bookings' => "
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_vehicle_id' 
                AND meta_value = '123'
            ",
			'post_date_query'  => "
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = 'vehicle_booking' 
                AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ",
		);

		foreach ($test_queries as $test_name => $query) {
			$start_time = microtime(true);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: Constant test queries defined in code, safe from user input.
			$result   = $wpdb->get_var($query);
			$end_time = microtime(true);

			$results[ $test_name ] = array(
				'execution_time' => round(( $end_time - $start_time ) * 1000, 2), // ms
				'result'         => $result,
				'query'          => $query,
			);
		}

		return $results;
	}

	/**
	 * Run database optimization
	 */
	public static function optimize_database(): array
	{
		global $wpdb;

		$results = array();

		try {
			// Optimize tables
			$tables = array( $wpdb->posts, $wpdb->postmeta );

			foreach ($tables as $table) {
				$start_time = microtime(true);
				$table_name = esc_sql(self::sanitize_table_identifier($table));
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers cannot be prepared; identifier is strictly sanitized and SQL-escaped.
				$result   = $wpdb->query(sprintf('OPTIMIZE TABLE `%s`', $table_name));
				$end_time = microtime(true);

				$results['optimize'][ $table ] = array(
					'success'        => $result !== false,
					'execution_time' => round(( $end_time - $start_time ) * 1000, 2),
					'error'          => $result === false ? $wpdb->last_error : null,
				);
			}

			// Rebuild indexes
			$results['rebuild_indexes'] = self::rebuild_indexes();
		} catch (\Exception $e) {
			$results['error'] = $e->getMessage();
		}

		return $results;
	}

	/**
	 * Rebuild indexes
	 */
	private static function rebuild_indexes(): array
	{
		global $wpdb;

		$results = array();

		// Rebuild critical indexes
		$critical_indexes = array(
			array(
				'action' => 'DROP',
				'name'   => 'idx_mhm_status_lookup',
				'table'  => $wpdb->postmeta,
				'sql'    => "DROP INDEX idx_mhm_status_lookup ON {$wpdb->postmeta}",
			),
			array(
				'action' => 'CREATE',
				'name'   => 'idx_mhm_status_lookup',
				'table'  => $wpdb->postmeta,
				'sql'    => "CREATE INDEX idx_mhm_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)",
			),
			array(
				'action' => 'DROP',
				'name'   => 'idx_mhm_booking_combined',
				'table'  => $wpdb->postmeta,
				'sql'    => "DROP INDEX idx_mhm_booking_combined ON {$wpdb->postmeta}",
			),
			array(
				'action' => 'CREATE',
				'name'   => 'idx_mhm_booking_combined',
				'table'  => $wpdb->postmeta,
				'sql'    => "CREATE INDEX idx_mhm_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))",
			),
		);

		foreach ($critical_indexes as $index) {
			$start_time = microtime(true);

			// Check existence for safety
			$exists = self::index_exists($index['table'], $index['name']);
			if ('DROP' === $index['action'] && ! $exists) {
				continue;
			}
			if ('CREATE' === $index['action'] && $exists) {
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: DDL statement using system prefix, strictly server-side.
			$result   = $wpdb->query($index['sql']);
			$end_time = microtime(true);

			$results[] = array(
				'sql'            => $index['sql'],
				'success'        => $result !== false,
				'execution_time' => round(( $end_time - $start_time ) * 1000, 2),
				'error'          => $result === false ? (string) $wpdb->last_error : null,
			);
		}

		return $results;
	}

	/**
	 * Check migration status
	 */
	public static function get_migration_status(): array
	{
		$current_version  = get_option('mhm_rentiva_db_version', '1.0.0');
		$index_status     = self::check_index_status();
		$performance_test = self::test_index_performance();

		return array(
			'current_version'  => $current_version,
			'target_version'   => self::CURRENT_VERSION,
			'needs_migration'  => version_compare($current_version, self::CURRENT_VERSION, '<'),
			'index_status'     => $index_status,
			'performance_test' => $performance_test,
			'last_migration'   => get_option('mhm_rentiva_last_migration', 'Never'),
		);
	}

	/**
	 * Rollback migration
	 */
	public static function rollback_migration(): bool
	{
		global $wpdb;

		try {
			// Delete MHM Rentiva indexes
			$drop_indexes = array(
				'idx_mhm_status_lookup'    => array(
					'table' => $wpdb->postmeta,
					'sql'   => "DROP INDEX idx_mhm_status_lookup ON {$wpdb->postmeta}",
				),
				'idx_mhm_timestamp_range'  => array(
					'table' => $wpdb->postmeta,
					'sql'   => "DROP INDEX idx_mhm_timestamp_range ON {$wpdb->postmeta}",
				),
				'idx_mhm_vehicle_bookings' => array(
					'table' => $wpdb->postmeta,
					'sql'   => "DROP INDEX idx_mhm_vehicle_bookings ON {$wpdb->postmeta}",
				),
				'idx_posts_date_type'      => array(
					'table' => $wpdb->posts,
					'sql'   => "DROP INDEX idx_posts_date_type ON {$wpdb->posts}",
				),
				'idx_mhm_booking_meta'     => array(
					'table' => $wpdb->postmeta,
					'sql'   => "DROP INDEX idx_mhm_booking_meta ON {$wpdb->postmeta}",
				),
				'idx_mhm_customer_email'   => array(
					'table' => $wpdb->postmeta,
					'sql'   => "DROP INDEX idx_mhm_customer_email ON {$wpdb->postmeta}",
				),
				'idx_mhm_price_range'      => array(
					'table' => $wpdb->postmeta,
					'sql'   => "DROP INDEX idx_mhm_price_range ON {$wpdb->postmeta}",
				),
				'idx_mhm_booking_combined' => array(
					'table' => $wpdb->postmeta,
					'sql'   => "DROP INDEX idx_mhm_booking_combined ON {$wpdb->postmeta}",
				),
			);

			foreach ($drop_indexes as $index_name => $index_data) {
				if (self::index_exists($index_data['table'], $index_name)) {
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: DDL statement using system prefix, strictly server-side.
					$wpdb->query($index_data['sql']);
				}
			}

			// Reset version to original state
			update_option('mhm_rentiva_db_version', '1.0.0');

			if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning('Database migration rolled back', array(), \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM);
			}

			return true;
		} catch (\Exception $e) {
			if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
					'Migration rollback failed',
					array(
						'error' => $e->getMessage(),
					),
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
				);
			}
			return false;
		}
	}

	/**
	 * Log database index creation error
	 */
	private static function log_index_error(string $sql, string $error): void
	{
		if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
				'Database index creation failed',
				array(
					'sql'   => $sql,
					'error' => $error,
				),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
			);
		}
	}

	/**
	 * Show admin notice
	 */
	public static function show_migration_notice(): void
	{
		if (! is_admin() || ! current_user_can('manage_options')) {
			return;
		}

		$status = self::get_migration_status();

		if ($status['needs_migration']) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__('MHM Rentiva: Database migration required. Run migration for performance.', 'mhm-rentiva');
			echo ' <a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva&action=run_migration')) . '">';
			echo esc_html__('Run Migration', 'mhm-rentiva');
			echo '</a>';
			echo '</p></div>';
		} elseif ($status['index_status']['performance_score'] < 80) {
			echo '<div class="notice notice-info"><p>';
			echo esc_html__('MHM Rentiva: Database performance can be optimized.', 'mhm-rentiva');
			echo ' <a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva&action=optimize_db')) . '">';
			echo esc_html__('Optimize', 'mhm-rentiva');
			echo '</a>';
			echo '</p></div>';
		}
	}
	/**
	 * Creates VIP Transfer tables (locations + routes).
	 *
	 * Both tables belong to the Transfer (Pro) module. Lite has no location search
	 * and no Transfer module (owner decision 2026-07-16), so it must not ship the
	 * schema: an empty `rentiva_transfer_locations` that nothing can populate or
	 * read is dead schema, which is WP.org-unclean (cf. faz1-exit-decisions Task 5
	 * REQUIREMENT 2).
	 *
	 * Task A9c seam inversion: the actual CREATE TABLE / legacy-rename SQL moved
	 * verbatim to Pro's `\MHMRentiva\Core\Database\Migrations\TransferMigration`
	 * (same "Migrations" cluster as LedgerMigration/VendorReportsMigration below),
	 * so this file no longer names any class from the Transfer module itself.
	 * Gate stays a class_exists() check -- unchanged semantics, only the target
	 * class changed -- keeping the single-point guard for all three call sites
	 * (run_migrations + the two create_table switch arms).
	 *
	 * @return bool True if the tables were created; false if skipped (no Transfer).
	 */
	private static function create_transfer_tables(): bool
	{
		if (! class_exists(\MHMRentiva\Core\Database\Migrations\TransferMigration::class)) {
			return false;
		}

		return \MHMRentiva\Core\Database\Migrations\TransferMigration::create_tables();
	}

	/**
	 * Creates rating database table
	 */
	public static function create_rating_table(): void
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'mhm_rentiva_ratings';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vehicle_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            rating decimal(2,1) NOT NULL,
            comment text DEFAULT NULL,
            status varchar(20) DEFAULT 'approved',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_vehicle_user (vehicle_id, user_id),
            KEY vehicle_id (vehicle_id),
            KEY user_id (user_id),
            KEY rating (rating),
            KEY status (status)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Cleanup orphan data
	 */
	private static function cleanup_orphan_data(): void
	{
		global $wpdb;

		// 1. Orphan Post Meta Cleaning
		$meta_sql = "DELETE pm
        FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
        WHERE wp.ID IS NULL
        AND pm.meta_key LIKE '_mhm_%%'";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Reason: DELETE query using system table identifiers, safely escaped LIKE.
		$wpdb->query($meta_sql);

		// 2. Transient Data Cleaning
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_mhm_rate_limit_%' 
             OR option_name LIKE '_transient_timeout_mhm_rate_limit_%'"
		);
	}
	/**
	 * Create specific table by key
	 */
	public static function create_table(string $table_key): bool
	{
		switch ($table_key) {
			case 'payment_log':
			case 'mhm_payment_log':
				self::create_payment_log_table();
				return true;
			case 'sessions':
			case 'mhm_sessions':
				self::create_sessions_table();
				return true;
			// Report the real outcome: without the Transfer module these tables are
			// deliberately not created, so claiming success would be a lie.
			case 'transfer_locations':
			case 'mhm_rentiva_transfer_locations':
			case 'rentiva_transfer_locations':
				return self::create_transfer_tables();
			case 'transfer_routes':
			case 'mhm_rentiva_transfer_routes':
			case 'rentiva_transfer_routes':
				return self::create_transfer_tables();
			case 'ratings':
			case 'mhm_rentiva_ratings':
				self::create_rating_table();
				return true;
			case 'queue':
			case 'mhm_rentiva_queue':
				self::create_queue_table();
				return true;
			case 'report_queue':
			case 'background_jobs':
			case 'mhm_rentiva_background_jobs':
				self::create_background_jobs_table();
				return true;
			case 'message_logs':
			case 'mhm_message_logs':
				self::create_message_logs_table();
				return true;
			case 'notification_queue':
			case 'mhm_notification_queue':
				if (class_exists(\MHMRentiva\Admin\Notifications\NotificationManager::class)) {
					\MHMRentiva\Admin\Notifications\NotificationManager::create_notification_queue_table();
				}
				return true;
			case 'payout_audit':
			case 'mhm_rentiva_payout_audit':
				self::create_payout_audit_table();
				return true;
		}
		return false;
	}

	/**
	 * Register governance capabilities to the administrator role.
	 */
	public static function register_governance_capabilities(): void
	{
		$role = get_role('administrator');
		if ($role instanceof \WP_Role) {
			$role->add_cap('mhm_rentiva_approve_payout');
			$role->add_cap('mhm_rentiva_freeze_payouts');
			$role->add_cap('mhm_rentiva_view_financial_audit');

			// Sprint 10: Multi-Actor Workflow Capabilities
			$role->add_cap('mhm_rentiva_create_payout');
			$role->add_cap('mhm_rentiva_review_payout');
			$role->add_cap('mhm_rentiva_finalize_payout');
			$role->add_cap('mhm_rentiva_override_maker_checker');
		}
	}

	/**
	 * Create payout audit table (append-only)
	 */
	public static function create_payout_audit_table(): void
	{
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mhm_rentiva_payout_audit';
		$table_escaped   = esc_sql($table_name);
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) unsigned NOT NULL DEFAULT 1,
			payout_id bigint(20) unsigned NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL,
			action varchar(50) NOT NULL,
			tx_uuid varchar(36) NOT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			metadata_json text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY tenant_id  (tenant_id),
			KEY payout_id  (payout_id),
			KEY actor_user_id  (actor_user_id),
			KEY action  (action)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		// Add unique payout-action idempotency key once; dbDelta re-add attempts can emit duplicate-key warnings on reruns.
		if (! self::index_exists($table_name, 'payout_action_tx')) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table identifier cannot be prepared and is strictly sanitized.
			$wpdb->query("ALTER TABLE `{$table_escaped}` ADD UNIQUE KEY `payout_action_tx` (`tenant_id`, `payout_id`, `action`, `tx_uuid`)");
		}
	}

	/**
	 * Create payment log table
	 */
	public static function create_payment_log_table(): void
	{
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mhm_payment_log';
		$table_escaped   = esc_sql($table_name);
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$table_escaped}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            transaction_id varchar(100) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'USD',
            gateway varchar(50) DEFAULT NULL,
            method varchar(50) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            raw_data text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY transaction_id (transaction_id),
            KEY status (status)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Create sessions table
	 */
	public static function create_sessions_table(): void
	{
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mhm_sessions';
		$table_escaped   = esc_sql($table_name);
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$table_escaped}` (
            session_id bigint(20) NOT NULL AUTO_INCREMENT,
            session_key varchar(32) NOT NULL,
            session_value longtext NOT NULL,
            session_expiry bigint(20) NOT NULL,
            PRIMARY KEY (session_id),
            UNIQUE KEY session_key (session_key)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Create queue table
	 */
	public static function create_queue_table(): void
	{
		if (class_exists(\MHMRentiva\Admin\Core\Utilities\QueueManager::class)) {
			\MHMRentiva\Admin\Core\Utilities\QueueManager::create_table();
		}
	}

	/**
	 * Create background jobs table
	 */
	public static function create_background_jobs_table(): void
	{
		if (class_exists(\MHMRentiva\Admin\Reports\BackgroundProcessor::class)) {
			\MHMRentiva\Admin\Reports\BackgroundProcessor::create_background_jobs_table();
		}
	}

	/**
	 * Create message logs table
	 */
	public static function create_message_logs_table(): void
	{
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mhm_message_logs';
		$table_escaped   = esc_sql($table_name);
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$table_escaped}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Create Key Registry table
	 */
	public static function create_key_registry_table(): void
	{
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mhm_rentiva_key_registry';
		$table_escaped   = esc_sql($table_name);
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$table_escaped}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL DEFAULT 1,
            key_uuid varchar(64) NOT NULL,
            key_algorithm varchar(32) NOT NULL DEFAULT 'ed25519',
            fingerprint char(64) NOT NULL,
            public_key text NOT NULL,
            private_key_encrypted text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            active_key tinyint(1) DEFAULT NULL,
            revocation_reason text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            signed_at datetime,
            expires_at datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_key_active  (tenant_id, key_uuid, active_key),
            KEY tenant_id (tenant_id),
            KEY status (status)
        ) $charset_collate;";

		// Drop old non-tenant-aware UNIQUE key if exists (dbDelta won't drop it automatically)
		if (self::index_exists($table_name, 'key_uuid')) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration intentionally removes a legacy index during upgrade.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP INDEX `key_uuid`',
					$table_escaped
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Migrate standalone settings into unified array
	 */
	private static function migrate_standalone_settings(): void
	{
		$settings = (array) get_option('mhm_rentiva_settings', array());

		// Map old mhm_ keys to new rentiva_ keys in the settings array
		$standalone_mapping = array(
			'mhm_transfer_deposit_type' => 'rentiva_transfer_deposit_type',
			'mhm_transfer_deposit_rate' => 'rentiva_transfer_deposit_rate',
			'mhm_transfer_custom_types' => 'rentiva_transfer_custom_types',
		);

		// Defaults
		$defaults = array(
			'rentiva_transfer_deposit_type' => 'full_payment',
			'rentiva_transfer_deposit_rate' => 20,
			'rentiva_transfer_custom_types' => '',
		);

		$migrated = false;

		foreach ($standalone_mapping as $old_key => $new_key) {
			// Check if old option exists
			$old_val = get_option($old_key, null);

			// If old option exists and new key is NOT in settings
			if ($old_val !== null && ! isset($settings[ $new_key ])) {
				$settings[ $new_key ] = $old_val;
				$migrated             = true;
				// Ideally we delete old option, but for safety lets keep it for a while or rename usages?
				// The instruction says "Update calls to get_option".
				// I will add the new key.
			} elseif (! isset($settings[ $new_key ])) {
				// Set default if not set
				$settings[ $new_key ] = $defaults[ $new_key ] ?? '';
				$migrated             = true;
			}
		}

		if ($migrated) {
			update_option('mhm_rentiva_settings', $settings);
		}
	}

	/**
	 * Check if index exists on a table
	 */
	private static function index_exists(string $table, string $index_name): bool
	{
		global $wpdb;

		$table_name = self::sanitize_table_identifier($table);
		if (empty($table_name)) {
			return false;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifiers cannot be prepared; identifier is strictly sanitized and index name run through esc_sql().
		$results = $wpdb->get_results(sprintf('SHOW INDEX FROM `%s` WHERE Key_name = \'%s\'', $table_name, esc_sql($index_name)));

		return ! empty($results);
	}

	/**
	 * Migrate existing vehicles to the new lifecycle status meta key.
	 *
	 * Maps: active → active, maintenance → paused, inactive → withdrawn (draft).
	 * Vehicles without _mhm_vehicle_status get 'active' (legacy default).
	 * Also sets initial listing_started_at and listing_expires_at for published vehicles.
	 *
	 * Idempotent — skips vehicles that already have _mhm_vehicle_lifecycle_status set.
	 */
	private static function migrate_vehicle_lifecycle_status(): void
	{
		if (get_option('mhm_rentiva_lifecycle_migration_done') === '1') {
			return;
		}

		global $wpdb;

		// Get all vehicles that do NOT already have the lifecycle meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$vehicle_ids = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhm_vehicle_lifecycle_status'
			 WHERE p.post_type = 'vehicle' AND pm.meta_id IS NULL"
		);

		if (empty($vehicle_ids)) {
			update_option('mhm_rentiva_lifecycle_migration_done', '1', false);
			return;
		}

		$status_map = array(
			'active'      => 'active',
			'maintenance' => 'paused',
			'inactive'    => 'withdrawn',
		);

		$now = gmdate('Y-m-d H:i:s');

		foreach ($vehicle_ids as $vid) {
			$vid        = (int) $vid;
			$old_status = get_post_meta($vid, '_mhm_vehicle_status', true);
			$new_status = $status_map[ $old_status ] ?? 'active';

			update_post_meta($vid, '_mhm_vehicle_lifecycle_status', $new_status);

			// Set listing timer for active/published vehicles.
			$post_status = get_post_status($vid);
			if ($new_status === 'active' && $post_status === 'publish') {
				$published = get_post_field('post_date_gmt', $vid);
				if (! empty($published) && $published !== '0000-00-00 00:00:00') {
					update_post_meta($vid, '_mhm_vehicle_listing_started_at', $published);
					$expires = gmdate('Y-m-d H:i:s', strtotime($now . ' +90 days'));
					update_post_meta($vid, '_mhm_vehicle_listing_expires_at', $expires);
				}
			}
		}

		update_option('mhm_rentiva_lifecycle_migration_done', '1', false);
	}

	/**
	 * Drop the dead SaaS orchestration tables.
	 *
	 * The multi-tenant "Control Plane" scaffolding (OrchestrationMigration) was removed in the
	 * #4 cleanup. Its two tables were never read by any live code path and are empty on
	 * single-site installs, so dropping them loses no data.
	 */
	private static function drop_orchestration_tables(): void
	{
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names derived from trusted $wpdb->prefix.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mhm_rentiva_usage_metrics");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mhm_rentiva_tenants");
		// phpcs:enable
	}

	/**
	 * Delete the dead `mhm_rentiva_country_restriction_enabled` option.
	 *
	 * WHAT WAS WRONG
	 * --------------
	 * The geo-blocking feature is gone: the free core's country check was removed
	 * in Faz 2a (it sent the visitor's IP to ip-api.com over plain HTTP), and the
	 * Pro-side `CountryRestriction` that inherited that call was deleted in Faz 2b
	 * Task 9 — zero callers, no UI, absent from the monolith. No code in EITHER
	 * edition reads this option any more.
	 *
	 * A leftover `mhm_rentiva_country_restriction_enabled = 1` row therefore states
	 * that geo-restriction is ON while nothing whatsoever enforces it. That is a
	 * false security promise, and this project has shipped that exact bug before
	 * (the "Brute Force Protection" toggle that reads ON while
	 * `LockoutManager::init()` is never called). A control that claims to be on and
	 * is not is worse than an absent one: it is relied upon. So the row goes.
	 *
	 * WHY NO SEPARATE run-once FLAG
	 * -----------------------------
	 * `run_migrations()` is already version-gated, so the version bump that carries
	 * this method IS the run-once. Adding an `..._done` flag would import a trap
	 * this project has been bitten by twice:
	 *   - a flag stamped BEFORE the pollution exists means the cleanup never fires
	 *     (v4.27.2);
	 *   - a migration added without bumping CURRENT_VERSION silently never runs on
	 *     existing installs at all.
	 * `delete_option()` is idempotent — deleting an absent option is a harmless
	 * no-op returning false — so there is nothing a flag would protect.
	 * CURRENT_VERSION is bumped to 3.11.0 alongside this method; without that bump
	 * this code would be dead on every existing site.
	 *
	 * SCOPE — deliberately exactly one key
	 * ------------------------------------
	 * Only the standalone `mhm_rentiva_country_restriction_enabled` row is deleted:
	 * it is the one that makes the false ON claim. Two related leftovers are
	 * KNOWINGLY left alone, as they are not this bug and removing user data needs
	 * its own decision:
	 *   - the standalone `mhm_rentiva_allowed_countries` row (a value list; claims
	 *     nothing on its own);
	 *   - the same two keys inside the `mhm_rentiva_settings` array, where
	 *     `SettingsCore::get()` actually reads and where the enabled key is already
	 *     `'0'`. (This is why the feature was doubly dead: even when
	 *     `CountryRestriction` existed, it read `'0'` from the array and never saw
	 *     the standalone `1`.)
	 */
	private static function delete_dead_country_restriction_option(): void
	{
		delete_option('mhm_rentiva_country_restriction_enabled');
	}
}
