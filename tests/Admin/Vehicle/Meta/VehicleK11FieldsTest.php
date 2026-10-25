<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Aşama 2 PR B / K11: daily km limit, minimum driver age, minimum licence
 * years and large luggage on the vehicle; deposit and fuel policy notes in
 * the booking settings.
 *
 * Large luggage reuses the key Pro's transfer box already writes
 * (_mhmrentiva_vehicle_max_big_luggage). While that box is on the screen the
 * Lite input is not drawn, and the save never writes a field that was not
 * posted, so the two editors cannot overwrite each other.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta
 */
final class VehicleK11FieldsTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	public function tearDown(): void
	{
		global $wp_meta_boxes;
		$_POST         = array();
		$wp_meta_boxes = array();
		delete_option('mhmrentiva_selected_details');
		parent::tearDown();
	}

	private function save(array $fields): void
	{
		$_POST = array_merge(
			array( 'mhmrentiva_vehicle_meta_nonce' => wp_create_nonce('mhmrentiva_vehicle_meta_action') ),
			$fields
		);
		VehicleMeta::save_meta($this->vehicle_id, get_post($this->vehicle_id));
	}

	public function test_meta_keys_are_named(): void
	{
		$this->assertSame('_mhmrentiva_daily_km_limit', MetaKeys::DAILY_KM_LIMIT);
		$this->assertSame('_mhmrentiva_min_driver_age', MetaKeys::MIN_DRIVER_AGE);
		$this->assertSame('_mhmrentiva_min_license_years', MetaKeys::MIN_LICENSE_YEARS);
		$this->assertSame('_mhmrentiva_vehicle_max_big_luggage', MetaKeys::LUGGAGE_LARGE);
	}

	public function test_fields_are_standard_details_selected_by_default(): void
	{
		$details = VehicleSettings::get_default_details();
		foreach (array( 'daily_km_limit', 'min_driver_age', 'min_license_years', 'vehicle_max_big_luggage' ) as $key) {
			$this->assertArrayHasKey($key, $details, $key);
			$this->assertContains($key, VehicleSettings::get_default_selected_details(), $key);
		}
	}

	public function test_meta_box_saves_and_clears_k11_fields(): void
	{
		$this->save(
			array(
				'mhmrentiva_daily_km_limit'          => '300',
				'mhmrentiva_min_driver_age'          => '23',
				'mhmrentiva_min_license_years'       => '2',
				'mhmrentiva_vehicle_max_big_luggage' => '2',
			)
		);
		$this->assertSame('300', get_post_meta($this->vehicle_id, MetaKeys::DAILY_KM_LIMIT, true));
		$this->assertSame('23', get_post_meta($this->vehicle_id, MetaKeys::MIN_DRIVER_AGE, true));
		$this->assertSame('2', get_post_meta($this->vehicle_id, MetaKeys::MIN_LICENSE_YEARS, true));
		$this->assertSame('2', get_post_meta($this->vehicle_id, MetaKeys::LUGGAGE_LARGE, true));

		$this->save(
			array(
				'mhmrentiva_daily_km_limit' => '',
				'mhmrentiva_min_driver_age' => '-5',
				'mhmrentiva_min_license_years' => 'abc',
			)
		);
		$this->assertFalse(metadata_exists('post', $this->vehicle_id, MetaKeys::DAILY_KM_LIMIT));
		$this->assertFalse(metadata_exists('post', $this->vehicle_id, MetaKeys::MIN_DRIVER_AGE));
		$this->assertFalse(metadata_exists('post', $this->vehicle_id, MetaKeys::MIN_LICENSE_YEARS));
		// Not posted: left alone.
		$this->assertSame('2', get_post_meta($this->vehicle_id, MetaKeys::LUGGAGE_LARGE, true));
	}

	public function test_detail_values_format(): void
	{
		$fmt = static fn(string $k, string $v): string => (string) ( VehicleFeatureHelper::format_detail_value($k, $v)['text'] ?? '' );

		$this->assertSame('300 km/day', $fmt('daily_km_limit', '300'));
		$this->assertSame('23+', $fmt('min_driver_age', '23'));
		$this->assertSame('2 years licence', $fmt('min_license_years', '2'));
		$this->assertSame('1 year licence', $fmt('min_license_years', '1'));
		$this->assertSame('2 large suitcases', $fmt('vehicle_max_big_luggage', '2'));
		$this->assertSame('1,500 km/day', $fmt('daily_km_limit', '1500'));
		$this->assertNull(VehicleFeatureHelper::format_detail_value('daily_km_limit', ''));
		$this->assertNull(VehicleFeatureHelper::format_detail_value('min_driver_age', '0'));
	}

	public function test_lite_luggage_input_yields_to_pro_transfer_box(): void
	{
		global $wp_meta_boxes;
		update_option('mhmrentiva_selected_details', array( 'vehicle_max_big_luggage', 'daily_km_limit' ));

		ob_start();
		VehicleMeta::render_meta_box(get_post($this->vehicle_id));
		$without = (string) ob_get_clean();
		$this->assertStringContainsString('name="mhmrentiva_vehicle_max_big_luggage"', $without);
		$this->assertStringContainsString('name="mhmrentiva_daily_km_limit"', $without);

		$wp_meta_boxes['mhmrentiva_vehicle']['side']['default']['mhmrentiva_vehicle_transfer_settings'] = array( 'id' => 'mhmrentiva_vehicle_transfer_settings' );
		ob_start();
		VehicleMeta::render_meta_box(get_post($this->vehicle_id));
		$with = (string) ob_get_clean();
		$this->assertStringNotContainsString('name="mhmrentiva_vehicle_max_big_luggage"', $with);
		$this->assertStringContainsString('name="mhmrentiva_daily_km_limit"', $with);

		// Pro's own field (rentiva_vehicle_max_big_luggage) is not Lite's to save.
		update_post_meta($this->vehicle_id, MetaKeys::LUGGAGE_LARGE, '3');
		$this->save(array( 'rentiva_vehicle_max_big_luggage' => '5' ));
		$this->assertSame('3', get_post_meta($this->vehicle_id, MetaKeys::LUGGAGE_LARGE, true));
	}

	public function test_booking_notes_are_saved_as_text(): void
	{
		$method = new ReflectionMethod(SettingsSanitizer::class, 'sanitize_booking_settings');
		$method->setAccessible(true);
		$clean = $method->invoke(
			null,
			array(
				'mhmrentiva_booking_fuel_policy_note'      => "<b>Full to full</b>\nMissing fuel is charged.",
				'mhmrentiva_booking_security_deposit_note' => '<script>x</script>5,000 TL hold at pickup',
			),
			\MHMRentiva\Admin\Settings\Groups\BookingSettings::get_default_settings()
		);

		$this->assertSame("Full to full\nMissing fuel is charged.", $clean['mhmrentiva_booking_fuel_policy_note']);
		$this->assertSame('5,000 TL hold at pickup', $clean['mhmrentiva_booking_security_deposit_note']);
	}
}
