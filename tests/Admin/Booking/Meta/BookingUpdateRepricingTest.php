<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Core\BookingPricingInputs;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * An Update on the booking edit screen re-prices the booking only when an
 * input of its price changed: vehicle, pickup/return date or time, add-ons
 * and their quantities, payment type. Found by the functional sweep (H2,
 * 2026-11-04): changing only the status of a fully paid booking re-priced
 * it at today's rates (2.550 -> 2.890) and -- the offline paid amount being
 * total minus remaining -- counted the new total as paid.
 *
 * @covers \MHMRentiva\Admin\Booking\Core\BookingPricingInputs
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta
 */
final class BookingUpdateRepricingTest extends WP_UnitTestCase
{
	private int $vehicle;

	private int $booking;

	public function setUp(): void
	{
		parent::setUp();
		BookingPricingInputs::reset();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

		$this->vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($this->vehicle, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle, '_mhmrentiva_vehicle_status', 'active');

		// Priced at an older rate (850/day): today's rate is 1000.
		$this->booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		foreach (array(
			'_mhmrentiva_vehicle_id'       => (string) $this->vehicle,
			'_mhmrentiva_status'           => 'confirmed',
			'_mhmrentiva_payment_status'   => 'paid',
			'_mhmrentiva_payment_type'     => 'full',
			'_mhmrentiva_total_price'      => '2550',
			'_mhmrentiva_deposit_amount'   => '2550',
			'_mhmrentiva_remaining_amount' => '0',
			'_mhmrentiva_pickup_date'      => '2099-05-01',
			'_mhmrentiva_start_time'       => '10:00',
			'_mhmrentiva_dropoff_date'     => '2099-05-04',
			'_mhmrentiva_end_time'         => '10:00',
		) as $key => $value) {
			update_post_meta($this->booking, $key, $value);
		}

		// The production order on the generic save_post (BookingMeta::register,
		// BookingEditMetaBox::register): register() guards run once per process.
		add_action('save_post', array( BookingPricingInputs::class, 'open' ), 1, 2);
		add_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' ), 5);
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		add_action('save_post', array( BookingPricingInputs::class, 'close' ), 99, 2);
		add_action('mhmrentiva_booking_meta_updated', array( BookingMeta::class, 'on_booking_meta_updated' ), 10, 3);
	}

	public function tearDown(): void
	{
		$_POST = array();
		BookingPricingInputs::reset();
		parent::tearDown();
	}

	/**
	 * @param array<string, string|array<int, int>> $override
	 */
	private function update(array $override = array()): void
	{
		$_POST = $override + array(
			'_wpnonce'                     => wp_create_nonce('update-post_' . $this->booking),
			'mhmrentiva_edit_vehicle_id'   => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'  => '2099-05-01',
			'mhmrentiva_edit_pickup_time'  => '10:00',
			'mhmrentiva_edit_dropoff_date' => '2099-05-04',
			'mhmrentiva_edit_dropoff_time' => '10:00',
			'mhmrentiva_edit_status'       => 'confirmed',
		);
		wp_update_post(array( 'ID' => $this->booking, 'post_title' => 'Updated' ));
	}

	private function total(): string
	{
		return (string) get_post_meta($this->booking, '_mhmrentiva_total_price', true);
	}

	public function test_a_status_only_update_keeps_the_price(): void
	{
		$this->update(array( 'mhmrentiva_edit_status' => 'completed' ));

		$this->assertSame('2550', $this->total());
		$this->assertSame('2550', (string) get_post_meta($this->booking, '_mhmrentiva_deposit_amount', true));
		$this->assertSame('0', (string) get_post_meta($this->booking, '_mhmrentiva_remaining_amount', true));
	}

	public function test_a_status_only_update_keeps_the_add_on_lines(): void
	{
		$addon = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_addon',
			'post_status' => 'publish',
		));
		update_post_meta($addon, 'mhmrentiva_addon_price', '50');
		update_post_meta($addon, 'mhmrentiva_addon_enabled', '1');
		update_post_meta($this->booking, '_mhmrentiva_selected_addons', array( $addon ));
		update_post_meta($this->booking, '_mhmrentiva_addon_quantities', array( $addon => 1 ));
		$lines = array( array( 'id' => $addon, 'title' => 'Old', 'price' => 40.0, 'line_total' => 120.0 ) );
		update_post_meta($this->booking, '_mhmrentiva_addon_details', $lines);
		update_post_meta($this->booking, '_mhmrentiva_addon_total', '120');

		$this->update(array(
			'mhmrentiva_edit_status'          => 'completed',
			'mhmrentiva_edit_selected_addons' => array( $addon ),
		));

		$this->assertSame($lines, get_post_meta($this->booking, '_mhmrentiva_addon_details', true));
		$this->assertSame('120', (string) get_post_meta($this->booking, '_mhmrentiva_addon_total', true));
	}

	public function test_add_ons_posted_in_another_order_keep_the_price(): void
	{
		// The edit box lists add-ons by menu_order and appends unpublished ones,
		// so the posted order can differ from the stored one (Fable Ö1, #171).
		$a = $this->addon();
		$b = $this->addon();
		update_post_meta($this->booking, '_mhmrentiva_selected_addons', array( $a, $b ));
		update_post_meta($this->booking, '_mhmrentiva_addon_quantities', array( $a => 1, $b => 1 ));

		$this->update(array(
			'mhmrentiva_edit_status'          => 'completed',
			'mhmrentiva_edit_selected_addons' => array( $b, $a ),
		));

		$this->assertSame('2550', $this->total());
	}

	public function test_production_registers_the_scope_around_the_writers(): void
	{
		$registered = new \ReflectionProperty(BookingMeta::class, 'registered');
		$registered->setAccessible(true);
		$was = $registered->getValue();
		$registered->setValue(null, false);
		remove_all_actions('save_post');
		BookingMeta::register();
		BookingEditMetaBox::register();
		$registered->setValue(null, $was);

		$this->assertSame(1, has_action('save_post', array( BookingPricingInputs::class, 'open' )));
		$this->assertSame(5, has_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' )));
		$this->assertSame(10, has_action('save_post', array( BookingMeta::class, 'save_meta' )));
		$this->assertSame(99, has_action('save_post', array( BookingPricingInputs::class, 'close' )));
		// open/close take ($post_id, $post): one accepted arg would be a TypeError.
		global $wp_filter;
		foreach (array( 1 => 'open', 99 => 'close' ) as $priority => $method) {
			$entries = array_filter(
				$wp_filter['save_post']->callbacks[ $priority ],
				static fn($cb) => array( BookingPricingInputs::class, $method ) === $cb['function']
			);
			$this->assertSame(2, current($entries)['accepted_args'], $method);
		}
		// The customer e-mail entry points BookingCustomerEmailTemplateTest drives.
		$this->assertSame(10, has_action('wp_ajax_mhmrentiva_get_email_template', array( BookingMeta::class, 'ajax_get_email_template' )));
		$this->assertSame(10, has_action('wp_ajax_mhmrentiva_send_customer_email', array( BookingMeta::class, 'ajax_send_customer_email' )));
		$this->assertSame(10, has_action('admin_post_mhmrentiva_send_customer_email', array( BookingMeta::class, 'handle_send_customer_email' )));
	}

	private function addon(): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_addon',
			'post_status' => 'publish',
		));
		update_post_meta($id, 'mhmrentiva_addon_price', '50');
		update_post_meta($id, 'mhmrentiva_addon_enabled', '1');
		return $id;
	}

	public function test_an_unchanged_update_keeps_the_price(): void
	{
		$this->update();
		$this->assertSame('2550', $this->total());
	}

	public function test_a_date_change_reprices(): void
	{
		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-05' ));
		$this->assertNotSame('2550', $this->total(), 'A longer rental is re-priced.');
		$this->assertSame('4', (string) get_post_meta($this->booking, '_mhmrentiva_rental_days', true));
	}

	public function test_an_add_on_change_reprices(): void
	{
		$addon = $this->addon();

		$this->update(array( 'mhmrentiva_edit_selected_addons' => array( $addon ) ));
		$with        = (float) $this->total();
		$addon_total = (float) get_post_meta($this->booking, '_mhmrentiva_addon_total', true);

		// Taking it off again re-prices too: the difference is the add-on's line.
		$this->update();
		$without = (float) $this->total();

		$this->assertSame(50.0, $addon_total, 'The add-on is priced (per booking, 50).');
		$this->assertSame($addon_total, $with - $without, 'The add-on moves the total by its line.');
	}

	public function test_a_legacy_booking_keeps_its_price_on_a_status_only_update(): void
	{
		// The edit box shows legacy-key values; the first Update writes them under
		// the current keys -- not a change of price input (Codex #171 r1 bulgu 2).
		$a = $this->addon();
		delete_post_meta($this->booking, '_mhmrentiva_start_time');
		delete_post_meta($this->booking, '_mhmrentiva_end_time');
		update_post_meta($this->booking, '_mhmrentiva_pickup_time', '10:00');
		update_post_meta($this->booking, '_mhmrentiva_dropoff_time', '10:00');
		update_post_meta($this->booking, 'mhmrentiva_selected_addons', array( $a ));

		$this->update(array(
			'mhmrentiva_edit_status'          => 'completed',
			'mhmrentiva_edit_selected_addons' => array( $a ),
		));

		$this->assertSame('2550', $this->total());
	}

	public function test_a_legacy_vehicle_key_is_not_a_vehicle_change(): void
	{
		// Codex #171 r2 bulgu 3: the vehicle-change branch re-priced directly.
		delete_post_meta($this->booking, '_mhmrentiva_vehicle_id');
		update_post_meta($this->booking, '_mhmrentiva_booking_vehicle_id', (string) $this->vehicle);

		$this->update(array( 'mhmrentiva_edit_status' => 'completed' ));

		$this->assertSame('2550', $this->total());
		$this->assertSame((string) $this->vehicle, (string) get_post_meta($this->booking, '_mhmrentiva_vehicle_id', true));
	}

	public function test_a_legacy_add_on_keeps_its_quantity_under_a_lowered_maximum(): void
	{
		// Codex #171 r2 bulgu 2: the cap read the booked quantity through the
		// current id list only, so a legacy list dropped 3 to the new maximum 1.
		$a = $this->addon();
		update_post_meta($a, \MHMRentiva\Admin\Core\MetaKeys::ADDON_MAX_QUANTITY, '1');
		update_post_meta($this->booking, 'mhmrentiva_selected_addons', array( $a ));
		update_post_meta($this->booking, '_mhmrentiva_addon_quantities', array( $a => 3 ));

		$this->update(array(
			'mhmrentiva_edit_status'          => 'completed',
			'mhmrentiva_edit_selected_addons' => array( $a ),
			'mhmrentiva_edit_addon_qty'       => array( $a => '3' ),
		));

		$this->assertSame('2550', $this->total());
		$this->assertSame(array( $a => 3 ), get_post_meta($this->booking, '_mhmrentiva_addon_quantities', true));
	}

	public function test_a_legacy_add_on_list_does_not_shadow_its_removal(): void
	{
		// Codex #171 r2 bulgu 1: the legacy list stayed behind and brought back,
		// on screen and in the fingerprint, an add-on the operator unticked.
		$a = $this->addon();
		update_post_meta($this->booking, '_mhmrentiva_selected_addons', array( $a ));
		update_post_meta($this->booking, 'mhmrentiva_selected_addons', array( $a ));

		$this->update(array( 'mhmrentiva_edit_status' => 'completed' ));

		$this->assertSame('', get_post_meta($this->booking, 'mhmrentiva_selected_addons', true), 'The legacy list is gone.');
		$this->assertSame(array(), \MHMRentiva\Admin\Addons\AddonSelection::for_booking($this->booking)->ids());
		$this->assertNotSame('2550', $this->total(), 'Removing an add-on is a price input.');
	}

	public function test_a_single_legacy_add_on_id_is_read_as_a_list(): void
	{
		// Codex #171 r2 bulgu 4: render() casts with (array); a scalar legacy
		// id must not read as "no add-ons" in the fingerprint.
		$a = $this->addon();
		update_post_meta($this->booking, 'mhmrentiva_selected_addons', (string) $a);

		$this->update(array(
			'mhmrentiva_edit_status'          => 'completed',
			'mhmrentiva_edit_selected_addons' => array( $a ),
		));

		$this->assertSame('2550', $this->total());
	}

	public function test_fingerprint_follows_every_price_input(): void
	{
		$before = BookingPricingInputs::fingerprint($this->booking);
		update_post_meta($this->booking, '_mhmrentiva_status', 'completed');
		update_post_meta($this->booking, '_mhmrentiva_special_notes', 'note');
		$this->assertSame($before, BookingPricingInputs::fingerprint($this->booking), 'Status and notes are not price inputs.');

		foreach (array(
			'_mhmrentiva_vehicle_id'   => '999',
			'_mhmrentiva_pickup_date'  => '2099-05-02',
			'_mhmrentiva_start_time'   => '11:00',
			'_mhmrentiva_dropoff_date' => '2099-05-06',
			'_mhmrentiva_end_time'     => '12:00',
			'_mhmrentiva_payment_type' => 'deposit',
		) as $key => $value) {
			$old = get_post_meta($this->booking, $key, true);
			update_post_meta($this->booking, $key, $value);
			$this->assertNotSame($before, BookingPricingInputs::fingerprint($this->booking), $key . ' is a price input.');
			update_post_meta($this->booking, $key, $old);
		}
	}

	public function test_outside_a_save_a_recalculation_still_runs(): void
	{
		// Callers that are not an Update (no open scope) keep their behaviour.
		$this->assertTrue(BookingPricingInputs::changed($this->booking));
	}
}
