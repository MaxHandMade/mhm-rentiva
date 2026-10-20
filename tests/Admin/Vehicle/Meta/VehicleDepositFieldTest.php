<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * The vehicle's deposit field is an override of the global rental setting.
 *
 * Its two writers disagreed: the meta box sanitizer turned an empty field into
 * 10 and clamped to 0-50, and the field-config save loop stored the raw text.
 * The edit screen printed 10 for an empty field, so every vehicle an admin
 * saved got a stored 10 that then overrode the global setting for good. Both
 * writers now store '' for "use the global setting" and clamp anything else
 * to 1-100, and the screen shows an empty field with the effective value.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::normalize_vehicle_rate
 */
final class VehicleDepositFieldTest extends WP_UnitTestCase
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
		$_POST = array();
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function inputs(): array
	{
		return array(
			'empty'        => array( '', '' ),
			'zero'         => array( '0', '' ),
			'negative'     => array( '-5', '' ),
			'non-numeric'  => array( 'abc', '' ),
			'in range'     => array( '15', '15' ),
			'with percent' => array( '25%', '25' ),
			'fraction'     => array( '12.5', '12.5' ),
			'below one'    => array( '0.5', '1' ),
			'above 50'     => array( '75', '75' ),
			'above 100'    => array( '150', '100' ),
		);
	}

	/**
	 * @dataProvider inputs
	 */
	public function test_meta_box_sanitizer_normalises_the_field(string $posted, string $stored): void
	{
		$method = new ReflectionMethod(VehicleMeta::class, 'sanitize_field');
		$method->setAccessible(true);

		$this->assertSame($stored, (string) $method->invoke(null, 'mhmrentiva_deposit', $posted));
	}

	/**
	 * @dataProvider inputs
	 */
	public function test_meta_box_save_stores_the_normalised_value(string $posted, string $stored): void
	{
		$_POST = array(
			'mhmrentiva_vehicle_meta_nonce' => wp_create_nonce('mhmrentiva_vehicle_meta_action'),
			'mhmrentiva_deposit'            => $posted,
		);

		VehicleMeta::save_meta($this->vehicle_id, get_post($this->vehicle_id));

		$this->assertSame($stored, get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_DEPOSIT, true));
	}

	/**
	 * @dataProvider inputs
	 */
	public function test_field_config_save_stores_the_normalised_value(string $posted, string $stored): void
	{
		$_POST = array(
			'mhmrentiva_vehicle_meta_nonce' => wp_create_nonce('mhmrentiva_vehicle_meta_action'),
			'_mhmrentiva_deposit'           => $posted,
		);

		VehicleMeta::save_vehicle_meta($this->vehicle_id, array());

		$this->assertSame($stored, get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_DEPOSIT, true));
	}

	public function test_edit_screen_leaves_an_empty_field_empty(): void
	{
		$data = $this->template_data();

		$this->assertSame('', (string) ( $data['detail_values']['deposit'] ?? 'missing' ), 'An empty field must not be shown -- and then saved -- as 10.');
	}

	public function test_edit_screen_names_the_global_percentage(): void
	{
		update_option(
			'mhmrentiva_settings',
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);

		$data = $this->template_data();

		$this->assertSame('30', $data['deposit_global_rate'] ?? null);
		// Written as the site's language writes a percentage (an English test site).
		$this->assertStringContainsString('30%', (string) ( $data['deposit_global_hint'] ?? '' ));
	}

	public function test_edit_screen_names_global_full_payment(): void
	{
		update_option('mhmrentiva_settings', array( 'mhmrentiva_rental_deposit_type' => 'full_payment' ));

		$data = $this->template_data();

		$this->assertSame('', $data['deposit_global_rate'] ?? null);
		$this->assertStringContainsString('Full Payment', (string) ( $data['deposit_global_hint'] ?? '' ));
	}

	public function test_edit_screen_keeps_a_stored_override(): void
	{
		update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_DEPOSIT, '10');

		$this->assertSame('10', (string) $this->template_data()['detail_values']['deposit']);
	}

	public function test_both_inputs_accept_one_to_hundred(): void
	{
		$template = (string) file_get_contents(dirname(__DIR__, 4) . '/src/Admin/Vehicle/Templates/vehicle-meta.php');

		$this->assertSame(0, substr_count($template, 'min="0" max="100" step="0.1" placeholder="10"'), 'The old 0-100 / placeholder-10 input must be gone.');
		$this->assertSame(2, substr_count($template, 'min="1" max="100"'), 'Both deposit inputs accept 1-100.');
	}

	public function test_normaliser_is_the_single_rule(): void
	{
		$this->assertSame('', DepositCalculator::normalize_vehicle_rate(null));
		$this->assertSame('100', DepositCalculator::normalize_vehicle_rate(500));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function template_data(): array
	{
		$method = new ReflectionMethod(VehicleMeta::class, 'prepare_template_data');
		$method->setAccessible(true);

		return $method->invoke(null, get_post($this->vehicle_id));
	}
}
