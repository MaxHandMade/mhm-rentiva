<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\CacheManager;
use WP_UnitTestCase;

/**
 * Invalidation has to reach the backend the writer actually wrote to.
 *
 * `set_cache()` and `get_cache()` switch to the external object cache whenever
 * one is present. The clearing side does not: `clear_cache_by_pattern()` and
 * `clear_cache_by_type()` run a LIKE over wp_options and nothing else. On a
 * site with Redis or Memcached -- which is most managed WordPress hosting --
 * the entries live in the object cache, so the LIKE matches nothing, reports
 * success, and the stale value keeps being served until its TTL expires.
 *
 * Five of the nine cache types go through that pattern path: customers,
 * booking_report, customer_report, vehicle_report, revenue_report and
 * vehicle_list. So saving a customer, a booking or a vehicle leaves the screen
 * that lists them showing the old data.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\CacheManager::clear_cache
 * @covers \MHMRentiva\Admin\Core\Utilities\CacheManager::clear_cache_by_type
 */
final class CacheInvalidationWithObjectCacheTest extends WP_UnitTestCase
{
	private $previous_ext_object_cache = null;

	public function setUp(): void
	{
		parent::setUp();
		$this->previous_ext_object_cache = wp_using_ext_object_cache();
	}

	public function tearDown(): void
	{
		// A global, not a hook -- nothing restores it for us, and leaving it on
		// would silently reroute every later test's caching.
		wp_using_ext_object_cache( $this->previous_ext_object_cache );
		CacheManager::clear_cache();
		parent::tearDown();
	}

	/**
	 * Before trusting anything below: prove the switch actually reroutes the
	 * write, rather than the test merely believing it did.
	 *
	 * Written with the object cache on and read with it off: if the value comes
	 * back, it went to the transient table and this whole file is measuring the
	 * wrong branch.
	 */
	public function test_the_object_cache_branch_is_really_being_exercised(): void
	{
		wp_using_ext_object_cache( true );
		CacheManager::set_cache( 'customers', 'probe', array( 'rows' => 1 ), 300 );

		$this->assertSame(
			array( 'rows' => 1 ),
			CacheManager::get_cache( 'customers', 'probe' ),
			'Precondition: the value is readable through the object cache.'
		);

		wp_using_ext_object_cache( false );

		$this->assertFalse(
			CacheManager::get_cache( 'customers', 'probe' ),
			'The write must have gone to the object cache, not the transient table -- otherwise this file proves nothing.'
		);
	}

	// --- The lock ---------------------------------------------------------

	public function test_clear_cache_reaches_a_pattern_type_in_the_object_cache(): void
	{
		wp_using_ext_object_cache( true );
		CacheManager::set_cache( 'customers', 'list_abc', array( 'rows' => 3 ), 300 );

		$this->assertSame(
			array( 'rows' => 3 ),
			CacheManager::get_cache( 'customers', 'list_abc' ),
			'Precondition: the entry is readable before the clear.'
		);

		CacheManager::clear_cache( array( 'customers' ) );

		$this->assertFalse(
			CacheManager::get_cache( 'customers', 'list_abc' ),
			'clear_cache() must invalidate the entry it can see through get_cache().'
		);
	}

	/**
	 * The spelling CustomersOptimizer actually calls.
	 */
	public function test_clear_cache_by_type_reaches_the_object_cache(): void
	{
		wp_using_ext_object_cache( true );
		CacheManager::set_cache( 'customers', 'list_def', array( 'rows' => 4 ), 300 );

		CacheManager::clear_cache_by_type( 'customers' );

		$this->assertFalse(
			CacheManager::get_cache( 'customers', 'list_def' ),
			'clear_cache_by_type() is the live invalidation path for the Customers screen.'
		);
	}

	// --- Negative controls ------------------------------------------------

	/**
	 * Without this the fix could be "flush everything", which would throw away
	 * the per-type granularity the call sites rely on.
	 */
	public function test_clearing_one_type_leaves_the_others_alone(): void
	{
		wp_using_ext_object_cache( true );
		CacheManager::set_cache( 'customers', 'x', array( 'who' => 'customers' ), 300 );
		CacheManager::set_cache( 'vehicle_list', 'x', array( 'who' => 'vehicles' ), 300 );

		CacheManager::clear_cache( array( 'customers' ) );

		$this->assertFalse( CacheManager::get_cache( 'customers', 'x' ), 'The cleared type must be gone.' );
		$this->assertSame(
			array( 'who' => 'vehicles' ),
			CacheManager::get_cache( 'vehicle_list', 'x' ),
			'An untouched type must survive -- the call sites clear single types on purpose.'
		);
	}

	/**
	 * The backend that works today must keep working.
	 */
	public function test_the_transient_path_still_invalidates(): void
	{
		wp_using_ext_object_cache( false );
		CacheManager::set_cache( 'customers', 'list_ghi', array( 'rows' => 5 ), 300 );

		$this->assertSame( array( 'rows' => 5 ), CacheManager::get_cache( 'customers', 'list_ghi' ) );

		CacheManager::clear_cache( array( 'customers' ) );

		$this->assertFalse(
			CacheManager::get_cache( 'customers', 'list_ghi' ),
			'The transient path is what production uses today; the fix may not cost it.'
		);
	}

	/**
	 * A single-key type, for the other half of clear_cache()'s branch.
	 */
	public function test_clear_cache_reaches_a_single_key_type_in_the_object_cache(): void
	{
		wp_using_ext_object_cache( true );
		CacheManager::set_cache( 'system_info', '', array( 'php' => '8.2' ), 300 );

		CacheManager::clear_cache( array( 'system_info' ) );

		$this->assertFalse(
			CacheManager::get_cache( 'system_info' ),
			'Single-key types go down the other branch of clear_cache() and must invalidate too.'
		);
	}
}
