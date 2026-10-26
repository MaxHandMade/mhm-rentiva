<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Aşama 2 PR C Task 2: the booking form on the rvs layer.
 *
 * The markup contract booking-form.js binds to (data-rvs-*), the per-instance
 * ids, the cache that never holds the form, the locked required add-on that is
 * still submitted, and the "Change" link back to the vehicle page.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks::details_url
 */
final class BookingFormMarkupTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		BookingForm::register();
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_root_is_the_rvs_booking_inside_one_shell(): void
	{
		$html = $this->render(array( 'vehicle_id' => (string) ShortcodeFixtures::vehicle() ));

		$this->assertStringStartsWith(sprintf(AbstractShortcode::RVS_SHELL_OPEN, 'rentiva_booking_form'), $html);
		$this->assertSame(1, substr_count($html, 'class="rvs-shell'), 'One shell, never nested.');
		$this->assertMatchesRegularExpression('/<div class="rvs-booking[^"]*"[^>]*\sdata-rvs-booking-form[\s>]/', $html);
		foreach (array( 'data-rvs-pickup-date', 'data-rvs-dropoff-date', 'data-rvs-pickup-time', 'data-rvs-dropoff-time', 'data-rvs-availability', 'data-rvs-breakdown', 'data-rvs-total', 'data-rvs-submit', 'data-rvs-messages', 'data-rvs-summary-toggle', 'data-rvs-change' ) as $hook) {
			$this->assertStringContainsString($hook, $html, "$hook is part of the script contract.");
		}
		$this->assertStringNotContainsString('rv-booking-form-wrapper', $html, 'The old rv-* markup is gone.');
	}

	public function test_two_forms_have_unique_ids(): void
	{
		$vehicle = (string) ShortcodeFixtures::vehicle();
		$this->addon('Child seat', '10', false, 3);
		$first  = $this->render(array( 'vehicle_id' => $vehicle ));
		$second = $this->render(array( 'vehicle_id' => $vehicle ));

		$a = $this->ids($first);
		$b = $this->ids($second);
		$this->assertNotEmpty($a);
		$this->assertSame(array(), array_values(array_intersect($a, $b)), 'Two forms on one page share no id.');
		foreach (array_merge($a, $b) as $id) {
			$this->assertMatchesRegularExpression('/^rvs-bf-\d+-/', $id, 'Every id carries the instance prefix.');
		}
		$this->assertSame(count($a), count(array_unique($a)), 'No id repeats inside one form.');
	}

	/**
	 * Review Focus 3: even with the HTML cache forced on, the form is neither
	 * read from nor written to it, and the renderer runs on every request.
	 */
	public function test_booking_form_is_never_cached(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);
		$touched = array();
		$spy     = static function ($pre, string $option) use (&$touched) {
			if (0 === strpos($option, '_transient_mhmrentiva_shc_') || 0 === strpos($option, '_transient_mhmrentiva_rv_cache_v_rentiva_booking_form')) {
				$touched[] = 'read ' . $option;
			}
			return $pre;
		};
		$writes  = static function (string $transient) use (&$touched): void {
			if (0 === strpos($transient, 'mhmrentiva_shc_') || 'mhmrentiva_rv_cache_v_rentiva_booking_form' === $transient) {
				$touched[] = 'write ' . $transient;
			}
		};
		$renders = 0;
		$count   = static function ($located, string $relative) use (&$renders) {
			if ('shortcodes/booking-form' === $relative) {
				++$renders;
			}
			return $located;
		};
		add_filter('pre_option', $spy, 10, 2);
		add_action('setted_transient', $writes);
		add_filter('mhmrentiva_locate_template', $count, 10, 2);
		try {
			$vehicle = (string) ShortcodeFixtures::vehicle();
			$this->render(array( 'vehicle_id' => $vehicle ), false);
			$this->render(array( 'vehicle_id' => $vehicle ), false);
		} finally {
			remove_filter('pre_option', $spy, 10);
			remove_action('setted_transient', $writes);
			remove_filter('mhmrentiva_locate_template', $count, 10);
			remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);
		}

		$this->assertSame(array(), $touched, 'The booking form never touches the shortcode HTML cache.');
		$this->assertSame(2, $renders, 'The template runs on both requests.');
	}

	public function test_required_addon_is_locked_and_still_submitted(): void
	{
		$required = $this->addon('Basic insurance', '150', true, 1);
		$html     = $this->render(array( 'vehicle_id' => (string) ShortcodeFixtures::vehicle() ));

		$card = $this->addon_card($html, $required);
		$this->assertMatchesRegularExpression('/class="rvs-option rvs-option--locked"/', $card);
		$this->assertMatchesRegularExpression('/<input type="checkbox"[^>]*value="' . $required . '"[^>]*\schecked\b[^>]*\sdisabled\b/', $card);
		$this->assertDoesNotMatchRegularExpression('/<input type="checkbox"[^>]*\sname=/', $card, 'The disabled box is never the submitted field.');
		$this->assertMatchesRegularExpression('/<input type="hidden" name="addons\[\]" value="' . $required . '">/', $card, 'A disabled box is not submitted; the hidden field carries the id.');
		$this->assertStringContainsString('Required · included in the rental', $card);
	}

	public function test_required_addon_sold_more_than_once_gets_a_stepper_from_one(): void
	{
		$required = $this->addon('Snow chains', '20', true, 4);
		$html     = $this->render(array( 'vehicle_id' => (string) ShortcodeFixtures::vehicle() ));

		$card = $this->addon_card($html, $required);
		$this->assertMatchesRegularExpression('/data-rvs-stepper="' . $required . '" data-min="1" data-max="4"/', $card);
		$this->assertMatchesRegularExpression('/<input type="hidden" name="addon_qty\[' . $required . '\]" value="1">/', $card);
		$this->assertMatchesRegularExpression('/<input type="hidden" name="addons\[\]" value="' . $required . '">/', $card);
	}

	public function test_optional_addons_are_a_box_or_a_stepper_from_zero(): void
	{
		$gps  = $this->addon('GPS', '50', false, 1);
		$seat = $this->addon('Child seat', '10', false, 3);
		$html = $this->render(array( 'vehicle_id' => (string) ShortcodeFixtures::vehicle() ));

		$box = $this->addon_card($html, $gps);
		$this->assertMatchesRegularExpression('/<input type="checkbox" name="addons\[\]" value="' . $gps . '"/', $box);
		$this->assertDoesNotMatchRegularExpression('/\bchecked\b/', $box);
		$this->assertStringNotContainsString('addon_qty', $box, 'A maximum of 1 stays a checkbox.');

		$stepper = $this->addon_card($html, $seat);
		$this->assertMatchesRegularExpression('/data-rvs-stepper="' . $seat . '" data-min="0" data-max="3"/', $stepper);
		$this->assertMatchesRegularExpression('/<output[^>]*>0<\/output>/', $stepper);
		$this->assertMatchesRegularExpression('/<input type="hidden" name="addons\[\]" value="' . $seat . '" disabled>/', $stepper, 'At zero the add-on is not in the request.');
		$this->assertMatchesRegularExpression('/<input type="hidden" name="addon_qty\[' . $seat . '\]" value="0" disabled>/', $stepper);
		$this->assertStringContainsString('up to 3', $stepper);
	}

	public function test_required_addons_render_when_show_addons_is_off(): void
	{
		$required = $this->addon('Basic insurance', '150', true, 1);
		$this->addon('Child seat', '10', false, 3);
		$html = $this->render(array(
			'vehicle_id'  => (string) ShortcodeFixtures::vehicle(),
			'show_addons' => '0',
		));

		$this->assertMatchesRegularExpression('/<input type="hidden" name="addons\[\]" value="' . $required . '">/', $html);
		$this->assertStringNotContainsString('Child seat', $html);
	}

	public function test_change_link_carries_the_window_in_iso(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$this->go_to('/?mhmrentiva_vehicle_id=' . $vehicle . '&mhmrentiva_start_date=2026-11-06&mhmrentiva_end_date=2026-11-09&mhmrentiva_pickup_time=10:00');

		$html = $this->render(array(), true, false);

		$this->assertSame(1, preg_match('/<a [^>]*href="([^"]*)"[^>]*data-rvs-change/', $html, $m), 'The Change link renders with an href in Case A.');
		$href = wp_specialchars_decode($m[1]);
		$this->assertStringStartsWith(get_permalink($vehicle), $href);
		parse_str((string) wp_parse_url($href, PHP_URL_QUERY), $query);
		$this->assertSame('2026-11-06', $query['mhmrentiva_pickup_date'] ?? null);
		$this->assertSame('2026-11-09', $query['mhmrentiva_return_date'] ?? null);
		$this->assertSame('10:00', $query['mhmrentiva_pickup_time'] ?? null);
	}

	public function test_details_url_forwards_only_known_keys(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$url     = VehicleCardLinks::details_url($vehicle, array(
			'pickup_date' => '2026-11-06',
			'return_date' => '2026-11-09',
			'pickup_time' => '10:00',
			'stray'       => 'x',
			'return_time' => '',
		));

		parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
		parse_str((string) wp_parse_url(get_permalink($vehicle), PHP_URL_QUERY), $own);
		$query = array_diff_key($query, $own);
		$this->assertSame(
			array(
				'mhmrentiva_pickup_date' => '2026-11-06',
				'mhmrentiva_pickup_time' => '10:00',
				'mhmrentiva_return_date' => '2026-11-09',
			),
			$query
		);
		$this->assertStringStartsWith(get_permalink($vehicle), $url);
	}

	public function test_no_english_chips_in_case_b(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$html    = $this->render(array());

		foreach (array( 'Automatic', 'Petrol', '4 Seats' ) as $chip) {
			$this->assertStringNotContainsString($chip, $html);
		}
		$this->assertSame(1, preg_match('/<option value="' . $vehicle . '"[^>]*>/', $html, $option));
		$this->assertStringContainsString('data-permalink="' . esc_attr(get_permalink($vehicle)) . '"', $option[0]);
		$this->assertMatchesRegularExpression('/<a [^>]*data-rvs-change[^>]*\shidden/', $html, 'Case B: the Change link waits for a vehicle.');
	}

	public function test_vehicle_info_off_keeps_breakdown(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$title   = get_the_title($vehicle);

		$on  = $this->render(array( 'vehicle_id' => (string) $vehicle ));
		$off = $this->render(array(
			'vehicle_id'        => (string) $vehicle,
			'show_vehicle_info' => '0',
		));

		$this->assertStringContainsString('data-rvs-summary-vehicle', $on);
		$this->assertStringContainsString($title, $this->summary($on));

		$summary = $this->summary($off);
		$this->assertStringNotContainsString('data-rvs-summary-vehicle', $summary);
		$this->assertStringNotContainsString($title, $summary);
		$this->assertStringContainsString('data-rvs-summary-dates', $summary);
		$this->assertStringContainsString('data-rvs-breakdown', $summary);
		$this->assertStringContainsString('data-rvs-total', $off);
	}

	public function test_hidden_time_fields_carry_the_hooks(): void
	{
		$html = $this->render(array( 'show_time_select' => '0' ));

		$this->assertMatchesRegularExpression('/<input type="hidden" name="pickup_time" value="12:00" data-rvs-pickup-time>/', $html);
		$this->assertMatchesRegularExpression('/<input type="hidden" name="dropoff_time" value="12:00" data-rvs-dropoff-time>/', $html);
		$this->assertDoesNotMatchRegularExpression('/<select[^>]*data-rvs-pickup-time/', $html);
	}

	public function test_deposit_attribute_reaches_the_root(): void
	{
		$this->assertMatchesRegularExpression('/data-rvs-booking-form[^>]*data-rvs-deposit="1"/', $this->render(array()));
		$this->assertMatchesRegularExpression('/data-rvs-booking-form[^>]*data-rvs-deposit="0"/', $this->render(array( 'enable_deposit' => '0' )));
	}

	public function test_empty_form_title_falls_back_to_the_default_heading(): void
	{
		$this->assertMatchesRegularExpression('#<h2 class="rvs-booking__title"[^>]*>Complete your booking</h2>#', $this->render(array()));
		$this->assertMatchesRegularExpression('#<h2 class="rvs-booking__title"[^>]*>Zz Own Title</h2>#', $this->render(array( 'form_title' => 'Zz Own Title' )));
	}

	public function test_pickup_location_line_only_with_a_name(): void
	{
		$location = (object) array(
			'id'   => 7,
			'name' => 'Zz Airport',
		);
		$filter   = static fn() => array( $location );
		$vehicle  = ShortcodeFixtures::vehicle();

		$this->assertStringNotContainsString('data-rvs-summary-location', $this->render(array( 'vehicle_id' => (string) $vehicle )));

		add_filter('mhmrentiva_locations', $filter);
		try {
			$this->go_to('/?mhmrentiva_vehicle_id=' . $vehicle . '&mhmrentiva_pickup_location=7');
			$html = $this->render(array(), true, false);
		} finally {
			remove_filter('mhmrentiva_locations', $filter);
		}
		$this->assertMatchesRegularExpression('/data-rvs-summary-location[^>]*>[^<]*Zz Airport/', $html);
	}

	public function test_assets_are_the_rvs_chain_without_the_old_stylesheets(): void
	{
		AbstractShortcode::reset_enqueued_assets_for_tests();
		$this->render(array());

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-booking', 'enqueued'), 'rvs-booking.css is enqueued.');
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-forms', 'enqueued'));
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-breakdown', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-datepicker-custom', 'enqueued'), 'The form does not load datepicker-custom.css (R6).');
		$this->assertFalse(wp_style_is('mhm-rentiva-rentiva-booking-form', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-booking-form', 'enqueued'));
		$deps = wp_scripts()->registered['mhm-rentiva-booking-form']->deps ?? array();
		$this->assertContains('mhm-rentiva-rvs-stepper', $deps);
		$this->assertFileDoesNotExist(dirname(__DIR__, 3) . '/assets/css/frontend/booking-form.css');
	}

	// ------------------------------------------------------------ helpers

	/**
	 * @param array<string, string> $atts
	 */
	private function render(array $atts, bool $no_cache = true, bool $fresh_request = true): string
	{
		if ($fresh_request) {
			RequestSimulator::new_request();
		}
		if ($no_cache) {
			add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 1000);
		}
		try {
			return BookingForm::render($atts);
		} finally {
			if ($no_cache) {
				remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 1000);
			}
		}
	}

	private function addon(string $title, string $price, bool $required, int $max): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta($id, 'mhmrentiva_addon_price', $price);
		update_post_meta($id, 'mhmrentiva_addon_enabled', '1');
		if ($required) {
			update_post_meta($id, 'mhmrentiva_addon_required', '1');
		}
		if ($max > 1) {
			update_post_meta($id, MetaKeys::ADDON_MAX_QUANTITY, (string) $max);
		}

		return $id;
	}

	/**
	 * @return list<string>
	 */
	private function ids(string $html): array
	{
		preg_match_all('/\sid="([^"]+)"/', $html, $m);
		return $m[1];
	}

	private function addon_card(string $html, int $addon_id): string
	{
		$this->assertSame(1, preg_match('#<(label|div) class="rvs-option[^"]*" data-rvs-addon="' . $addon_id . '".*?</\1>\s*(?=<(?:label|div) class="rvs-option|</div>\s*</section>)#s', $html, $m), "Add-on card $addon_id not found.");
		return $m[0];
	}

	private function summary(string $html): string
	{
		$this->assertSame(1, preg_match('#<div class="rvs-booking__summary"[^>]*>.*?(?=<div class="rvs-booking__bar")#s', $html, $m), 'Summary region not found.');
		return $m[0];
	}
}
