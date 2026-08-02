<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap;
use WP_UnitTestCase;

/**
 * The 6.0.0 prefix-rename migration.
 *
 * Every identifier in the tree now reads `mhmrentiva_`. No live database has
 * ever stored that name, so this migration is the only thing that makes the
 * code and the data agree; if it is wrong the settings, the bookings' vehicle
 * links and the vendor rows are gone, and the old names no longer exist
 * anywhere in the tree to recover them from.
 *
 * The cases below are the five hazards measured on the real pre-rename dev
 * database during Görev 12, not invented edge cases:
 *
 *  1. wp_postmeta has no unique index on (post_id, meta_key), so a colliding
 *     rename leaves TWO rows and get_post_meta() picks one nondeterministically.
 *  2. The renamed code runs BEFORE the migration on a real upgrade, so the
 *     destination option/term/table frequently already exists.
 *  3. `_mhm_` is a vendor prefix shared with sibling products; the currency
 *     switcher's `_mhm_cs_fixed_prices` sits in the same table.
 *  4. `mhm_rentiva_send_booking_reminder` is a per-booking single event with
 *     args -- clearing it silently drops every pending reminder.
 *  5. run_migrations() is one all-or-nothing gate, so the version stamp has to
 *     survive its own rename or all twelve earlier steps re-run.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations
 */
final class PrefixRenameMigrationTest extends WP_UnitTestCase
{
    private const DB_VERSION        = 'mhmrentiva_db_version';
    private const LEGACY_DB_VERSION = 'mhm_rentiva_db_version';
    private const LIFECYCLE_FLAG    = 'mhmrentiva_lifecycle_migration_done';

    /**
     * Options these tests write or move, in both spellings. See tearDown().
     *
     * @var list<string>
     */
    private const SEEDED_OPTIONS = array(
        'mhm_custom_details',
        'mhmrentiva_custom_details',
        'mhm_rentiva_currency',
        'mhmrentiva_currency',
        'mhm_rentiva_license',
        'mhmrentiva_license',
    );

    /**
     * Tables this test created and must remove itself: RENAME/DROP are DDL and
     * commit implicitly, so the suite's transaction rollback cannot undo them.
     *
     * @var list<string>
     */
    private array $temp_tables = array();

    /**
     * Users these tests create. Their meta outlives the suite's rollback for the
     * same reason the options do -- see tearDown().
     *
     * @var list<int>
     */
    private array $seeded_users = array();

    /**
     * Clean up AFTER the suite's rollback, not before it.
     *
     * run_migrations() issues ANALYZE TABLE, an implicit COMMIT, so everything
     * this class writes has already left the per-test transaction by the time
     * tearDown() runs. Deleting first and calling parent::tearDown() second
     * looks right and is exactly wrong: the ROLLBACK then undoes the DELETEs
     * while the committed INSERTs survive, and the options leak into every
     * later test. That is how a migrated `mhmrentiva_custom_details` reached
     * DatabaseCleanerAllowlistTest, whose fail-closed case is premised on there
     * being NO custom-field definitions -- it stopped aborting and started
     * deleting, and the failure surfaced two test classes away from its cause.
     */
    protected function tearDown(): void
    {
        global $wpdb;

        parent::tearDown();

        foreach ($this->temp_tables as $table) {
            $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
        }
        $this->temp_tables = array();

        delete_option(self::DB_VERSION);
        delete_option(self::LEGACY_DB_VERSION);
        delete_option(self::LIFECYCLE_FLAG);

        // Every option these tests seed, in BOTH spellings.
        //
        // Not belt-and-braces: run_migrations() issues ANALYZE TABLE, which is
        // an implicit COMMIT, so the suite's per-test transaction has already
        // ended by the time this class writes anything -- nothing here rolls
        // back. Leaving `mhmrentiva_custom_details` behind made
        // DatabaseCleanerAllowlistTest's fail-closed test go green for the
        // wrong reason: its premise is that NO custom-field definitions exist,
        // and this class had just migrated one into place.
        foreach (self::SEEDED_OPTIONS as $option) {
            delete_option($option);
        }

        // Same story for user meta: the migration commits, so these rows survive
        // the rollback and would otherwise leak into every later test.
        foreach ($this->seeded_users as $user_id) {
            $wpdb->delete($wpdb->usermeta, array( 'user_id' => $user_id ), array( '%d' ));
            $wpdb->delete($wpdb->users, array( 'ID' => $user_id ), array( '%d' ));
        }
        $this->seeded_users = array();

        foreach (array_keys(PrefixMigrationMap::CRON_HOOKS) as $hook) {
            wp_unschedule_hook($hook);
        }
        foreach (PrefixMigrationMap::CRON_HOOKS as $hook) {
            wp_unschedule_hook($hook);
        }
    }

    /**
     * Put the install back at the last pre-6.0.0 schema version so
     * run_migrations()'s outer gate opens.
     */
    private function seed_pre_rename_version(): void
    {
        delete_option(self::DB_VERSION);
        update_option(self::LEGACY_DB_VERSION, '3.15.0');
    }

    private function table(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . $suffix;
    }

    /**
     * Stop the suite turning CREATE TABLE into CREATE TEMPORARY TABLE.
     *
     * WP_UnitTestCase filters `query` to rewrite DDL onto temporary tables so
     * each test is isolated. Temporary tables are invisible to SHOW TABLES and
     * cannot be RENAMEd, so a table test left on the default filters is
     * VACUOUS: table_exists() answers "no" to everything, the migration finds
     * nothing to do, and an "is it gone?" assertion passes because it was never
     * visible in the first place. tearDown() drops what this creates.
     */
    private function use_real_tables(): void
    {
        remove_filter('query', array( $this, '_create_temporary_tables' ));
        remove_filter('query', array( $this, '_drop_temporary_tables' ));
    }

    private function create_temp_table(string $name): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'CREATE TABLE IF NOT EXISTS %i ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, note VARCHAR(20) NULL, PRIMARY KEY (id) )',
                $name
            )
        );
        $this->temp_tables[] = $name;
    }

    private function count_user_meta(int $user_id, string $meta_key): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
                $user_id,
                $meta_key
            )
        );
    }

    /**
     * The most recent merge-loser backup table, or null if none was created.
     */
    private function merge_loser_backup_table(): ?string
    {
        global $wpdb;

        $tables = $wpdb->get_col(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'mhmrentiva_merge_losers_backup_') . '%')
        );
        sort($tables);

        return empty($tables) ? null : (string) end($tables);
    }

    private function count_meta(int $post_id, string $meta_key): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
                $post_id,
                $meta_key
            )
        );
    }

    /**
     * The headline case from the brief: one representative row from each of the
     * four families that live in core tables.
     */
    public function test_prefix_rename_migrates_options_meta_cpt_taxonomy(): void
    {
        update_option('mhm_custom_details', array( 'boot_size' => 'Boot Size' ));
        $vid = self::factory()->post->create(array( 'post_type' => 'vehicle' ));
        update_post_meta($vid, '_mhm_rentiva_price_per_day', '100');
        register_taxonomy('vehicle_category', 'vehicle');
        wp_insert_term('Sedan', 'vehicle_category');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame(array( 'boot_size' => 'Boot Size' ), get_option('mhmrentiva_custom_details'));
        $this->assertFalse(get_option('mhm_custom_details'), 'The old option name survived the move.');
        $this->assertSame('mhmrentiva_vehicle', get_post_type($vid));
        $this->assertSame('100', get_post_meta($vid, '_mhmrentiva_price_per_day', true));

        register_taxonomy('mhmrentiva_vehicle_category', 'mhmrentiva_vehicle');
        $this->assertNotEmpty(
            get_terms(array( 'taxonomy' => 'mhmrentiva_vehicle_category', 'hide_empty' => false )),
            'The vehicle category terms did not follow the taxonomy rename.'
        );
    }

    /**
     * Hazard 2, options arm. On a real upgrade the renamed code reaches
     * add_option() under the NEW name before the migration runs -- that is what
     * killed Görev 12's dev run with a duplicate-key error on
     * `mhmrentiva_addon_context_migrated_4_36_0`. The customer's value is the
     * one under the OLD name; the new-name row can only be a default the new
     * code just wrote, so the old value has to win.
     */
    public function test_an_existing_destination_option_does_not_win_over_the_customer_value(): void
    {
        update_option('mhm_rentiva_currency', 'TRY');
        update_option('mhmrentiva_currency', 'USD');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('TRY', get_option('mhmrentiva_currency'), 'The freshly-defaulted new-name row overwrote the customer value.');
        $this->assertFalse(get_option('mhm_rentiva_currency'));
    }

    /**
     * The autoload flag is part of the value: an autoloaded setting that lands
     * as autoload=no stops being on every page load, and a large non-autoloaded
     * blob that lands as autoload=yes is a permanent performance regression.
     */
    public function test_the_autoload_flag_survives_the_move(): void
    {
        add_option('mhm_rentiva_license', 'blob', '', false);
        add_option('mhm_rentiva_currency', 'TRY', '', true);

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertFalse($this->option_is_autoloaded('mhmrentiva_license'), 'A non-autoloaded option became autoloaded.');
        $this->assertTrue($this->option_is_autoloaded('mhmrentiva_currency'), 'An autoloaded option stopped being autoloaded.');
    }

    private function option_is_autoloaded(string $name): bool
    {
        global $wpdb;

        $stored = (string) $wpdb->get_var(
            $wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name)
        );

        return in_array($stored, array( 'yes', 'on', 'auto', 'auto-on' ), true);
    }

    /**
     * Hazard 1. `_mhm_vehicle_id` and `_mhm_rentiva_vehicle_id` both target
     * `_mhmrentiva_vehicle_id`, and wp_postmeta has no unique index to collapse
     * them -- a post carrying both ends up with TWO rows under the new name and
     * get_post_meta() returns whichever the storage engine orders first.
     *
     * The winner is the owner's decision in POSTMETA_MERGE_WINNERS, not a
     * heuristic: on the real database the BARE spelling holds 25 rows -- every
     * writer uses it -- while the rentiva-qualified one, which is what
     * Testimonials filtered on, has no writer and no live rows at all.
     * "Prefer the more specific spelling" gives the wrong answer here.
     */
    public function test_a_merged_pair_keeps_the_named_winner_and_leaves_one_row(): void
    {
        $booking = self::factory()->post->create(array( 'post_type' => 'vehicle_booking' ));
        update_post_meta($booking, '_mhm_vehicle_id', '25-row-writer');
        update_post_meta($booking, '_mhm_rentiva_vehicle_id', '3-row-reader');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame(1, $this->count_meta($booking, '_mhmrentiva_vehicle_id'), 'The merge left two rows under one key.');
        $this->assertSame('25-row-writer', get_post_meta($booking, '_mhmrentiva_vehicle_id', true));
        $this->assertSame(0, $this->count_meta($booking, '_mhm_vehicle_id'));
        $this->assertSame(0, $this->count_meta($booking, '_mhm_rentiva_vehicle_id'));
    }

    /**
     * The EIGHTH merged pair, added to the map on 2026-08-02.
     *
     * It collides between `_mhm_` and `_rentiva_` rather than between `_mhm_` and
     * `_mhm_rentiva_`, which is why the original seven-pair analysis did not see
     * it. The winner is the spelling the WRITERS use -- Pro's VehicleSubmit and
     * VehicleTransferMetaBox both update_post_meta() the `_rentiva_` key, while
     * the `_mhm_` spelling has no writer at all and survives only as a legacy
     * read-fallback. Note this is the mirror image of vehicle_id, where the BARE
     * spelling was the writer: same rule, opposite-looking answer.
     */
    public function test_the_service_type_pair_keeps_the_writers_spelling(): void
    {
        $vid = self::factory()->post->create(array( 'post_type' => 'vehicle' ));
        update_post_meta($vid, '_rentiva_vehicle_service_type', 'transfer');
        update_post_meta($vid, '_mhm_vehicle_service_type', 'stale-fallback');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame(1, $this->count_meta($vid, '_mhmrentiva_vehicle_service_type'), 'The merge left two rows under one key.');
        $this->assertSame('transfer', get_post_meta($vid, '_mhmrentiva_vehicle_service_type', true));
        $this->assertSame(0, $this->count_meta($vid, '_rentiva_vehicle_service_type'));
        $this->assertSame(0, $this->count_meta($vid, '_mhm_vehicle_service_type'));
    }

    /**
     * A post that carries only the LOSING spelling still keeps its value -- the
     * de-duplication may only fire where both rows are actually present.
     */
    public function test_the_loser_spelling_alone_still_carries_its_value(): void
    {
        $booking = self::factory()->post->create(array( 'post_type' => 'vehicle_booking' ));
        update_post_meta($booking, '_mhm_rentiva_vehicle_id', 'only-copy');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('only-copy', get_post_meta($booking, '_mhmrentiva_vehicle_id', true));
    }

    /**
     * The one pair of the seven the owner decided must NOT merge: the metabox
     * writes JSON with dates plus notes, the cancellation handler writes a flat
     * date array. Same object, different value shapes.
     */
    public function test_the_two_blocked_dates_families_stay_distinct(): void
    {
        $vid = self::factory()->post->create(array( 'post_type' => 'vehicle' ));
        update_post_meta($vid, '_mhm_blocked_dates', '{"2026-01-01":"servis"}');
        update_post_meta($vid, '_mhm_rentiva_blocked_dates', array( '2026-02-02' ));

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('{"2026-01-01":"servis"}', get_post_meta($vid, '_mhmrentiva_blocked_dates', true));
        $this->assertSame(array( '2026-02-02' ), get_post_meta($vid, '_mhmrentiva_booking_blocked_dates', true));
    }

    /**
     * Hazard 3. `_mhm_` is a VENDOR prefix, not a Rentiva one: the currency
     * switcher's `_mhm_cs_fixed_prices` and mhm-pay's `_mhm_pay_*` live in the
     * same wp_postmeta. Görev 12's prefix-only draft renamed the currency
     * switcher's row on the dev database.
     */
    public function test_a_sibling_products_meta_on_a_foreign_post_is_left_alone(): void
    {
        $product = self::factory()->post->create(array( 'post_type' => 'product' ));
        update_post_meta($product, '_mhm_cs_fixed_prices', '{"EUR":"19"}');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('{"EUR":"19"}', get_post_meta($product, '_mhm_cs_fixed_prices', true), 'The currency switcher lost its row.');
        $this->assertSame('', get_post_meta($product, '_mhmrentiva_cs_fixed_prices', true));
    }

    /**
     * Our own rows on a foreign post type still have to move. The shortcode
     * page installer writes to `page` posts, and the WooCommerce bridge writes
     * to orders -- neither is a Rentiva post type, so post-type scoping alone
     * would strand them.
     */
    public function test_our_own_meta_on_a_foreign_post_type_still_moves(): void
    {
        $page = self::factory()->post->create(array( 'post_type' => 'page' ));
        update_post_meta($page, '_mhm_shortcode', '[mhmrentiva_vehicles]');
        update_post_meta($page, '_mhm_auto_created', '1');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('[mhmrentiva_vehicles]', get_post_meta($page, '_mhmrentiva_shortcode', true));
        $this->assertSame('1', get_post_meta($page, '_mhmrentiva_auto_created', true));
    }

    /**
     * `addon_` carries no vendor token at all, so `meta_key LIKE 'addon_%'`
     * would capture ANY plugin's addon meta. It migrates as five enumerated
     * keys on addon posts and nothing else.
     */
    public function test_the_addon_family_moves_only_on_addon_posts(): void
    {
        $addon = self::factory()->post->create(array( 'post_type' => 'vehicle_addon' ));
        update_post_meta($addon, 'addon_price', '50');
        $page = self::factory()->post->create(array( 'post_type' => 'page' ));
        update_post_meta($page, 'addon_price', 'someone-elses');
        update_post_meta($addon, 'addon_unmapped_key', 'not-ours');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('50', get_post_meta($addon, 'mhmrentiva_addon_price', true));
        $this->assertSame('someone-elses', get_post_meta($page, 'addon_price', true), 'A foreign plugin lost its addon_price row.');
        $this->assertSame('not-ours', get_post_meta($addon, 'addon_unmapped_key', true), 'An unmapped addon_* key was swept up by a prefix match.');
    }

    /**
     * The vendor profile family, which had NO usermeta rule until 2026-08-02.
     *
     * Görev 12 renamed these literals in the code -- DashboardContext.php:29 now
     * reads `_mhmrentiva_vendor_status` -- so without the `_rentiva_` rule every
     * vendor's active status, IBAN, slug and profile is orphaned and no vendor
     * can enter the panel. 18 keys / 65 rows on the dev database.
     */
    public function test_the_vendor_profile_user_meta_family_moves(): void
    {
        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        update_user_meta($user, '_rentiva_vendor_status', 'active');
        update_user_meta($user, '_rentiva_vendor_iban', 'TR000000000000000000000000');
        update_user_meta($user, '_rentiva_vendor_slug', 'acme-rentals');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('active', get_user_meta($user, '_mhmrentiva_vendor_status', true));
        $this->assertSame('TR000000000000000000000000', get_user_meta($user, '_mhmrentiva_vendor_iban', true));
        $this->assertSame('acme-rentals', get_user_meta($user, '_mhmrentiva_vendor_slug', true));
        $this->assertSame('', get_user_meta($user, '_rentiva_vendor_status', true));
    }

    /**
     * wp_usermeta has no unique index on (user_id, meta_key) either.
     *
     * `_rentiva_vendor_city` (MetaKeys::VENDOR_CITY, one writer and six readers)
     * and `_mhm_rentiva_vendor_city` (zero writers, zero readers -- an orphan a
     * fixed Pro bug used to read) both land on `_mhmrentiva_vendor_city`. On the
     * dev database four vendors carry both, and three of them hold a DIFFERENT
     * city on the orphan row.
     */
    public function test_the_vendor_city_pair_keeps_the_live_key_and_leaves_one_row(): void
    {
        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        add_user_meta($user, '_rentiva_vendor_city', 'Kocaeli');
        add_user_meta($user, '_mhm_rentiva_vendor_city', 'Istanbul');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame(1, $this->count_user_meta($user, '_mhmrentiva_vendor_city'), 'The merge left two rows under one key.');
        $this->assertSame('Kocaeli', get_user_meta($user, '_mhmrentiva_vendor_city', true), 'The orphan spelling won and the vendor directory would show the wrong city.');
        $this->assertSame(0, $this->count_user_meta($user, '_mhm_rentiva_vendor_city'));
        $this->assertSame(0, $this->count_user_meta($user, '_rentiva_vendor_city'));
    }

    /**
     * A merge DELETES a row that held a real value, and this migration cannot be
     * un-run. Every discarded row must survive somewhere findable.
     */
    public function test_discarded_merge_losers_are_recorded_before_deletion(): void
    {
        global $wpdb;

        $this->use_real_tables();

        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        add_user_meta($user, '_rentiva_vendor_city', 'Kocaeli');
        add_user_meta($user, '_mhm_rentiva_vendor_city', 'Istanbul');

        $booking = self::factory()->post->create(array( 'post_type' => 'vehicle_booking' ));
        update_post_meta($booking, '_mhm_customer_email', 'live@example.com');
        update_post_meta($booking, '_mhm_rentiva_customer_email', 'orphan@example.com');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $table = $this->merge_loser_backup_table();
        $this->assertNotNull($table, 'Nothing recorded the discarded rows.');
        $this->temp_tables[] = $table;

        // Scoped to the two objects THIS test created. The backup table is
        // per-run, and every test in this class runs its own migration, so
        // earlier tests' discarded rows are legitimately in there too.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT family, object_id, meta_key, meta_value FROM %i
                  WHERE ( family = %s AND object_id = %d ) OR ( family = %s AND object_id = %d )
                  ORDER BY family, meta_key',
                $table,
                'postmeta',
                $booking,
                'usermeta',
                $user
            ),
            ARRAY_A
        );

        $this->assertSame(
            array(
                array( 'family' => 'postmeta', 'object_id' => (string) $booking, 'meta_key' => '_mhm_rentiva_customer_email', 'meta_value' => 'orphan@example.com' ),
                array( 'family' => 'usermeta', 'object_id' => (string) $user, 'meta_key' => '_mhm_rentiva_vendor_city', 'meta_value' => 'Istanbul' ),
            ),
            $rows,
            'Both families must be recorded, with the value that was thrown away.'
        );
    }

    /**
     * If the recovery copy cannot be written, NOTHING may be discarded.
     *
     * The failure is routine, not exotic: a DB user without CREATE is normal on
     * managed and shared hosting. Before this lock the helper logged one line
     * and the caller deleted anyway -- every losing-spelling row for all nine
     * declared pairs, permanently, with the old names already gone from the
     * tree. Leaving the duplicates is the map's stated posture: an unresolved
     * collision is visible; a row deleted without a copy is gone.
     *
     * Note the M-no-backup mutation could NOT catch this. Removing the call
     * turns a test red; the call FAILING did not.
     */
    public function test_nothing_is_discarded_when_the_recovery_copy_cannot_be_written(): void
    {
        global $wpdb;

        $this->use_real_tables();

        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        add_user_meta($user, '_rentiva_vendor_city', 'Kocaeli');
        add_user_meta($user, '_mhm_rentiva_vendor_city', 'Istanbul');

        $booking = self::factory()->post->create(array( 'post_type' => 'vehicle_booking' ));
        update_post_meta($booking, '_mhm_customer_email', 'live@example.com');
        update_post_meta($booking, '_mhm_rentiva_customer_email', 'orphan@example.com');

        // Make every write to the recovery table fail, exactly as a missing
        // CREATE grant would. The SELECT/DELETE traffic is untouched.
        $break = static function ($query) {
            if (preg_match('/(CREATE TABLE IF NOT EXISTS|INSERT INTO)\s+`?[a-z0-9_]*merge_losers_backup/i', (string) $query)) {
                return 'SELECT 1 FROM DUAL WHERE 1=0';
            }
            return $query;
        };
        add_filter('query', $break);

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        remove_filter('query', $break);

        // The rename still runs -- only the DISCARD is withheld. So both values
        // arrive under the new name and the collision is left unresolved and
        // VISIBLE as two rows, which is exactly the posture the map states.
        // What must not happen is a value disappearing.
        $this->assertSame(
            array( 'Istanbul', 'Kocaeli' ),
            $this->meta_values($wpdb->usermeta, 'user_id', $user, '_mhmrentiva_vendor_city'),
            'A discarded user-meta value is gone and there is no recovery copy.'
        );
        $this->assertSame(
            array( 'live@example.com', 'orphan@example.com' ),
            $this->meta_values($wpdb->postmeta, 'post_id', $booking, '_mhmrentiva_customer_email'),
            'A discarded post-meta value is gone and there is no recovery copy.'
        );
    }

    /**
     * Every value stored under one key on one object, oldest row first.
     *
     * @return list<string>
     */
    private function meta_values(string $table, string $id_column, int $object_id, string $meta_key): array
    {
        global $wpdb;

        // wp_usermeta's primary key is umeta_id, wp_postmeta's is meta_id, so
        // there is no shared ORDER BY; sorting the values makes the comparison
        // order-independent instead.
        $column = 'user_id' === $id_column ? 'user_id' : 'post_id';

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT meta_value FROM %i WHERE ' . $column . ' = %d AND meta_key = %s',
                $table,
                $object_id,
                $meta_key
            )
        );

        sort($rows);

        return $rows;
    }

    /**
     * The de-duplication may only fire where the rename will actually carry the
     * survivor.
     *
     * `_mhm_customer_email` is a vendor-token-only key, so the rename carries it
     * on OUR post types and nowhere else. On a `product` post -- a shape this
     * database really has, from a mis-scoped metabox -- deleting the qualified
     * copy and then declining to rename the bare one leaves the object with ZERO
     * rows under the name the code reads, having had a readable value before.
     */
    public function test_no_row_is_discarded_on_an_object_the_rename_will_not_carry(): void
    {
        $product = self::factory()->post->create(array( 'post_type' => 'product' ));
        update_post_meta($product, '_mhm_customer_email', 'live@example.com');
        update_post_meta($product, '_mhm_rentiva_customer_email', 'orphan@example.com');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        // The bare key is NOT carried on a foreign post type, so it stays put --
        // and, crucially, it is still there: the merge did not delete it.
        $this->assertSame('live@example.com', get_post_meta($product, '_mhm_customer_email', true), 'A row was destroyed on an object the rename does not carry.');

        // The qualified key IS carried everywhere (product token), so it moved.
        // Its value must have survived the move rather than been discarded in
        // favour of a winner that was never going to arrive.
        $this->assertSame('orphan@example.com', get_post_meta($product, '_mhmrentiva_customer_email', true), 'The loser was discarded although the winner is not carried here.');
        $this->assertSame('', get_post_meta($product, '_mhm_rentiva_customer_email', true));
    }

    /**
     * The bare `_mhm_` USER-meta family is deliberately never touched by
     * pattern, because sibling products write user meta too. The merge path
     * derives `_mhm_vendor_city` as a candidate loser for the vendor_city pair
     * and must not touch it by derivation either.
     */
    public function test_the_merge_does_not_reach_the_user_meta_family_the_design_excludes(): void
    {
        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        add_user_meta($user, '_rentiva_vendor_city', 'Kocaeli');
        add_user_meta($user, '_mhm_vendor_city', 'somebody-elses');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('somebody-elses', get_user_meta($user, '_mhm_vendor_city', true), 'The merge deleted a key the rename refuses to touch.');
        $this->assertSame('Kocaeli', get_user_meta($user, '_mhmrentiva_vendor_city', true));
    }

    /**
     * The recurrence NAME was renamed too, and it is not a hook name so it is
     * easy to miss. A pre-6.0.0 cron option holds AutoCancel's event under
     * `mhm_rentiva_5min`, which wp_get_schedules() no longer registers; passing
     * it straight through makes wp_schedule_event() fail, after which clearing
     * the old hook would delete the recurring job outright.
     */
    public function test_a_recurring_event_under_a_legacy_interval_name_is_carried(): void
    {
        $when = time() + 3600;

        // Built by hand, NOT via wp_schedule_event(): that function validates the
        // recurrence against wp_get_schedules() and would refuse the pre-6.0.0
        // name outright, so the fixture would silently not be the thing under
        // test. This is exactly the row an upgrading site carries.
        wp_unschedule_hook('mhm_rentiva_auto_cancel_event');
        wp_unschedule_hook('mhmrentiva_auto_cancel_event');
        _set_cron_array(array(
            $when => array(
                'mhm_rentiva_auto_cancel_event' => array(
                    md5(serialize(array())) => array(
                        'schedule' => 'mhm_rentiva_5min',
                        'args'     => array(),
                        'interval' => 300,
                    ),
                ),
            ),
        ));

        // The premise, asserted rather than assumed.
        $seeded = _get_cron_array();
        $this->assertSame(
            'mhm_rentiva_5min',
            $seeded[ $when ]['mhm_rentiva_auto_cancel_event'][ md5(serialize(array())) ]['schedule'],
            'Fixture premise: the stored recurrence must be the PRE-rename name.'
        );

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        // Carried, not merely present: the same timestamp proves this row came
        // from the old one rather than from a class re-scheduling itself.
        $this->assertSame(
            $when,
            wp_next_scheduled('mhmrentiva_auto_cancel_event'),
            'The recurring event was not carried: its interval name was the pre-rename one.'
        );
        $this->assertFalse(wp_next_scheduled('mhm_rentiva_auto_cancel_event'));
    }

    /**
     * The recovery copy has to be reachable by the screen that lists backups,
     * or it is not a recovery copy. And it must NOT be restorable in place: its
     * rows span two target tables and the generic restore path defaults to
     * wp_postmeta, which would invent postmeta rows keyed by a user id.
     */
    public function test_the_merge_loser_backup_is_listed_and_refuses_blind_restore(): void
    {
        $this->use_real_tables();

        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        add_user_meta($user, '_rentiva_vendor_city', 'Kocaeli');
        add_user_meta($user, '_mhm_rentiva_vendor_city', 'Istanbul');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $table = $this->merge_loser_backup_table();
        $this->assertNotNull($table);
        $this->temp_tables[] = $table;

        $listed = array_filter(
            DatabaseCleaner::list_backups(),
            static fn (array $b): bool => $b['table_name'] === $table
        );
        $this->assertNotEmpty($listed, 'The recovery copy is invisible to the Backups screen.');
        $this->assertSame('merge_losers', reset($listed)['type']);

        $this->assertTrue(DatabaseCleaner::is_managed_backup_table($table), 'Not managed, so export refuses it.');
        $this->assertNotSame('', DatabaseCleaner::export_backup_to_sql($table), 'The recovery copy cannot be exported.');

        global $wpdb;
        $postmeta_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");

        $restore = DatabaseCleaner::restore_backup($table);

        // assertFalse($restore['success']) ALONE is vacuous here, and this was
        // caught by mutation: with the guard removed the generic branch defaults
        // its target to wp_postmeta and the INSERT fails on a column-count
        // mismatch, so 'success' is false either way. What must be asserted is
        // WHY -- a deliberate refusal, not an accident that would have corrupted
        // wp_postmeta the moment the two schemas happened to line up.
        $this->assertFalse($restore['success']);
        $this->assertStringContainsString(
            'cannot be restored in place',
            $restore['message'],
            'The refusal must be deliberate, not a lucky SQL error.'
        );
        $this->assertSame(
            $postmeta_before,
            (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}"),
            'A restore attempt wrote rows into wp_postmeta.'
        );
    }

    /**
     * The underscore-LESS rentiva-qualified user meta. The map's docblock used to
     * claim the bare `mhm_` rule covered these; it does not -- it cuts after
     * `mhm_` and produces `mhmrentiva_rentiva_favorites`, which nothing reads.
     *
     * `mhmrentiva_customer` is the load-bearing one: AccountController.php:425
     * gates customer account access on it.
     */
    public function test_the_bare_rentiva_user_meta_family_does_not_double_up_the_token(): void
    {
        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        update_user_meta($user, 'mhm_rentiva_favorites', array( 101, 202 ));
        update_user_meta($user, 'mhm_rentiva_customer', '1');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame(array( 101, 202 ), get_user_meta($user, 'mhmrentiva_favorites', true));
        $this->assertSame('1', get_user_meta($user, 'mhmrentiva_customer', true), 'Customer account access meta was orphaned.');
        $this->assertSame('', get_user_meta($user, 'mhmrentiva_rentiva_favorites', true), 'The bare rule cut at the wrong offset and doubled the product token.');
        $this->assertSame('', get_user_meta($user, 'mhm_rentiva_favorites', true));
    }

    /**
     * usermeta has no post to join against, so the two vendor-token-only rules
     * are driven by an explicit key allowlist read out of the pre-rename tree --
     * never by a prefix LIKE. Sibling MHM products write user meta too, and two
     * of their keys are sitting in the dev database right now.
     */
    public function test_a_sibling_products_user_meta_is_left_alone(): void
    {
        $user = self::factory()->user->create();
        $this->seeded_users[] = $user;
        update_user_meta($user, '_mhm_is_demo_user', '1');
        update_user_meta($user, 'mhm_cs_preferred_currency', 'EUR');
        // Ours, same bare prefix, in the allowlist -- proves the lock is not just
        // "leave every mhm_ user meta alone".
        update_user_meta($user, 'mhm_gdpr_consent_given', 'yes');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('1', get_user_meta($user, '_mhm_is_demo_user', true), 'A key in no Rentiva source file was renamed.');
        $this->assertSame('EUR', get_user_meta($user, 'mhm_cs_preferred_currency', true), 'The currency switcher lost its user meta.');
        $this->assertSame('yes', get_user_meta($user, 'mhmrentiva_gdpr_consent_given', true), 'Our own bare-prefix key did not move.');
    }

    /**
     * Vehicle ratings are stored as WP comments; the meta key never had a
     * product token and the sweep renamed the literal, so without this arm every
     * rating and every manually verified review silently reverts.
     */
    public function test_comment_meta_moves(): void
    {
        $comment_id = self::factory()->comment->create();
        update_comment_meta($comment_id, 'mhm_rating', '5');
        update_comment_meta($comment_id, 'mhm_verified_review', '1');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('5', get_comment_meta($comment_id, 'mhmrentiva_rating', true));
        $this->assertSame('1', get_comment_meta($comment_id, 'mhmrentiva_verified_review', true));
    }

    /**
     * The contact-message bucket: 26 characters into a varchar(20) column.
     *
     * Nobody calls register_post_type() for it, so it had no POST_TYPES entry
     * and never met the map's "<= 20" check; the bare `mhm_` catch-all rewrote
     * the literal instead, and that rule has no length rule to break. Every
     * submission would have truncated on a non-strict server and ERRORED on a
     * strict one -- and, having no map entry, the existing rows belonged to no
     * migration family at all.
     */
    public function test_the_contact_message_bucket_migrates_and_fits_its_column(): void
    {
        global $wpdb;

        $post_id = self::factory()->post->create(array( 'post_type' => 'mhm_contact_message' ));

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        // Read the column, not the cache: truncation is invisible through
        // get_post() once the object is in memory.
        $stored = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $post_id));

        $this->assertSame('mhmrentiva_contact', $stored, 'The contact-message rows were stranded or truncated.');
        $this->assertLessThanOrEqual(20, strlen((string) $stored), 'The stored post type does not fit wp_posts.post_type.');
    }

    /**
     * What ContactForm actually writes has to survive the round trip.
     *
     * A length assertion on the constant is vacuous -- it would pass on the
     * broken name too if the column were wider. This inserts the literal the
     * shortcode uses and reads the column back.
     */
    public function test_a_new_contact_message_survives_the_column_round_trip(): void
    {
        global $wpdb;

        $post_id = wp_insert_post(array(
            'post_type'   => 'mhmrentiva_contact',
            'post_title'  => 'Contact Message - round trip',
            'post_status' => 'private',
        ));

        $this->assertIsInt($post_id);
        $this->assertGreaterThan(0, $post_id, 'The insert failed outright -- strict mode rejects an over-long post_type.');

        $stored = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $post_id));
        $this->assertSame('mhmrentiva_contact', $stored, 'The post type was truncated on the way in.');
    }

    /**
     * Dead orchestration schema is dropped under BOTH spellings.
     *
     * Neither table is in PrefixMigrationMap::TABLES, so no rename ever produces
     * the new names -- the table a real install has carries the old one, and
     * dropping only the new name dropped nothing at all.
     */
    public function test_dead_orchestration_tables_are_dropped_under_both_spellings(): void
    {
        global $wpdb;

        $this->use_real_tables();

        $legacy = $this->table('mhm_rentiva_tenants');
        $this->create_temp_table($legacy);
        $this->assertNotNull(
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy))),
            'Premise: the legacy-named table must exist before the migration runs.'
        );

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertNull(
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy))),
            'The pre-6.0.0 spelling of a dead table survived the migration.'
        );
    }

    /**
     * Hazard 2, tables arm -- measured, not hypothetical: the dev database
     * carried an EMPTY `wp_mhmrentiva_key_registry` (planted by the renamed
     * code) next to a populated `wp_mhm_rentiva_key_registry` with 12 rows.
     * A plain "rename only if the destination is absent" strands all 12.
     */
    public function test_a_table_rename_reclaims_an_empty_destination(): void
    {
        global $wpdb;

        $this->use_real_tables();

        $old = $this->table('mhm_rentiva_ratings');
        $new = $this->table('mhmrentiva_ratings');

        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $old));
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $new));
        $this->create_temp_table($old);
        $this->create_temp_table($new);
        $wpdb->query($wpdb->prepare('INSERT INTO %i (note) VALUES (%s)', $old, 'real-data'));

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame(
            'real-data',
            $wpdb->get_var($wpdb->prepare('SELECT note FROM %i WHERE note = %s', $new, 'real-data')),
            'The populated legacy table was stranded behind an empty destination.'
        );
        $this->assertNull(
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)),
            'The legacy table is still there after the rename.'
        );
    }

    /**
     * Hazard 4. Booking reminders are per-booking single events carrying the
     * booking id in `args`. wp_clear_scheduled_hook() with no args does not
     * even match them, and clearing them at all means every reminder already
     * scheduled at upgrade time never fires. They have to be re-keyed.
     */
    public function test_a_pending_booking_reminder_is_rekeyed_with_its_args(): void
    {
        $when = time() + 3600;
        wp_schedule_single_event($when, 'mhm_rentiva_send_booking_reminder', array( 4242 ));

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame(
            $when,
            wp_next_scheduled('mhmrentiva_send_booking_reminder', array( 4242 )),
            'The pending reminder was dropped instead of re-keyed.'
        );
        $this->assertFalse(wp_next_scheduled('mhm_rentiva_send_booking_reminder', array( 4242 )));
    }

    /**
     * Hazard 5 / Adim 3b. run_migrations() is ONE all-or-nothing gate whose key
     * was itself renamed. If the version read falls through to its '1.0.0'
     * default on an upgraded site, all twelve earlier steps re-run as though
     * this were a fresh install.
     *
     * The assertion that matters is the second one: not "the result looks
     * right" but "the earlier steps did not fire". `migrate_vehicle_lifecycle_status()`
     * stamps its own flag option, so an absent flag proves it never ran.
     */
    public function test_a_legacy_version_stamp_is_adopted_and_does_not_replay_earlier_steps(): void
    {
        // Read the constant rather than repeating it: hard-coding the current
        // version made this test fail on the next bump for a reason that had
        // nothing to do with what it asserts.
        $current = (string) (new \ReflectionClass(DatabaseMigrator::class))->getConstant('CURRENT_VERSION');
        $this->assertNotSame('', $current, 'Could not read CURRENT_VERSION.');

        delete_option(self::DB_VERSION);
        delete_option(self::LIFECYCLE_FLAG);
        update_option(self::LEGACY_DB_VERSION, $current);

        DatabaseMigrator::run_migrations();

        $this->assertSame($current, get_option(self::DB_VERSION), 'The legacy stamp was not adopted under the new name.');
        $this->assertFalse(get_option(self::LEGACY_DB_VERSION), 'The legacy stamp survived, so the next load reads two sources of truth.');
        $this->assertFalse(
            get_option(self::LIFECYCLE_FLAG),
            'An already-current install replayed the twelve earlier migration steps.'
        );
    }

    /**
     * A large site can time out mid-run. Running the whole thing twice must not
     * double-apply anything, and the second run must be a no-op -- which is a
     * different claim from "the second run also produced the right answer".
     */
    public function test_the_migration_is_idempotent(): void
    {
        $vid = self::factory()->post->create(array( 'post_type' => 'vehicle' ));
        update_post_meta($vid, '_mhm_rentiva_price_per_day', '100');
        update_option('mhm_rentiva_currency', 'TRY');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $first = array(
            'price'    => get_post_meta($vid, '_mhmrentiva_price_per_day', true),
            'rows'     => $this->count_meta($vid, '_mhmrentiva_price_per_day'),
            'currency' => get_option('mhmrentiva_currency'),
            'type'     => get_post_type($vid),
        );

        // Re-open the gate and run the whole step again over already-migrated data.
        update_option(self::DB_VERSION, '3.15.0');
        DatabaseMigrator::run_migrations();

        $this->assertSame($first['price'], get_post_meta($vid, '_mhmrentiva_price_per_day', true));
        $this->assertSame(1, $this->count_meta($vid, '_mhmrentiva_price_per_day'), 'The second run duplicated a meta row.');
        $this->assertSame($first['rows'], $this->count_meta($vid, '_mhmrentiva_price_per_day'));
        $this->assertSame($first['currency'], get_option('mhmrentiva_currency'));
        $this->assertSame($first['type'], get_post_type($vid));
        $this->assertSame('', get_post_meta($vid, '_mhmrentiva_rentiva_price_per_day', true), 'The second run re-applied a prefix rule to its own output.');
    }

    /**
     * The `_mhm_` LIKE pattern is a trap: in SQL LIKE, `_` is a single-character
     * wildcard, so an unescaped `_mhm_%` also matches `_mhmrentiva_...` -- the
     * migration's own output -- and would keep re-prefixing it every batch.
     */
    public function test_the_rename_does_not_re_match_its_own_output(): void
    {
        $vid = self::factory()->post->create(array( 'post_type' => 'vehicle' ));
        update_post_meta($vid, '_mhmrentiva_already_migrated', 'kept');

        $this->seed_pre_rename_version();
        DatabaseMigrator::run_migrations();

        $this->assertSame('kept', get_post_meta($vid, '_mhmrentiva_already_migrated', true));
        $this->assertSame('', get_post_meta($vid, '_mhmrentivarentiva_already_migrated', true));
    }
}
