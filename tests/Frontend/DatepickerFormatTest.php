<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\Assets\DatepickerAssets;
use WP_UnitTestCase;

/**
 * PR S: customer date fields show the site's date format.
 *
 * The shared datepicker init showed a hard-coded `yy-mm-dd`; the site setting is
 * WordPress's `date_format`. DatepickerAssets::js_date_format() is the one PHP ->
 * jQuery UI conversion (the old booking form used its own copy); the init gets it
 * as `dateFormat`, the submitted value stays Y-m-d (altField, JS).
 *
 * @covers \MHMRentiva\Admin\Core\Assets\DatepickerAssets::js_date_format
 * @covers \MHMRentiva\Admin\Core\Assets\DatepickerAssets::enqueue
 * @covers \MHMRentiva\Admin\Core\Assets\DatepickerAssets::parse_date
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
	 * The field is rendered in the site format, so it shows
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

	/**
	 * Without JavaScript a date field submits what it shows (the site
	 * format); the server reads it back with parse_date(), the inverse of
	 * display_date(). Y-m-d is always accepted (the script's hidden field,
	 * links, old bookmarks).
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function shown_dates(): array
	{
		return array(
			'd/m/Y'               => array( 'd/m/Y', '12/07/2027', '2027-07-12' ),
			'm/d/Y'               => array( 'm/d/Y', '07/12/2027', '2027-07-12' ),
			'Y-m-d'               => array( 'Y-m-d', '2027-07-12', '2027-07-12' ),
			'j F Y'               => array( 'j F Y', '12 July 2027', '2027-07-12' ),
			'j F Y, any case'     => array( 'j F Y', '12 july 2027', '2027-07-12' ),
			'F j, Y (WP)'         => array( 'F j, Y', 'July 12, 2027', '2027-07-12' ),
			'd.m.y'               => array( 'd.m.y', '05.01.27', '2027-01-05' ),
			'D, j M Y'            => array( 'D, j M Y', 'Mon, 7 Dec 2026', '2026-12-07' ),
			'l j F Y'             => array( 'l j F Y', 'Sunday 6 December 2026', '2026-12-06' ),
			'escaped literal'     => array( 'j \d\e F Y', '7 de December 2026', '2026-12-07' ),
			'apostrophe'          => array( "d 'M' Y", "07 'Dec' 2026", '2026-12-07' ),
			'single-digit d/m/Y'  => array( 'd/m/Y', '7/1/2027', '2027-01-07' ),
			'spaces around'       => array( 'd/m/Y', ' 12/07/2027 ', '2027-07-12' ),
			'ISO under d/m/Y'     => array( 'd/m/Y', '2027-07-12', '2027-07-12' ),
			'ISO under j F Y'     => array( 'j F Y', '2027-07-12', '2027-07-12' ),
			'fallback format'     => array( 'F j', '2027-07-12', '2027-07-12' ),
			'no such day'         => array( 'd/m/Y', '31/02/2027', '' ),
			'no such ISO day'     => array( 'd/m/Y', '2027-02-30', '' ),
			'other order'         => array( 'd/m/Y', '2027/07/12', '' ),
			'words'               => array( 'd/m/Y', 'next friday', '' ),
			'markup'              => array( 'd/m/Y', '"><script>', '' ),
			'empty'               => array( 'd/m/Y', '', '' ),
			'trailing junk'       => array( 'd/m/Y', '12/07/2027x', '' ),
			'unknown month'       => array( 'j F Y', '12 Julember 2027', '' ),
		);
	}

	/**
	 * @dataProvider shown_dates
	 */
	public function test_a_shown_date_parses_back_to_ymd(string $php, string $shown, string $expected): void
	{
		update_option('date_format', $php);

		$this->assertSame($expected, DatepickerAssets::parse_date($shown));
	}

	/**
	 * Every date the page renders reads back as the same day.
	 *
	 * @dataProvider displays
	 */
	public function test_every_rendered_date_round_trips(string $php, string $ymd): void
	{
		update_option('date_format', $php);

		$this->assertSame($ymd, DatepickerAssets::parse_date(DatepickerAssets::display_date($ymd)));
	}

	/**
	 * A translated site shows its own month names (the plugin's
	 * strings); typed back, they parse -- Turkish, with its dotted and dotless
	 * letters, in any case.
	 */
	public function test_translated_month_names_parse(): void
	{
		$turkish = array(
			'January'  => 'Ocak',
			'February' => 'Şubat',
			'July'     => 'Temmuz',
			'August'   => 'Ağustos',
			'December' => 'Aralık',
		);
		$filter  = static function ($translation, $text, $domain) use ($turkish) {
			return 'mhm-rentiva' === $domain && isset($turkish[ $text ]) ? $turkish[ $text ] : $translation;
		};
		add_filter('gettext', $filter, 10, 3);
		update_option('date_format', 'j F Y');
		try {
			$this->assertSame('7 Aralık 2026', DatepickerAssets::display_date('2026-12-07'));
			$this->assertSame('2026-12-07', DatepickerAssets::parse_date('7 Aralık 2026'));
			$this->assertSame('2027-02-14', DatepickerAssets::parse_date('14 Şubat 2027'));
			$this->assertSame('2027-08-30', DatepickerAssets::parse_date('30 ağustos 2027'));
			$this->assertSame('2027-07-12', DatepickerAssets::parse_date('12 TEMMUZ 2027'));
			$this->assertSame('', DatepickerAssets::parse_date('12 July 2027'), 'The English name is not the site\'s.');
		} finally {
			remove_filter('gettext', $filter, 10);
		}
	}
}
