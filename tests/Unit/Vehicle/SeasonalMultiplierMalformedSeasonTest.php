<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Vehicle;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings;
use MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings;
use WP_UnitTestCase;

/**
 * T8 final review I-1: Görev 9 closed the from-scratch case of the
 * seasonal-multiplier TypeError, not the class.
 *
 * The READ path -- VehiclePricingSettings::get_seasonal_multiplier_for_month()
 * -- did `in_array( $month, $season['months'] )` with no shape check. A season
 * entry carrying only a 'multiplier' key (no 'months') therefore reached
 * in_array() with null as its haystack: `TypeError: in_array(): Argument #2
 * ($haystack) must be of type array, null given` -- thrown on the PUBLIC
 * BOOKING FORM, because BookingForm.php:1123 calls
 * get_seasonal_multiplier_for_date() once per rental day whenever the
 * mhmrentiva_vehicle_seasonal_pricing master switch is on.
 *
 * Two writers could produce that shape:
 *   1. SettingsSanitizer::sanitize_vehicle_pricing_settings(), which accepted
 *      ANY sanitize_key()-able slug and wrote only ['multiplier'] under it.
 *   2. sanitize_programmatic_update(), which passes array values through
 *      untouched once 'vehicle_pricing' is a known top-level key.
 *
 * Writer 1 is now constrained to the seasons that actually exist (which is
 * exactly what VehicleManagementSettings::render_seasonal_multipliers_field()
 * can ever post -- it renders one input per EXISTING key and nothing else).
 * Writer 2 cannot be constrained without breaking its stated contract, so the
 * READ path carries the defensive guard: whatever ends up in the option, the
 * public booking form must degrade to the neutral 1.0 multiplier rather than
 * fatal.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer
 */
final class SeasonalMultiplierMalformedSeasonTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( 'mhmrentiva_settings' );
		parent::tearDown();
	}

	/**
	 * Writes a raw vehicle_pricing block straight into the option, bypassing
	 * the sanitizer -- the shape sanitize_programmatic_update() lets through.
	 *
	 * @param array<string, mixed> $seasonal_multipliers Raw seasonal block.
	 */
	private function store_seasonal( $seasonal_multipliers ): void {
		update_option(
			'mhmrentiva_settings',
			array(
				'vehicle_pricing' => array( 'seasonal_multipliers' => $seasonal_multipliers ),
			)
		);
	}

	/**
	 * THE FINDING. A bogus season slug with no 'months' key must not fatal the
	 * read path.
	 */
	public function test_season_without_months_does_not_fatal_the_read_path(): void {
		$this->store_seasonal(
			array(
				'foo' => array( 'multiplier' => 1.5 ),
			)
		);

		$this->assertSame(
			1.0,
			VehiclePricingSettings::get_seasonal_multiplier_for_date( '2026-07-15' ),
			'A season entry with no months must be skipped, leaving the neutral multiplier.'
		);
	}

	/**
	 * Same defect one level out: get_seasonal_multiplier_for_month() is the
	 * method that actually holds the in_array(), and Pro/third-party code can
	 * call it directly.
	 */
	public function test_season_without_months_does_not_fatal_the_month_reader(): void {
		$this->store_seasonal(
			array(
				'foo' => array( 'multiplier' => 1.5 ),
			)
		);

		$this->assertSame( 1.0, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ) );
	}

	/**
	 * KANUN 0 -- the malformed entry is not one shape, it is a class. Every
	 * wrong type for 'months', plus a non-array season entry, plus a 'months'
	 * that is a scalar, must all degrade rather than fatal.
	 *
	 * @dataProvider malformed_season_provider
	 *
	 * @param mixed $season Malformed season entry.
	 */
	public function test_malformed_season_shapes_all_degrade_to_neutral( $season ): void {
		$this->store_seasonal( array( 'foo' => $season ) );

		$this->assertSame( 1.0, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function malformed_season_provider(): array {
		return array(
			'months missing'      => array( array( 'multiplier' => 1.5 ) ),
			'months null'         => array( array( 'months' => null, 'multiplier' => 1.5 ) ),
			'months a string'     => array( array( 'months' => '7', 'multiplier' => 1.5 ) ),
			'months an int'       => array( array( 'months' => 7, 'multiplier' => 1.5 ) ),
			'season not an array' => array( 'summer' ),
			'season is null'      => array( null ),
			'season empty array'  => array( array() ),
		);
	}

	/**
	 * The whole seasonal block being the wrong type must not fatal either --
	 * get_seasonal_multipliers() declares `: array`, so a scalar there is a
	 * return-type TypeError one frame earlier than the in_array().
	 *
	 * Note the expected value: an unusable block falls back to the DEFAULT
	 * seasons (the `??` branch that already existed for a missing block), so
	 * July resolves to summer's 1.3. That is deliberately different from the
	 * per-entry guard above, which skips one broken season and leaves the rest
	 * of the stored list authoritative.
	 */
	public function test_non_array_seasonal_block_falls_back_to_defaults_instead_of_fataling(): void {
		$this->store_seasonal( 'not-an-array' );

		$this->assertSame( 1.3, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ), 'July is summer in the defaults.' );
		$this->assertSame( 0.8, VehiclePricingSettings::get_seasonal_multiplier_for_month( 1 ), 'January is winter in the defaults.' );
	}

	/**
	 * Same one level up: `vehicle_pricing` itself not being an array must not
	 * blow get_settings()'s own `: array` return type.
	 */
	public function test_non_array_vehicle_pricing_block_does_not_fatal(): void {
		update_option( 'mhmrentiva_settings', array( 'vehicle_pricing' => 'not-an-array' ) );

		$this->assertSame( 1.3, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ) );
		$this->assertIsArray( VehiclePricingSettings::get_settings() );
	}

	/**
	 * A well-formed season stored alongside a malformed one must still win --
	 * the guard skips the broken entry, it does not abandon the loop.
	 */
	public function test_a_valid_season_still_matches_when_a_broken_one_precedes_it(): void {
		$this->store_seasonal(
			array(
				'foo'    => array( 'multiplier' => 9.9 ),
				'summer' => array(
					'name'       => 'Summer',
					'months'     => array( 6, 7, 8 ),
					'multiplier' => 1.3,
				),
			)
		);

		$this->assertSame( 1.3, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ) );
	}

	/**
	 * A matching season whose 'multiplier' is missing or a numeric string must
	 * not blow the `: float` return type under declare(strict_types=1).
	 */
	public function test_matching_season_with_odd_multiplier_returns_a_float(): void {
		$this->store_seasonal(
			array(
				'summer' => array(
					'months' => array( 6, 7, 8 ),
				),
			)
		);
		$this->assertSame( 1.0, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ) );

		$this->store_seasonal(
			array(
				'summer' => array(
					'months'     => array( 6, 7, 8 ),
					'multiplier' => '1.3',
				),
			)
		);
		$this->assertSame( 1.3, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ) );
	}

	/**
	 * The defaults must be untouched by the guard -- a real season still
	 * returns its real multiplier.
	 */
	public function test_default_seasons_are_unaffected(): void {
		delete_option( 'mhmrentiva_settings' );

		$this->assertSame( 1.3, VehiclePricingSettings::get_seasonal_multiplier_for_month( 7 ), 'July is summer (1.3).' );
		$this->assertSame( 0.8, VehiclePricingSettings::get_seasonal_multiplier_for_month( 1 ), 'January is winter (0.8).' );
		$this->assertSame( 1.0, VehiclePricingSettings::get_seasonal_multiplier_for_month( 4 ), 'April is spring (1.0).' );
		$this->assertSame( 1.1, VehiclePricingSettings::get_seasonal_multiplier_for_month( 10 ), 'October is autumn (1.1).' );
	}

	/**
	 * WRITER SIDE. The sanitizer must not be able to introduce an unknown
	 * season at all. render_seasonal_multipliers_field() renders exactly one
	 * input per EXISTING season key, so no legitimate POST can name a key that
	 * is not already stored -- constraining to the known set can never reject
	 * real input.
	 */
	public function test_sanitizer_drops_an_unknown_season_slug(): void {
		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'vehicle',
				'vehicle_pricing'    => array(
					'seasonal_multipliers' => array(
						'foo' => array( 'multiplier' => '1.75' ),
					),
				),
			)
		);

		$this->assertArrayNotHasKey(
			'foo',
			$result['vehicle_pricing']['seasonal_multipliers'] ?? array(),
			'An unknown season slug must never be written -- no rendered field can produce one.'
		);
	}

	/**
	 * And the four real seasons must still round-trip -- the constraint must
	 * not turn the save path into a no-op.
	 */
	public function test_sanitizer_still_writes_every_known_season(): void {
		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'vehicle',
				'vehicle_pricing'    => array(
					'seasonal_multipliers' => array(
						'spring' => array( 'multiplier' => '1.25' ),
						'summer' => array( 'multiplier' => '1.60' ),
						'autumn' => array( 'multiplier' => '1.05' ),
						'winter' => array( 'multiplier' => '0.70' ),
					),
				),
			)
		);

		$seasonal = $result['vehicle_pricing']['seasonal_multipliers'] ?? array();

		$this->assertSame( 1.25, $seasonal['spring']['multiplier'] ?? null );
		$this->assertSame( 1.6, $seasonal['summer']['multiplier'] ?? null );
		$this->assertSame( 1.05, $seasonal['autumn']['multiplier'] ?? null );
		$this->assertSame( 0.7, $seasonal['winter']['multiplier'] ?? null );
	}

	/**
	 * FIX ROUND 2 / F-1(a) — THE MIDDLE LEVEL.
	 *
	 * The first pass guarded the level ABOVE (`vehicle_pricing` not an array)
	 * and the level BELOW (the read path), and left the one between them:
	 * `vehicle_pricing` can be a perfectly good array whose
	 * `seasonal_multipliers` is a scalar. The write
	 * `$current_pricing['seasonal_multipliers'][$key]['multiplier'] = …` then
	 * fataled on a NORMAL Save of Settings > Vehicle Management -- no crafted
	 * POST needed, because the RENDERER masks the corruption (it falls back to
	 * the four defaults and draws its four fields), so the admin sees a normal
	 * screen, presses Save, and the tab dies.
	 *
	 * @dataProvider unusable_block_provider
	 *
	 * @param mixed $block Unusable seasonal_multipliers block.
	 */
	public function test_saving_the_vehicle_tab_does_not_fatal_on_an_unusable_stored_block( $block ): void {
		$this->store_seasonal( $block );

		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'vehicle',
				'vehicle_pricing'    => array(
					'seasonal_multipliers' => array(
						'spring' => array( 'multiplier' => '1.50' ),
					),
				),
			)
		);

		$seasonal = $result['vehicle_pricing']['seasonal_multipliers'] ?? null;

		$this->assertIsArray( $seasonal, 'An unusable block must be healed to an array, not written through.' );
		$this->assertSame( 1.5, $seasonal['spring']['multiplier'] ?? null, 'The posted value must still land.' );
		$this->assertSame(
			array( 3, 4, 5 ),
			$seasonal['spring']['months'] ?? null,
			'Healing must restore the DEFAULTS -- the same fallback get_seasonal_multipliers() uses -- so spring keeps its months and the read path cannot meet a months-less season.'
		);
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function unusable_block_provider(): array {
		return array(
			'block is a string' => array( 'not-an-array' ),
			'block is an int'   => array( 7 ),
			'block is true'     => array( true ),
			'block is null'     => array( null ),
		);
	}

	/**
	 * FIX ROUND 2 / F-1(a), second half. A stored `vehicle_pricing` with NO
	 * seasonal block at all is the same hole pointing the other way: the
	 * renderer still draws four fields (it falls back to the defaults), the
	 * admin saves, and the writer used to create `['spring']['multiplier']`
	 * inside a block that had no spring entry -- manufacturing exactly the
	 * months-less season I-1 exists to prevent, through the REAL UI this time.
	 */
	public function test_saving_cannot_create_a_months_less_season_when_the_block_is_absent(): void {
		update_option(
			'mhmrentiva_settings',
			array( 'vehicle_pricing' => array( 'currency_settings' => array( 'default_currency' => 'USD' ) ) )
		);

		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'vehicle',
				'vehicle_pricing'    => array(
					'seasonal_multipliers' => array(
						'summer' => array( 'multiplier' => '1.90' ),
					),
				),
			)
		);

		$summer = $result['vehicle_pricing']['seasonal_multipliers']['summer'] ?? null;

		$this->assertIsArray( $summer );
		$this->assertSame( 1.9, $summer['multiplier'] ?? null );
		$this->assertArrayHasKey( 'months', $summer, 'A season written by the real form must never lack its months.' );
		$this->assertSame( array( 6, 7, 8 ), $summer['months'] );
	}

	/**
	 * FIX ROUND 2 / F-1(b) — THE RENDERER, the third consumer the first pass
	 * did not enumerate. A season ENTRY stored as a string fataled
	 * `esc_html( $season['name'] )` and took the whole Vehicle Management tab
	 * down on render.
	 *
	 * @dataProvider unrenderable_entry_provider
	 *
	 * @param mixed $entry Unrenderable season entry.
	 */
	public function test_rendering_the_seasonal_field_does_not_fatal_on_an_unrenderable_entry( $entry ): void {
		$this->store_seasonal(
			array(
				'foo'    => $entry,
				'summer' => array(
					'name'        => 'Summer',
					'months'      => array( 6, 7, 8 ),
					'multiplier'  => 1.3,
					'description' => 'High season',
				),
			)
		);

		ob_start();
		VehicleManagementSettings::render_seasonal_multipliers_field();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Summer', $html, 'The good season must still render -- the guard skips one entry, it does not abandon the loop.' );
		$this->assertStringContainsString( 'seasonal_multipliers][summer][multiplier]', $html, 'Its field must keep the name the sanitizer expects.' );
		$this->assertStringNotContainsString( 'seasonal_multipliers][foo][multiplier]', $html, 'An unrenderable entry must not produce a field.' );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function unrenderable_entry_provider(): array {
		return array(
			'entry is a string' => array( 'summer' ),
			'entry is an int'   => array( 7 ),
			'entry is null'     => array( null ),
			'entry is true'     => array( true ),
		);
	}

	/**
	 * An ARRAY entry missing its optional keys is renderable -- it must render
	 * without a notice and without inventing a field name, because that is the
	 * shape the old sanitizer actually manufactured and some install may carry
	 * it.
	 */
	public function test_rendering_survives_an_array_entry_missing_its_keys(): void {
		$this->store_seasonal( array( 'spring' => array( 'multiplier' => 1.2 ) ) );

		ob_start();
		VehicleManagementSettings::render_seasonal_multipliers_field();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'seasonal_multipliers][spring][multiplier]', $html );
		$this->assertStringContainsString( 'value="1.2"', $html );
	}

	/**
	 * The constraint reads the STORED season set, not a hardcoded list, so an
	 * install that already carries an extra season keeps being able to save it
	 * -- and that stored entry still has its months, so it cannot reintroduce
	 * the TypeError.
	 */
	public function test_sanitizer_accepts_a_season_that_already_exists_in_the_option(): void {
		update_option(
			'mhmrentiva_settings',
			array(
				'vehicle_pricing' => array(
					'seasonal_multipliers' => array(
						'monsoon' => array(
							'name'       => 'Monsoon',
							'months'     => array( 6, 7 ),
							'multiplier' => 1.4,
						),
					),
				),
			)
		);

		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'vehicle',
				'vehicle_pricing'    => array(
					'seasonal_multipliers' => array(
						'monsoon' => array( 'multiplier' => '2.0' ),
					),
				),
			)
		);

		$this->assertSame(
			2.0,
			$result['vehicle_pricing']['seasonal_multipliers']['monsoon']['multiplier'] ?? null,
			'A season already present in the option is a known season -- the allowlist is derived, not hardcoded.'
		);
		$this->assertSame(
			array( 6, 7 ),
			$result['vehicle_pricing']['seasonal_multipliers']['monsoon']['months'] ?? null,
			'Its months must survive the partial save.'
		);
	}
}
