<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Pricing;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * PR 0: the availability answer is cached with its total price. Turning the
 * season multiplier on must not keep serving the old total.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\Cache
 */
final class AvailabilityPriceCacheTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	private function save(bool $season): void
	{
		$input = array(
			'current_active_tab'                    => 'vehicle',
			'mhmrentiva_vehicle_base_price'         => '1.0',
			'mhmrentiva_vehicle_weekend_multiplier' => '1.2',
			'vehicle_pricing'                       => array(
				'seasonal_multipliers' => array(
					'summer' => array( 'multiplier' => '1.3' ),
				),
			),
		);
		if ($season) {
			$input['mhmrentiva_vehicle_seasonal_pricing'] = '1';
		}
		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize($input));
	}

	public function test_saving_pricing_settings_refreshes_cached_total(): void
	{
		$vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', '1000');

		$this->save(false);
		$first = Util::check_availability($vehicle_id, '2026-07-13', '10:00', '2026-07-16', '10:00');
		$this->assertTrue($first['ok'], wp_json_encode($first));
		$this->assertEqualsWithDelta(3000.0, (float) $first['total_price'], 0.001);

		$this->save(true);
		$second = Util::check_availability($vehicle_id, '2026-07-13', '10:00', '2026-07-16', '10:00');

		$this->assertEqualsWithDelta(3900.0, (float) $second['total_price'], 0.001);
	}

	public function test_changing_the_daily_price_refreshes_cached_total(): void
	{
		$vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', '1000');
		$this->save(false);

		$first = Util::check_availability($vehicle_id, '2026-07-13', '10:00', '2026-07-16', '10:00');
		$this->assertEqualsWithDelta(3000.0, (float) $first['total_price'], 0.001);

		update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', '1200');
		$second = Util::check_availability($vehicle_id, '2026-07-13', '10:00', '2026-07-16', '10:00');

		$this->assertEqualsWithDelta(3600.0, (float) $second['total_price'], 0.001, 'The cached total must not outlive a price edit.');
	}
}
