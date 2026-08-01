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
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration/DDL routines intentionally execute controlled schema and maintenance SQL against known WordPress tables.
final class DatabaseMigrator {





	/**
	 * Migration version
	 *
	 * Bump this when a new schema-creating migration is added so that
	 * `version_compare()` triggers `run_migrations()` on existing installs.
	 */
	private const CURRENT_VERSION = '3.14.0';

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

			// Payout governance schema (the `payout_audit` table + the seven
			// `mhm_rentiva_*` capabilities added to the administrator role)
			// belongs to the add-on's GovernanceService -- the class that actually
			// reads/writes the audit trail and enforces those capabilities.
			// Gated on its presence rather than a registration check for the same
			// reason as the Ledger cluster below: this is a question of which
			// FILES this build ships, not which features are registered as
			// active. A registration gate would also skip the schema on an add-on
			// install whose extension is not yet activated, and since
			// run_migrations() is version-gated it would not re-run after
			// activation -- leaving the add-on with a half-built schema.
			if (class_exists(\MHMRentiva\Core\Financial\GovernanceService::class)) {
				self::create_table('mhm_rentiva_payout_audit');
				self::register_governance_capabilities();
			}

			// Financial / ledger-audit schema. Every table in this cluster --
			// `ledger`, `commission_policy`, and the `key_registry` that holds the
			// ledger-signing keys -- belongs to the add-on; Lite ships no class that reads
			// or writes any of them (Ledger, CommissionResolver, KeyPairManager and
			// KeyRegistryRepository all moved to the add-on). Creating them anyway left
			// dead schema in every Lite install.
			//
			// Gated on LedgerMigration (the seam that owns the cluster's primary
			// table) rather than on a registration check: this is a question about
			// which FILES this build ships, not which features are registered as
			// active. A registration gate would also skip the schema on an add-on
			// install whose extension is not yet activated, and since
			// run_migrations() is version-gated it would not re-run after
			// activation -- leaving the add-on with a half-built schema. Mirrors
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
			// Settings -> Security tab removed — drop the rows it left behind.
			self::delete_dead_security_setting_keys();
			// API-key management removed — drop the credentials it stored.
			self::delete_dead_api_key_option();
			// The scheduled-notification queue had no producer; the cron and its
			// table go with the class.
			self::remove_dead_notification_queue();
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

		// Every statement is written out in full at its own $wpdb->query() call
		// rather than looped over a table of SQL strings. The index name and the
		// column list are fixed text and the only interpolation is $wpdb's own
		// table properties, so nothing here can originate from a request -- and
		// that is readable at the line that runs the query.

		// 1. Composite index for status queries
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhm_status_lookup',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhm_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)")
		);

		// 2. Timestamp index for date range queries
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhm_timestamp_range',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhm_timestamp_range ON {$wpdb->postmeta} (post_id, meta_key(50), meta_value(20))")
		);

		// 3. Index for vehicle booking lookups
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhm_vehicle_bookings',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhm_vehicle_bookings ON {$wpdb->postmeta} (meta_value(20), post_id)")
		);

		// 4. Index for post date queries
		self::create_index_if_missing(
			$wpdb->posts,
			'idx_posts_date_type',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_posts_date_type ON {$wpdb->posts} (post_date, post_type(20), post_status(20))")
		);

		// 5. Index for booking meta queries
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhm_booking_meta',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhm_booking_meta ON {$wpdb->postmeta} (meta_key(50), post_id, meta_value(50))")
		);

		// 6. Index for customer email lookups
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhm_customer_email',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhm_customer_email ON {$wpdb->postmeta} (meta_key(50), meta_value(100))")
		);

		// 7. Index for price range queries
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhm_price_range',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhm_price_range ON {$wpdb->postmeta} (meta_key(50), meta_value(20))")
		);

		// 8. Index for combined booking lookup
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhm_booking_combined',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhm_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))")
		);
	}

	/**
	 * Run one CREATE INDEX statement unless the index is already there.
	 *
	 * The statement itself is passed as a closure so it stays a literal at its
	 * own call site; this helper only owns the "does it exist / did it fail"
	 * bookkeeping that used to be duplicated per loop iteration.
	 *
	 * @param string          $table      Table the index belongs to.
	 * @param string          $index_name Index name, also used in the error log.
	 * @param callable():bool $run        Runs the statement, true when it succeeded.
	 */
	private static function create_index_if_missing(string $table, string $index_name, callable $run): void
	{
		global $wpdb;

		try {
			if (self::index_exists($table, $index_name)) {
				return;
			}

			if (! $run()) {
				self::log_index_error($index_name, (string) $wpdb->last_error);
			}
		} catch (\Exception $e) {
			self::log_index_error($index_name, $e->getMessage());
		}
	}

	/**
	 * Optimize existing indexes
	 */
	private static function optimize_existing_indexes(): void
	{
		global $wpdb;

		// Both statements are literals naming $wpdb's own tables.
		try {
			$wpdb->query("ANALYZE TABLE {$wpdb->posts}");
		} catch (\Exception $e) {
			self::log_index_error('ANALYZE TABLE posts', $e->getMessage());
		}

		try {
			$wpdb->query("ANALYZE TABLE {$wpdb->postmeta}");
		} catch (\Exception $e) {
			self::log_index_error('ANALYZE TABLE postmeta', $e->getMessage());
		}
	}

	/**
	 * Detect and add missing indexes
	 */
	private static function add_missing_indexes(): void
	{
		global $wpdb;

		// One statement shape for all of them: only the index name varies, and it
		// is bound through %i rather than pasted into the SQL.
		foreach (self::missing_index_names() as $index_name) {
			self::create_index_if_missing(
				$wpdb->postmeta,
				$index_name,
				static fn (): bool => false !== $wpdb->query(
					$wpdb->prepare(
						'CREATE INDEX %i ON %i (meta_key(50), meta_value(50), post_id)',
						$index_name,
						$wpdb->postmeta
					)
				)
			);
		}
	}

	/**
	 * Names of the per-meta-key postmeta indexes this plugin maintains.
	 *
	 * @return list<string>
	 */
	private static function missing_index_names(): array
	{
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

		return array_map(
			static fn (string $meta_key): string => 'idx_mhm_' . str_replace('_mhm_', '', $meta_key),
			$mhm_meta_keys
		);
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

		// Each probe is timed around its own literal statement. Looping over a
		// table of SQL strings hid the query text from the line that ran it.
		return array(
			'status_lookup'    => self::time_probe(
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhm_status' AND meta_value = 'confirmed'"),
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhm_status' AND meta_value = 'confirmed'"
			),
			'date_range'       => self::time_probe(
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhm_start_ts' AND meta_value > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))"),
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhm_start_ts' AND meta_value > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))"
			),
			'vehicle_bookings' => self::time_probe(
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhm_vehicle_id' AND meta_value = '123'"),
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhm_vehicle_id' AND meta_value = '123'"
			),
			'post_date_query'  => self::time_probe(
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vehicle_booking' AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vehicle_booking' AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
			),
		);
	}

	/**
	 * Time one probe and report it the way test_index_performance() always has.
	 *
	 * @param callable $run   Runs the probe.
	 * @param string   $query The statement text, for the report.
	 */
	private static function time_probe(callable $run, string $query): array
	{
		$start_time = microtime(true);
		$result     = $run();
		$end_time   = microtime(true);

		return array(
			'execution_time' => round(( $end_time - $start_time ) * 1000, 2), // ms
			'result'         => $result,
			'query'          => $query,
		);
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
				$result     = $wpdb->query($wpdb->prepare('OPTIMIZE TABLE %i', $table));
				$end_time   = microtime(true);

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

		// Drop-then-recreate for the two indexes worth rebuilding. Each statement
		// is a literal at its own call site; only the existence bookkeeping is
		// shared.
		return array_values(array_filter(array(
			self::rebuild_index(
				'DROP',
				$wpdb->postmeta,
				'idx_mhm_status_lookup',
				"DROP INDEX idx_mhm_status_lookup ON {$wpdb->postmeta}",
				static fn () => $wpdb->query("DROP INDEX idx_mhm_status_lookup ON {$wpdb->postmeta}")
			),
			self::rebuild_index(
				'CREATE',
				$wpdb->postmeta,
				'idx_mhm_status_lookup',
				"CREATE INDEX idx_mhm_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)",
				static fn () => $wpdb->query("CREATE INDEX idx_mhm_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)")
			),
			self::rebuild_index(
				'DROP',
				$wpdb->postmeta,
				'idx_mhm_booking_combined',
				"DROP INDEX idx_mhm_booking_combined ON {$wpdb->postmeta}",
				static fn () => $wpdb->query("DROP INDEX idx_mhm_booking_combined ON {$wpdb->postmeta}")
			),
			self::rebuild_index(
				'CREATE',
				$wpdb->postmeta,
				'idx_mhm_booking_combined',
				"CREATE INDEX idx_mhm_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))",
				static fn () => $wpdb->query("CREATE INDEX idx_mhm_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))")
			),
		)));
	}

	/**
	 * Run one rebuild step, skipping it when the index is already in the wanted state.
	 *
	 * @param string   $action DROP or CREATE.
	 * @param string   $table  Table the index belongs to.
	 * @param string   $name   Index name.
	 * @param string   $sql    Statement text, for the report.
	 * @param callable $run    Runs the statement.
	 * @return array|null The report row, or null when the step was skipped.
	 */
	private static function rebuild_index(string $action, string $table, string $name, string $sql, callable $run): ?array
	{
		global $wpdb;

		$exists = self::index_exists($table, $name);
		if (( 'DROP' === $action && ! $exists ) || ( 'CREATE' === $action && $exists )) {
			return null;
		}

		$start_time = microtime(true);
		$result     = $run();
		$end_time   = microtime(true);

		return array(
			'sql'            => $sql,
			'success'        => $result !== false,
			'execution_time' => round(( $end_time - $start_time ) * 1000, 2),
			'error'          => $result === false ? (string) $wpdb->last_error : null,
		);
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
			// Delete MHM Rentiva indexes. Each DROP is a literal at its own call
			// site; only the "is it there" check is shared, so an absent index
			// stays a silent no-op exactly as before.
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhm_status_lookup', static fn () => $wpdb->query("DROP INDEX idx_mhm_status_lookup ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhm_timestamp_range', static fn () => $wpdb->query("DROP INDEX idx_mhm_timestamp_range ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhm_vehicle_bookings', static fn () => $wpdb->query("DROP INDEX idx_mhm_vehicle_bookings ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->posts, 'idx_posts_date_type', static fn () => $wpdb->query("DROP INDEX idx_posts_date_type ON {$wpdb->posts}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhm_booking_meta', static fn () => $wpdb->query("DROP INDEX idx_mhm_booking_meta ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhm_customer_email', static fn () => $wpdb->query("DROP INDEX idx_mhm_customer_email ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhm_price_range', static fn () => $wpdb->query("DROP INDEX idx_mhm_price_range ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhm_booking_combined', static fn () => $wpdb->query("DROP INDEX idx_mhm_booking_combined ON {$wpdb->postmeta}"));

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
	 * Run one DROP INDEX statement, but only when the index is actually there.
	 *
	 * @param string   $table Table the index belongs to.
	 * @param string   $name  Index name.
	 * @param callable $run   Runs the statement.
	 */
	private static function drop_index_if_present(string $table, string $name, callable $run): void
	{
		if (self::index_exists($table, $name)) {
			$run();
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
	 * Both tables belong to the Transfer add-on module. Lite has no location search
	 * and no Transfer module (owner decision 2026-07-16), so it must not ship the
	 * schema: an empty `rentiva_transfer_locations` that nothing can populate or
	 * read is dead schema, which is WP.org-unclean (cf. faz1-exit-decisions Task 5
	 * REQUIREMENT 2).
	 *
	 * Task A9c seam inversion: the actual CREATE TABLE / legacy-rename SQL moved
	 * verbatim to the add-on's `\MHMRentiva\Core\Database\Migrations\TransferMigration`
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

		// 1. Orphan Post Meta Cleaning.
		//
		// The pattern was `_mhm_%%`, a leftover from a prepare() this statement
		// never went through. Measured rather than assumed: in MySQL LIKE, `%%`
		// is simply two wildcards and behaves identically to `%` (verified over
		// `_mhm_status`, `_mhm_%_legacy`, `_mhm_`, `xmhm_foo`, `_mhmX_foo` --
		// all match both patterns). So this is a no-op cleanup, NOT a bug fix:
		// the doubling only becomes wrong the day someone wraps this statement
		// in prepare(), where `%%` means a literal percent sign and the clause
		// would silently stop matching. Written singly so that trap is gone.
		//
		// Note the leading `_` is itself a single-character wildcard, so this
		// also reaches e.g. `xmhm_foo`. Pre-existing and deliberately left
		// alone here; escaping it would change which rows get deleted.
		$wpdb->query(
			"DELETE pm
        FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
        WHERE wp.ID IS NULL
        AND pm.meta_key LIKE '_mhm_%'"
		);

		// 2. Transient Data Cleaning
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_mhm_rentiva_rate_limit_%'
             OR option_name LIKE '_transient_timeout_mhm_rentiva_rate_limit_%'
             OR option_name LIKE '_transient_mhm_rate_limit_%'
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Adding the idempotency key IS this migration.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD UNIQUE KEY `payout_action_tx` (`tenant_id`, `payout_id`, `action`, `tx_uuid`)',
					$table_name
				)
			);
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
				// The old standalone option is deliberately left in place: this copy is
				// additive, and deleting the source would make the migration
				// unrepeatable if it were ever interrupted.
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

		$results = $wpdb->get_results(
			$wpdb->prepare('SHOW INDEX FROM %i WHERE Key_name = %s', $table_name, $index_name)
		);

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
	 * add-on's `CountryRestriction` that inherited that call was deleted in Faz 2b
	 * Task 9 — zero callers, no UI, absent from the monolith. No code reads this
	 * option any more.
	 *
	 * A leftover `mhm_rentiva_country_restriction_enabled = 1` row therefore states
	 * that geo-restriction is ON while nothing whatsoever enforces it. That is a
	 * false security promise, and this project has shipped that exact bug before:
	 * the "Brute Force Protection" toggle read ON while nothing enforced it,
	 * which is why the whole Security tab was removed and is cleaned up by
	 * `delete_dead_security_setting_keys()` below. A control that claims to be on
	 * and is not is worse than an absent one: it is relied upon. So the row goes.
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

	/**
	 * Remove the rows the deleted Settings -> Security tab left in
	 * `mhm_rentiva_settings`.
	 *
	 * WHY
	 * ---
	 * That screen rendered seventeen controls under headings like "Brute Force
	 * Protection", "SQL Injection Protection" and "Enable Rate Limiting". None
	 * was wired to anything: `LockoutManager`, `WafManager` and
	 * `SecurityManager` autoloaded and registered zero hooks, so each toggle
	 * wrote a row that nothing ever read. The screen and those three classes
	 * are gone.
	 *
	 * Deleting the UI is only half of it. Wherever an administrator saved that
	 * tab, the option still carries `..._brute_force_protection = '1'` and its
	 * siblings: rows asserting that protections are ON while nothing enforces
	 * them. This is the same false-security-promise bug as the dead
	 * `..._country_restriction_enabled` row handled directly above, and it is
	 * settled the same way.
	 *
	 * SCOPE
	 * -----
	 * Exactly the fifteen keys that tab owned, and only inside the settings
	 * array it wrote to. `mhm_rentiva_ip_whitelist` also existed as a
	 * standalone option read by `AuthHelper::isIpWhitelisted()` — both the
	 * reader and the method's only caller were absent, so the method went with
	 * the tab; no standalone row is touched here because the tab never wrote
	 * one.
	 *
	 * Like the method above, this needs no run-once flag: `run_migrations()` is
	 * version-gated, and the CURRENT_VERSION bump that carries this method IS
	 * the run-once. The write is skipped entirely when there is nothing to
	 * remove, so a clean install is not rewritten.
	 */
	/**
	 * Remove the API keys the deleted key manager stored.
	 *
	 * The Integration tab offered "Secure API Access Tokens" with READ / WRITE /
	 * ADMIN ("Full system control") levels. `APIKeyManager::verify_api_key()`
	 * had no caller anywhere in either edition: every REST route authenticates
	 * through `AuthHelper::verifyAuth()`, which accepts a WordPress nonce and
	 * nothing else. A key issued there opened no endpoint, and the stored
	 * permission was never evaluated. The whole surface is gone.
	 *
	 * The option holds hashes rather than the keys themselves, so leaving it
	 * would not expose a credential — but it would leave a row describing
	 * ADMIN-level grants that no longer have any machinery behind them, which
	 * is the same misleading state the sibling method above exists to clear.
	 *
	 * Same version gate, same reasoning about run-once flags as
	 * `delete_dead_country_restriction_option()`; `delete_option()` on an absent
	 * option is a harmless no-op.
	 */
	/**
	 * Remove the scheduled-notification queue.
	 *
	 * `NotificationManager` registered an hourly cron, drained a queue table and
	 * reported itself in Cron Monitor as a healthy "Scheduled Notifications"
	 * job. Nothing ever filled that queue: `send_notification()` and
	 * `queue_notification()` had no callers in either edition, so the job ran
	 * forever over an empty table while the monitor showed it working. Real
	 * email never went through it -- booking confirmations, reminders and
	 * refunds are sent synchronously by the Emails module.
	 *
	 * Deleting the class is not enough. A scheduled event lives in the `cron`
	 * option and outlives the code that scheduled it, so both the current hook
	 * name and its pre-5.2.0 spelling are cleared here, and the queue table --
	 * which nothing has written since it was introduced -- is dropped.
	 */
	private static function remove_dead_notification_queue(): void
	{
		global $wpdb;

		foreach (array( 'mhm_rentiva_send_scheduled_notifications', 'mhm_send_scheduled_notifications' ) as $hook) {
			$timestamp = wp_next_scheduled($hook);
			while ($timestamp) {
				wp_unschedule_event($timestamp, $hook);
				$timestamp = wp_next_scheduled($hook);
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- A schema change is the entire point of the method: this is the migration that removes the queue table whose feature was deleted. There is nothing to cache.
		$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'mhm_notification_queue'));
	}

	private static function delete_dead_api_key_option(): void
	{
		delete_option('mhm_rentiva_api_keys');
	}

	private static function delete_dead_security_setting_keys(): void
	{
		$dead_keys = array(
			'mhm_rentiva_ip_whitelist_enabled',
			'mhm_rentiva_ip_whitelist',
			'mhm_rentiva_ip_blacklist_enabled',
			'mhm_rentiva_ip_blacklist',
			'mhm_rentiva_brute_force_protection',
			'mhm_rentiva_max_login_attempts',
			'mhm_rentiva_login_lockout_duration',
			'mhm_rentiva_sql_injection_protection',
			'mhm_rentiva_xss_protection',
			'mhm_rentiva_csrf_protection',
			'mhm_rentiva_rate_limit_enabled',
			'mhm_rentiva_rate_limit_block_duration',
			'mhm_rentiva_rate_limit_requests_per_minute',
			'mhm_rentiva_rate_limit_booking_per_minute',
			'mhm_rentiva_rate_limit_payment_per_minute',
		);

		$settings = get_option('mhm_rentiva_settings');

		if (! is_array($settings)) {
			return;
		}

		$removed = false;
		foreach ($dead_keys as $key) {
			if (array_key_exists($key, $settings)) {
				unset($settings[ $key ]);
				$removed = true;
			}
		}

		if ($removed) {
			update_option('mhm_rentiva_settings', $settings);
		}
	}
}
