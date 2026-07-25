<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * A number field declares `min` and `max`. The browser honours them; a POST does
 * not. Every such field must be clamped again on the server, or the declared
 * bound is a claim the plugin does not keep — the same shape as a settings
 * toggle that says a protection is on while nothing enforces it.
 *
 * Found while verifying the Security-tab removal in the browser: saving the
 * System tab with `mhm_rentiva_cache_default_ttl = 53` stored 53, though the
 * field declares `max="24"`. The sweep for that shape across
 * `SettingsSanitizer` turned up three more, one of which had no clamp at all.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize
 */
final class SettingsSanitizerNumericBoundsTest extends WP_UnitTestCase
{
	private function sanitize( string $tab, array $fields ): array
	{
		return SettingsSanitizer::sanitize( array_merge( array( 'current_active_tab' => $tab ), $fields ) );
	}

	/**
	 * Field declares max="24" (CoreSettings::register_settings).
	 */
	public function test_cache_default_ttl_is_clamped_to_its_declared_max(): void
	{
		$result = $this->sanitize( 'system', array( 'mhm_rentiva_cache_default_ttl' => '53' ) );

		$this->assertSame(
			24.0,
			(float) $result['mhm_rentiva_cache_default_ttl'],
			'The field declares max="24"; a POST of 53 must not be stored.'
		);
	}

	public function test_cache_default_ttl_still_clamps_its_min(): void
	{
		$result = $this->sanitize( 'system', array( 'mhm_rentiva_cache_default_ttl' => '0.1' ) );

		$this->assertSame( 0.5, (float) $result['mhm_rentiva_cache_default_ttl'] );
	}

	/**
	 * Both price multipliers declare max="100".
	 */
	public function test_price_multipliers_are_clamped_to_their_declared_max(): void
	{
		$result = $this->sanitize(
			'vehicle',
			array(
				'mhm_rentiva_vehicle_base_price'         => '9999',
				'mhm_rentiva_vehicle_weekend_multiplier' => '9999',
			)
		);

		$this->assertSame( 100.0, (float) $result['mhm_rentiva_vehicle_base_price'] );
		$this->assertSame( 100.0, (float) $result['mhm_rentiva_vehicle_weekend_multiplier'] );
	}

	/**
	 * The seasonal multiplier had no clamp at all, so a negative value went
	 * straight through — and it multiplies a price.
	 */
	public function test_seasonal_multiplier_cannot_be_negative(): void
	{
		update_option(
			'mhm_rentiva_settings',
			array(
				'vehicle_pricing' => array(
					'seasonal_multipliers' => array(
						'summer' => array(
							'multiplier' => 1.5,
							'months'     => array( 6, 7, 8 ),
						),
					),
				),
			)
		);

		$result = $this->sanitize(
			'vehicle',
			array(
				'vehicle_pricing' => array(
					'seasonal_multipliers' => array(
						'summer' => array(
							'multiplier' => '-3',
							'months'     => array( 6, 7, 8 ),
						),
					),
				),
			)
		);

		$stored = $result['vehicle_pricing']['seasonal_multipliers']['summer']['multiplier'] ?? null;

		$this->assertNotNull( $stored, 'The seasonal multiplier disappeared from the sanitized output.' );
		$this->assertGreaterThanOrEqual(
			0.1,
			(float) $stored,
			'A negative seasonal multiplier would produce a negative rental price.'
		);
	}

	/**
	 * The field declares max="5.0".
	 */
	public function test_seasonal_multiplier_is_clamped_to_its_declared_max(): void
	{
		update_option(
			'mhm_rentiva_settings',
			array(
				'vehicle_pricing' => array(
					'seasonal_multipliers' => array(
						'summer' => array(
							'multiplier' => 1.5,
							'months'     => array( 6, 7, 8 ),
						),
					),
				),
			)
		);

		$result = $this->sanitize(
			'vehicle',
			array(
				'vehicle_pricing' => array(
					'seasonal_multipliers' => array(
						'summer' => array(
							'multiplier' => '500',
							'months'     => array( 6, 7, 8 ),
						),
					),
				),
			)
		);

		$stored = $result['vehicle_pricing']['seasonal_multipliers']['summer']['multiplier'] ?? null;

		$this->assertSame( 5.0, (float) $stored, 'The field declares max="5.0".' );
	}
}
