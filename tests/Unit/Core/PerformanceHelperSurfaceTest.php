<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\PerformanceHelper;
use WP_UnitTestCase;

/**
 * Prevent unused query helpers from expanding the shipped review surface.
 */
final class PerformanceHelperSurfaceTest extends WP_UnitTestCase {

	public function test_unreachable_query_helpers_are_not_shipped(): void {
		$this->assertFalse(method_exists(PerformanceHelper::class, 'batch_load_availability_data'));
		$this->assertFalse(method_exists(PerformanceHelper::class, 'optimize_query'));
	}
}
