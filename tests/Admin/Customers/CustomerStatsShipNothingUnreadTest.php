<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomersOptimizer;
use WP_UnitTestCase;

/**
 * The stat payload and the screen that reads it are a contract.
 *
 * The Customers screen reads five fields. The payload shipped seven. The two
 * extras were not merely tidy-up:
 *
 *   `active`  counted accounts registered in the last thirty days -- despite
 *             the name -- and nothing displayed it. Measured across both the
 *             Lite and Pro trees.
 *   `average` came from calculate_monthly_average(), a whole extra query on
 *             every Customers page load, feeding a payload key
 *             (`monthly_avg`) that no component reads. It also still selected
 *             its population the old narrow way, through an INNER JOIN on
 *             `_mhmrentiva_customer_email`, so it was the last survivor of the
 *             bug the rest of this method was fixed for.
 *
 * This test is the contract rather than a cleanup receipt: adding a field here
 * without a reader turns it red, which is the only thing that stops the payload
 * growing a third dead entry.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomersOptimizer::get_customer_stats_optimized
 */
final class CustomerStatsShipNothingUnreadTest extends WP_UnitTestCase
{
	/**
	 * Exactly what src-react/admin/customers reads, via CustomersPage's
	 * wp_localize_script mapping. Grep `stats\.` under that directory to check.
	 *
	 * @var list<string>
	 */
	private const READ_BY_THE_SCREEN = array(
		'total',
		'new',
		'average_trend',
		'active_90d',
		'avg_spend',
	);

	public function setUp(): void
	{
		parent::setUp();
		CustomersOptimizer::clear_cache();
	}

	public function tearDown(): void
	{
		CustomersOptimizer::clear_cache();
		parent::tearDown();
	}

	public function test_the_payload_ships_nothing_the_screen_does_not_read(): void
	{
		$keys = array_keys( CustomersOptimizer::get_customer_stats_optimized() );

		sort( $keys );
		$expected = self::READ_BY_THE_SCREEN;
		sort( $expected );

		$this->assertSame(
			$expected,
			$keys,
			'Every field here is localised to the browser on every Customers page load. One with no reader is a query and a payload nobody asked for.'
		);
	}

	/**
	 * The other half: the screen must not lose a field it does read. An
	 * exact-set assertion catches both directions, and this names the
	 * consequence so a failure is not mistaken for pedantry.
	 */
	public function test_every_field_the_screen_reads_is_present(): void
	{
		$stats = CustomersOptimizer::get_customer_stats_optimized();

		foreach ( self::READ_BY_THE_SCREEN as $field ) {
			$this->assertArrayHasKey(
				$field,
				$stats,
				"The Customers screen renders {$field}; dropping it leaves a card blank."
			);
		}
	}

	/**
	 * The measurable half of the change.
	 *
	 * calculate_monthly_average() ran its own aggregate on every uncached call,
	 * for a value nothing displayed. Counting queries is the only way to state
	 * that as a fact rather than an intention.
	 */
	public function test_the_stats_read_does_not_grow_past_its_measured_cost(): void
	{
		global $wpdb;

		CustomersOptimizer::clear_cache();

		$before = $wpdb->num_queries;
		CustomersOptimizer::get_customer_stats_optimized();
		$spent = $wpdb->num_queries - $before;

		// Measured, not chosen: this read cost 8 queries before
		// calculate_monthly_average() was removed and costs 7 after. The bound
		// is the measurement, so it can only be raised by someone who has
		// looked at what they added -- which is the entire point of having it.
		//
		// A ceiling rather than an equality: spending fewer is always welcome,
		// and calculate_trend() still accounts for most of what is left.
		$this->assertLessThanOrEqual(
			7,
			$spent,
			'The stats read grew a query. If the new one feeds a field the screen displays, raise this bound deliberately; if it does not, it should not exist.'
		);
	}
}
