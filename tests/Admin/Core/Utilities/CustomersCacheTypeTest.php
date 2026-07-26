<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\CacheManager;
use WP_UnitTestCase;

/**
 * `CustomersOptimizer` caches through a type `CacheManager` does not know.
 *
 * The optimizer calls `CacheManager::get_cache('customers', …)` and
 * `set_cache('customers', …)` at ten sites, but `customers` was missing from
 * `CACHE_KEYS`. Both methods return early on an unknown type, so every read
 * missed, every write was discarded, and `delete_cache` cleared keys that were
 * never written. The class named "Optimizer" ran its full query set on every
 * customers-page load while reporting nothing wrong — a performance control with
 * nothing behind it, the same shape as the settings toggles removed elsewhere in
 * this release.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache
 * @covers \MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache
 */
final class CustomersCacheTypeTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		CacheManager::delete_cache( 'customers', 'unit_test' );
		parent::tearDown();
	}

	public function test_a_customers_entry_survives_a_write_and_read(): void
	{
		$written = CacheManager::set_cache( 'customers', 'unit_test', array( 'rows' => 3 ), 300 );

		$this->assertTrue(
			$written,
			'The customers cache type is unknown to CacheManager, so the write was discarded.'
		);

		$this->assertSame(
			array( 'rows' => 3 ),
			CacheManager::get_cache( 'customers', 'unit_test' ),
			'The value written a moment ago did not come back.'
		);
	}

	public function test_deleting_a_customers_entry_actually_removes_it(): void
	{
		CacheManager::set_cache( 'customers', 'unit_test', array( 'rows' => 3 ), 300 );

		CacheManager::delete_cache( 'customers', 'unit_test' );

		$this->assertFalse(
			CacheManager::get_cache( 'customers', 'unit_test' ),
			'The entry is still readable after being deleted.'
		);
	}

	/**
	 * An unknown type must still fail closed rather than write somewhere
	 * unexpected — the guard is right, it was the missing entry that was wrong.
	 */
	public function test_an_unknown_type_still_returns_false(): void
	{
		$this->assertFalse( CacheManager::set_cache( 'not_a_real_type', 'k', 'v', 60 ) );
		$this->assertFalse( CacheManager::get_cache( 'not_a_real_type', 'k' ) );
	}
}
