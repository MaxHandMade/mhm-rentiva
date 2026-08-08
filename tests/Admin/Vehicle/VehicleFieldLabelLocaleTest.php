<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;

/**
 * Locale independence of the stored vehicle field-label options.
 *
 * Canonical rule (documented on VehicleMeta::ensure_default_options()):
 * `mhmrentiva_vehicle_{details,features,equipment}` holds ONLY user-renamed
 * overrides; the full label list is composed at render time from the
 * translatable defaults. Persisting a `__()` result freezes the session
 * locale into the database — the v4.27.1 locale-leakage bug. These tests
 * pin the two write paths that reopened that leak and the two readers that
 * silently depended on the frozen full map.
 */
final class VehicleFieldLabelLocaleTest extends \WP_UnitTestCase
{
	private const LABEL_OPTIONS = array(
		'mhmrentiva_vehicle_details',
		'mhmrentiva_vehicle_features',
		'mhmrentiva_vehicle_equipment',
		'mhmrentiva_custom_details',
		'mhmrentiva_custom_features',
		'mhmrentiva_custom_equipment',
		'mhmrentiva_selected_details',
	);

	public function set_up(): void
	{
		parent::set_up();
		foreach (self::LABEL_OPTIONS as $option) {
			delete_option($option);
		}
		$this->reset_feature_helper_cache();
	}

	public function tear_down(): void
	{
		foreach (self::LABEL_OPTIONS as $option) {
			delete_option($option);
		}
		$this->reset_feature_helper_cache();
		parent::tear_down();
	}

	private function reset_feature_helper_cache(): void
	{
		$prop = new \ReflectionProperty(VehicleFeatureHelper::class, 'fields_map_cache');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
	}

	private function default_details(): array
	{
		$method = new \ReflectionMethod(VehicleSettings::class, 'get_default_details');
		$method->setAccessible(true);

		return $method->invoke(null);
	}

	/**
	 * The Edit Names modal posts every rendered label. Saving with one edit
	 * must store ONLY that override — not the other thirteen labels frozen
	 * in whatever language the admin's session happened to be in.
	 */
	public function test_editing_one_label_stores_only_that_override(): void
	{
		$submitted         = $this->default_details();
		$submitted['year'] = 'Model Year';

		VehicleSettings::apply_label_updates('details', $submitted);

		$this->assertSame(
			array( 'year' => 'Model Year' ),
			get_option('mhmrentiva_vehicle_details'),
			'Labels equal to their translatable default must not be persisted; only the real override may be stored.'
		);
	}

	/**
	 * Typing the default text back into an overridden field must DELETE the
	 * override, so a later locale switch renders the new locale's default.
	 */
	public function test_restoring_the_default_label_removes_the_override(): void
	{
		update_option('mhmrentiva_vehicle_details', array( 'year' => 'Model Year' ));

		$defaults = $this->default_details();
		VehicleSettings::apply_label_updates('details', array( 'year' => $defaults['year'] ));

		$this->assertSame(
			array(),
			get_option('mhmrentiva_vehicle_details'),
			'An override restored to the default must leave the stored map, not freeze the translation.'
		);
	}

	/**
	 * Custom (user-created) fields keep their labels editable through the
	 * same path.
	 */
	public function test_custom_field_label_updates_still_apply(): void
	{
		update_option('mhmrentiva_custom_details', array( 'custom_1_abc' => 'Boot Size' ));

		VehicleSettings::apply_label_updates('details', array( 'custom_1_abc' => 'Trunk Size' ));

		$stored = get_option('mhmrentiva_custom_details');
		$this->assertSame('Trunk Size', $stored['custom_1_abc']);
		$this->assertFalse(get_option('mhmrentiva_vehicle_details'), 'A custom-field rename must not touch the standard-label option.');
	}

	/**
	 * Render-time composition: a stored subset must not hide the untouched
	 * standard fields — they resolve from the translatable defaults.
	 */
	public function test_available_details_compose_defaults_under_a_partial_override_map(): void
	{
		update_option('mhmrentiva_vehicle_details', array( 'year' => 'Model Year' ));

		$all      = VehicleSettings::get_all_available_details();
		$defaults = $this->default_details();

		$this->assertSame('Model Year', $all['year']);
		$this->assertSame($defaults['price_per_day'], $all['price_per_day']);
		$this->assertCount(count($defaults), array_intersect_key($all, $defaults));
	}

	/**
	 * Reader regression: the frontend field map must include standard fields
	 * that have NO stored override when the option holds a partial map.
	 */
	public function test_feature_helper_field_map_sees_non_overridden_standard_fields(): void
	{
		update_option('mhmrentiva_vehicle_details', array( 'year' => 'Model Year' ));
		update_option('mhmrentiva_selected_details', array( 'year', 'price_per_day' ));

		$map      = VehicleFeatureHelper::get_available_fields_map();
		$details  = $map[ VehicleFeatureHelper::TYPE_DETAIL ];
		$defaults = $this->default_details();

		$this->assertArrayHasKey('year', $details);
		$this->assertSame('Model Year', $details['year']['label']);
		$this->assertArrayHasKey(
			'price_per_day',
			$details,
			'A standard field without an override must stay available; only the frozen full map made this work before.'
		);
		$this->assertSame($defaults['price_per_day'], $details['price_per_day']['label']);
	}
}
