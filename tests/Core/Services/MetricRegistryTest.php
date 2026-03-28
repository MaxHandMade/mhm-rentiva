<?php

namespace MHMRentiva\Tests\Core\Services;

use MHMRentiva\Core\Services\Metrics\MetricInterface;
use MHMRentiva\Core\Services\Metrics\MetricRegistry;
use WP_UnitTestCase;

/**
 * Metric registry tests.
 */
class MetricRegistryTest extends WP_UnitTestCase
{
	public function test_registry_returns_known_metric_handlers(): void
	{
		$all = MetricRegistry::all();

		$this->assertArrayHasKey('total_bookings', $all);
		$this->assertArrayHasKey('upcoming_pickups', $all);
		$this->assertArrayHasKey('unread_messages', $all);
		$this->assertArrayHasKey('revenue_7d', $all);

		foreach (array_keys($all) as $metric) {
			$handler = MetricRegistry::get($metric);
			$this->assertInstanceOf(MetricInterface::class, $handler);
		}
	}

	public function test_registry_returns_null_for_unknown_metric(): void
	{
		$this->assertNull(MetricRegistry::get('unknown_metric'));
	}
}

