<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomersOptimizer;
use MHMRentiva\Admin\Core\Utilities\CacheManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Locks the customer-list query against SQL injection through its three
 * caller-supplied inputs: the free-text search term and the sort column /
 * direction that end up in ORDER BY.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * `CustomersOptimizer::get_customers_optimized()` is reached from the REST
 * controller with request-supplied `search`, `sort_by` and `sort_dir`. The
 * controller rejects an unknown `sort_by` before calling, but that check lives
 * in the caller: the class that BUILDS the SQL has to hold on its own, because
 * `CustomerExporter` calls the same method and any future caller may too. These
 * assertions are about the SQL builder, so they call it directly and bypass the
 * controller's whitelist entirely.
 *
 * HOW THE ASSERTIONS DETECT AN INJECTION
 * --------------------------------------
 * Every case compares against a baseline call that must return rows. An
 * injected fragment that reaches the server either changes the result set or
 * makes the statement invalid, and an invalid statement yields zero rows -- so
 * "same non-zero row count as the baseline" is the signal. `$wpdb->last_error`
 * is deliberately NOT asserted on: it is reset at the start of every
 * subsequent query, and this method runs several after the SELECT (cache
 * writes), which wipes it before the test can read it.
 *
 * The baseline always sorts by `last_booking`, the one sort key whose mapped
 * value equals its own name, so the baseline stays valid even when the
 * `$column_map` lookup is mutated away -- otherwise a mutant would break the
 * baseline too and the comparison would pass vacuously at 0 === 0.
 *
 * MUTATION-PROVEN (WP.org T7 Task 9.5), each direction run and recorded:
 * replacing `$column_map[$sort_by]` with `$sort_by` turns
 * test_malicious_sort_by red; replacing the ASC/DESC ternary with `$sort_dir`
 * turns test_malicious_sort_dir red; dropping `$wpdb->esc_like()` turns
 * test_like_wildcards red.
 *
 * @package MHMRentiva\Tests\Integration\Admin\Customers
 */
class CustomersOptimizerSqlInjectionTest extends \WP_UnitTestCase
{
    /**
     * Sort key whose mapped column name is identical to the key itself, so a
     * baseline call survives a mutated column allowlist.
     */
    private const SAFE_SORT_KEY = 'last_booking';

    public function setUp(): void
    {
        parent::setUp();

        // Three customers, each with one booking: the list query only returns
        // users that have a `_mhm_customer_email` booking meta row.
        foreach (array( 'alice', 'bob', 'carol' ) as $index => $login) {
            $email = $login . '@example.test';

            self::factory()->user->create(array(
                'user_login'   => $login,
                'user_email'   => $email,
                'display_name' => ucfirst($login),
                'role'         => 'subscriber',
            ));

            $booking_id = self::factory()->post->create(array(
                'post_type'   => 'vehicle_booking',
                'post_status' => 'publish',
                'post_title'  => 'Booking ' . $login,
            ));

            update_post_meta($booking_id, '_mhm_customer_email', $email);
            update_post_meta($booking_id, '_mhm_total_price', (string) ( 100 + $index ));
        }

        // The list is cached per argument set; a stale entry would let a query
        // that was never run answer these assertions.
        CacheManager::clear_cache_by_type('customers');
    }

    public function tearDown(): void
    {
        CacheManager::clear_cache_by_type('customers');

        parent::tearDown();
    }

    /**
     * Run the list query and drop the cache so the next call really queries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function list_customers(string $search = '', string $sort_by = self::SAFE_SORT_KEY, string $sort_dir = 'desc'): array
    {
        $result = CustomersOptimizer::get_customers_optimized(1, 20, $search, $sort_by, $sort_dir);
        CacheManager::clear_cache_by_type('customers');

        return $result['customers'] ?? array();
    }

    /**
     * A hostile sort column must neither execute nor change the result set.
     */
    public function test_malicious_sort_by_does_not_alter_the_result_set(): void
    {
        global $wpdb;

        $baseline = $this->list_customers();
        $this->assertNotEmpty($baseline, 'Fixture sanity: the baseline list must return rows.');

        $injected = $this->list_customers(
            '',
            "u.ID DESC, (SELECT 1 FROM {$wpdb->users} WHERE 1=1)"
        );

        $this->assertCount(
            count($baseline),
            $injected,
            'An injected sort column must not change how many customers come back.'
        );
    }

    /**
     * A hostile sort direction must not be able to append a second statement.
     */
    public function test_malicious_sort_dir_does_not_alter_the_result_set(): void
    {
        $baseline = $this->list_customers();
        $this->assertNotEmpty($baseline, 'Fixture sanity: the baseline list must return rows.');

        // Statement terminator plus a second statement: harmless once the
        // direction is chosen from {ASC, DESC}, a broken statement the moment
        // the raw string reaches ORDER BY.
        $injected = $this->list_customers('', self::SAFE_SORT_KEY, 'asc; SELECT 1');

        $this->assertCount(
            count($baseline),
            $injected,
            'An injected sort direction must not change how many customers come back.'
        );
    }

    /**
     * `' OR 1=1 -- ` is a search term, not a predicate: it must match nobody.
     */
    public function test_sql_metacharacters_in_search_are_matched_as_text(): void
    {
        $this->assertNotEmpty($this->list_customers(), 'Fixture sanity: the unfiltered list must return rows.');

        $this->assertSame(
            array(),
            $this->list_customers("' OR 1=1 -- "),
            'A search term that is pure SQL must match no customer.'
        );
    }

    /**
     * LIKE wildcards typed into the search box are data, not wildcards.
     */
    public function test_like_wildcards_in_search_are_escaped(): void
    {
        $this->assertNotEmpty($this->list_customers(), 'Fixture sanity: the unfiltered list must return rows.');

        $this->assertSame(
            array(),
            $this->list_customers('%'),
            'A literal "%" must be searched for as a character, not expanded into a LIKE wildcard.'
        );
    }
}
