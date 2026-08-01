<?php

namespace MHMRentiva\Tests\Core\Services;

use MHMRentiva\Core\Services\Metrics\MetricInterface;
use MHMRentiva\Core\Services\Metrics\MetricRegistry;
use MHMRentiva\Core\Services\Metrics\TotalBookingsMetric;
use WP_UnitTestCase;

/**
 * Metric registry tests.
 */
class MetricRegistryTest extends WP_UnitTestCase
{
	protected function tearDown(): void
	{
		// MetricRegistry memoises its map in a static; drop it so a filter added by
		// one test cannot leak into the next.
		$map = new \ReflectionProperty(MetricRegistry::class, 'map');
		$map->setAccessible(true);
		$map->setValue(null, null);

		parent::tearDown();
	}

	public function test_registry_returns_core_metric_handlers(): void
	{
		$all = MetricRegistry::all();

		$this->assertArrayHasKey('total_bookings', $all);
		$this->assertArrayHasKey('upcoming_pickups', $all);

		foreach (array_keys($all) as $metric) {
			$handler = MetricRegistry::get($metric);
			$this->assertInstanceOf(MetricInterface::class, $handler);
		}
	}

	/**
	 * The registry must not hardcode Pro metrics.
	 *
	 * unread_messages/revenue_7d/available_balance/pending_balance/total_paid_out
	 * and the vendor_* analytics metrics resolve only under the 'vendor' KPI
	 * context and read the messaging tables or the financial ledger — all of which
	 * ship with Pro. Naming them here would put a compile-time reference to an
	 * absent class in the Lite package. Pro registers them via the
	 * 'mhmrentiva_registered_metrics' filter instead (see the seam test below).
	 */
	public function test_registry_does_not_hardcode_pro_metrics(): void
	{
		$all = MetricRegistry::all();

		foreach (
			array(
				'unread_messages',
				'revenue_7d',
				'available_balance',
				'pending_balance',
				'total_paid_out',
				'vendor_revenue_30d',
				'vendor_growth_7d',
				'vendor_avg_booking_value',
			) as $pro_metric
		) {
			$this->assertArrayNotHasKey($pro_metric, $all, "$pro_metric must be registered by Pro, not by the core registry");
		}
	}

	/**
	 * Seam contract: the filter Pro uses to add its metrics back must work.
	 */
	public function test_pro_can_register_a_metric_through_the_filter(): void
	{
		add_filter(
			'mhmrentiva_registered_metrics',
			static function (array $metrics): array {
				$metrics['seam_probe'] = TotalBookingsMetric::class;
				return $metrics;
			}
		);

		$this->assertArrayHasKey('seam_probe', MetricRegistry::all());
		$this->assertInstanceOf(MetricInterface::class, MetricRegistry::get('seam_probe'));
	}

	public function test_registry_returns_null_for_unknown_metric(): void
	{
		$this->assertNull(MetricRegistry::get('unknown_metric'));
	}
}
