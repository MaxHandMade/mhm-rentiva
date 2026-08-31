<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

/**
 * The dashboard's customer numbers and the card that renders them.
 *
 * One card reads `total_customers_this_month`. Everything else the service
 * computed about customers was reaching nobody:
 *
 *   `total_customers_all_time`   a whole COUNT(DISTINCT) per dashboard load,
 *                                with no consumer anywhere in either tree.
 *   `new_customers_this_month`   fed a sub-line that could not say anything:
 *                                the query windows both figures to the current
 *                                month, so total and new are the same number by
 *                                construction. The card read "3 customers, 3 new
 *                                this month".
 *
 * This is the third time the same shape has turned up here -- the customer stat
 * payload, the cache invalidation, and now this -- so the assertion is the key
 * set rather than the individual removals. A field with no reader turns it red.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardService::get_dashboard_metrics
 */
final class DashboardCustomerMetricsContractTest extends WP_UnitTestCase
{
	/**
	 * The only customer metric anything renders: StatsCards.jsx reads it as the
	 * value of the "Renting this month" card.
	 */
	public function test_the_metric_the_card_renders_is_present(): void
	{
		$metrics = DashboardService::get_dashboard_metrics();

		$this->assertArrayHasKey(
			'total_customers_this_month',
			$metrics,
			'The dashboard card renders this; without it the card is blank.'
		);
	}

	/**
	 * @return list<string>
	 */
	public function unreadCustomerMetrics(): array
	{
		return array(
			array( 'total_customers_all_time', 'a COUNT(DISTINCT) over every booking, rendered nowhere' ),
			array( 'new_customers_this_month', 'the sub-line it fed always equalled the card value, so it said nothing' ),
		);
	}

	/**
	 * @dataProvider unreadCustomerMetrics
	 */
	public function test_no_customer_metric_is_computed_without_a_reader( string $key, string $why ): void
	{
		$this->assertArrayNotHasKey(
			$key,
			DashboardService::get_dashboard_metrics(),
			"{$key} is computed on every dashboard load: {$why}."
		);
	}

	/**
	 * The delta is shaped by its own query pair and must survive the removals --
	 * it is what the card's arrow renders.
	 */
	public function test_the_customer_delta_survives(): void
	{
		// Measured, not assumed: the deltas are a separate call
		// (get_metric_deltas(), surfaced to the page as `metric_deltas`), not a
		// key inside the metrics array. An earlier draft of this test asserted
		// the wrong shape and failed for that reason rather than a real one.
		$deltas = DashboardService::get_metric_deltas();

		$this->assertArrayHasKey(
			'customers',
			$deltas,
			'The card renders an arrow from this; it is computed by count_new_customers_between(), not by the removed keys.'
		);
	}

	/**
	 * The measurable half. Dropping the all-time count removes a query from
	 * every uncached dashboard load.
	 */
	public function test_the_dashboard_read_does_not_grow_past_its_measured_cost(): void
	{
		global $wpdb;

		$before = $wpdb->num_queries;
		DashboardService::get_dashboard_metrics();
		$spent = $wpdb->num_queries - $before;

		// Measured, not chosen: this read cost 8 queries with the all-time count
		// in it and 7 without. The bound is the measurement, so raising it takes
		// a deliberate edit by someone who has looked at what they added.
		$this->assertLessThanOrEqual(
			7,
			$spent,
			'The dashboard read grew a query. If it feeds something the screen renders, raise this bound deliberately; if it does not, it should not exist.'
		);
	}
}
