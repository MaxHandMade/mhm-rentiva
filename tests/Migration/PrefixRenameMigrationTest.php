<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

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
     * heuristic: on the real database the BARE spelling holds 25 rows (every
     * writer) and the rentiva-qualified one 3 (what Testimonials filtered on).
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
        delete_option(self::DB_VERSION);
        delete_option(self::LIFECYCLE_FLAG);
        update_option(self::LEGACY_DB_VERSION, '4.0.0');

        DatabaseMigrator::run_migrations();

        $this->assertSame('4.0.0', get_option(self::DB_VERSION), 'The legacy stamp was not adopted under the new name.');
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
