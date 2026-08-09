<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings;
use MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings;
use WP_UnitTestCase;

/**
 * Seasonal season names/descriptions ("Spring", "Standard pricing", ...) showed
 * English on a Turkish admin even though the TR catalogue translates them. Root
 * cause: the labels are translatable presentation, but SettingsSanitizer seeds
 * the whole seasonal block from get_default_settings() -- resolving __() in the
 * locale active at save time -- and persists it into mhmrentiva_settings; the
 * render then printed the stored name/description verbatim, freezing whatever
 * locale first saved. The fix re-derives the label from the canonical defaults
 * (keyed by season slug) at render time; only the multiplier stays user data.
 *
 * This test is locale-independent: it freezes a sentinel into the stored option
 * and asserts the render ignores it in favour of the canonical label.
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::render_seasonal_multipliers_field
 */
final class SeasonalLabelTranslationTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option( 'mhmrentiva_settings' );
		parent::tearDown();
	}

	public function test_render_uses_canonical_labels_not_the_frozen_stored_ones(): void
	{
		update_option(
			'mhmrentiva_settings',
			array(
				'vehicle_pricing' => array(
					'seasonal_multipliers' => array(
						'spring' => array(
							'name'        => 'FROZEN_SENTINEL_NAME',
							'description' => 'FROZEN_SENTINEL_DESC',
							'multiplier'  => 1.4,
						),
					),
				),
			)
		);

		$canonical      = VehiclePricingSettings::get_default_settings()['seasonal_multipliers']['spring'];
		$canonical_name = (string) $canonical['name'];
		$canonical_desc = (string) $canonical['description'];

		ob_start();
		VehicleManagementSettings::render_seasonal_multipliers_field();
		$html = (string) ob_get_clean();

		// The frozen stored label must NOT reach the screen...
		$this->assertStringNotContainsString( 'FROZEN_SENTINEL_NAME', $html );
		$this->assertStringNotContainsString( 'FROZEN_SENTINEL_DESC', $html );
		// ...the canonical (re-translated) label must...
		$this->assertStringContainsString( esc_html( $canonical_name ), $html );
		$this->assertStringContainsString( esc_html( $canonical_desc ), $html );
		// ...and the user-editable multiplier must still come from the store.
		$this->assertStringContainsString( 'value="1.4"', $html );
	}
}
