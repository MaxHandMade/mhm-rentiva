<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Uninstall;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Uninstaller
 *
 * Handles complete removal of all plugin data from database
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall routines must execute controlled bulk cleanup SQL across plugin-owned data.
final class Uninstaller {


	/**
	 * The pre-rename, vendor-token-only option names this plugin owns.
	 *
	 * Uninstall must clear them on a site that never ran Görev 13's migration.
	 * They carry no 'rentiva' token, so a LIKE pattern that reached them would
	 * also reach sibling MHM products' options -- the exact widening every other
	 * pattern in this class deliberately avoids. Read by exact name from the
	 * migration map instead, which is the only list that knows which vendor-token
	 * options belong to this plugin.
	 *
	 * @return array<int,string>
	 */
	private static function legacy_bare_option_names(): array {
		$names = array();
		foreach ( array_keys( \MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap::OPTIONS ) as $old ) {
			$old = (string) $old;
			// prefix-rename:ignore-start
			if ( ! str_starts_with( $old, 'mhm_rentiva' ) ) {
				// prefix-rename:ignore-end
				$names[] = $old;
			}
		}

		return $names;
	}

	/**
	 * The add-on's tables, in both spellings -- never dropped by Lite.
	 *
	 * See the note in get_all_plugin_tables() for why these six are the add-on's
	 * to remove. Listed here as whole names so the broad orphan pattern can carve
	 * them out by identity rather than by a LIKE that might drift.
	 *
	 * @return array<int,string>
	 */
	private static function addon_owned_tables(): array {
		global $wpdb;

		$names = array(
			'mhmrentiva_ledger',
			'mhmrentiva_commission_policy',
			'mhmrentiva_vendor_reports',
			'mhmrentiva_background_jobs',
			'mhmrentiva_payout_audit',
			'mhmrentiva_key_registry',
			// prefix-rename:ignore-start
			'mhm_rentiva_ledger',
			'mhm_rentiva_commission_policy',
			'mhm_rentiva_vendor_reports',
			'mhm_rentiva_background_jobs',
			'mhm_rentiva_payout_audit',
			'mhm_rentiva_key_registry',
			// prefix-rename:ignore-end
		);

		return array_map(
			static function ( string $name ) use ( $wpdb ): string {
				return $wpdb->prefix . $name;
			},
			$names
		);
	}

	/**
	 * Is this one of the recovery copies?
	 *
	 * Anchored on the TIMESTAMP, not on the word "backup" appearing anywhere in
	 * the name. A bare substring test also matches mhmrentiva_backup_records --
	 * a real data table, not a recovery copy -- which would then be skipped by
	 * the orphan sweep and survive only because the explicit whitelist happens
	 * to run first. That is an ordering dependency nothing asserts, and it is
	 * the kind of accident that outlives whoever knew about it.
	 *
	 * Every recovery copy this plugin writes ends in the Ymd_His stamp that
	 * DatabaseCleaner::list_backups() parses back out for display -- the
	 * postmeta backups and the merge-loser copies alike -- so that stamp is
	 * what identifies the family.
	 */
	private static function is_backup_table( string $table ): bool {
		return 1 === preg_match( '/_backup[a-z_]*_\d{8}_\d{6}/', $table );
	}

	/**
	 * Every cron hook this plugin has ever scheduled, in every spelling.
	 *
	 * A scheduled event lives in the `cron` option and survives the code that
	 * scheduled it, so uninstall has to clear each hook under every name it has
	 * ever had -- and after 6.0.0 there are two populations in the wild at once:
	 * a site deleted BEFORE the rename migration ran still carries the old
	 * names, a site deleted after carries the new ones. Both families are taken
	 * from PrefixMigrationMap rather than re-typed here, so this list cannot
	 * drift away from what the migration actually renames.
	 *
	 * Previously this list named only the auto-cancel and notification hooks,
	 * which left the five log/reminder/queue hooks scheduled forever on every
	 * uninstall, under either spelling.
	 *
	 * @return array<int,string>
	 */
	private static function plugin_cron_hooks(): array {
		$hooks = array(
			'mhmrentiva_auto_cancel_event',
			'mhmrentiva_send_scheduled_notifications',
			// prefix-rename:ignore-start
			// The two PRE-6.0.0 spellings of the notification hook. The rename
			// collapsed them onto the name above, which is why they are spelled
			// out rather than left as a duplicate of it -- and why they cannot
			// come from the map: that hook has no map entry, its class is gone.
			'mhm_rentiva_send_scheduled_notifications',
			'mhm_send_scheduled_notifications',
			// prefix-rename:ignore-end
			'mhmrentiva_email_log_retention',
			'mhmrentiva_log_retention',
		);

		return array_values(
			array_unique(
				array_merge(
					$hooks,
					array_keys( PrefixMigrationMap::CRON_HOOKS ),
					array_values( PrefixMigrationMap::CRON_HOOKS )
				)
			)
		);
	}

	/**
	 * Get uninstall statistics (what will be deleted)
	 */
	public static function get_uninstall_stats(): array {
		global $wpdb;

		$stats = array(
			'options'       => 0,
			'post_types'    => array(
				'vehicles' => 0,
				'bookings' => 0,
			),
			'postmeta'      => 0,
			'custom_tables' => array(),
			'cron_jobs'     => 0,
			'transients'    => 0,
			'backup_files'  => 0,
		);

		// Count options - using prepare for LIKE patterns
		$options          = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s",
				// prefix-rename:ignore-start
				'mhmrentiva%',
				'_mhmrentiva%',
				'mhm_rentiva%',
				'_mhm_rentiva%'
				// prefix-rename:ignore-end
			)
		);
		$stats['options'] = (int) $options;
		foreach ( self::legacy_bare_option_names() as $legacy_option ) {
			if ( null !== get_option( $legacy_option, null ) ) {
				++$stats['options'];
			}
		}

		// Count vehicles
		$vehicles                        = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
               OR ( p.post_type = %s AND EXISTS (
                     SELECT 1 FROM {$wpdb->postmeta} pm
                     WHERE pm.post_id = p.ID AND pm.meta_key LIKE %s
                   ) )
        ",
				// prefix-rename:ignore-start
				'mhmrentiva_vehicle',
				'vehicle',
				'_mhm%'
				// prefix-rename:ignore-end
			)
		);
		$stats['post_types']['vehicles'] = (int) $vehicles;

		// Count bookings
		$bookings                        = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
               OR ( p.post_type = %s AND EXISTS (
                     SELECT 1 FROM {$wpdb->postmeta} pm
                     WHERE pm.post_id = p.ID AND pm.meta_key LIKE %s
                   ) )
        ",
				// prefix-rename:ignore-start
				'mhmrentiva_booking',
				'vehicle_booking',
				'_mhm%'
				// prefix-rename:ignore-end
			)
		);
		$stats['post_types']['bookings'] = (int) $bookings;

		// Count postmeta - using prepare for LIKE pattern. Scoped to this
		// plugin's own '_mhmrentiva%' prefix (not the broader '_mhm%') so a
		// sibling MHM plugin's postmeta on a shared post is never counted.
		$postmeta          = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE %s
            OR meta_key LIKE %s",
				// prefix-rename:ignore-start
				'_mhmrentiva%',
				'_mhm_rentiva%'
				// prefix-rename:ignore-end
			)
		);
		$stats['postmeta'] = (int) $postmeta;

		// Count custom tables — centralized via get_all_plugin_tables() so stats and drop stay in sync.
		$custom_tables = self::get_all_plugin_tables();

		foreach ( $custom_tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists ) {
				$rows                             = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
				$stats['custom_tables'][ $table ] = (int) $rows;
			}
		}

		// Count cron jobs
		$crons        = _get_cron_array();
		$plugin_crons = self::plugin_cron_hooks();

		$cron_count = 0;
		if ( ! empty( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				foreach ( $cron as $hook => $dings ) {
					if ( in_array( $hook, $plugin_crons, true ) ) {
						$cron_count += count( $dings );
					}
				}
			}
		}
		$stats['cron_jobs'] = $cron_count;

		// Count transients - using prepare for LIKE patterns
		$transients          = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->options}
            WHERE (option_name LIKE %s 
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s)",
				// prefix-rename:ignore-start
				'_transient_mhmrentiva%',
				'_transient_timeout_mhmrentiva%',
				'_transient_mhm_rentiva%',
				'_transient_timeout_mhm_rentiva%'
				// prefix-rename:ignore-end
			)
		);
		$stats['transients'] = (int) $transients;

		// Count backup files in both directories: the number is shown to the site
		// owner before they confirm deletion, so it has to match what deletion will
		// actually remove -- including backups written before the directory moved
		// out of wp-content.
		$backup_files = 0;
		if ( self::init_filesystem() ) {
			global $wp_filesystem;
			foreach ( \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::backup_dirs() as $backup_dir ) {
				if ( ! $wp_filesystem->exists( $backup_dir ) || ! $wp_filesystem->is_dir( $backup_dir ) ) {
					continue;
				}

				$file_list = $wp_filesystem->dirlist( $backup_dir );
				if ( is_array( $file_list ) ) {
					foreach ( $file_list as $file ) {
						if ( substr( $file['name'], -4 ) === '.sql' ) {
							++$backup_files;
						}
					}
				}
			}
		}
		$stats['backup_files'] = $backup_files;

		return $stats;
	}

	/**
	 * Perform complete uninstall (delete all plugin data)
	 */
	public static function uninstall( bool $delete_backups = false ): array {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied', 'mhm-rentiva' ),
			);
		}

		return self::uninstall_direct( $delete_backups );
	}

	/**
	 * Direct uninstall (bypasses permission check - for use in uninstall.php)
	 */
	public static function uninstall_direct( bool $delete_backups = false ): array {
		global $wpdb;

		$results = array(
			'options_deleted'      => 0,
			'posts_deleted'        => 0,
			'postmeta_deleted'     => 0,
			'tables_dropped'       => 0,
			'cron_jobs_cleared'    => 0,
			'transients_deleted'   => 0,
			'backup_files_deleted' => 0,
			'errors'               => array(),
		);

		// 1. Delete all options - using prepare for LIKE patterns
		$options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s",
				// prefix-rename:ignore-start
				'mhmrentiva%',
				'_mhmrentiva%',
				'mhm_rentiva%',
				'_mhm_rentiva%'
				// prefix-rename:ignore-end
			)
		);

		// The pre-rename vendor-token-only options carry no product token, so no
		// LIKE pattern can select them without also selecting a sibling MHM
		// product's options. They are taken by EXACT name from the migration map
		// -- the only list that knows which of them are ours -- and
		// deleted through the options API rather than an IN() clause, which keeps
		// the query above a fixed set of literal placeholders.
		$options = array_values(
			array_unique( array_merge( (array) $options, self::legacy_bare_option_names() ) )
		);

		foreach ( $options as $option_name ) {
			if ( delete_option( $option_name ) ) {
				++$results['options_deleted'];
			}
		}

		// 2. Delete all vehicles
		$vehicles = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
               OR ( p.post_type = %s AND EXISTS (
                     SELECT 1 FROM {$wpdb->postmeta} pm
                     WHERE pm.post_id = p.ID AND pm.meta_key LIKE %s
                   ) )
        ",
				// prefix-rename:ignore-start
				'mhmrentiva_vehicle',
				'vehicle',
				'_mhm%'
				// prefix-rename:ignore-end
			)
		);

		foreach ( $vehicles as $post_id ) {
			wp_delete_post( $post_id, true );
			++$results['posts_deleted'];
		}

		// 3. Delete all bookings
		$bookings = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
               OR ( p.post_type = %s AND EXISTS (
                     SELECT 1 FROM {$wpdb->postmeta} pm
                     WHERE pm.post_id = p.ID AND pm.meta_key LIKE %s
                   ) )
        ",
				// prefix-rename:ignore-start
				'mhmrentiva_booking',
				'vehicle_booking',
				'_mhm%'
				// prefix-rename:ignore-end
			)
		);

		foreach ( $bookings as $post_id ) {
			wp_delete_post( $post_id, true );
			++$results['posts_deleted'];
		}

		// 4. Delete all postmeta - using prepare for LIKE pattern. Scoped to
		// this plugin's own '_mhmrentiva%' prefix (every other step in this
		// method already scopes to 'mhmrentiva%'/'_mhmrentiva%'; the
		// broader '_mhm%' would also delete a sibling MHM plugin's postmeta
		// on a shared post, e.g. a WooCommerce order).
		$postmeta_deleted            = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta}
            WHERE meta_key LIKE %s
            OR meta_key LIKE %s",
				// prefix-rename:ignore-start
				'_mhmrentiva%',
				'_mhm_rentiva%'
				// prefix-rename:ignore-end
			)
		);
		$results['postmeta_deleted'] = (int) $postmeta_deleted;

		// 5. Drop custom tables (whitelist + pattern-based safety net for orphaned tables)
		$custom_tables = self::get_all_plugin_tables();

		foreach ( $custom_tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists ) {
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
				++$results['tables_dropped'];
			}
		}

		// 5b. Safety net: drop any remaining orphan tables that match our prefix.
		//
		// The pattern is plugin-unique, so there is no cross-plugin risk -- but it
		// is broad enough to reach two things it must NOT take, and both had to be
		// carved out by name rather than by hoping the explicit list came first:
		//
		//   1. The add-on's tables. Taking them out of the whitelist above
		//      achieves nothing while this pattern still matches them.
		//   2. Backup tables. This pattern subsumed
		//      `mhmrentiva_postmeta_backup_invalid_%` -- which used to be listed
		//      beside it as a second, redundant pattern -- so recovery copies were
		//      dropped whatever the owner answered. $delete_backups was read in
		//      exactly one place, and only for backup FILES.
		$protected = self::addon_owned_tables();

		$orphans = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'mhmrentiva_%' ) );
		foreach ( $orphans as $orphan_table ) {
			if ( in_array( $orphan_table, $protected, true ) || self::is_backup_table( $orphan_table ) ) {
				continue;
			}

			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $orphan_table ) );
			++$results['tables_dropped'];
		}

		// Backup tables go only when the owner actually asked for them -- the same
		// answer they gave about backup FILES, now honoured for the tables too.
		//
		// Deliberately limited to the current spelling, which is exactly the reach
		// this code had before: whether uninstall should also delete PRE-6.0.0
		// backup tables -- a site's only recovery copy, which no rename ever
		// touches -- is a separate decision and is recorded as open rather than
		// taken here by a widened pattern.
		if ( $delete_backups ) {
			$backups = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'mhmrentiva_%backup%' ) );
			foreach ( $backups as $backup_table ) {
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $backup_table ) );
				++$results['tables_dropped'];
			}
		}

		// 6. Clear all cron jobs
		$plugin_crons = self::plugin_cron_hooks();

		// wp_unschedule_hook(), not the wp_next_scheduled()/wp_unschedule_event()
		// pair this used to run: that pair matches only events whose args are
		// EMPTY, so the per-booking reminder events -- which carry the booking id
		// -- were never cleared and survived the plugin being deleted.
		foreach ( $plugin_crons as $hook ) {
			$cleared = wp_unschedule_hook( $hook );
			if ( is_int( $cleared ) ) {
				// The > 0 guard is not defensive noise: wp_unschedule_hook() is
				// documented as int|false, so without it the counter's inferred
				// type widens from int<0, max> to int and PHPStan's ignore
				// patterns for this array shape stop matching.
				$results['cron_jobs_cleared'] += $cleared > 0 ? $cleared : 0;
			}
		}

		// 7. Delete all transients - using prepare for LIKE patterns
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s
            OR option_name LIKE %s",
				// prefix-rename:ignore-start
				'_transient_mhmrentiva%',
				'_transient_timeout_mhmrentiva%',
				'_transient_mhm_rentiva%',
				'_transient_timeout_mhm_rentiva%'
				// prefix-rename:ignore-end
			)
		);

		foreach ( $transients as $transient_name ) {
			$name = str_replace( array( '_transient_', '_transient_timeout_' ), '', $transient_name );
			if ( delete_transient( $name ) ) {
				++$results['transients_deleted'];
			}
		}

		// 8. Delete backup files (optional). Both directories, to match the count
		// the site owner was shown.
		if ( $delete_backups && self::init_filesystem() ) {
			global $wp_filesystem;

			foreach ( \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::backup_dirs() as $backup_dir ) {
				if ( ! $wp_filesystem->exists( $backup_dir ) ) {
					continue;
				}

				// Delete directory recursively (handles files inside)
				if ( $wp_filesystem->delete( $backup_dir, true ) ) {
					// We can assume files were deleted if directory gone, but let's be conservative with stats
					// Ideally we would count them before deleting, but we already did that in stats query
					$results['backup_files_deleted'] = 1;
				}
			}
		}

		// 9. Delete taxonomies and terms
		$taxonomies = array( 'mhmrentiva_vehicle_category', 'vehicle_category', 'vehicle_cat' ); // old names kept: uninstall must clear terms written before the 6.0.0 rename too
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					wp_delete_term( $term->term_id, $taxonomy );
				}
			}
		}

		return array(
			'success' => empty( $results['errors'] ),
			'results' => $results,
			'message' => empty( $results['errors'] )
				? __( 'All plugin data has been removed successfully', 'mhm-rentiva' )
				: __( 'Uninstall completed with some errors', 'mhm-rentiva' ),
		);
	}


	/**
	 * Canonical list of every custom table the plugin has ever created.
	 *
	 * Used by both stat counting and the DROP phase so the two cannot drift.
	 * Includes active tables, legacy names, and orphaned subsystem tables
	 * from removed feature branches. Pattern-based cleanup in uninstall_direct()
	 * provides a final safety net for anything missed here.
	 *
	 * @return array<string> Table names prefixed with $wpdb->prefix.
	 */
	public static function get_all_plugin_tables(): array {
		global $wpdb;

		return array(
			// 🔴 THE ADD-ON'S SIX TABLES ARE NOT LISTED HERE, DELIBERATELY.
			//
			//   ledger · commission_policy · vendor_reports · background_jobs
			//   payout_audit · key_registry
			//
			// Measured, not assumed: Lite queries NONE of them outside schema and
			// cleanup plumbing (PenaltyCalculator.php:167 only READS the ledger),
			// every one is created only behind an add-on `class_exists()` gate,
			// and their contents are financial -- the commission ledger, payout
			// statements and audit trail, and the keys that SIGN the ledger.
			//
			// They were all dropped here historically, so uninstalling Lite
			// destroyed append-only financial history belonging to a separate
			// product, on a site that may be removing Lite intending to reinstall.
			// Owner decision 2026-08-02: each plugin removes its own data, which is
			// also the rule WordPress.org applies. The add-on owns their removal.
			//
			// key_registry is why the list is six and not four: dropping the keys
			// while leaving the ledger produces an append-only financial record
			// that nobody can verify -- worse than either consistent choice.
			//
			// NOTE the distinction: PrefixMigrationMap::TABLES still RENAMES
			// payout_audit and key_registry, because Lite's own DatabaseMigrator
			// is what creates them. Carrying a table's name is not owning its
			// removal.
			//
			// --- Active tables (created by DatabaseMigrator / QueueManager / etc.) ---
			$wpdb->prefix . 'mhmrentiva_queue',
			$wpdb->prefix . 'mhmrentiva_ratings',
			$wpdb->prefix . 'mhmrentiva_tenants',
			$wpdb->prefix . 'mhmrentiva_usage_metrics',
			$wpdb->prefix . 'mhmrentiva_message_logs',
			$wpdb->prefix . 'mhmrentiva_notification_queue',
			$wpdb->prefix . 'mhmrentiva_payment_log',
			$wpdb->prefix . 'mhmrentiva_sessions',
			$wpdb->prefix . 'mhmrentiva_backup_records',
			$wpdb->prefix . 'rentiva_transfer_locations',
			$wpdb->prefix . 'rentiva_transfer_routes',

			// --- Legacy names before rename migration ---
			$wpdb->prefix . 'mhm_rentiva_transfer_locations',
			$wpdb->prefix . 'mhm_rentiva_transfer_routes',
			$wpdb->prefix . 'mhmrentiva_report_queue',

			// --- PRE-6.0.0 spellings of every table above ---
			//
			// Uninstall must work on a site that never ran Görev 13's migration,
			// where every one of these tables still carries its old name. FOUR of
			// them additionally have NO entry in PrefixMigrationMap::TABLES --
			// notification_queue, backup_records, transfers and report_queue --
			// so the migration will never rename the physical
			// table at all and the old name is the ONLY name they will ever have.
			// Dropping by the new name alone leaves them behind on every install,
			// permanently. (report_queue previously sat under the "legacy" heading
			// above, but the sweep converted it to the NEW spelling, so the name it
			// was there to catch stopped being dropped anywhere.)
			//
			// The explanation lives OUTSIDE the region deliberately: a region is a
			// blind spot and its length is the size of that blind spot, so it
			// wraps the literals and nothing else.
			// prefix-rename:ignore-start
			$wpdb->prefix . 'mhm_rentiva_tenants',
			$wpdb->prefix . 'mhm_rentiva_usage_metrics',
			$wpdb->prefix . 'mhm_rentiva_queue',
			$wpdb->prefix . 'mhm_rentiva_ratings',
			$wpdb->prefix . 'mhm_message_logs',
			$wpdb->prefix . 'mhm_notification_queue',
			$wpdb->prefix . 'mhm_payment_log',
			$wpdb->prefix . 'mhm_sessions',
			$wpdb->prefix . 'mhm_backup_records',
			$wpdb->prefix . 'mhm_transfers',
			$wpdb->prefix . 'mhm_rentiva_report_queue',
			// prefix-rename:ignore-end

			// --- Orphan tables from removed subsystems (kept for cleanup on historic installs) ---
			$wpdb->prefix . 'mhmrentiva_subscriptions',
			$wpdb->prefix . 'mhmrentiva_usage_billing_feature_flags',
			$wpdb->prefix . 'mhmrentiva_payment_events_raw',
			$wpdb->prefix . 'mhmrentiva_payment_event_aggregates',
			$wpdb->prefix . 'mhmrentiva_payment_event_aggregate_windows',
			$wpdb->prefix . 'mhmrentiva_payment_registry',
			$wpdb->prefix . 'mhmrentiva_alert_state',
			$wpdb->prefix . 'mhmrentiva_alert_dispatch_state',
			$wpdb->prefix . 'mhmrentiva_external_alert_bridge_queue',
			$wpdb->prefix . 'mhmrentiva_external_alert_bridge_circuit',
			$wpdb->prefix . 'mhmrentiva_event_queue',

			// --- PRE-6.0.0 spellings of the orphan family above ---
			//
			// These eleven are genuinely dead schema: no code in either plugin
			// creates, reads or writes them. None is in PrefixMigrationMap::TABLES
			// and none is in the add-on's map either, so nothing ever renames
			// them -- the name a real install carries is the OLD one, and the
			// new-spelling list above matched nothing. Measured on the dev
			// database: all eleven were still sitting there after a full
			// migration, invisible to both the migration and uninstall. WP.org
			// reads uninstall closely and orphan tables are not cosmetic.
			//
			// `vendor_reports` was briefly filed here and does NOT belong: the
			// add-on declares it, creates it in VendorReportsMigration and
			// reads/writes it from three services, with live rows. It is the
			// add-on's to remove, like the rest of its schema.
			// prefix-rename:ignore-start
			$wpdb->prefix . 'mhm_rentiva_subscriptions',
			$wpdb->prefix . 'mhm_rentiva_usage_billing_feature_flags',
			$wpdb->prefix . 'mhm_rentiva_payment_events_raw',
			$wpdb->prefix . 'mhm_rentiva_payment_event_aggregates',
			$wpdb->prefix . 'mhm_rentiva_payment_event_aggregate_windows',
			$wpdb->prefix . 'mhm_rentiva_payment_registry',
			$wpdb->prefix . 'mhm_rentiva_alert_state',
			$wpdb->prefix . 'mhm_rentiva_alert_dispatch_state',
			$wpdb->prefix . 'mhm_rentiva_external_alert_bridge_queue',
			$wpdb->prefix . 'mhm_rentiva_external_alert_bridge_circuit',
			$wpdb->prefix . 'mhm_rentiva_event_queue',
			// prefix-rename:ignore-end
		);
	}

	/**
	 * Initialize Filesystem
	 */
	private static function init_filesystem(): bool {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( ! WP_Filesystem() ) {
				return false;
			}
		}

		return ! empty( $wp_filesystem );
	}
}
