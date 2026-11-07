<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * The booking edit form (PR 3b-1, canvas v135).
 *
 * - Every <label for> points at a real input: the labels said
 *   `mhmrentiva_edit_*` while the inputs carry `mhmrentiva_booking_edit_*`, so
 *   clicking a label focused nothing and screen readers announced no name
 *   (spec bug 1).
 * - The guests field is gone from the canvas. Both savers defaulted guests to
 *   1 when the field was absent, so removing the input alone would have reset
 *   every booking's stored guests on its next Update (Review Focus 3).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta
 */
final class BookingEditFormMarkupTest extends WP_UnitTestCase
{
	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Toyota Corolla 1.6',
		));
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	private function booking(): \WP_Post
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		foreach (array(
			'_mhmrentiva_vehicle_id'    => (string) $this->vehicle,
			'_mhmrentiva_pickup_date'   => '2099-05-01',
			'_mhmrentiva_dropoff_date'  => '2099-05-04',
			'_mhmrentiva_status'        => 'confirmed',
			'_mhmrentiva_guests'        => '3',
			'_mhmrentiva_booking_guests' => '3',
		) as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return get_post($id);
	}

	private function form(\WP_Post $post): string
	{
		ob_start();
		BookingEditMetaBox::render($post);
		return (string) ob_get_clean();
	}

	public function test_every_label_points_at_its_input(): void
	{
		$html = $this->form($this->booking());

		preg_match_all('/<label[^>]*\sfor="([^"]+)"/', $html, $labels);
		$this->assertNotEmpty($labels[1], 'The form has labelled fields.');

		foreach ($labels[1] as $for) {
			$this->assertStringContainsString('id="' . $for . '"', $html, sprintf('<label for="%s"> points at nothing.', $for));
		}
	}

	public function test_no_guests_input(): void
	{
		$html = $this->form($this->booking());

		$this->assertStringNotContainsString('mhmrentiva_edit_guests', $html);
		$this->assertStringNotContainsString('Number of Guests', $html);
	}

	public function test_update_without_guests_field_keeps_stored_guests(): void
	{
		$post = $this->booking();
		add_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' ), 5);
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		$_POST = array(
			'mhmrentiva_booking_edit_meta_nonce' => wp_create_nonce('mhmrentiva_booking_edit_action'),
			'_wpnonce'                           => wp_create_nonce('update-post_' . $post->ID),
			'mhmrentiva_edit_vehicle_id'         => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'        => '2099-05-01',
			'mhmrentiva_edit_dropoff_date'       => '2099-05-04',
			'mhmrentiva_edit_status'             => 'confirmed',
		);

		wp_update_post(array( 'ID' => $post->ID, 'post_title' => 'Updated' ));

		$this->assertSame('3', (string) get_post_meta($post->ID, '_mhmrentiva_guests', true));
		$this->assertSame('3', (string) get_post_meta($post->ID, '_mhmrentiva_booking_guests', true));
	}

	public function test_a_posted_guests_value_is_still_saved(): void
	{
		// A form that does carry the field (an add-on, an older screen) keeps working.
		$post = $this->booking();
		add_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' ), 5);
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		$_POST = array(
			'mhmrentiva_booking_edit_meta_nonce' => wp_create_nonce('mhmrentiva_booking_edit_action'),
			'_wpnonce'                           => wp_create_nonce('update-post_' . $post->ID),
			'mhmrentiva_edit_vehicle_id'         => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'        => '2099-05-01',
			'mhmrentiva_edit_dropoff_date'       => '2099-05-04',
			'mhmrentiva_edit_guests'             => '5',
		);

		wp_update_post(array( 'ID' => $post->ID, 'post_title' => 'Updated' ));

		$this->assertSame('5', (string) get_post_meta($post->ID, '_mhmrentiva_guests', true));
	}
	/** Codex plan audit r1: the total line was a hidden "0,00" nothing filled. */
	public function test_addon_total_shows_the_stored_total(): void
	{
		$post  = $this->booking();
		$addon = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_addon',
			'post_status' => 'publish',
			'post_title'  => 'Child seat',
		));
		update_post_meta($post->ID, '_mhmrentiva_selected_addons', array( $addon ));
		update_post_meta($post->ID, '_mhmrentiva_addon_total', '1350');

		$html = $this->form($post);

		$this->assertStringContainsString('Additional Services Total:', $html);
		$this->assertStringContainsString(
			esc_html(\MHMRentiva\Admin\Core\CurrencyHelper::format_price(1350.0, 2)),
			$html
		);
		$this->assertStringNotContainsString('display: none', $html);
	}

	public function test_no_addon_total_line_without_a_stored_total(): void
	{
		$post  = $this->booking();
		self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_addon',
			'post_status' => 'publish',
			'post_title'  => 'Child seat',
		));

		$this->assertStringNotContainsString('Additional Services Total:', $this->form($post));
	}
}
