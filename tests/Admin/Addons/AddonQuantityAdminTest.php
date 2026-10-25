<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Booking\Addons\AddonBooking;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

/**
 * Aşama 2 PR G (K12): quantities after the booking exists -- the admin edit
 * screen, the admin re-price, the customer's account page and the add-on
 * revenue report. Three of these were wrong before quantities, too: the edit
 * screen wrote unit prices as the detail rows, and the report counted rows
 * at the unit price.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::recalculate_booking_costs
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox::save_booking_details
 * @covers \MHMRentiva\Admin\Booking\Addons\AddonBooking::get_addon_revenue_report
 */
final class AddonQuantityAdminTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_deposit', '10');
	}

	private function addon(string $title, string $price, string $type, int $max): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish', 'post_title' => $title ));
		update_post_meta($id, 'mhmrentiva_addon_price', $price);
		update_post_meta($id, '_mhmrentiva_addon_pricing_type', $type);
		update_post_meta($id, 'mhmrentiva_addon_enabled', '1');
		update_post_meta($id, MetaKeys::ADDON_MAX_QUANTITY, (string) $max);

		return $id;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function booking(array $meta = array()): int
	{
		return (int) self::factory()->post->create(
			array(
				'post_type'  => 'mhmrentiva_booking',
				'meta_input' => array_merge(
					array(
						'_mhmrentiva_vehicle_id'    => $this->vehicle_id,
						'_mhmrentiva_payment_type'  => 'full',
						'_mhmrentiva_status'        => 'confirmed',
						'_mhmrentiva_start_time'    => '10:00',
						'_mhmrentiva_end_time'      => '10:00',
						'_mhmrentiva_pickup_time'   => '10:00',
						'_mhmrentiva_dropoff_time'  => '10:00',
						'_mhmrentiva_pickup_date'   => '2099-11-02',
						'_mhmrentiva_dropoff_date'  => '2099-11-05',
					),
					$meta
				),
			)
		);
	}

	public function test_recalculate_keeps_quantity_and_rewrites_details(): void
	{
		$seat       = $this->addon('Child seat', '50', 'per_day', 3);
		$booking_id = $this->booking(array(
			'_mhmrentiva_selected_addons' => array( $seat ),
			MetaKeys::BOOKING_ADDON_QUANTITIES => array( $seat => 2 ),
		));

		// Monday -> Thursday: three weekdays.
		BookingMeta::recalculate_booking_costs($booking_id, '2026-11-02', '2026-11-05');

		$this->assertSame(300.0, (float) get_post_meta($booking_id, '_mhmrentiva_addon_total', true), '50 x 3 days x 2.');
		$this->assertSame(3300.0, (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true));
		$details = get_post_meta($booking_id, '_mhmrentiva_addon_details', true);
		$this->assertSame(300.0, (float) $details[0]['line_total']);
		$this->assertSame(2, $details[0]['quantity']);
		$this->assertSame(array( $seat => 2 ), get_post_meta($booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES, true));
	}

	public function test_edit_screen_saves_quantity(): void
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$seat       = $this->addon('Child seat', '50', 'per_day', 3);
		$gps        = $this->addon('GPS', '20', 'per_booking', 1);
		$booking_id = $this->booking();

		$_POST = array(
			'_wpnonce'                        => wp_create_nonce('update-post_' . $booking_id),
			'mhmrentiva_edit_vehicle_id'      => (string) $this->vehicle_id,
			'mhmrentiva_edit_pickup_date'     => '2099-11-02',
			'mhmrentiva_edit_pickup_time'     => '10:00',
			'mhmrentiva_edit_dropoff_date'    => '2099-11-05',
			'mhmrentiva_edit_dropoff_time'    => '10:00',
			'mhmrentiva_edit_status'          => 'confirmed',
			'mhmrentiva_edit_guests'          => '1',
			'mhmrentiva_edit_special_notes'   => '',
			'mhmrentiva_edit_selected_addons' => array( (string) $seat, (string) $gps ),
			'mhmrentiva_edit_addon_qty'       => array( $seat => '9', $gps => '4' ),
		);
		BookingEditMetaBox::save_booking_details($booking_id);

		$this->assertSame(array( $seat => 3 ), get_post_meta($booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES, true), 'Clamped to the maximum; a maximum of 1 stays 1.');
		$details = get_post_meta($booking_id, '_mhmrentiva_addon_details', true);
		$this->assertSame(450.0, (float) $details[0]['line_total'], 'A per-day row is priced for the rental days, not the unit price.');
		$this->assertSame(20.0, (float) $details[1]['line_total']);
		$this->assertSame(470.0, (float) get_post_meta($booking_id, '_mhmrentiva_addon_total', true));

		$html = $this->render_edit($booking_id);
		$this->assertSame(1, preg_match('/<input[^>]*name="mhmrentiva_edit_addon_qty\[' . $seat . '\]"[^>]*>/', $html, $input));
		$this->assertMatchesRegularExpression('/\bvalue="3"/', $input[0]);
		$this->assertStringNotContainsString('mhmrentiva_edit_addon_qty[' . $gps . ']', $html);
	}

	public function test_report_counts_units_and_line_totals(): void
	{
		$seat = $this->addon('Child seat', '50', 'per_day', 3);
		$gps  = $this->addon('GPS', '20', 'per_booking', 1);

		$new = $this->booking();
		update_post_meta($new, '_mhmrentiva_addon_details', array(
			array( 'id' => $seat, 'title' => 'Child seat', 'price' => 50.0, 'quantity' => 2, 'line_total' => 300.0 ),
		));
		// Stored before line totals existed: {id, title, price} only.
		$old = $this->booking();
		update_post_meta($old, '_mhmrentiva_addon_details', array(
			array( 'id' => $gps, 'title' => 'GPS', 'price' => 20.0 ),
		));
		wp_update_post(array( 'ID' => $new, 'post_date' => '2026-08-01 10:00:00' ));
		wp_update_post(array( 'ID' => $old, 'post_date' => '2026-08-02 10:00:00' ));

		$report = AddonBooking::get_addon_revenue_report(new \DateTime('2026-07-01'), new \DateTime('2026-09-01'));

		$this->assertSame(2, $report['addon_stats'][ $seat ]['count'], 'Units, not rows.');
		$this->assertSame(300.0, $report['addon_stats'][ $seat ]['revenue'], 'What the booking charged for the line.');
		$this->assertSame(1, $report['addon_stats'][ $gps ]['count']);
		$this->assertSame(20.0, $report['addon_stats'][ $gps ]['revenue'], 'An old row reads as 1 x price.');
		$this->assertSame(320.0, $report['total_revenue']);
	}

	public function test_account_detail_shows_quantity_label(): void
	{
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($customer);
		$seat       = $this->addon('Child seat', '50', 'per_day', 3);
		$gps        = $this->addon('GPS', '20', 'per_booking', 1);
		$booking_id = $this->booking(array(
			'_mhmrentiva_customer_user_id'     => $customer,
			'_mhmrentiva_selected_addons'      => array( $seat, $gps ),
			MetaKeys::BOOKING_ADDON_QUANTITIES => array( $seat => 2 ),
		));

		$html = AccountRenderer::render_booking_detail($booking_id, true);

		$this->assertStringContainsString('<li>Child seat × 2</li>', $html);
		$this->assertStringContainsString('<li>GPS</li>', $html);
	}

	public function test_max_quantity_field_shows_its_default_on_a_new_addon(): void
	{
		$addon_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon' ));
		$fields   = new \ReflectionMethod(\MHMRentiva\Admin\Addons\AddonMeta::class, 'get_fields');
		$fields->setAccessible(true);
		$field  = $fields->invoke(null)['addon_details']['fields'][ MetaKeys::ADDON_MAX_QUANTITY ];
		$render = new \ReflectionMethod(\MHMRentiva\Admin\Addons\AddonMeta::class, 'render_field');
		$render->setAccessible(true);

		ob_start();
		$render->invoke(null, get_post($addon_id), MetaKeys::ADDON_MAX_QUANTITY, $field);
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression('/name="' . MetaKeys::ADDON_MAX_QUANTITY . '" value="1"/', $html, 'An empty row reads as 1 everywhere; the field must say so.');
	}

	private function render_edit(int $booking_id): string
	{
		ob_start();
		try {
			BookingEditMetaBox::render(get_post($booking_id));
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}
}
