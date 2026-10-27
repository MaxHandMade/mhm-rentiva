<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsStatus;
use WP_UnitTestCase;

/**
 * Aşama 2 PR D Task 0: status -> tag modifier (R7), label from Status.
 */
final class RvsStatusTest extends WP_UnitTestCase
{
	public function test_maps_every_status(): void
	{
		$map = array(
			'pending_payment' => 'payment',
			'pending'         => 'pending',
			'draft'           => 'pending',
			'confirmed'       => 'confirmed',
			'in_progress'     => 'active',
			'completed'       => 'done',
			'cancelled'       => 'cancelled',
			'refunded'        => 'cancelled',
			'no_show'         => 'cancelled',
		);
		foreach ($map as $status => $modifier) {
			$tag = RvsStatus::for($status);
			$this->assertSame($modifier, $tag['modifier'], $status);
			$this->assertSame(Status::get_label($status), $tag['label'], $status);
		}
	}

	public function test_unknown_status_falls_back_to_pending(): void
	{
		$this->assertSame(
			array(
				'modifier' => 'pending',
				'label'    => 'mystery',
			),
			RvsStatus::for('mystery')
		);
	}
}
