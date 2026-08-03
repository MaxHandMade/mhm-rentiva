<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This class exists to run DROP INDEX / SHOW INDEX against WordPress core tables; there is no core API for either statement, matching DatabaseMigrator.php's identical file-level suppression and rationale for the same category of DDL. Caching does not apply: SHOW INDEX is read fresh on purpose (drop() re-reads it immediately after every DROP to confirm the index is actually gone rather than trusting the DROP's own return value), and a cached read here would defeat that check by construction.

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The 35 indexes this plugin used to create on WordPress CORE tables
 * (wp_posts / wp_postmeta / wp_usermeta), and the one routine that drops them.
 *
 * WHY THIS EXISTS
 * ----------------
 * Through 4.1.x, DatabaseMigrator::run_migrations() created up to 20 named
 * indexes on wp_posts/wp_postmeta/wp_usermeta on every install
 * (add_performance_indexes() / add_missing_indexes()), and a further 15
 * pre-6.0.0-rename `idx_mhm_*` twins survive on any site that migrated
 * through that rename without ever being cleaned up (the rename moved
 * options/post types/meta/tables; it never touched these indexes). A
 * same-day independent audit verdicted REMOVE 20/20 -- WordPress core tables
 * are shared with every other plugin and theme on the install, and a plugin
 * has no way to know it is safe to keep piling composite indexes onto them.
 * The owner approved removal; this class is the removal, and the WP.org
 * submission depends on it (0 DROP INDEX previously existed anywhere,
 * including uninstall.php).
 *
 * SINGLE SOURCE OF TRUTH
 * -----------------------
 * DatabaseMigrator::run_migrations() (the live upgrade path) and
 * uninstall.php (which has zero dependency on the rest of the plugin and
 * `require_once`s this file directly, not through the autoloader) both call
 * self::drop() against the SAME LIST below. The list is not duplicated
 * anywhere; there is nothing to drift.
 *
 * LIST is a ledger, not a guess: every current-name signature was read
 * directly from the CREATE INDEX statements that used to build them (column
 * order and prefix lengths transcribed literally), then cross-checked
 * against the live dev database, which independently confirmed all 35 and
 * surfaced one real discrepancy worth recording here rather than only in the
 * task's ledger file: `idx_posts_date_type`'s source read
 * `(post_date, post_type(20), post_status(20))`, but wp_posts.post_type and
 * post_status are both `varchar(20)` -- when a prefix length is >= a
 * column's declared length, MySQL silently elides the prefix and stores a
 * full-column key, so the index ACTUALLY carries `sub => null` for both
 * columns, not `sub => 20`. Encoding the source text literally here would
 * make signature() (which reads what MySQL actually stored) never equal
 * this LIST, and the entry would be misfiled under 'skipped' forever -- a
 * genuinely-ours index silently left behind. The 15 legacy `idx_mhm_*`
 * entries were read from live `information_schema.STATISTICS` (they are not
 * in source at all) and diffed against their `idx_mhmrentiva_*` twins:
 * zero mismatches found.
 */
final class RetiredIndexes {

    /**
     * @var array<string, array<string, list<array{seq:int, col:string, sub:?int, non_unique:int, type:string}>>>
     *
     * Outer key: table suffix in the literal form "wp_<name>" -- self::drop()
     * strips the first 3 characters and re-prepends the REAL $wpdb->prefix,
     * so this list works under any actual table prefix, "wp_" or otherwise.
     * Inner key: index name. Value: its column signature, ordered by seq,
     * in the exact shape self::signature() returns from a live table so the
     * two can be compared with `!==`.
     */
    public const LIST = array(
        'wp_postmeta' => array(
            // 15 current idx_mhmrentiva_* names (add_performance_indexes() + add_missing_indexes()).
            'idx_mhmrentiva_status_lookup'    => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_timestamp_range'  => array(
                array(
					'seq'        => 1,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_vehicle_bookings' => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_booking_meta'     => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_customer_email'   => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 100,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_price_range'      => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_booking_combined' => array(
                array(
					'seq'        => 1,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_status'           => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_vehicle_id'       => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_start_ts'         => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_end_ts'           => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_total_price'      => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_contact_email'    => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_contact_name'     => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhmrentiva_customer_id'      => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),

            // 2 unprefixed current names (ex-CustomersOptimizer, folded into add_performance_indexes()).
            'idx_postmeta_customer_email'     => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_postmeta_booking_price'      => array(
                array(
					'seq'        => 1,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_key',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),

            // 15 legacy idx_mhm_* twins (pre-6.0.0-rename names; not in source,
            // read from live information_schema -- see class docblock).
            'idx_mhm_status_lookup'           => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_timestamp_range'         => array(
                array(
					'seq'        => 1,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_vehicle_bookings'        => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_booking_meta'            => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_customer_email'          => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 100,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_price_range'             => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 20,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_booking_combined'        => array(
                array(
					'seq'        => 1,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_status'                  => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_vehicle_id'              => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_start_ts'                => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_end_ts'                  => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_total_price'             => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_contact_email'           => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_contact_name'            => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_mhm_customer_id'             => array(
                array(
					'seq'        => 1,
					'col'        => 'meta_key',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_value',
					'sub'        => 50,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
        ),
        'wp_posts'    => array(
            // sub => null for post_type/post_status, NOT 20 -- see class docblock's prefix-elision note.
            'idx_posts_date_type'    => array(
                array(
					'seq'        => 1,
					'col'        => 'post_date',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'post_type',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_status',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
            'idx_posts_booking_date' => array(
                array(
					'seq'        => 1,
					'col'        => 'post_type',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'post_status',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 3,
					'col'        => 'post_date',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
        ),
        'wp_usermeta' => array(
            'idx_usermeta_customer_phone' => array(
                array(
					'seq'        => 1,
					'col'        => 'user_id',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
                array(
					'seq'        => 2,
					'col'        => 'meta_key',
					'sub'        => null,
					'non_unique' => 1,
					'type'       => 'BTREE',
				),
            ),
        ),
    );

    /**
     * Drop every index in $expected whose live signature matches exactly.
     *
     * Never touches an index whose signature does not match -- same name,
     * different columns means it is not one this plugin created, and the
     * safe assumption is that dropping someone else's index is worse than
     * leaving ours behind. Re-reads the signature after a successful DROP
     * and only then reports it as dropped, so a DROP that appears to
     * succeed but leaves the index in place (should not happen, but this is
     * a destructive DDL operation on tables this plugin does not own) is
     * caught rather than trusted.
     *
     * @param array<string, array<string, list<array{seq:int, col:string, sub:?int, non_unique:int, type:string}>>>|null $expected Defaults to self::LIST.
     * @param callable(string,string):bool|null $runner Runs one DROP INDEX statement, given (index name, table). Defaults to `$wpdb->query( $wpdb->prepare( 'DROP INDEX %i ON %i', $index, $table ) )`. Tests inject a stub to force the failure path without touching the database.
     * @return array{dropped: list<string>, skipped: list<string>, failed: list<string>}
     */
    public static function drop(\wpdb $wpdb, ?array $expected = null, ?callable $runner = null): array
    {
        $expected ??= self::LIST;
        // The prepare() call lives INSIDE the runner (not at the call site
        // below, one indirection removed) so that both WPCS's PreparedSQL
        // sniff and WP.org Plugin Check's own DirectDB sniff can see the
        // statement is prepared -- a $wpdb->query($sql) call fed an
        // already-prepared string one indirection away reads, to both
        // tools, as an unescaped parameter. Verified: the alternative shape
        // (prepare() at the call site, runner takes the built SQL string)
        // ships a real PluginCheck.Security.DirectDB.UnescapedDBParameter
        // finding in WP.org's own tool for no behavioural difference.
        $runner ??= static fn (string $index, string $table): bool => false !== $wpdb->query($wpdb->prepare('DROP INDEX %i ON %i', $index, $table));

        $out = array(
            'dropped' => array(),
            'skipped' => array(),
            'failed'  => array(),
        );

        foreach ($expected as $table_suffix => $indexes) {
            $bare = substr($table_suffix, 3); // 'wp_postmeta' -> 'postmeta'
            // usermeta (like users) is a GLOBAL table -- WordPress does not
            // reprefix it per site, so $wpdb->prefix . 'usermeta' names a
            // table that does not exist on any subsite other than the one
            // $wpdb->prefix currently points at. Preferring the matching
            // $wpdb property when one exists resolves posts/postmeta
            // per-site and usermeta globally -- exactly what the retired
            // creator methods used ($wpdb->postmeta / $wpdb->posts /
            // $wpdb->usermeta) -- and falls back to prefix+bare only for a
            // suffix with no wpdb property, which is how the test fixture
            // table resolves.
            $table = isset($wpdb->$bare) ? $wpdb->$bare : $wpdb->prefix . $bare;

            foreach ($indexes as $name => $sig) {
                $actual = self::signature($wpdb, $table, $name);

                if (null === $actual) {
                    continue; // Already gone -- not a failure, not "not ours", just done.
                }

                if ($actual !== $sig) {
                    $out['skipped'][] = $name; // Same name, different shape -- not ours. Do not touch.
                    continue;
                }

                if (false === $runner($name, $table)) {
                    $out['failed'][] = $name;
                    continue;
                }

                if (null !== self::signature($wpdb, $table, $name)) {
                    // The DROP reported success but the index is still there -- ask again rather than trust it.
                    $out['failed'][] = $name;
                    continue;
                }

                $out['dropped'][] = $name;
            }
        }

        return $out;
    }

    /**
     * The live column signature of one index, or null if it does not exist.
     *
     * 🔴 TYPE CANONICALIZATION IS NOT OPTIONAL. $wpdb returns every SHOW INDEX
     * column as a string (or null for Sub_part), never a native int/bool --
     * comparing that against LIST's int-typed literals with `!==` (as drop()
     * does) would make every genuinely-matching index compare unequal and
     * fall into 'skipped', silently leaving all 35 in place. seq/non_unique
     * are cast to int, sub to int-or-null, type is upper-cased and trimmed
     * (defensive against a MySQL/MariaDB build that varies casing/whitespace),
     * col is cast to string.
     *
     * @return ?list<array{seq:int, col:string, sub:?int, non_unique:int, type:string}>
     */
    public static function signature(\wpdb $wpdb, string $table, string $name): ?array
    {
        $rows = $wpdb->get_results(
            $wpdb->prepare('SHOW INDEX FROM %i WHERE Key_name = %s', $table, $name)
        );

        if (empty($rows)) {
            return null;
        }

        usort($rows, static fn ($a, $b): int => ( (int) $a->Seq_in_index ) <=> ( (int) $b->Seq_in_index ));

        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'seq'        => (int) $row->Seq_in_index,
                'col'        => (string) $row->Column_name,
                'sub'        => null === $row->Sub_part ? null : (int) $row->Sub_part,
                'non_unique' => (int) $row->Non_unique,
                'type'       => strtoupper(trim( (string) $row->Index_type)),
            );
        }

        return $out;
    }
}
