<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use WP_UnitTestCase;

/**
 * Mileage and engine size are plain numbers, not money: they are written with
 * the separators of the language the page is shown in (WP locale), the same
 * way the daily km limit already is. An English page must read "18,500 km",
 * a Turkish one "18.500 km".
 */
final class VehicleFeatureHelperNumberLocaleTest extends WP_UnitTestCase {

	/** @var array<string,string> */
	private array $saved_number_format = array();

	public function set_up(): void {
		parent::set_up();
		global $wp_locale;
		$this->saved_number_format = $wp_locale->number_format;
	}

	public function tear_down(): void {
		global $wp_locale;
		$wp_locale->number_format = $this->saved_number_format;
		parent::tear_down();
	}

	private function use_separators( string $thousands, string $decimal ): void {
		global $wp_locale;
		$wp_locale->number_format = array(
			'thousands_sep' => $thousands,
			'decimal_point' => $decimal,
		);
	}

	private function text( string $key, $raw ): string {
		$formatted = VehicleFeatureHelper::format_detail_value( $key, $raw );
		$this->assertIsArray( $formatted );
		return (string) $formatted['text'];
	}

	public function test_mileage_uses_english_separators_on_an_english_page(): void {
		$this->use_separators( ',', '.' );
		$this->assertSame( '18,500 km', $this->text( 'mileage', '18500' ) );
	}

	public function test_mileage_uses_turkish_separators_on_a_turkish_page(): void {
		$this->use_separators( '.', ',' );
		$this->assertSame( '18.500 km', $this->text( 'mileage', '18500' ) );
	}

	public function test_mileage_stored_with_separators_is_still_read_as_a_whole_number(): void {
		$this->use_separators( ',', '.' );
		$this->assertSame( '18,500 km', $this->text( 'mileage', '18.500' ) );
		$this->assertSame( '18,500 km', $this->text( 'mileage', '18,500' ) );
	}

	public function test_engine_size_uses_english_decimal_point_on_an_english_page(): void {
		$this->use_separators( ',', '.' );
		$this->assertSame( '1.6 L', $this->text( 'engine_size', '1.6' ) );
	}

	public function test_engine_size_uses_turkish_decimal_comma_on_a_turkish_page(): void {
		$this->use_separators( '.', ',' );
		$this->assertSame( '1,6 L', $this->text( 'engine_size', '1.6' ) );
	}
}
