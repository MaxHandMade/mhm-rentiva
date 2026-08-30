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
		CacheManager::clear_cache( array( 'system_info' ) );
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
	 * The regression this file exists to hold shut.
	 *
	 * set_cache()/get_cache() scope the key per site through
	 * get_multisite_cache_key(); delete_cache() used to build its own key and
	 * skip that call. On a single site the two spellings collide by accident,
	 * so the divergence was invisible for as long as nothing ran this suite
	 * under multisite. Deleting on one site must not touch another site's
	 * entry, and — the half that was actually broken — deleting on THIS site
	 * must actually reach the entry this site wrote.
	 */
	public function test_a_customers_entry_is_scoped_to_one_site_on_a_network(): void
	{
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Per-site cache scoping only has meaning on a network.' );
		}

		// 🔴 KNOWN DEBT, not noise. Creating a site fires this plugin's own
		// listener at mhm-rentiva.php:519, which is still hooked to
		// `wpmu_new_blog` -- deprecated since WordPress 5.1.0 in favour of
		// `wp_initialize_site`. Core only still fires it for back-compat. This
		// is declared rather than fixed here because moving the network
		// activation path is its own change with its own test and browser
		// verification; the declaration keeps the debt visible and named
		// instead of letting a bare deprecation notice fail an unrelated test.
		$this->setExpectedDeprecated( 'wpmu_new_blog' );

		$other_blog = (int) self::factory()->blog->create();

		CacheManager::set_cache( 'customers', 'unit_test', array( 'rows' => 'main' ), 300 );

		switch_to_blog( $other_blog );
		CacheManager::set_cache( 'customers', 'unit_test', array( 'rows' => 'other' ), 300 );
		restore_current_blog();

		$this->assertSame(
			array( 'rows' => 'main' ),
			CacheManager::get_cache( 'customers', 'unit_test' ),
			'Two sites writing the same logical key must not share one entry.'
		);

		CacheManager::delete_cache( 'customers', 'unit_test' );

		$this->assertFalse(
			CacheManager::get_cache( 'customers', 'unit_test' ),
			'Deleting must reach the entry THIS site wrote -- the half that was broken.'
		);

		switch_to_blog( $other_blog );
		$survivor = CacheManager::get_cache( 'customers', 'unit_test' );
		CacheManager::delete_cache( 'customers', 'unit_test' );
		restore_current_blog();

		$this->assertSame(
			array( 'rows' => 'other' ),
			$survivor,
			'And it must not reach across into another site entry.'
		);
	}

	/**
	 * The busiest member of the same class, found by an independent audit
	 * after the first fix landed.
	 *
	 * delete_cache() was the sample, not the class. In Lite it has no
	 * production caller at all -- every live invalidation goes through
	 * clear_cache(), and for a single-key type that ends at
	 * delete_cache_object( $pattern ) with the raw pattern. set_cache() writes
	 * the same type through get_multisite_cache_key(), so on a network the two
	 * spelled different keys and the entry survived its own invalidation.
	 *
	 * system_info is the live pair: SystemInfo.php writes it, and clearing the
	 * settings cache is supposed to drop it.
	 */
	public function test_clear_cache_reaches_a_single_key_type_on_a_network(): void
	{
		CacheManager::set_cache( 'system_info', '', array( 'php' => '8.2' ), 300 );

		$this->assertSame(
			array( 'php' => '8.2' ),
			CacheManager::get_cache( 'system_info' ),
			'Precondition: the entry is readable before the clear.'
		);

		CacheManager::clear_cache( array( 'system_info' ) );

		$this->assertFalse(
			CacheManager::get_cache( 'system_info' ),
			'clear_cache() must delete the key set_cache() wrote, in either mode.'
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
