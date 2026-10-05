<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * TEMPORARY: remove in Task 6 -- VehicleCard duplicate typography ids.
 *
 * VehicleCardWidget registers four typography groups under one id, so Elementor
 * raises a _doing_it_wrong for Controls_Manager::add_control_to_stack once per
 * process, from whichever test builds the card first. That makes a test pass or
 * fail by run order. Call this after building the widget and reading its
 * controls; it drops only that one notice, every other one still fails the test.
 */
trait IgnoresVehicleCardTypographyNotice
{
	protected function forget_vehicle_card_typography_notice(): void
	{
		unset($this->caught_doing_it_wrong['Elementor\Controls_Manager::add_control_to_stack']);
	}
}
