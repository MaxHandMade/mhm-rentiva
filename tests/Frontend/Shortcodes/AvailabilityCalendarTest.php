<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\CalendarView;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * The calendar's render: which vehicle, what each attribute does (keşif §3,
 * R5, R8, R9, R13, R22, R24), what no longer ships.
 */
class AvailabilityCalendarTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		parent::tearDown();
	}

	private function vehicle(string $title = 'Calendar Test Vehicle', string $date = ''): int
	{
		$args = array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => $title,
		);
		if ('' !== $date) {
			$args['post_date']     = $date;
			$args['post_date_gmt'] = $date;
		}
		$id = (int) self::factory()->post->create($args);
		update_post_meta($id, '_mhmrentiva_price_per_day', '100');
		return $id;
	}

	private function render(string $atts = ''): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_availability_calendar ' . $atts . ']');
	}

	public function test_no_vehicles_at_all_is_an_empty_state(): void
	{
		$output = $this->render();

		$this->assertStringContainsString('data-rvs-availability-empty', $output);
		$this->assertStringContainsString('No vehicles found.', $output);
		$this->assertStringNotContainsString('rvs-availability__months', $output);
	}

	/**
	 * R13: a wrong id says so, it does not quietly show another car.
	 */
	public function test_invalid_vehicle_id_is_an_empty_state_not_another_vehicle(): void
	{
		$this->vehicle('Other Car');

		$output = $this->render('vehicle_id="99999"');

		$this->assertStringContainsString('Vehicle not found.', $output);
		$this->assertStringNotContainsString('Other Car', $output);
	}

	public function test_closed_vehicle_cannot_be_pinned_or_chosen(): void
	{
		$closed = $this->vehicle('Closed Car');
		update_post_meta($closed, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS, 'maintenance');
		$open = $this->vehicle('Open Car');

		$this->assertStringContainsString('Vehicle not found.', $this->render('vehicle_id="' . $closed . '"'));
		$this->assertSame(array( $open ), array_column(CalendarView::vehicles(), 'id'));
	}

	/**
	 * r2 M1: no id is the newest rental vehicle, whatever the list's title order.
	 */
	public function test_empty_vehicle_id_is_the_newest_vehicle(): void
	{
		$this->vehicle('Aaa Old', '2020-01-01 10:00:00');
		$newest = $this->vehicle('Zzz New', '2024-01-01 10:00:00');

		$this->assertSame($newest, CalendarView::resolve_vehicle(''));
		$this->assertStringContainsString('data-vehicle-id="' . $newest . '"', $this->render());
	}

	public function test_renders_the_rvs_shell_and_interactive_months(): void
	{
		$output = $this->render('vehicle_id="' . $this->vehicle() . '"');

		$this->assertStringContainsString('rvs-shell', $output);
		$this->assertStringContainsString('data-rvs-availability', $output);
		$this->assertStringContainsString('rvs-cal__btn', $output);
		$this->assertStringContainsString('data-rvs-cal-panel hidden', $output);
	}

	public function test_empty_state_honours_class(): void
	{
		$this->assertStringContainsString('class="rvs-empty my-class"', $this->render('vehicle_id="99999" class="my-class"'));
		$this->assertStringContainsString('class="rvs-empty my-class"', $this->render('class="my-class"'), 'No vehicles at all.');
	}

	/**
	 * The fleet is not walked to find one car: one row for the newest, no list and
	 * no prices without a selector.
	 */
	public function test_newest_vehicle_is_one_row_and_no_list_without_a_selector(): void
	{
		$this->vehicle('Aaa', '2020-01-01 10:00:00');
		$newest = $this->vehicle('Bbb', '2024-01-01 10:00:00');
		$sql    = array();
		$spy    = static function (string $query) use (&$sql): string {
			if (false !== strpos($query, 'mhmrentiva_vehicle') && false !== strpos($query, 'LIMIT')) {
				$sql[] = $query;
			}
			return $query;
		};
		add_filter('query', $spy);
		$output = $this->render('show_vehicle_selector="0"');
		remove_filter('query', $spy);

		$this->assertStringContainsString('data-vehicle-id="' . $newest . '"', $output);
		$this->assertNotEmpty(array_filter($sql, static fn(string $q): bool => (bool) preg_match('/LIMIT 0,\s*1\b/', $q)), 'Positive control: the one-row query ran.');
		$this->assertSame(array(), array_filter($sql, static fn(string $q): bool => (bool) preg_match('/LIMIT 0,\s*' . CalendarView::MAX_SELECTOR_VEHICLES . '\b/', $q)), 'No list query without a selector.');
	}

	/**
	 * A closed newest vehicle by the status helper's reading is skipped, not shown.
	 */
	public function test_newest_skips_a_vehicle_the_status_helper_closes(): void
	{
		$older  = $this->vehicle('Older', '2020-01-01 10:00:00');
		$closed = $this->vehicle('Closed', '2024-01-01 10:00:00');
		update_post_meta($closed, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LIFECYCLE_STATUS, 'active');
		update_post_meta($closed, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS, 'maintenance');

		$this->assertSame($older, CalendarView::resolve_vehicle(''));
	}

	/**
	 * A price per vehicle only when both the selector and pricing print it; a second
	 * calendar on the page shares the list.
	 */
	public function test_prices_are_computed_only_when_printed_and_once_per_request(): void
	{
		$first = $this->vehicle('One');
		$this->vehicle('Two');
		$this->vehicle('Three');
		$atts = 'vehicle_id="' . $first . '"';

		$this->assertSame(0, $this->price_calls($atts . ' show_pricing="0"'), 'Pricing off: no price at all.');
		$this->assertSame(1, $this->price_calls($atts . ' show_vehicle_selector="0"'), 'No selector: the card alone.');
		$this->assertSame(4, $this->price_calls($atts), 'Card plus three options.');
		$this->assertSame(1, $this->price_calls($atts), 'The second calendar reuses the list.');

		update_post_meta($first, '_mhmrentiva_price_per_day', '200');
		$this->assertSame(4, $this->price_calls($atts), 'A write starts a new list.');
	}

	private function price_calls(string $atts): int
	{
		if (! function_exists('wc_price')) {
			$this->markTestSkipped('WooCommerce is not loaded.');
		}
		$calls = 0;
		$spy   = static function (string $html) use (&$calls): string {
			++$calls;
			return $html;
		};
		add_filter('wc_price', $spy);
		$this->render($atts);
		remove_filter('wc_price', $spy);
		return $calls;
	}

	public function test_selector_list_is_capped_by_title(): void
	{
		for ($i = 0; $i <= CalendarView::MAX_SELECTOR_VEHICLES; $i++) {
			$this->vehicle(sprintf('Car %03d', $i));
		}

		$list = CalendarView::vehicles();

		$this->assertCount(CalendarView::MAX_SELECTOR_VEHICLES, $list);
		$this->assertSame('Car 000', $list[0]['title']);
	}

	/**
	 * R5: the card is image, name, price, switch; the legend three states.
	 */
	public function test_removed_elements_stay_gone(): void
	{
		$output = $this->render('vehicle_id="' . $this->vehicle() . '"');

		foreach (array( 'rv-vcal-favorite-btn', 'rv-vehicle-excerpt', 'rv-feature-item', 'rv-vehicle-rating-block', 'rv-status-partial', 'style=', 'Format:', 'data-price', 'Partially' ) as $gone) {
			$this->assertStringNotContainsString($gone, $output, "$gone must be gone.");
		}
	}

	/**
	 * R8: pricing is the vehicle card's price and the panel's total; no price in a cell.
	 * The booking button's panel stays either way.
	 */
	public function test_show_pricing_switches_price_and_total_together(): void
	{
		$id  = $this->vehicle();
		$on  = $this->render('vehicle_id="' . $id . '"');
		$off = $this->render('vehicle_id="' . $id . '" show_pricing="0"');

		$this->assertStringContainsString('data-rvs-cal-price', $on);
		$this->assertStringContainsString('data-rvs-cal-total', $on);
		$this->assertStringContainsString('data-show-pricing="1"', $on);

		$this->assertStringNotContainsString('data-rvs-cal-price', $off);
		$this->assertStringNotContainsString('data-rvs-cal-total', $off);
		$this->assertStringContainsString('data-show-pricing="0"', $off);
		$this->assertStringContainsString('data-rvs-cal-book', $off);
	}

	public function test_no_booking_button_keeps_the_total(): void
	{
		$output = $this->render('vehicle_id="' . $this->vehicle() . '" show_booking_button="0"');

		$this->assertStringNotContainsString('data-rvs-cal-book', $output);
		$this->assertStringContainsString('data-rvs-cal-total', $output);
		$this->assertStringContainsString('data-show-booking-button="0"', $output);
	}

	/**
	 * R9: months_to_show is real, 1 to 12; the default is 2 on every surface.
	 */
	public function test_months_to_show_draws_that_many_months(): void
	{
		$id = $this->vehicle();

		$this->assertSame(2, substr_count($this->render('vehicle_id="' . $id . '"'), 'data-rvs-calendar'));
		$this->assertSame(3, substr_count($this->render('vehicle_id="' . $id . '" months_to_show="3"'), 'data-rvs-calendar'));
		$this->assertSame(12, substr_count($this->render('vehicle_id="' . $id . '" months_to_show="40"'), 'data-rvs-calendar'));
		$this->assertSame(1, substr_count($this->render('vehicle_id="' . $id . '" months_to_show="0"'), 'data-rvs-calendar'));
	}

	/**
	 * R9: start_month opens there; the past stays closed.
	 */
	public function test_start_month_opens_there_and_past_is_closed(): void
	{
		$id = $this->vehicle();

		$future = $this->render('vehicle_id="' . $id . '" start_month="2099-03"');
		$this->assertStringContainsString('data-start-month="2099-03"', $future);
		$this->assertStringContainsString('data-year="2099" data-month="3"', $future);
		$this->assertDoesNotMatchRegularExpression('/data-rvs-cal-nav="-1"[^>]*disabled/', $future);

		$past = $this->render('vehicle_id="' . $id . '" start_month="2001-01"');
		$this->assertStringContainsString('data-start-month="' . CalendarView::current_month() . '"', $past);
		$this->assertMatchesRegularExpression('/data-rvs-cal-nav="-1"[^>]*disabled/', $past);
	}

	/**
	 * R24, and the class attribute.
	 */
	public function test_theme_and_class_reach_the_root(): void
	{
		$output = $this->render('vehicle_id="' . $this->vehicle() . '" theme="compact" class="my-class"');

		$this->assertMatchesRegularExpression('/<section class="rvs-availability rv-theme-compact my-class"/', $output);
	}

	public function test_selector_is_a_dialog_of_buttons_with_more_than_one_vehicle(): void
	{
		$one = $this->vehicle('First Car');

		$this->assertStringNotContainsString('data-rvs-cal-dialog', $this->render('vehicle_id="' . $one . '"'));

		$two    = $this->vehicle('Second Car');
		$output = $this->render('vehicle_id="' . $one . '"');

		$this->assertStringContainsString('data-rvs-cal-dialog hidden', $output);
		$this->assertStringContainsString('data-rvs-cal-option="' . $two . '"', $output);
		$this->assertStringContainsString('Second Car', $output);
		$this->assertStringNotContainsString('<select', $output);
		$this->assertStringNotContainsString('data-vehicles', $output);
		$this->assertStringNotContainsString('data-rvs-cal-dialog', $this->render('vehicle_id="' . $one . '" show_vehicle_selector="0"'));
	}

	/**
	 * Mistake 13: a translation with an apostrophe printed as "&amp;#039;".
	 */
	public function test_strings_are_escaped_once(): void
	{
		$translate = static fn(string $translation, string $text): string => 'Change vehicle' === $text ? "Changer d'auto" : $translation;
		add_filter('gettext', $translate, 10, 2);
		$output = $this->render('vehicle_id="' . $this->vehicle() . '"');
		$this->vehicle('Second');
		$output .= $this->render('vehicle_id="' . $this->vehicle() . '"');
		remove_filter('gettext', $translate, 10);

		$this->assertStringNotContainsString('&amp;#039;', $output);
	}

	/**
	 * Mistake: a UTC month read, west of UTC, the previous month's name.
	 */
	public function test_month_title_is_the_month_itself_west_of_utc(): void
	{
		update_option('timezone_string', 'America/New_York');
		$output = $this->render('vehicle_id="' . $this->vehicle() . '" start_month="2099-03" months_to_show="1"');
		update_option('timezone_string', '');

		$this->assertStringContainsString('March 2099', $output);
		$this->assertStringNotContainsString('February 2099', $output);
	}

	/**
	 * The HTML cache key separates what changes the render, and a guest's
	 * calendar is not personal: two guests share an entry.
	 */
	public function test_guest_cache_key_separates_calendars_and_is_shared_between_guests(): void
	{
		$key = new \ReflectionMethod(\MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::class, 'get_cache_key');
		$key->setAccessible(true);

		$a = array(
			'vehicle_id'     => '1',
			'months_to_show' => '2',
		);

		$this->assertSame($key->invoke(null, $a), $key->invoke(null, $a));
		$this->assertNotSame($key->invoke(null, $a), $key->invoke(null, array( 'vehicle_id' => '2' ) + $a));
		$this->assertNotSame($key->invoke(null, $a), $key->invoke(null, array( 'months_to_show' => '3' ) + $a));
	}

	/**
	 * R22: a page the plugin creates has no fixed id to miss.
	 *
	 * @dataProvider created_page_markup
	 */
	public function test_created_pages_name_no_fixed_id(string $shortcode, string $markup): void
	{
		$actions = new \ReflectionClass(\MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions::class);
		$method  = $actions->getMethod('get_shortcode_markup');
		$method->setAccessible(true);

		$created = (string) $method->invoke($actions->newInstanceWithoutConstructor(), $shortcode);

		$this->assertStringContainsString($markup, $created);
		$this->assertStringNotContainsString('="1', $created);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function created_page_markup(): array
	{
		return array(
			'calendar'   => array( 'rentiva_availability_calendar', '[rentiva_availability_calendar]' ),
			'comparison' => array( 'rentiva_vehicle_comparison', '[rentiva_vehicle_comparison]' ),
		);
	}

	/**
	 * R23, m10: no legacy stylesheet; the Elementor control and the schema default.
	 */
	public function test_assets_and_defaults(): void
	{
		$this->render('vehicle_id="' . $this->vehicle() . '"');

		$this->assertFalse(wp_style_is('mhm-rentiva-availability-calendar', 'enqueued'));
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-availability', 'enqueued'));
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-overlay', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-availability-calendar', 'enqueued'));
		$this->assertContains('mhm-rentiva-rvs-dialog', wp_scripts()->registered['mhm-rentiva-availability-calendar']->deps);

		$this->assertSame('2', \MHMRentiva\Core\Attribute\AllowlistRegistry::get_schema('rentiva_availability_calendar')['months_to_show']['default'] ?? null);
		$this->assertArrayHasKey('months_to_show', \MHMRentiva\Tests\Support\SettingProbe::data_controls('AvailabilityCalendarWidget'));
	}
}
