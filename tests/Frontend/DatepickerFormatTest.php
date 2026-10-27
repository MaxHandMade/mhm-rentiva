<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\Assets\DatepickerAssets;
use WP_UnitTestCase;

/**
 * PR S Task 8 item 2: customer date fields show the site's date format.
 *
 * The shared datepicker init showed a hard-coded `yy-mm-dd`; the site setting is
 * WordPress's `date_format`. DatepickerAssets::js_date_format() is the one PHP ->
 * jQuery UI conversion (the old booking form used its own copy); the init gets it
 * as `dateFormat`, the submitted value stays Y-m-d (altField, JS).
 *
 * @covers \MHMRentiva\Admin\Core\Assets\DatepickerAssets::js_date_format
 * @covers \MHMRentiva\Admin\Core\Assets\DatepickerAssets::enqueue
 */
final class DatepickerFormatTest extends WP_UnitTestCase
{
	private string $saved_format = '';

	public function set_up(): void
	{
		parent::set_up();
		$this->saved_format = (string) get_option('date_format');
	}

	public function tear_down(): void
	{
		update_option('date_format', $this->saved_format);
		wp_scripts()->remove('mhm-rentiva-datepicker-init');
		parent::tear_down();
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function formats(): array
	{
		return array(
			'd/m/Y'              => array( 'd/m/Y', 'dd/mm/yy' ),
			'm/d/Y'              => array( 'm/d/Y', 'mm/dd/yy' ),
			'Y-m-d'              => array( 'Y-m-d', 'yy-mm-dd' ),
			'j F Y'              => array( 'j F Y', 'd MM yy' ),
			'd.m.Y'              => array( 'd.m.Y', 'dd.mm.yy' ),
			'D, j M y'           => array( 'D, j M y', 'D, d M y' ),
			'l j F Y'            => array( 'l j F Y', 'DD d MM yy' ),
			// No jQuery UI token for the ordinal suffix: dropped, the day stays.
			'F jS, Y'            => array( 'F jS, Y', 'MM d, yy' ),
			// Time and timezone characters: a date field shows the date.
			'Y-m-d H:i'          => array( 'Y-m-d H:i', 'yy-mm-dd' ),
			'j F Y, g:i a T'     => array( 'j F Y, g:i a T', 'd MM yy' ),
			// Escaped and plain literal letters are quoted for jQuery UI.
			'escaped literal'    => array( 'j \d\e F Y', "d 'de' MM yy" ),
			'apostrophe'         => array( "d 'M' Y", "dd ''M'' yy" ),
			// Without a day, a month and a year the field could not round-trip.
			'no year'            => array( 'F j', 'yy-mm-dd' ),
			'full ISO c'         => array( 'c', 'yy-mm-dd' ),
			'timestamp U'        => array( 'U', 'yy-mm-dd' ),
			'empty'              => array( '', 'yy-mm-dd' ),
		);
	}

	/**
	 * @dataProvider formats
	 */
	public function test_php_format_maps_to_jquery_ui(string $php, string $expected): void
	{
		$this->assertSame($expected, DatepickerAssets::js_date_format($php));
	}

	/**
	 * PR S Task 9 item 4: the field is rendered in the site format, so it shows
	 * no Y-m-d before the script runs. Same rules as jQuery UI's formatDate()
	 * on the converted format, with the names the init receives.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function displays(): array
	{
		return array(
			'd/m/Y'           => array( 'd/m/Y', '2026-12-07', '07/12/2026' ),
			'm/d/Y'           => array( 'm/d/Y', '2026-12-07', '12/07/2026' ),
			'Y-m-d'           => array( 'Y-m-d', '2026-12-07', '2026-12-07' ),
			'j F Y'           => array( 'j F Y', '2026-12-07', '7 December 2026' ),
			'F j, Y (WP)'     => array( 'F j, Y', '2027-07-12', 'July 12, 2027' ),
			'd.m.y'           => array( 'd.m.y', '2027-01-05', '05.01.27' ),
			'D, j M Y'        => array( 'D, j M Y', '2026-12-07', 'Mon, 7 Dec 2026' ),
			'l j F Y'         => array( 'l j F Y', '2026-12-06', 'Sunday 6 December 2026' ),
			'ordinal dropped' => array( 'F jS, Y', '2026-12-01', 'December 1, 2026' ),
			'time dropped'    => array( 'j F Y, g:i a T', '2026-12-07', '7 December 2026' ),
			'escaped literal' => array( 'j \d\e F Y', '2026-12-07', '7 de December 2026' ),
			'apostrophe'      => array( "d 'M' Y", '2026-12-07', "07 'Dec' 2026" ),
			'fallback'        => array( 'F j', '2026-12-07', '2026-12-07' ),
		);
	}

	/**
	 * @dataProvider displays
	 */
	public function test_a_date_renders_in_the_site_format(string $php, string $ymd, string $expected): void
	{
		update_option('date_format', $php);

		$this->assertSame($expected, DatepickerAssets::display_date($ymd));
	}

	public function test_no_date_renders_as_nothing(): void
	{
		update_option('date_format', 'd/m/Y');

		$this->assertSame('', DatepickerAssets::display_date(''));
		$this->assertSame('', DatepickerAssets::display_date('2026-02-30'));
		$this->assertSame('', DatepickerAssets::display_date('"><b>'));
	}

	/**
	 * The page and the init speak one format: the localized dateFormat is the
	 * one display_date() renders with.
	 */
	public function test_render_and_init_share_one_format(): void
	{
		update_option('date_format', 'j F Y');
		wp_scripts()->remove('mhm-rentiva-datepicker-init');
		DatepickerAssets::enqueue();

		$data = (string) wp_scripts()->get_data('mhm-rentiva-datepicker-init', 'data');
		$this->assertStringContainsString('"dateFormat":"' . DatepickerAssets::display_format() . '"', $data);
		$this->assertSame('d MM yy', DatepickerAssets::display_format());
	}

	public function test_the_init_gets_the_site_format(): void
	{
		update_option('date_format', 'd/m/Y');
		wp_scripts()->remove('mhm-rentiva-datepicker-init');

		DatepickerAssets::enqueue();

		$data = (string) wp_scripts()->get_data('mhm-rentiva-datepicker-init', 'data');
		$this->assertStringContainsString('"dateFormat":"dd/mm/yy"', $data);
		$this->assertStringContainsString('"altFormat":"yy-mm-dd"', $data);
		$this->assertStringContainsString('"monthNamesShort":', $data);
	}
}
