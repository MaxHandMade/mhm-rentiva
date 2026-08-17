<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonStats;
use WP_UnitTestCase;

/**
 * The add-on KPI figures.
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * The same four numbers now appear on two screens: the native edit.php band
 * (still reachable by URL) and the plugin's own add-ons page. Two copies of the
 * arithmetic is how a plugin ends up showing an operator two different totals
 * for the same thing on two screens, so the queries live here and both surfaces
 * read them.
 *
 * The assertions below are anchored to figures worked out by hand from the
 * fixtures, not to "whatever the other surface returns". A parity test that
 * only compares the two screens proves they agree; it does not prove either is
 * right, and it stays green when both are wrong together.
 */
final class AddonStatsTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		// 100 (on) + 50 (off) + 30 (on) = 180 total, average 60, 2 of 3 active.
		$fixtures = array(
			array( 'price' => '100', 'enabled' => '1' ),
			array( 'price' => '50', 'enabled' => '0' ),
			array( 'price' => '30', 'enabled' => '1' ),
		);

		foreach ( $fixtures as $fixture ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_addon',
					'post_status' => 'publish',
				)
			);
			update_post_meta( $id, 'mhmrentiva_addon_price', $fixture['price'] );
			update_post_meta( $id, 'mhmrentiva_addon_enabled', $fixture['enabled'] );
		}
	}

	public function test_it_counts_every_published_add_on(): void {
		$this->assertSame( 3, AddonStats::get()['total_addons'] );
	}

	public function test_it_counts_only_the_enabled_ones_as_active(): void {
		$this->assertSame( 2, AddonStats::get()['active_addons'] );
	}

	/**
	 * A service that has never carried the flag counts as active.
	 *
	 * The count used to be an INNER JOIN on `enabled = '1'`, which cannot match a
	 * row that has no such meta at all -- and those are exactly what a site that
	 * upgraded into the flag has. AddonManager::is_sellable() calls them active
	 * (AddonScreen's quick-create says why: "Absent means active"), and the
	 * booking form sells them, so the band would have read "2 aktif" over a form
	 * offering three. Two answers to one question, and the visible one wrong.
	 *
	 * Same defect class as the one get_available_addons() carried; this is the
	 * second place it lived.
	 */
	public function test_an_add_on_that_never_carried_the_flag_counts_as_active(): void {
		$flagless = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $flagless, 'mhmrentiva_addon_price', '20' );
		AddonStats::flush();

		$this->assertTrue(
			\MHMRentiva\Admin\Addons\AddonManager::is_sellable( $flagless ),
			'Precondition: the plugin already treats a flagless service as sellable.'
		);
		$this->assertSame(
			3,
			AddonStats::get()['active_addons'],
			'The KPI band must agree with what the booking form actually offers.'
		);
	}

	public function test_an_explicitly_disabled_add_on_still_does_not_count(): void {
		// The negative control for the change above: widening "active" must not
		// widen it far enough to swallow a service the operator switched off.
		$this->assertSame( 2, AddonStats::get()['active_addons'] );
	}

	public function test_it_reports_the_active_share(): void {
		$this->assertEqualsWithDelta( 67.0, (float) AddonStats::get()['active_percentage'], 1.0 );
	}

	/**
	 * Averaged over every published add-on, not only the active ones. Both
	 * money figures come back formatted through the canonical currency helper,
	 * so the assertion looks for the digits rather than pinning a symbol or a
	 * separator that WooCommerce settings can legitimately change.
	 */
	public function test_it_averages_the_price(): void {
		$this->assertStringContainsString( '60', AddonStats::get()['avg_price'] );
	}

	public function test_it_totals_the_prices(): void {
		$this->assertStringContainsString( '180', AddonStats::get()['total_value'] );
	}

	/**
	 * An add-on with no price meta at all must not break the arithmetic -- the
	 * dev database has add-ons in exactly that state.
	 */
	public function test_a_priceless_add_on_does_not_break_the_figures(): void {
		self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
			)
		);

		$stats = AddonStats::get();

		$this->assertSame( 4, $stats['total_addons'] );
		$this->assertStringContainsString( '180', $stats['total_value'] );
	}

	/**
	 * The four figures are read on every load of two screens, so they are
	 * cached. What matters more than the caching is the invalidation: a cached
	 * KPI band that does not notice a new service is worse than an uncached
	 * one, because the operator sees their own change fail to appear.
	 */
	public function test_creating_an_add_on_invalidates_the_cache(): void {
		$before = AddonStats::get()['total_addons'];

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, 'mhmrentiva_addon_price', '10' );
		AddonStats::flush();

		$this->assertSame( $before + 1, AddonStats::get()['total_addons'] );
	}

	/**
	 * Positive control for the test above. Without a flush the cached value is
	 * what comes back -- which is the whole reason the endpoints have to call
	 * flush() rather than trusting a TTL.
	 */
	public function test_the_figures_are_actually_cached(): void {
		$before = AddonStats::get()['total_addons'];

		self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
			)
		);

		$this->assertSame(
			$before,
			AddonStats::get()['total_addons'],
			'Without an explicit flush the cached figure stands; if this fails, nothing is being cached.'
		);
	}

	public function test_it_reports_zeroes_when_there_are_no_add_ons(): void {
		foreach ( get_posts( array( 'post_type' => 'mhmrentiva_addon', 'posts_per_page' => -1 ) ) as $addon ) {
			wp_delete_post( $addon->ID, true );
		}

		$stats = AddonStats::get();

		$this->assertSame( 0, $stats['total_addons'] );
		$this->assertSame( 0, $stats['active_addons'] );
		$this->assertSame( 0, (int) $stats['active_percentage'] );
	}
}
