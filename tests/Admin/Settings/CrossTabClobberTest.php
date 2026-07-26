<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Saving one settings tab must not reset a field that lives on another.
 *
 * The sanitizer runs one tab's branch and merges the result over the stored
 * values, so any key it names but the submitted form does not contain is
 * written with its default. Two Frontend-tab fields — "Vehicles per page" and
 * "Default sort order" — were named in the Vehicle-tab branch, where neither is
 * rendered. Changing the tax rate on the Vehicle tab therefore silently reset
 * both, on a screen that shows neither.
 *
 * The same trap was already understood two lines further down, where the card
 * and detail field lists are wrapped in `isset()` with a comment explaining
 * exactly this. The guard simply was not extended upwards.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize
 */
final class CrossTabClobberTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option( 'mhm_rentiva_settings' );
		parent::tearDown();
	}

	public function test_saving_the_vehicle_tab_keeps_the_frontend_page_size(): void
	{
		update_option(
			'mhm_rentiva_settings',
			array(
				'mhm_rentiva_vehicle_cards_per_page' => 24,
				'mhm_rentiva_vehicle_default_sort'   => 'year_desc',
			)
		);

		// A Vehicle-tab save. Neither frontend field is present in the POST,
		// because neither is rendered on that tab.
		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab'            => 'vehicle',
				'mhm_rentiva_vehicle_tax_rate'  => '18',
			)
		);

		// sanitize() returns the stored settings with this tab's batch merged over
		// them, so the keys are present either way. What matters is the VALUE: the
		// administrator's choice has to survive a save on a screen that does not
		// show the field.
		$this->assertSame(
			24,
			(int) $result['mhm_rentiva_vehicle_cards_per_page'],
			'A Vehicle-tab save reset a Frontend-tab field it never displayed.'
		);
		$this->assertSame(
			'year_desc',
			$result['mhm_rentiva_vehicle_default_sort'],
			'A Vehicle-tab save reset a Frontend-tab field it never displayed.'
		);
	}

	/**
	 * And the Frontend tab, which does render them, must still save them.
	 */
	public function test_the_frontend_tab_still_saves_both_fields(): void
	{
		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab'                 => 'frontend',
				'mhm_rentiva_vehicle_cards_per_page' => '24',
				'mhm_rentiva_vehicle_default_sort'   => 'year_desc',
			)
		);

		$this->assertSame( 24, $result['mhm_rentiva_vehicle_cards_per_page'] );
		$this->assertSame( 'year_desc', $result['mhm_rentiva_vehicle_default_sort'] );
	}

	/**
	 * The Frontend field declares max="50"; the Vehicle-tab copy clamped to 100.
	 * With the duplicate gone there is one bound, and it is the declared one.
	 */
	public function test_the_page_size_is_clamped_to_the_bound_the_form_declares(): void
	{
		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab'                 => 'frontend',
				'mhm_rentiva_vehicle_cards_per_page' => '999',
			)
		);

		$this->assertSame( 50, $result['mhm_rentiva_vehicle_cards_per_page'] );
	}
}
