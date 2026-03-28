<?php

namespace MHMRentiva\Tests\Core\Services;

use MHMRentiva\Core\Services\TrendService;
use WP_UnitTestCase;

/**
 * Generic trend service tests.
 */
class TrendServiceGenericTest extends WP_UnitTestCase
{
	public function test_unknown_metric_returns_empty_trend_payload(): void
	{
		$payload = TrendService::get_trend('unknown_metric', 'customer');

		$this->assertSame(0, (int) ($payload['total'] ?? 0));
		$this->assertSame(0, (int) ($payload['current'] ?? 0));
		$this->assertSame(0, (int) ($payload['previous'] ?? 0));
		$this->assertSame(0, (int) ($payload['trend'] ?? 0));
		$this->assertSame('neutral', (string) ($payload['direction'] ?? ''));
	}
}

