<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Groups\PaymentSettings;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;
use WP_UnitTestCase;

/**
 * The site-wide rental deposit setting (Payment tab).
 *
 * Until now a rental deposit could only be set per vehicle, and an empty
 * vehicle field silently meant 10%. These fields are what an empty field now
 * falls back to (DepositCalculator::resolve_rate()).
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\PaymentSettings
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer
 * @covers \MHMRentiva\Admin\Settings\View\TabRendererRegistry
 */
final class RentalDepositSettingsTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	private function save_payment_tab(array $fields): array
	{
		return SettingsSanitizer::sanitize(array_merge(array( 'current_active_tab' => 'payment' ), $fields));
	}

	public function test_defaults_keep_todays_ten_percent(): void
	{
		$defaults = SettingsCore::get_defaults();

		$this->assertSame('percentage', $defaults['mhmrentiva_rental_deposit_type'] ?? null);
		$this->assertSame(10, $defaults['mhmrentiva_rental_deposit_rate'] ?? null);
	}

	public function test_a_valid_save_is_kept(): void
	{
		$out = $this->save_payment_tab(
			array(
				'mhmrentiva_rental_deposit_type' => 'full_payment',
				'mhmrentiva_rental_deposit_rate' => '35',
			)
		);

		$this->assertSame('full_payment', $out['mhmrentiva_rental_deposit_type']);
		$this->assertSame(35, $out['mhmrentiva_rental_deposit_rate']);
	}

	public function test_an_unknown_payment_type_falls_back_to_percentage(): void
	{
		$out = $this->save_payment_tab(array( 'mhmrentiva_rental_deposit_type' => 'fixed' ));

		$this->assertSame('percentage', $out['mhmrentiva_rental_deposit_type']);
	}

	/**
	 * @return array<string, array{0: string, 1: int}>
	 */
	public static function rates(): array
	{
		return array(
			'zero'       => array( '0', 1 ),
			'negative'   => array( '-20', 1 ),
			'above 100'  => array( '250', 100 ),
			'in range'   => array( '55', 55 ),
		);
	}

	/**
	 * @dataProvider rates
	 */
	public function test_the_rate_is_clamped_to_one_to_hundred(string $posted, int $stored): void
	{
		$out = $this->save_payment_tab(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => $posted,
			)
		);

		$this->assertSame($stored, $out['mhmrentiva_rental_deposit_rate']);
	}

	public function test_saving_the_payment_tab_keeps_values_it_does_not_show(): void
	{
		// Two offline-payment keys from an older release have no field on this
		// tab; the tab used to be hidden while WooCommerce was active, so saving
		// it now must not wipe them.
		$stored = SettingsCore::get_all();
		$stored['mhmrentiva_offline_instructions'] = 'Pay at the desk.';
		$stored['mhmrentiva_offline_accounts']     = 'IBAN TR00';
		update_option(SettingsCore::OPTION_NAME, $stored);

		$saved = $this->save_payment_tab(array( 'mhmrentiva_rental_deposit_rate' => '25' ));

		$this->assertSame('Pay at the desk.', $saved['mhmrentiva_offline_instructions'] ?? null);
		$this->assertSame('IBAN TR00', $saved['mhmrentiva_offline_accounts'] ?? null);
		$this->assertSame(25, $saved['mhmrentiva_rental_deposit_rate']);
	}

	public function test_both_fields_are_on_the_payment_section(): void
	{
		global $wp_settings_fields;

		PaymentSettings::register();

		$fields = $wp_settings_fields[ SettingsCore::PAGE ][ PaymentSettings::SECTION_GENERAL ] ?? array();

		$this->assertArrayHasKey('mhmrentiva_rental_deposit_type', $fields);
		$this->assertArrayHasKey('mhmrentiva_rental_deposit_rate', $fields);
	}

	public function test_the_payment_tab_is_reachable_with_woocommerce_active(): void
	{
		$this->assertTrue(class_exists('WooCommerce'), 'Sanity: the suite runs with WooCommerce active.');

		$this->assertNotNull(
			( new TabRendererRegistry() )->get('payment'),
			'The rental deposit setting lives on the Payment tab; hiding the tab whenever WooCommerce is active hid the setting on every site that can take payments.'
		);
	}
}
