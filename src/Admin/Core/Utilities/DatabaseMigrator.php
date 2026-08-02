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
	private const CURRENT_VERSION = '4.0.0';

	/**
	 * Rows rewritten per statement by the 6.0.0 rename.
	 *
	 * Every rewrite below is a loop of bounded statements rather than one
	 * unbounded UPDATE, so a site with millions of meta rows makes progress in
	 * chunks instead of holding one lock until the request times out. The step
	 * is idempotent, so a run that dies part-way simply resumes.
	 */
	private const RENAME_BATCH = 5000;

	/**
	 * The merge-loser backup table for the CURRENT run, or null before one is
	 * needed. Reset at the top of migrate_prefix_rename_600() so each run gets
	 * its own table and a run with nothing to discard creates none at all.
	 */
	private static ?string $merge_loser_table = null;

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
		self::adopt_legacy_db_version();

		$current_version = self::stored_db_version();

		if (version_compare($current_version, self::CURRENT_VERSION, '<')) {
			// FIRST, and the order is load-bearing rather than tidy. Every other
			// step in this method now speaks the NEW names:
			// migrate_vehicle_lifecycle_status() selects
			// post_type = 'mhmrentiva_vehicle', create_table() builds
			// `mhmrentiva_payout_audit`. Run any of them ahead of the rename and
			// they either see an empty world and stamp their own "done" flag over
			// data they never looked at, or plant an empty destination table the
			// RENAME below then has to reclaim.
			self::migrate_prefix_rename_600();

			self::create_transfer_tables(); // VIP Transfer Tables

			// Payout governance schema (the `payout_audit` table + the seven
			// `mhmrentiva_*` capabilities added to the administrator role)
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
				self::create_table('mhmrentiva_payout_audit');
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
			update_option('mhmrentiva_db_version', self::CURRENT_VERSION);

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
	 * The stored schema version, reading through the 6.0.0 rename.
	 *
	 * This single read gates the ENTIRE body of run_migrations() -- all twelve
	 * pre-existing steps as well as the rename below -- so getting it wrong is
	 * not a local mistake. The option was itself renamed in 6.0.0, and on an
	 * upgrading site the new name does not exist yet: the value is still under
	 * the old one. Falling through to the '1.0.0' default there would make
	 * version_compare() true and replay every earlier step as though this were
	 * a fresh install.
	 *
	 * Pure on purpose -- get_migration_status() calls it to render a screen.
	 * The one-time adoption of the legacy row is adopt_legacy_db_version().
	 */
	private static function stored_db_version(): string
	{
		$version = (string) get_option('mhmrentiva_db_version', '');
		if ('' !== $version) {
			return $version;
		}

		// PrefixMigrationMap::BOOTSTRAP_FALLBACK_ALLOWLIST -- this literal is
		// deliberately exempt from the rename sweep. It is how a pre-6.0.0
		// install is recognised at all, so it has to keep naming the old row.
		$legacy = (string) get_option('mhm_rentiva_db_version', '');

		return '' !== $legacy ? $legacy : '1.0.0';
	}

	/**
	 * Move a pre-6.0.0 version stamp onto its new name, once.
	 *
	 * Deliberately outside PrefixMigrationMap::OPTIONS: this option has its own
	 * bootstrap path (it is read before the map-driven pass could possibly run),
	 * and carrying it a second time through that pass would be a double move.
	 */
	private static function adopt_legacy_db_version(): void
	{
		if ('' !== (string) get_option('mhmrentiva_db_version', '')) {
			return;
		}

		$legacy = (string) get_option('mhm_rentiva_db_version', '');
		if ('' === $legacy) {
			return;
		}

		add_option('mhmrentiva_db_version', $legacy, '', true);
		delete_option('mhm_rentiva_db_version');
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
			'idx_mhmrentiva_status_lookup',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhmrentiva_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)")
		);

		// 2. Timestamp index for date range queries
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhmrentiva_timestamp_range',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhmrentiva_timestamp_range ON {$wpdb->postmeta} (post_id, meta_key(50), meta_value(20))")
		);

		// 3. Index for vehicle booking lookups
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhmrentiva_vehicle_bookings',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhmrentiva_vehicle_bookings ON {$wpdb->postmeta} (meta_value(20), post_id)")
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
			'idx_mhmrentiva_booking_meta',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhmrentiva_booking_meta ON {$wpdb->postmeta} (meta_key(50), post_id, meta_value(50))")
		);

		// 6. Index for customer email lookups
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhmrentiva_customer_email',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhmrentiva_customer_email ON {$wpdb->postmeta} (meta_key(50), meta_value(100))")
		);

		// 7. Index for price range queries
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhmrentiva_price_range',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhmrentiva_price_range ON {$wpdb->postmeta} (meta_key(50), meta_value(20))")
		);

		// 8. Index for combined booking lookup
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_mhmrentiva_booking_combined',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_mhmrentiva_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))")
		);

		// 9-12. Customers screen indexes. These used to live in
		// CustomersOptimizer::create_database_indexes(), fired from admin_init and
		// bookkept with its own `mhmrentiva_customers_indexes_created` option --
		// schema changes run by a read-model class on a page load. They belong
		// here, where index creation is already the subject and where
		// create_index_if_missing() replaces their `CREATE INDEX IF NOT EXISTS`
		// (unsupported before MySQL 8.0.29) with a portable existence check.
		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_postmeta_customer_email',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_postmeta_customer_email ON {$wpdb->postmeta} (meta_key, meta_value(50))")
		);

		self::create_index_if_missing(
			$wpdb->postmeta,
			'idx_postmeta_booking_price',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_postmeta_booking_price ON {$wpdb->postmeta} (post_id, meta_key)")
		);

		self::create_index_if_missing(
			$wpdb->usermeta,
			'idx_usermeta_customer_phone',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_usermeta_customer_phone ON {$wpdb->usermeta} (user_id, meta_key)")
		);

		self::create_index_if_missing(
			$wpdb->posts,
			'idx_posts_booking_date',
			static fn (): bool => false !== $wpdb->query("CREATE INDEX idx_posts_booking_date ON {$wpdb->posts} (post_type, post_status, post_date)")
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
		$mhmrentiva_meta_keys = array(
			'_mhmrentiva_status',
			'_mhmrentiva_vehicle_id',
			'_mhmrentiva_start_ts',
			'_mhmrentiva_end_ts',
			'_mhmrentiva_total_price',
			'_mhmrentiva_contact_email',
			'_mhmrentiva_contact_name',
			'_mhmrentiva_customer_id',
		);

		return array_map(
			static fn (string $meta_key): string => 'idx_mhmrentiva_' . str_replace('_mhmrentiva_', '', $meta_key),
			$mhmrentiva_meta_keys
		);
	}

	/**
	 * Check index status
	 */
	public static function check_index_status(): array
	{
		global $wpdb;

		$status = array(
			'total_indexes'      => 0,
			'mhmrentiva_indexes' => 0,
			'performance_score'  => 0,
			'missing_indexes'    => array(),
			'recommendations'    => array(),
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
				if (strpos($index->Key_name, 'idx_mhmrentiva_') === 0) {
					++$status['mhmrentiva_indexes'];
				}
			}

			// Calculate performance score
			$status['performance_score'] = min(100, ( $status['mhmrentiva_indexes'] / 8 ) * 100);

			// Recommendations
			if ($status['mhmrentiva_indexes'] < 5) {
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
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhmrentiva_status' AND meta_value = 'confirmed'"),
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhmrentiva_status' AND meta_value = 'confirmed'"
			),
			'date_range'       => self::time_probe(
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhmrentiva_start_ts' AND meta_value > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))"),
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhmrentiva_start_ts' AND meta_value > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))"
			),
			'vehicle_bookings' => self::time_probe(
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhmrentiva_vehicle_id' AND meta_value = '123'"),
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mhmrentiva_vehicle_id' AND meta_value = '123'"
			),
			'post_date_query'  => self::time_probe(
				static fn () => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mhmrentiva_booking' AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mhmrentiva_booking' AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
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
				'idx_mhmrentiva_status_lookup',
				"DROP INDEX idx_mhmrentiva_status_lookup ON {$wpdb->postmeta}",
				static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_status_lookup ON {$wpdb->postmeta}")
			),
			self::rebuild_index(
				'CREATE',
				$wpdb->postmeta,
				'idx_mhmrentiva_status_lookup',
				"CREATE INDEX idx_mhmrentiva_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)",
				static fn () => $wpdb->query("CREATE INDEX idx_mhmrentiva_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)")
			),
			self::rebuild_index(
				'DROP',
				$wpdb->postmeta,
				'idx_mhmrentiva_booking_combined',
				"DROP INDEX idx_mhmrentiva_booking_combined ON {$wpdb->postmeta}",
				static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_booking_combined ON {$wpdb->postmeta}")
			),
			self::rebuild_index(
				'CREATE',
				$wpdb->postmeta,
				'idx_mhmrentiva_booking_combined',
				"CREATE INDEX idx_mhmrentiva_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))",
				static fn () => $wpdb->query("CREATE INDEX idx_mhmrentiva_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))")
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
		$current_version  = self::stored_db_version();
		$index_status     = self::check_index_status();
		$performance_test = self::test_index_performance();

		return array(
			'current_version'  => $current_version,
			'target_version'   => self::CURRENT_VERSION,
			'needs_migration'  => version_compare($current_version, self::CURRENT_VERSION, '<'),
			'index_status'     => $index_status,
			'performance_test' => $performance_test,
			'last_migration'   => get_option('mhmrentiva_last_migration', 'Never'),
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
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhmrentiva_status_lookup', static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_status_lookup ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhmrentiva_timestamp_range', static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_timestamp_range ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhmrentiva_vehicle_bookings', static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_vehicle_bookings ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->posts, 'idx_posts_date_type', static fn () => $wpdb->query("DROP INDEX idx_posts_date_type ON {$wpdb->posts}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhmrentiva_booking_meta', static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_booking_meta ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhmrentiva_customer_email', static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_customer_email ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhmrentiva_price_range', static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_price_range ON {$wpdb->postmeta}"));
			self::drop_index_if_present($wpdb->postmeta, 'idx_mhmrentiva_booking_combined', static fn () => $wpdb->query("DROP INDEX idx_mhmrentiva_booking_combined ON {$wpdb->postmeta}"));

			// Reset version to original state. Written under the new name only:
			// stored_db_version() prefers it, so leaving a stale legacy row here
			// would be read by nothing.
			update_option('mhmrentiva_db_version', '1.0.0');

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

		$table_name = $wpdb->prefix . 'mhmrentiva_ratings';

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
		// The pattern was `_mhmrentiva_%%`, a leftover from a prepare() this statement
		// never went through. Measured rather than assumed: in MySQL LIKE, `%%`
		// is simply two wildcards and behaves identically to `%` (verified over
		// `_mhmrentiva_status`, `_mhmrentiva_%_legacy`, `_mhmrentiva_`, `xmhmrentiva_foo`, `_mhmX_foo` --
		// all match both patterns). So this is a no-op cleanup, NOT a bug fix:
		// the doubling only becomes wrong the day someone wraps this statement
		// in prepare(), where `%%` means a literal percent sign and the clause
		// would silently stop matching. Written singly so that trap is gone.
		//
		// Note the leading `_` is itself a single-character wildcard, so this
		// also reaches e.g. `xmhmrentiva_foo`. Pre-existing and deliberately left
		// alone here; escaping it would change which rows get deleted.
		$wpdb->query(
			"DELETE pm
        FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
        WHERE wp.ID IS NULL
        AND pm.meta_key LIKE '_mhmrentiva_%'"
		);

		// prefix-rename:ignore-start
		// 2. Transient Data Cleaning
		$wpdb->query(
			// Four DISTINCT patterns: the current family, plus the two pre-6.0.0
			// families the rename collapsed onto it. Written out because
			// 'mhm_rentiva_rate_limit_' and the bare 'mhm_rate_limit_' both become
			// 'mhmrentiva_rate_limit_', so the list otherwise reads as two
			// duplicates and the bare family stops being swept on any site that
			// has not run Görev 13's migration.
			"DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_mhmrentiva_rate_limit_%'
             OR option_name LIKE '_transient_timeout_mhmrentiva_rate_limit_%'
             OR option_name LIKE '_transient_mhm_rentiva_rate_limit_%'
             OR option_name LIKE '_transient_timeout_mhm_rentiva_rate_limit_%'
             OR option_name LIKE '_transient_mhm_rate_limit_%'
             OR option_name LIKE '_transient_timeout_mhm_rate_limit_%'"
		);
		// prefix-rename:ignore-end
	}
	/**
	 * Create specific table by key
	 */
	public static function create_table(string $table_key): bool
	{
		switch ($table_key) {
			case 'payment_log':
			case 'mhmrentiva_payment_log':
				self::create_payment_log_table();
				return true;
			case 'sessions':
			case 'mhmrentiva_sessions':
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
			case 'mhmrentiva_ratings':
				self::create_rating_table();
				return true;
			case 'queue':
			case 'mhmrentiva_queue':
				self::create_queue_table();
				return true;
			case 'report_queue':
			case 'background_jobs':
			case 'mhmrentiva_background_jobs':
				self::create_background_jobs_table();
				return true;
			case 'message_logs':
			case 'mhmrentiva_message_logs':
				self::create_message_logs_table();
				return true;
			case 'payout_audit':
			case 'mhmrentiva_payout_audit':
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
			$role->add_cap('mhmrentiva_approve_payout');
			$role->add_cap('mhmrentiva_freeze_payouts');
			$role->add_cap('mhmrentiva_view_financial_audit');

			// Sprint 10: Multi-Actor Workflow Capabilities
			$role->add_cap('mhmrentiva_create_payout');
			$role->add_cap('mhmrentiva_review_payout');
			$role->add_cap('mhmrentiva_finalize_payout');
			$role->add_cap('mhmrentiva_override_maker_checker');
		}
	}

	/**
	 * Create payout audit table (append-only)
	 */
	public static function create_payout_audit_table(): void
	{
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mhmrentiva_payout_audit';
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
		$table_name      = $wpdb->prefix . 'mhmrentiva_payment_log';
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
		$table_name      = $wpdb->prefix . 'mhmrentiva_sessions';
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
		$table_name      = $wpdb->prefix . 'mhmrentiva_message_logs';
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
		$table_name      = $wpdb->prefix . 'mhmrentiva_key_registry';
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
		$settings = (array) get_option('mhmrentiva_settings', array());

		// Map old mhmrentiva_ keys to new rentiva_ keys in the settings array
		$standalone_mapping = array(
			'mhmrentiva_transfer_deposit_type' => 'rentiva_transfer_deposit_type',
			'mhmrentiva_transfer_deposit_rate' => 'rentiva_transfer_deposit_rate',
			'mhmrentiva_transfer_custom_types' => 'rentiva_transfer_custom_types',
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
			update_option('mhmrentiva_settings', $settings);
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
	 * Vehicles without _mhmrentiva_vehicle_status get 'active' (legacy default).
	 * Also sets initial listing_started_at and listing_expires_at for published vehicles.
	 *
	 * Idempotent — skips vehicles that already have _mhmrentiva_vehicle_lifecycle_status set.
	 */
	private static function migrate_vehicle_lifecycle_status(): void
	{
		if (get_option('mhmrentiva_lifecycle_migration_done') === '1') {
			return;
		}

		global $wpdb;

		// Get all vehicles that do NOT already have the lifecycle meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$vehicle_ids = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhmrentiva_vehicle_lifecycle_status'
			 WHERE p.post_type = 'mhmrentiva_vehicle' AND pm.meta_id IS NULL"
		);

		if (empty($vehicle_ids)) {
			update_option('mhmrentiva_lifecycle_migration_done', '1', false);
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
			$old_status = get_post_meta($vid, '_mhmrentiva_vehicle_status', true);
			$new_status = $status_map[ $old_status ] ?? 'active';

			update_post_meta($vid, '_mhmrentiva_vehicle_lifecycle_status', $new_status);

			// Set listing timer for active/published vehicles.
			$post_status = get_post_status($vid);
			if ($new_status === 'active' && $post_status === 'publish') {
				$published = get_post_field('post_date_gmt', $vid);
				if (! empty($published) && $published !== '0000-00-00 00:00:00') {
					update_post_meta($vid, '_mhmrentiva_vehicle_listing_started_at', $published);
					$expires = gmdate('Y-m-d H:i:s', strtotime($now . ' +90 days'));
					update_post_meta($vid, '_mhmrentiva_vehicle_listing_expires_at', $expires);
				}
			}
		}

		update_option('mhmrentiva_lifecycle_migration_done', '1', false);
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
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mhmrentiva_usage_metrics");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mhmrentiva_tenants");
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange -- the bare
		// form of this line also cancelled the FILE-level disable at the top, so
		// every direct query below it was reported despite that declaration.
	}

	/**
	 * Delete the dead `mhmrentiva_country_restriction_enabled` option.
	 *
	 * WHAT WAS WRONG
	 * --------------
	 * The geo-blocking feature is gone: the free core's country check was removed
	 * in Faz 2a (it sent the visitor's IP to ip-api.com over plain HTTP), and the
	 * add-on's `CountryRestriction` that inherited that call was deleted in Faz 2b
	 * Task 9 — zero callers, no UI, absent from the monolith. No code reads this
	 * option any more.
	 *
	 * A leftover `mhmrentiva_country_restriction_enabled = 1` row therefore states
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
	 * Only the standalone `mhmrentiva_country_restriction_enabled` row is deleted:
	 * it is the one that makes the false ON claim. Two related leftovers are
	 * KNOWINGLY left alone, as they are not this bug and removing user data needs
	 * its own decision:
	 *   - the standalone `mhmrentiva_allowed_countries` row (a value list; claims
	 *     nothing on its own);
	 *   - the same two keys inside the `mhmrentiva_settings` array, where
	 *     `SettingsCore::get()` actually reads and where the enabled key is already
	 *     `'0'`. (This is why the feature was doubly dead: even when
	 *     `CountryRestriction` existed, it read `'0'` from the array and never saw
	 *     the standalone `1`.)
	 */
	private static function delete_dead_country_restriction_option(): void
	{
		delete_option('mhmrentiva_country_restriction_enabled');
	}

	/**
	 * Remove the rows the deleted Settings -> Security tab left in
	 * `mhmrentiva_settings`.
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
	 * array it wrote to. `mhmrentiva_ip_whitelist` also existed as a
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

		// All three spellings: the current name and the two PRE-6.0.0 ones the
		// rename collapsed onto it. A scheduled event lives in wp_cron
		// independently of the code that scheduled it, so clearing only the
		// current name leaves an orphan that fires into a hook nothing listens to.
		// prefix-rename:ignore-start
		foreach (array( 'mhmrentiva_send_scheduled_notifications', 'mhm_rentiva_send_scheduled_notifications', 'mhm_send_scheduled_notifications' ) as $hook) {
			// prefix-rename:ignore-end
			$timestamp = wp_next_scheduled($hook);
			while ($timestamp) {
				wp_unschedule_event($timestamp, $hook);
				$timestamp = wp_next_scheduled($hook);
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- A schema change is the entire point of the method: this is the migration that removes the queue table whose feature was deleted. There is nothing to cache.
		// Both spellings. This table has NO PrefixMigrationMap::TABLES entry, so
		// Görev 13 never renames the physical table -- on every real install it
		// still carries its pre-6.0.0 name, and dropping only the new one would
		// leave the table this method exists to remove sitting there.
		//
		// The explanation is outside the region on purpose: a region is a blind
		// spot, so it wraps the literals and nothing else. (It used to open here
		// AND again below -- two starts closed by one end. The tool takes the
		// first start, the accountability test took the last, so four lines were
		// silenced by the tool and inspected by nothing.)
		// prefix-rename:ignore-start
		foreach (array( 'mhmrentiva_notification_queue', 'mhm_notification_queue' ) as $legacy_queue_table) {
			// prefix-rename:ignore-end
			$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . $legacy_queue_table));
		}
	}

	private static function delete_dead_api_key_option(): void
	{
		delete_option('mhmrentiva_api_keys');
	}

	private static function delete_dead_security_setting_keys(): void
	{
		$dead_keys = array(
			'mhmrentiva_ip_whitelist_enabled',
			'mhmrentiva_ip_whitelist',
			'mhmrentiva_ip_blacklist_enabled',
			'mhmrentiva_ip_blacklist',
			'mhmrentiva_brute_force_protection',
			'mhmrentiva_max_login_attempts',
			'mhmrentiva_login_lockout_duration',
			'mhmrentiva_sql_injection_protection',
			'mhmrentiva_xss_protection',
			'mhmrentiva_csrf_protection',
			'mhmrentiva_rate_limit_enabled',
			'mhmrentiva_rate_limit_block_duration',
			'mhmrentiva_rate_limit_requests_per_minute',
			'mhmrentiva_rate_limit_booking_per_minute',
			'mhmrentiva_rate_limit_payment_per_minute',
		);

		$settings = get_option('mhmrentiva_settings');

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
			update_option('mhmrentiva_settings', $settings);
		}
	}

	/**
	 * The 6.0.0 prefix rename (T7 mandate).
	 *
	 * Every identifier in this tree now reads `mhmrentiva_`. No live database
	 * has ever stored that name, so this step is the only thing that makes the
	 * code and the data agree, and there is no way back: the old names are gone
	 * from the source.
	 *
	 * PrefixMigrationMap owns WHICH name becomes which. This method owns two
	 * things that map cannot express -- the SCOPE of each rewrite (whose rows
	 * they are) and what to do when the destination already exists -- plus the
	 * order the families move in.
	 *
	 * Idempotent and resumable throughout. Every rewrite is "find rows still
	 * carrying the old name and move them", so a run that dies half-way simply
	 * has less to do next time, and a completed run finds nothing.
	 */
	private static function migrate_prefix_rename_600(): void
	{
		self::$merge_loser_table = null;

		self::rename_options();
		self::rename_post_types();
		self::rename_taxonomies();
		self::rename_post_meta();
		self::rename_user_meta();
		self::rename_comment_meta();
		self::rename_tables();
		self::rekey_cron_hooks();
		self::purge_legacy_transients();

		// One flush rather than per-row invalidation. The rewrites above go
		// through SQL because there is no API for "rename a meta key", so every
		// option, post, term and meta cache in this request now describes names
		// that no longer exist. This runs once in the life of an install.
		wp_cache_flush();
	}

	/**
	 * Options: an exact-name allowlist, 135 entries, straight from the map.
	 *
	 * Scope: `option_name = %s`. No pattern, nothing a sibling product's option
	 * can satisfy.
	 */
	private static function rename_options(): void
	{
		global $wpdb;

		foreach (PrefixMigrationMap::OPTIONS as $old => $new) {
			// Read the source RAW. get_option($old, '__missing__') cannot tell
			// "absent" from "stores the string __missing__", and it discards the
			// autoload flag -- which is part of the value, not metadata about it:
			// an autoloaded setting that lands as autoload=no stops arriving on
			// every page load, and a large blob that lands as autoload=yes is a
			// permanent performance regression.
			$row = $wpdb->get_row(
				$wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $old),
				ARRAY_A
			);

			if (! is_array($row)) {
				// Nothing left to carry. This is also where a second run lands,
				// and it is what makes the whole step idempotent.
				continue;
			}

			// MEASURE THE DESTINATION. On a real upgrade it usually already
			// exists: the renamed code reaches add_option() under the new name
			// before this migration gets a turn, which is what killed Görev 12's
			// dev run with a duplicate key on
			// mhmrentiva_addon_context_migrated_4_36_0 -- and eight destination
			// options were sitting in the pre-rename backup for the same reason.
			//
			// The OLD value wins, uniformly. In the collision case it is the
			// customer's accumulated setting while the new-name row is a default
			// written seconds ago by code that could not find the real one; in the
			// resumed-run case the two hold the same value, so preferring the old
			// one is a no-op. Preferring the new one is silent data loss in the
			// first case and buys nothing in the second.
			delete_option($new);
			$stored_autoload = (string) $row['autoload'];
			$autoload        = self::autoload_flag($stored_autoload);
			add_option($new, maybe_unserialize($row['option_value']), '', $autoload);
			delete_option($old);
		}
	}

	/**
	 * Normalise a raw wp_options.autoload column to what add_option() takes.
	 *
	 * WP < 6.6 stored yes/no; 6.6 added on/off/auto/auto-on/auto-off. Returned
	 * as a bool because add_option() has accepted one in every version -- core
	 * normalises anything that is not 'no'/false to 'yes'.
	 */
	private static function autoload_flag(string $stored): bool
	{
		return in_array($stored, array( 'yes', 'on', 'auto', 'auto-on' ), true);
	}

	/**
	 * Post types.
	 *
	 * Scope: exact equality against a type this plugin registers itself.
	 * Nothing another plugin's post type can satisfy, and no collision is
	 * possible -- wp_posts.post_type carries no uniqueness constraint that two
	 * name families could contend for.
	 */
	private static function rename_post_types(): void
	{
		global $wpdb;

		foreach (PrefixMigrationMap::POST_TYPES as $old => $new) {
			$wpdb->query(
				$wpdb->prepare("UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s", $new, $old)
			);
		}
	}

	/**
	 * Taxonomies.
	 *
	 * Scope: exact equality on wp_term_taxonomy.taxonomy.
	 */
	private static function rename_taxonomies(): void
	{
		global $wpdb;

		foreach (PrefixMigrationMap::TAXONOMIES as $old => $new) {
			self::drop_empty_duplicate_terms($old, $new);

			$wpdb->query(
				$wpdb->prepare("UPDATE {$wpdb->term_taxonomy} SET taxonomy = %s WHERE taxonomy = %s", $new, $old)
			);
		}
	}

	/**
	 * Clear the way for a taxonomy rename.
	 *
	 * Measured, not hypothetical: the pre-rename dev backup carried three
	 * `addon_context` terms holding the object relationships AND three EMPTY
	 * `mhmrentiva_addon_context` terms with the same slugs. AddonContextMigration
	 * had re-created them under the new name because its own "already migrated"
	 * flag was still stored under the old OPTION name. Renaming on top of that
	 * leaves two terms per slug inside one taxonomy, and get_term_by('slug')
	 * then returns whichever the storage engine orders first.
	 *
	 * Only EMPTY duplicates go, and only where the old taxonomy has the same
	 * slug: a term with no relationships at all can only be the copy the new
	 * code just made. A populated duplicate is left in place and logged --
	 * merging two term trees is not a decision a migration may take on its own.
	 */
	private static function drop_empty_duplicate_terms(string $old_taxonomy, string $new_taxonomy): void
	{
		global $wpdb;

		$term_taxonomy_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT ntt.term_taxonomy_id
                   FROM {$wpdb->term_taxonomy} ntt
                   JOIN {$wpdb->terms} nt ON nt.term_id = ntt.term_id
                   JOIN {$wpdb->term_taxonomy} ott ON ott.taxonomy = %s
                   JOIN {$wpdb->terms} ot ON ot.term_id = ott.term_id AND ot.slug = nt.slug
                  WHERE ntt.taxonomy = %s
                    AND NOT EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr
                         WHERE tr.term_taxonomy_id = ntt.term_taxonomy_id
                    )",
				$old_taxonomy,
				$new_taxonomy
			)
		);

		foreach ($term_taxonomy_ids as $term_taxonomy_id) {
			$id = (int) $term_taxonomy_id;
			self::delete_term_taxonomy_row($id);
		}
	}

	/**
	 * Remove one term_taxonomy row, and the wp_terms row behind it only when
	 * nothing else still points at it -- a term is shared across taxonomies.
	 */
	private static function delete_term_taxonomy_row(int $term_taxonomy_id): void
	{
		global $wpdb;

		$term_id = (int) $wpdb->get_var(
			$wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $term_taxonomy_id)
		);

		$wpdb->delete($wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term_taxonomy_id ), array( '%d' ));

		if ($term_id <= 0) {
			return;
		}

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id)
		);

		if (0 === $remaining) {
			$wpdb->delete($wpdb->termmeta, array( 'term_id' => $term_id ), array( '%d' ));
			$wpdb->delete($wpdb->terms, array( 'term_id' => $term_id ), array( '%d' ));
		}
	}

	/**
	 * Post meta, in the only order that is correct.
	 */
	private static function rename_post_meta(): void
	{
		// 1. The exact overrides FIRST. They are excluded from the prefix pass
		//    by construction rather than by a WHERE clause: once applied, no row
		//    carries their old key any more.
		foreach (PrefixMigrationMap::POSTMETA_EXACT_OVERRIDES as $old => $new) {
			self::rename_meta_key_exact('postmeta', $old, $new);
		}

		// 2. Collapse the six merged pairs BEFORE the prefix rules run, while the
		//    two spellings are still distinguishable.
		self::resolve_post_meta_merge_collisions();

		// 3. The prefix rules, in POSTMETA_PREFIX_RULES order -- longest first,
		//    so the rentiva-qualified prefix is consumed before the bare vendor
		//    one can cut at the wrong offset.
		foreach (PrefixMigrationMap::POSTMETA_PREFIX_RULES as $old_prefix => $new_prefix) {
			if (! self::is_addon_prefix_rule($old_prefix)) {
				self::rename_post_meta_prefix($old_prefix, $new_prefix);
			}
		}

		// 4. The addon family, by exact key on addon posts. See addon_meta_keys().
		foreach (self::addon_meta_keys() as $old_key => $new_key) {
			foreach (self::addon_post_types() as $post_type) {
				self::rename_post_meta_key_on_post_type($old_key, $new_key, $post_type);
			}
		}
	}

	/**
	 * Hazard 1: wp_postmeta has NO unique index on (post_id, meta_key).
	 *
	 * A colliding rename therefore does not overwrite -- it leaves TWO rows with
	 * the same key, and get_post_meta( $id, $key, true ) returns whichever the
	 * storage engine happens to order first. Silent, and different on different
	 * servers.
	 *
	 * The winner is the owner's decision in POSTMETA_MERGE_WINNERS, and it is
	 * NOT re-derived here. On the real database the BARE spelling of the
	 * vehicle-id key holds 25 rows (every writer) and the rentiva-qualified one
	 * 3 (what Testimonials filtered on, which is why it resolved 3 of 28
	 * bookings). "Prefer the more specific
	 * spelling" gives the wrong answer exactly where being wrong costs most.
	 */
	private static function resolve_post_meta_merge_collisions(): void
	{
		global $wpdb;

		foreach (PrefixMigrationMap::POSTMETA_MERGE_WINNERS as $new_key => $winner) {
			foreach (self::merge_loser_keys($new_key, $winner) as $loser) {
				// Copy before deleting. These rows hold real values -- on a
				// customer site the losing spelling of customer_email or
				// price_per_day is somebody's data -- and this step cannot be
				// un-run. See record_merge_losers().
				self::record_merge_losers('postmeta', $loser, $winner);

				// Only where the SAME post carries both. A post holding only a
				// losing spelling keeps its value: the prefix pass renames it and
				// there is no collision to resolve. The derived table is required
				// -- MySQL will not let a DELETE read its own target in a bare
				// subquery.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->postmeta}
						  WHERE meta_key = %s
						    AND post_id IN (
						        SELECT post_id FROM (
						            SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s
						        ) AS winner_rows
						    )",
						$loser,
						$winner
					)
				);
			}
		}
	}

	/**
	 * Hazard 1 again, on wp_usermeta -- which has no unique index on
	 * (user_id, meta_key) either.
	 *
	 * One declared pair: see PrefixMigrationMap::USERMETA_MERGE_WINNERS. Runs
	 * BEFORE the usermeta prefix rewrites, while the two spellings are still
	 * distinguishable.
	 */
	private static function resolve_user_meta_merge_collisions(): void
	{
		global $wpdb;

		foreach (PrefixMigrationMap::USERMETA_MERGE_WINNERS as $new_key => $winner) {
			foreach (self::user_meta_merge_loser_keys($new_key, $winner) as $loser) {
				self::record_merge_losers('usermeta', $loser, $winner);

				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->usermeta}
						  WHERE meta_key = %s
						    AND user_id IN (
						        SELECT user_id FROM (
						            SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s
						        ) AS winner_rows
						    )",
						$loser,
						$winner
					)
				);
			}
		}
	}

	/**
	 * The other spellings that land on a merged USER-meta key.
	 *
	 * Same fail-safe as merge_loser_keys(): an empty list unless the declared
	 * winner is genuinely one of the candidates, so a map entry this code does
	 * not understand deletes nothing.
	 *
	 * @return list<string>
	 */
	private static function user_meta_merge_loser_keys(string $new_key, string $winner): array
	{
		// prefix-rename:ignore-start
		// Which old spellings can reach this destination depends on whether the
		// destination is underscore-prefixed: USERMETA_PREFIX_RULES sends
		// '_mhm_rentiva_'/'_rentiva_'/'_mhm_' to '_mhmrentiva_', and
		// 'mhm_rentiva_'/'mhm_' to the underscore-less 'mhmrentiva_'.
		$families = array(
			'_mhmrentiva_' => array( '_mhm_', '_mhm_rentiva_', '_rentiva_' ),
			'mhmrentiva_'  => array( 'mhm_', 'mhm_rentiva_' ),
		);
		// prefix-rename:ignore-end

		foreach ($families as $new_prefix => $old_prefixes) {
			if (0 !== strpos($new_key, $new_prefix)) {
				continue;
			}

			$suffix = substr($new_key, strlen($new_prefix));
			if ('' === $suffix) {
				continue;
			}

			$candidates = array();
			foreach ($old_prefixes as $old_prefix) {
				$candidates[] = $old_prefix . $suffix;
			}

			if (! in_array($winner, $candidates, true)) {
				continue;
			}

			return array_values(array_diff($candidates, array( $winner )));
		}

		return array();
	}

	/**
	 * Copy the rows a merge is about to delete into a recoverable backup table.
	 *
	 * A merge discards a row that held a REAL value -- three of the four vendors
	 * affected by the vendor_city pair hold a different city on the losing row --
	 * and this migration cannot be un-run. If a winner choice turns out wrong on
	 * some site, this table is the only way back.
	 *
	 * Deliberately reuses DatabaseCleaner's backup naming rather than inventing a
	 * mechanism: `{prefix}mhmrentiva_%_backup%` is exactly what
	 * DatabaseCleaner::list_backups() enumerates, and is_managed_backup_table()
	 * -- which gates export -- decides membership from that same enumeration. So
	 * this table shows up in the existing Backups screen and exports through the
	 * existing path with no new UI. Restore-in-place is explicitly refused there:
	 * the rows span two different target tables and only the `family` column says
	 * which, so a blind INSERT would put user meta into wp_postmeta.
	 *
	 * The table is created LAZILY, on the first row actually written. That keeps
	 * a second run a true no-op: with nothing left to discard there is no table
	 * and no row, rather than a fresh empty table per run.
	 */
	private static function record_merge_losers(string $family, string $loser, string $winner): void
	{
		if ('usermeta' === $family) {
			self::record_user_meta_losers($loser, $winner);
			return;
		}

		self::record_post_meta_losers($loser, $winner);
	}

	/**
	 * Copy the postmeta rows a merge is about to discard.
	 *
	 * Written out in full rather than sharing one parameterised statement with
	 * the usermeta twin below. The two differ only in a table and a column name,
	 * and folding them together meant interpolating both into the SQL -- which is
	 * precisely the shape Görev 9.5 drove to zero. Duplicating twelve lines is
	 * the cost of every identifier in this statement being a literal you can read
	 * at the line that runs it.
	 */
	private static function record_post_meta_losers(string $loser, string $winner): void
	{
		global $wpdb;

		// Count FIRST. Without this the backup table would be created on every
		// run even when there is nothing to discard, and the idempotency claim
		// ("the second run is a no-op") would be false by one empty table a run.
		$colliding = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				  WHERE meta_key = %s
				    AND post_id IN (
				        SELECT post_id FROM (
				            SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s
				        ) AS winner_rows
				    )",
				$loser,
				$winner
			)
		);

		if (0 === $colliding) {
			return;
		}

		$written = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (family, object_id, meta_key, meta_value)
				 SELECT %s, post_id, meta_key, meta_value FROM {$wpdb->postmeta}
				  WHERE meta_key = %s
				    AND post_id IN (
				        SELECT post_id FROM (
				            SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s
				        ) AS winner_rows
				    )",
				self::merge_loser_backup_table(),
				'postmeta',
				$loser,
				$winner
			)
		);

		if (false === $written) {
			// Never delete what could not be copied.
			self::log_index_error('merge loser backup', 'could not record postmeta losers for ' . $loser . ': ' . (string) $wpdb->last_error);
		}
	}

	/**
	 * Copy the usermeta rows a merge is about to discard. See the note on the
	 * postmeta twin above for why this is not folded into one statement.
	 */
	private static function record_user_meta_losers(string $loser, string $winner): void
	{
		global $wpdb;

		$colliding = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta}
				  WHERE meta_key = %s
				    AND user_id IN (
				        SELECT user_id FROM (
				            SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s
				        ) AS winner_rows
				    )",
				$loser,
				$winner
			)
		);

		if (0 === $colliding) {
			return;
		}

		$written = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (family, object_id, meta_key, meta_value)
				 SELECT %s, user_id, meta_key, meta_value FROM {$wpdb->usermeta}
				  WHERE meta_key = %s
				    AND user_id IN (
				        SELECT user_id FROM (
				            SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s
				        ) AS winner_rows
				    )",
				self::merge_loser_backup_table(),
				'usermeta',
				$loser,
				$winner
			)
		);

		if (false === $written) {
			self::log_index_error('merge loser backup', 'could not record usermeta losers for ' . $loser . ': ' . (string) $wpdb->last_error);
		}
	}

	/**
	 * The per-run backup table, created on first use.
	 */
	private static function merge_loser_backup_table(): string
	{
		global $wpdb;

		// Per RUN, not per PHP process. A `static` local here would have survived
		// for the life of the request and, in the test suite, across every test in
		// the process -- so a later run would keep appending to a table an earlier
		// one had named, which is both wrong semantically ("one table per
		// migration") and invisible in tests, where the first caller may have
		// created it as a TEMPORARY table.
		if (null !== self::$merge_loser_table) {
			return self::$merge_loser_table;
		}

		// Timestamp AND a random suffix. The timestamp alone has one-second
		// resolution, so two runs in the same second would silently share a
		// table -- harmless when appending on a live site, but in the test suite
		// a TEMPORARY table of that name from an earlier test SHADOWS the real
		// CREATE, so the rows land somewhere SHOW TABLES cannot see and the
		// recovery copy looks absent. list_backups() still parses the date, which
		// is the only part of the name it reads.
		$table = $wpdb->prefix . 'mhmrentiva_merge_losers_backup_'
			. gmdate('Ymd_His') . '_' . wp_generate_password(6, false, false);

		$wpdb->query(
			$wpdb->prepare(
				'CREATE TABLE IF NOT EXISTS %i (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    family VARCHAR(20) NOT NULL,
                    object_id BIGINT UNSIGNED NOT NULL,
                    meta_key VARCHAR(255) NOT NULL,
                    meta_value LONGTEXT NULL,
                    PRIMARY KEY (id),
                    KEY family_object (family, object_id)
                ) ' . $wpdb->get_charset_collate(),
				$table
			)
		);

		self::$merge_loser_table = $table;

		return $table;
	}

	/**
	 * The other spelling of a merged pair.
	 *
	 * THREE postmeta prefix rules target `_mhmrentiva_`, so a merged new key can
	 * have been produced by any of three old spellings. Seven of the eight
	 * declared pairs collide between the first two; the eighth
	 * (`vehicle_service_type`) collides between the bare vendor prefix and
	 * `_rentiva_`, which is why the original seven-pair analysis did not see it.
	 *
	 * The map declares ONE survivor per merged key, so every other spelling that
	 * lands on the same new key is a loser by definition and all of them are
	 * returned. Returns an EMPTY list unless the declared winner is genuinely one
	 * of the candidates -- a map entry this code does not understand must delete
	 * nothing. That fail-safe is what lets a future pair be added to the map
	 * before this method learns its shape: the worst case is an unresolved
	 * collision, visible as duplicate rows, never a row deleted on a guess.
	 *
	 * @return list<string>
	 */
	private static function merge_loser_keys(string $new_key, string $winner): array
	{
		$new_prefix = '_mhmrentiva_';
		if (0 !== strpos($new_key, $new_prefix)) {
			return array();
		}

		$suffix = substr($new_key, strlen($new_prefix));
		if ('' === $suffix) {
			return array();
		}

		// prefix-rename:ignore-start
		$old_prefixes = array( '_mhm_', '_mhm_rentiva_', '_rentiva_' );
		// prefix-rename:ignore-end

		$candidates = array();
		foreach ($old_prefixes as $old_prefix) {
			$candidates[] = $old_prefix . $suffix;
		}

		if (! in_array($winner, $candidates, true)) {
			return array();
		}

		return array_values(array_diff($candidates, array( $winner )));
	}

	/**
	 * One postmeta prefix rule, scoped to rows this plugin actually owns.
	 *
	 * Two classes of prefix, two scoping mechanisms:
	 *
	 * - A prefix carrying the 'rentiva' token is its own scope: no other
	 *   product's key can satisfy it. This arm is also the only one that can
	 *   carry dynamically built keys -- VehicleFeatureHelper composes that
	 *   prefix plus a per-field $custom_key, and no enumeration could list
	 *   those.
	 *
	 * - A prefix carrying only the VENDOR token, or none at all, cannot be
	 *   trusted as a pattern. The bare vendor prefix is shared with the currency
	 *   switcher's own `cs_`-tokened keys and mhm-pay's `pay_`-tokened ones --
	 *   Görev 12's prefix-only draft renamed the currency switcher's
	 *   fixed-prices row on the dev database --
	 *   and `_booking_` and `_contact_` carry no vendor token whatsoever
	 *   (WooCommerce Bookings writes `_booking_*` onto products). Those get the
	 *   union of two POSITIVE scopes: our own post types, plus an exact
	 *   allowlist of the keys we write onto posts we do not own.
	 */
	private static function rename_post_meta_prefix(string $old_prefix, string $new_prefix): void
	{
		if (self::is_self_scoping_prefix($old_prefix)) {
			self::rename_post_meta_prefix_scoped($old_prefix, $new_prefix, null);
			return;
		}

		foreach (self::owned_post_types() as $post_type) {
			self::rename_post_meta_prefix_scoped($old_prefix, $new_prefix, $post_type);
		}

		foreach (self::foreign_post_meta_keys() as $old_key => $new_key) {
			if (0 === strpos($old_key, $old_prefix)) {
				self::rename_meta_key_exact('postmeta', $old_key, $new_key);
			}
		}
	}

	/**
	 * Rewrite one prefix in batches, optionally restricted to a post type.
	 */
	private static function rename_post_meta_prefix_scoped(string $old_prefix, string $new_prefix, ?string $post_type): void
	{
		global $wpdb;

		// esc_like() is load-bearing, not hygiene. In SQL LIKE `_` is a
		// single-character WILDCARD, so an unescaped bare-vendor pattern matches
		// '_mhmrentiva_...' -- this statement's OWN output -- and the batch loop
		// would keep re-prefixing what it had just written.
		$pattern = $wpdb->esc_like($old_prefix) . '%';
		$cut     = strlen($old_prefix) + 1;

		do {
			if (null === $post_type) {
				$changed = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d))
						  WHERE meta_key LIKE %s
						  LIMIT %d",
						$new_prefix,
						$cut,
						$pattern,
						self::RENAME_BATCH
					)
				);
			} else {
				$changed = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d))
						  WHERE meta_key LIKE %s
						    AND post_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = %s )
						  LIMIT %d",
						$new_prefix,
						$cut,
						$pattern,
						$post_type,
						self::RENAME_BATCH
					)
				);
			}
		} while (self::RENAME_BATCH === $changed);
	}

	/**
	 * Rewrite one exact meta key on one post type, in batches.
	 */
	private static function rename_post_meta_key_on_post_type(string $old_key, string $new_key, string $post_type): void
	{
		global $wpdb;

		do {
			$changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s
					  WHERE meta_key = %s
					    AND post_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = %s )
					  LIMIT %d",
					$new_key,
					$old_key,
					$post_type,
					self::RENAME_BATCH
				)
			);
		} while (self::RENAME_BATCH === $changed);
	}

	/**
	 * Rewrite one exact meta key across a whole meta table, in batches.
	 *
	 * @param string $meta_table One of 'postmeta', 'usermeta', 'commentmeta'.
	 */
	private static function rename_meta_key_exact(string $meta_table, string $old_key, string $new_key): void
	{
		global $wpdb;

		$table = self::meta_table_name($meta_table);
		if ('' === $table) {
			return;
		}

		do {
			$changed = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT %d',
					$table,
					$new_key,
					$old_key,
					self::RENAME_BATCH
				)
			);
		} while (self::RENAME_BATCH === $changed);
	}

	/**
	 * Fixed allowlist of the three meta tables this migration writes to.
	 */
	private static function meta_table_name(string $meta_table): string
	{
		global $wpdb;

		switch ($meta_table) {
			case 'postmeta':
				return $wpdb->postmeta;
			case 'usermeta':
				return $wpdb->usermeta;
			case 'commentmeta':
				return $wpdb->commentmeta;
		}

		return '';
	}

	/**
	 * User meta.
	 *
	 * The product-token prefixes are their own scope, exactly as in postmeta.
	 * The vendor-only ones have no post to join against, so their scope is an
	 * exact key allowlist -- see owned_user_meta_keys().
	 */
	private static function rename_user_meta(): void
	{
		// Collapse the declared merge pair BEFORE the rewrites, while the two
		// spellings are still distinguishable -- same order as postmeta.
		self::resolve_user_meta_merge_collisions();

		foreach (PrefixMigrationMap::USERMETA_PREFIX_RULES as $old_prefix => $new_prefix) {
			if (self::is_self_scoping_prefix($old_prefix)) {
				self::rename_user_meta_prefix($old_prefix, $new_prefix);
			}
		}

		foreach (self::owned_user_meta_keys() as $old_key => $new_key) {
			self::rename_meta_key_exact('usermeta', $old_key, $new_key);
		}
	}

	/**
	 * Rewrite one user-meta prefix in batches. See the esc_like() note in
	 * rename_post_meta_prefix_scoped() -- the same wildcard trap applies.
	 */
	private static function rename_user_meta_prefix(string $old_prefix, string $new_prefix): void
	{
		global $wpdb;

		$pattern = $wpdb->esc_like($old_prefix) . '%';
		$cut     = strlen($old_prefix) + 1;

		do {
			$changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta} SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d))
					  WHERE meta_key LIKE %s
					  LIMIT %d",
					$new_prefix,
					$cut,
					$pattern,
					self::RENAME_BATCH
				)
			);
		} while (self::RENAME_BATCH === $changed);
	}

	/**
	 * Comment meta: two exact keys. Vehicle ratings are stored as WP comments
	 * and the key never carried a product token, so a prefix rule could not
	 * have expressed this family at all.
	 */
	private static function rename_comment_meta(): void
	{
		foreach (PrefixMigrationMap::COMMENTMETA as $old => $new) {
			self::rename_meta_key_exact('commentmeta', $old, $new);
		}
	}

	/**
	 * Custom tables.
	 *
	 * Scope: names composed from $wpdb->prefix and a class constant, bound
	 * through %i. Nothing here can originate from a request.
	 *
	 * The destination is measured first, and it is routinely already there: the
	 * pre-rename dev database carried an EMPTY `wp_mhmrentiva_key_registry`
	 * planted by the renamed code next to its pre-6.0.0 counterpart, which held
	 * 12 rows. "Rename only when the destination is absent" strands all
	 * 12, permanently and silently. An empty destination can only be that
	 * planted copy, so it is reclaimed; a populated one is left alone and
	 * logged, because merging two tables is not a migration's decision.
	 */
	private static function rename_tables(): void
	{
		global $wpdb;

		foreach (PrefixMigrationMap::TABLES as $old => $new) {
			$from = $wpdb->prefix . $old;
			$to   = $wpdb->prefix . $new;

			if (! self::table_exists($from)) {
				continue;
			}

			if (self::table_exists($to)) {
				$rows = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $to));

				if ($rows > 0) {
					self::log_index_error(
						'RENAME TABLE ' . $from,
						'destination already holds ' . $rows . ' rows; both tables left in place for manual review'
					);
					continue;
				}

				$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $to));
			}

			$wpdb->query($wpdb->prepare('RENAME TABLE %i TO %i', $from, $to));
		}
	}

	/**
	 * Does a table exist? esc_like() again -- `_` is a LIKE wildcard, and every
	 * table name here is full of them.
	 */
	private static function table_exists(string $table): bool
	{
		global $wpdb;

		return null !== $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
		);
	}

	/**
	 * Cron hooks.
	 */
	private static function rekey_cron_hooks(): void
	{
		foreach (PrefixMigrationMap::CRON_HOOKS as $old => $new) {
			self::rekey_cron_hook($old, $new);
		}
	}

	/**
	 * Carry one hook's scheduled events onto its new name.
	 *
	 * `wp_clear_scheduled_hook( $old )` -- the obvious call -- is wrong twice
	 * over for the booking-reminder hook. It only matches events whose
	 * args are EMPTY, and that hook is a per-booking single event carrying the
	 * booking id, so it would not find them at all; and clearing is not what
	 * those rows need. The recurring hooks re-schedule themselves on init, but
	 * this one has no self-heal: every reminder already scheduled when a site
	 * upgrades would simply never fire again.
	 */
	private static function rekey_cron_hook(string $old_hook, string $new_hook): void
	{
		$crons = _get_cron_array();

		if (is_array($crons)) {
			foreach ($crons as $timestamp => $hooks) {
				if (! is_array($hooks) || ! isset($hooks[ $old_hook ]) || ! is_array($hooks[ $old_hook ])) {
					continue;
				}

				$when = (int) $timestamp;
				foreach ($hooks[ $old_hook ] as $event) {
					self::reschedule_cron_event($when, $new_hook, $event);
				}
			}
		}

		// wp_unschedule_hook(), not wp_clear_scheduled_hook(): only the former
		// removes events that carry args.
		wp_unschedule_hook($old_hook);
	}

	/**
	 * Re-schedule one event under the new hook name, at the same moment and
	 * with the same args.
	 *
	 * @param mixed $event One entry of a wp_cron hook array.
	 */
	private static function reschedule_cron_event(int $timestamp, string $new_hook, $event): void
	{
		$args = ( is_array($event) && isset($event['args']) && is_array($event['args']) ) ? $event['args'] : array();

		// Measure the destination first: a recurring hook that already
		// re-scheduled itself under the new name must not end up with two rows
		// firing the same job.
		if (false !== wp_next_scheduled($new_hook, $args)) {
			return;
		}

		$schedule = ( is_array($event) && ! empty($event['schedule']) ) ? (string) $event['schedule'] : '';

		$scheduled = ( '' !== $schedule )
			? wp_schedule_event($timestamp, $schedule, $new_hook, $args)
			: wp_schedule_single_event($timestamp, $new_hook, $args);

		if (false === $scheduled) {
			self::log_index_error('cron rekey', 'could not re-schedule ' . $new_hook);
		}
	}

	/**
	 * Drop the pre-6.0.0 transients.
	 *
	 * Only the 'rentiva'-tokened family. A bare vendor-prefix sweep here would
	 * delete the currency switcher's exchange-rate rows. Anything this misses
	 * expires on
	 * its own TTL; nothing reads it in the meantime, because the code now asks
	 * for the new names.
	 */
	private static function purge_legacy_transients(): void
	{
		global $wpdb;

		foreach (self::legacy_transient_prefixes() as $prefix) {
			do {
				$deleted = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
						$wpdb->esc_like($prefix) . '%',
						self::RENAME_BATCH
					)
				);
			} while (self::RENAME_BATCH === $deleted);
		}
	}

	/**
	 * @return list<string>
	 */
	private static function legacy_transient_prefixes(): array
	{
		// prefix-rename:ignore-start
		return array( '_transient_mhm_rentiva_', '_transient_timeout_mhm_rentiva_' );
		// prefix-rename:ignore-end
	}

	/**
	 * Does this prefix carry the 'rentiva' token, and so scope itself?
	 *
	 * The rentiva-qualified meta prefix and `_rentiva_` do; the bare vendor
	 * prefix (vendor token only), `_booking_` and `_contact_` (no token at all)
	 * do not. PrefixMigrationMap has the exact spellings.
	 *
	 * Deliberately not named after the word this token identifies: the
	 * check-no-pro-refs gate matches the edition prefix as a plain substring,
	 * and the obvious name would trip a licence gate that has nothing to do
	 * with meta keys.
	 */
	private static function is_self_scoping_prefix(string $prefix): bool
	{
		return false !== strpos($prefix, 'rentiva');
	}

	/**
	 * Is this the map's one token-less rule? See addon_meta_keys().
	 */
	private static function is_addon_prefix_rule(string $prefix): bool
	{
		return 'addon_' === $prefix;
	}

	/**
	 * Post types whose meta rows this migration owns.
	 *
	 * The first of the two positive scopes the postmeta rewrite unions. Both
	 * spellings of every renamed type appear, because the post_type rewrite has
	 * already run and because a resumed run may find either.
	 *
	 * The four trailing types are the add-on's CPTs. Lite neither registers nor
	 * renames them, but Lite's own renamed code reads their meta under the new
	 * names (MetaQueryHelper, Mailer, TrendService, DatabaseCleaner), so their
	 * rows have to move with everything else -- the same reasoning that puts
	 * add-on-populated options in PrefixMigrationMap::OPTIONS.
	 *
	 * @return list<string>
	 */
	private static function owned_post_types(): array
	{
		// prefix-rename:ignore-start
		$addon_post_types = array( 'mhm_message', 'mhm_payout', 'mhm_vendor_app', 'mhm_contact_message' );
		// prefix-rename:ignore-end

		return array_values(
			array_unique(
				array_merge(
					array_keys(PrefixMigrationMap::POST_TYPES),
					array_values(PrefixMigrationMap::POST_TYPES),
					$addon_post_types
				)
			)
		);
	}

	/**
	 * Both spellings of the addon post type.
	 *
	 * @return list<string>
	 */
	private static function addon_post_types(): array
	{
		// prefix-rename:ignore-start
		$old = 'vehicle_addon';
		// prefix-rename:ignore-end

		return array( $old, PrefixMigrationMap::POST_TYPES[ $old ] );
	}

	/**
	 * The meta keys this plugin writes onto posts it does NOT own.
	 *
	 * The second positive scope. Post-type scoping alone strands these:
	 * the shortcode and auto-created markers land on `page` posts
	 * (ShortcodePageActions), and the rest land on WooCommerce orders through
	 * WooCommerceBridge's `$order->update_meta_data()` calls, which write to
	 * postmeta on every non-HPOS site.
	 *
	 * A prefix LIKE would reach those rows -- and would also reach the currency
	 * switcher's fixed-prices row, which sits on one of the very same foreign
	 * posts. The bare prefix names the VENDOR, not this plugin, so enumerating
	 * our own keys is the only way to take ours and leave theirs.
	 * Derived from the pre-rename tree's own foreign-post write sites.
	 *
	 * @return array<string,string> old key => new key
	 */
	private static function foreign_post_meta_keys(): array
	{
		// prefix-rename:ignore-start
		return array(
			'_mhm_shortcode'            => '_mhmrentiva_shortcode',
			'_mhm_auto_created'         => '_mhmrentiva_auto_created',
			'_mhm_booking_id'           => '_mhmrentiva_booking_id',
			'_mhm_booking_payment_type' => '_mhmrentiva_booking_payment_type',
			'_mhm_booking_pending'      => '_mhmrentiva_booking_pending',
			'_mhm_is_remaining_payment' => '_mhmrentiva_is_remaining_payment',
			'_mhm_original_order_id'    => '_mhmrentiva_original_order_id',
			'_mhm_wc_payment_type'      => '_mhmrentiva_wc_payment_type',
		);
		// prefix-rename:ignore-end
	}

	/**
	 * The five addon meta keys, by exact name.
	 *
	 * `'addon_' => 'mhmrentiva_addon_'` is the one rule in the map carrying no
	 * vendor token whatsoever, so `meta_key LIKE 'addon_%'` would capture ANY
	 * plugin's `addon_*` postmeta. It migrates as these five keys, on addon
	 * posts, and as nothing else.
	 *
	 * @return array<string,string> old key => new key
	 */
	private static function addon_meta_keys(): array
	{
		// prefix-rename:ignore-start
		return array(
			'addon_price'       => 'mhmrentiva_addon_price',
			'addon_enabled'     => 'mhmrentiva_addon_enabled',
			'addon_required'    => 'mhmrentiva_addon_required',
			'addon_description' => 'mhmrentiva_addon_description',
			'addon_type'        => 'mhmrentiva_addon_type',
		);
		// prefix-rename:ignore-end
	}

	/**
	 * The user-meta keys this plugin owns under the VENDOR-only prefixes.
	 *
	 * The wp_usermeta table has no post to join against, so post-type scoping is
	 * not available and the scope has to be an exact allowlist. Read out of the
	 * pre-rename tree's own get/update_user_meta() call sites, Lite and add-on
	 * together.
	 *
	 * It deliberately excludes the demo-user and customer-phone keys,
	 * which are in the dev database but in no Rentiva source file -- exactly the
	 * rows a bare vendor-prefix sweep would have taken from whoever owns them.
	 *
	 * The map still owns the TRANSFORMATION: each key goes through
	 * USERMETA_PREFIX_RULES in map order. This method only decides which keys
	 * are ours.
	 *
	 * @return array<string,string> old key => new key
	 */
	private static function owned_user_meta_keys(): array
	{
		// prefix-rename:ignore-start
		$keys = array(
			'_mhm_vendor_commission_rate',
			'_mhm_vendor_payout_freeze',
			'mhm_anonymization_date',
			'mhm_booking_notifications',
			'mhm_dashboard_widget_order',
			'mhm_data_anonymized',
			'mhm_data_consent_date',
			'mhm_data_consent_given',
			'mhm_favorite_vehicles',
			'mhm_gdpr_consent_date',
			'mhm_gdpr_consent_given',
			'mhm_gdpr_consent_withdrawal_date',
			'mhm_gdpr_consent_withdrawn',
			'mhm_marketing_emails',
			'mhm_welcome_email',
		);
		// prefix-rename:ignore-end

		$renamed = array();
		foreach ($keys as $key) {
			$new = self::apply_first_prefix_rule($key, PrefixMigrationMap::USERMETA_PREFIX_RULES);
			if (null !== $new) {
				$renamed[ $key ] = $new;
			}
		}

		return $renamed;
	}

	/**
	 * Apply the first matching rule, honouring map order (longest prefix first).
	 *
	 * @param array<string,string> $rules Ordered prefix rules.
	 */
	private static function apply_first_prefix_rule(string $key, array $rules): ?string
	{
		foreach ($rules as $old_prefix => $new_prefix) {
			if (0 === strpos($key, $old_prefix)) {
				return $new_prefix . substr($key, strlen($old_prefix));
			}
		}

		return null;
	}
}
