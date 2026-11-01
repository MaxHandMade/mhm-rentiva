<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks;
use MHMRentiva\Admin\Frontend\Shortcodes\ComparisonView;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\BookingUrl;
use MHMRentiva\Admin\Services\CompareService;
use WP_UnitTestCase;

/**
 * The comparison's data (R2, R14-R19, r2 m6, m9, m15): groups, formats, the
 * drawing's difference rule, columns, the header.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\ComparisonView
 */
final class ComparisonViewTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		delete_option('mhmrentiva_selected_features');
		parent::tearDown();
	}

	private function vehicle(array $meta = array(), array $post = array()): int
	{
		$id = (int) self::factory()->post->create(array_merge(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Car',
			),
			$post
		));
		update_post_meta($id, '_mhmrentiva_price_per_day', '1000');
		foreach ($meta as $key => $value) {
			update_post_meta($id, '_mhmrentiva_' . $key, $value);
		}

		return $id;
	}

	private function select(array $fields, array $settings = array()): void
	{
		update_option('mhmrentiva_settings', array_merge(array( 'comparison_fields' => $fields ), $settings));
	}

	/**
	 * @param list<array<string, mixed>> $groups
	 * @return array<string, array<string, mixed>>
	 */
	private function rows(array $groups): array
	{
		$rows = array();
		foreach ($groups as $group) {
			foreach ($group['rows'] as $row) {
				$rows[ $row['key'] ] = $row + array( 'group' => $group['key'] );
			}
		}

		return $rows;
	}

	public function test_groups_are_the_drawings_not_the_admin_categories(): void
	{
		$this->assertSame('details', ComparisonView::group_of('fuel_type', 'details'));
		$this->assertSame('features', ComparisonView::group_of('deposit', 'details'));
		$this->assertSame('features', ComparisonView::group_of('daily_km_limit', 'details'));
		$this->assertSame('features', ComparisonView::group_of(ComparisonView::CANCELLATION_KEY, ''));
		$this->assertSame('equipment', ComparisonView::group_of('bluetooth', 'features'));
		$this->assertSame('equipment', ComparisonView::group_of('gps', 'equipment'));
		$this->assertSame('details', ComparisonView::group_of('something_new', 'a_category_of_a_third_party'));
	}

	public function test_a_number_is_never_a_tick(): void
	{
		$this->assertSame('1', ComparisonView::format('seats', '1')['text']);
		$this->assertSame('0', ComparisonView::format('doors', '0')['text']);
		$this->assertSame('1 large suitcase', ComparisonView::format('vehicle_max_big_luggage', '1')['text']);
		$this->assertSame('1 year licence', ComparisonView::format('min_license_years', '1')['text']);
		$this->assertSame('300 km/day', ComparisonView::format('daily_km_limit', '300')['text']);
		$this->assertSame('21+', ComparisonView::format('min_driver_age', '21')['text']);
	}

	public function test_empty_values(): void
	{
		$km = ComparisonView::format('daily_km_limit', '');
		$this->assertSame('Unlimited', $km['text']);
		$this->assertTrue($km['empty']);

		foreach (array( 'min_driver_age', 'min_license_years', 'vehicle_max_big_luggage', 'seats' ) as $key) {
			$this->assertSame(array( 'text' => '–', 'value' => '', 'empty' => true ), ComparisonView::format($key, ''), $key);
		}
	}

	public function test_a_boolean_word_reads_yes_or_no(): void
	{
		$this->assertSame(array( 'text' => 'Yes', 'value' => '1', 'empty' => false ), ComparisonView::format('available', 'yes'));
		$this->assertSame(array( 'text' => 'No', 'value' => '0', 'empty' => false ), ComparisonView::format('available', 'off'));
	}

	public function test_coded_values_compare_by_code_and_print_their_label(): void
	{
		$cell = ComparisonView::format('fuel_type', 'electric');
		$this->assertSame('Electric', $cell['text']);
		$this->assertSame('electric', $cell['value']);
	}

	public function test_equipment_reads_yes_and_no_from_the_vehicles_lists(): void
	{
		$has  = $this->vehicle(array( 'features' => array( 'bluetooth' ) ));
		$lacks = $this->vehicle();

		$this->assertSame('Yes', ComparisonView::cell($has, 'bluetooth', 'features')['text']);
		$this->assertSame('No', ComparisonView::cell($lacks, 'bluetooth', 'features')['text']);
	}

	public function test_a_rate_prints_as_a_percentage(): void
	{
		$id = $this->vehicle(array( 'deposit' => '10' ));

		$cell = ComparisonView::cell($id, 'deposit', 'details');
		$this->assertStringContainsString('10', $cell['text']);
		$this->assertStringContainsString('%', $cell['text']);
		$this->assertSame('10', $cell['value']);
	}

	public function test_no_deposit_reads_full_payment_and_keeps_the_row(): void
	{
		$this->select(
			array( 'details' => array( 'deposit' ) ),
			array( 'mhmrentiva_rental_deposit_type' => 'full_payment', 'mhmrentiva_booking_cancellation_deadline_hours' => 0 )
		);
		$a = $this->vehicle();
		$b = $this->vehicle();

		$this->assertSame(array( 'text' => 'Full payment', 'value' => '0', 'empty' => false ), ComparisonView::cell($a, 'deposit', 'details'));

		$row = $this->rows(ComparisonView::groups(array( $a, $b )))['deposit'];
		$this->assertSame('features', $row['group']);
		$this->assertTrue($row['all_same']);
	}

	/**
	 * @dataProvider difference_cases
	 *
	 * @param list<string> $values
	 * @param list<bool>   $different
	 */
	public function test_the_drawings_difference_rule(array $values, array $different, bool $all_same): void
	{
		$cells = array_map(static fn (string $value): array => array( 'text' => $value, 'value' => $value, 'empty' => false ), $values);

		$row = ComparisonView::row('k', 'Label', $cells);

		$this->assertSame($different, array_column($row['cells'], 'different'));
		$this->assertSame($all_same, $row['all_same']);
	}

	/**
	 * @return array<string, array{0: list<string>, 1: list<bool>, 2: bool}>
	 */
	public function difference_cases(): array
	{
		return array(
			'all the same'   => array( array( 'a', 'a', 'a' ), array( false, false, false ), true ),
			'one stands out' => array( array( 'a', 'a', 'b' ), array( false, false, true ), false ),
			'all differ'     => array( array( 'a', 'b', 'c' ), array( true, true, true ), false ),
			'two differ'     => array( array( 'a', 'b' ), array( true, true ), false ),
			'one vehicle'    => array( array( 'a' ), array( false ), true ),
		);
	}

	public function test_a_row_empty_for_every_vehicle_is_not_drawn(): void
	{
		$this->select(array( 'details' => array( 'daily_km_limit', 'min_driver_age', 'seats' ) ));
		$a = $this->vehicle(array( 'seats' => '5' ));
		$b = $this->vehicle(array( 'seats' => '5' ));

		$rows = $this->rows(ComparisonView::groups(array( $a, $b )));

		$this->assertArrayNotHasKey('daily_km_limit', $rows, 'Even the row that would read "Unlimited".');
		$this->assertArrayNotHasKey('min_driver_age', $rows);
		$this->assertArrayHasKey('seats', $rows);
	}

	public function test_one_empty_cell_keeps_the_row_and_reads_unlimited(): void
	{
		$this->select(array( 'details' => array( 'daily_km_limit' ) ));
		$a = $this->vehicle(array( 'daily_km_limit' => '300' ));
		$b = $this->vehicle();
		$c = $this->vehicle();

		$row = $this->rows(ComparisonView::groups(array( $a, $b, $c )))['daily_km_limit'];

		$this->assertSame(array( '300 km/day', 'Unlimited', 'Unlimited' ), array_column($row['cells'], 'text'));
		$this->assertSame(array( true, false, false ), array_column($row['cells'], 'different'));
		$this->assertSame('features', $row['group']);
	}

	public function test_empty_groups_are_left_out(): void
	{
		$this->select(array( 'details' => array( 'seats' ) ), array( 'mhmrentiva_booking_cancellation_deadline_hours' => 0 ));
		$groups = ComparisonView::groups(array( $this->vehicle(array( 'seats' => '5' )), $this->vehicle(array( 'seats' => '4' )) ));

		$this->assertSame(array( 'details' ), array_column($groups, 'key'));
	}

	public function test_the_price_is_no_row(): void
	{
		$this->select(array( 'details' => array( 'price_per_day', 'seats' ) ));

		$rows = $this->rows(ComparisonView::groups(array( $this->vehicle(array( 'seats' => '5' )), $this->vehicle(array( 'seats' => '5' )) )));

		$this->assertArrayNotHasKey('price_per_day', $rows);
	}

	public function test_the_cancellation_row_follows_the_site_setting(): void
	{
		$a = $this->vehicle(array( 'seats' => '5' ));
		$b = $this->vehicle(array( 'seats' => '5' ));

		$this->select(array( 'details' => array( 'seats' ) ), array( 'mhmrentiva_booking_cancellation_deadline_hours' => 24 ));
		$row = $this->rows(ComparisonView::groups(array( $a, $b )))[ ComparisonView::CANCELLATION_KEY ];
		$this->assertSame('features', $row['group']);
		$this->assertSame('24 hours before', $row['cells'][0]['text']);
		$this->assertTrue($row['all_same'], 'So "only differences" hides it.');

		$this->select(array( 'details' => array( 'seats' ) ), array( 'mhmrentiva_booking_cancellation_deadline_hours' => 0 ));
		$this->assertArrayNotHasKey(ComparisonView::CANCELLATION_KEY, $this->rows(ComparisonView::groups(array( $a, $b ))));
	}

	public function test_an_emptied_selection_compares_nothing_not_even_the_cancellation(): void
	{
		$this->select(array(), array( 'mhmrentiva_booking_cancellation_deadline_hours' => 24 ));

		$this->assertSame(array(), ComparisonView::groups(array( $this->vehicle(), $this->vehicle() )));
	}

	public function test_a_passive_field_is_no_row_though_the_vehicle_has_it(): void
	{
		update_option('mhmrentiva_selected_features', array( 'bluetooth' ));
		$this->select(array( 'features' => array( 'bluetooth', 'navigation' ) ));
		$rows = $this->rows(ComparisonView::groups(array(
			$this->vehicle(array( 'features' => array( 'bluetooth', 'navigation' ) )),
			$this->vehicle(),
		)));

		$this->assertArrayHasKey('bluetooth', $rows);
		$this->assertArrayNotHasKey('navigation', $rows);
	}

	public function test_default_selection_has_the_conditions_and_deposit_in_order(): void
	{
		$keys = array_keys(ComparisonView::selected_rows());

		foreach (array( 'daily_km_limit', 'min_driver_age', 'min_license_years', 'vehicle_max_big_luggage', 'deposit' ) as $key) {
			$this->assertContains($key, $keys);
		}
		$this->assertLessThan(array_search('daily_km_limit', $keys, true), array_search('year', $keys, true));
	}

	public function test_the_limit_never_passes_the_lists(): void
	{
		$this->assertSame(CompareService::max_items(), ComparisonView::max_vehicles(array( 'max_vehicles' => '6' )));
		$this->assertSame(2, ComparisonView::max_vehicles(array( 'max_vehicles' => '2' )));
		$this->assertSame(2, ComparisonView::max_vehicles(array( 'max_vehicles' => '0' )));
		$this->assertSame(CompareService::max_items(), ComparisonView::max_vehicles(array()));
	}

	public function test_a_vehicle_out_of_service_or_transfer_only_or_private_is_not_compared(): void
	{
		$ok        = $this->vehicle();
		$out       = $this->vehicle(array( 'vehicle_status' => 'maintenance' ));
		$transfer  = $this->vehicle(array( 'vehicle_service_type' => 'transfer' ));
		$draft     = $this->vehicle(array(), array( 'post_status' => 'draft' ));
		$protected = $this->vehicle(array(), array( 'post_password' => 'secret' ));

		$view = ComparisonView::build(array(), array( $out, $transfer, $draft, $protected, $ok ), true);

		$this->assertSame(array( $ok ), array_column($view['columns'], 'id'));
		$this->assertFalse($view['has_vehicles']);
	}

	public function test_a_filtered_vehicle_does_not_take_a_seat(): void
	{
		$out  = $this->vehicle(array( 'vehicle_status' => 'inactive' ));
		$list = array( $out, $this->vehicle(), $this->vehicle(), $this->vehicle() );

		$view = ComparisonView::build(array(), $list, true);

		$this->assertCount(3, $view['columns']);
		$this->assertNotContains($out, array_column($view['columns'], 'id'));
	}

	public function test_without_dates_a_column_has_the_daily_price(): void
	{
		$view   = ComparisonView::build(array(), array( $this->vehicle(), $this->vehicle() ), true, array( 'pickup_location' => '7' ));
		$column = $view['columns'][0];

		$this->assertFalse($column['is_total']);
		$this->assertStringContainsString(PriceDisplay::for_shop(1000.0)['html'], $column['price_html']);
		$this->assertSame('per day', $column['days_label']);
		$this->assertSame('2 vehicles · up to 3', $view['header']);
		$this->assertSame('', $view['range']);
		$this->assertFalse($column['booked']);
	}

	public function test_with_dates_a_column_has_the_total_and_the_header_the_range(): void
	{
		$dates  = array( 'pickup_date' => '2027-03-08', 'return_date' => '2027-03-11', 'pickup_time' => '10:00' );
		$id     = $this->vehicle();
		$view   = ComparisonView::build(array(), array( $id, $this->vehicle() ), true, $dates);
		$column = $view['columns'][0];

		$this->assertTrue($column['is_total']);
		$this->assertSame('3 days', $column['days_label']);
		$this->assertStringStartsWith('2 vehicles · up to 3 · Prices for ', $view['header']);
		$this->assertStringContainsString(' – ', $view['range']);
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-03-08', html_entity_decode($column['booking_url']));
		$this->assertStringContainsString('mhmrentiva_vehicle_id=' . $id, $column['booking_url']);
		$this->assertStringStartsWith(BookingUrl::base_url(), $column['booking_url']);
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-03-08', $view['browse_url']);
	}

	public function test_the_column_carries_the_price_suffix(): void
	{
		if (! class_exists('WC_Product_Simple')) {
			$this->markTestSkipped('WooCommerce is not loaded.');
		}
		update_option('woocommerce_calc_taxes', 'yes');
		update_option('woocommerce_price_display_suffix', 'incl. test VAT');

		$view = ComparisonView::build(array(), array( $this->vehicle(), $this->vehicle() ), true, array( 'pickup_location' => '7' ));

		$this->assertStringContainsString('incl. test VAT', $view['columns'][0]['price_html']);
	}

	public function test_a_booked_vehicle_gets_its_cards_note_and_link(): void
	{
		$dates = array( 'pickup_date' => '2027-03-08', 'return_date' => '2027-03-11', 'pickup_time' => '10:00' );
		$id    = $this->vehicle(array( 'blocked_dates' => array( '2027-03-09' ) ));

		$view   = ComparisonView::build(array(), array( $id, $this->vehicle() ), true, $dates);
		$column = $view['columns'][0];
		$free   = $view['columns'][1];

		$this->assertTrue($column['booked']);
		$this->assertStringStartsWith('Earliest free: ', $column['booked_note']);
		$this->assertSame('See from that date', $column['booking_label']);
		$this->assertStringNotContainsString('mhmrentiva_vehicle_id', $column['booking_url'], 'The vehicle page, not the form.');
		$this->assertStringContainsString('mhmrentiva_pickup_date=', $column['booking_url']);

		$this->assertFalse($free['booked']);
		$this->assertSame('', $free['booked_note']);
		$this->assertSame('Select', $free['booking_label']);
	}

	public function test_adding_is_offered_only_with_room_on_the_visitors_own_list(): void
	{
		add_filter('mhmrentiva_shortcode_url_rentiva_search_results', static fn (): string => 'http://example.org/results/');
		$ids = array( $this->vehicle(), $this->vehicle() );

		$url = ComparisonView::build(array(), $ids, false)['add_url'];
		if ('' === $url) {
			$this->markTestSkipped('No search results page in this environment.');
		}

		$this->assertTrue(ComparisonView::build(array(), $ids, false)['can_add']);
		$this->assertFalse(ComparisonView::build(array(), $ids, true)['can_add'], 'vehicle_ids: a fixed set.');
		$this->assertFalse(ComparisonView::build(array( 'max_vehicles' => '2' ), $ids, false)['can_add'], 'At the limit.');
	}

	public function test_toggles_and_remove_flag(): void
	{
		$ids = array( $this->vehicle(array(), array( 'post_title' => 'Duster' )), $this->vehicle() );

		$on = ComparisonView::build(array(), $ids, false);
		$this->assertTrue($on['columns'][0]['remove']);
		$this->assertSame('Remove Duster from comparison', $on['columns'][0]['remove_label']);
		$this->assertTrue($on['show']['booking']);

		$off = ComparisonView::build(array( 'show_remove_buttons' => '0', 'show_book_button' => '0', 'show_prices' => '0', 'show_images' => '0' ), $ids, false);
		$this->assertFalse($off['columns'][0]['remove']);
		$this->assertSame(array( 'prices' => false, 'images' => false, 'remove' => false, 'booking' => false ), $off['show']);
	}

	public function test_links_helper_keeps_only_the_window(): void
	{
		$this->assertSame('', VehicleCardLinks::with_window('', array( 'pickup_date' => '2027-01-01' )));
		$url = VehicleCardLinks::with_window('http://example.org/r/', array( 'pickup_date' => '2027-01-01', 'other' => 'x' ));
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-01-01', $url);
		$this->assertStringNotContainsString('other', $url);
	}
}
