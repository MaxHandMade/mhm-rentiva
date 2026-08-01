<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * WP.org T6: both booking metabox save handlers verified a nonce and then
 * threw the result away with a compound condition of the shape
 *
 *     if ( ! $nonce_valid && ! isset( $_POST['<one of our fields>'] ) ) { return; }
 *
 * which saves whenever our own field names are present, nonce or not -- i.e.
 * the nonce is decorative and any cross-site POST from a logged-in editor's
 * browser writes booking data (CSRF). BookingEditMetaBox was fixed in phase 1;
 * BookingMeta carried the identical bypass and is fixed here.
 *
 * Both handlers render wp_nonce_field() on their metabox and the classic
 * editor additionally sends _wpnonce, so every legitimate save presents one.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::save_meta
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox::save_booking_details
 */
final class BookingSaveNonceRequiredTest extends WP_UnitTestCase
{
	private int $booking_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->booking_id = self::factory()->post->create(
			array( 'post_type' => 'mhmrentiva_booking' )
		);

		wp_set_current_user(
			self::factory()->user->create( array( 'role' => 'administrator' ) )
		);

		$_POST = array();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * The bypass: our field names present, no nonce at all.
	 */
	public function test_booking_meta_save_is_refused_without_a_nonce(): void
	{
		update_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', '2026-01-01' );

		$_POST = array(
			'mhmrentiva_edit_pickup_date'   => '2030-12-31',
			'mhmrentiva_edit_special_notes' => 'injected by a cross-site form',
		);

		BookingMeta::save_meta( $this->booking_id, get_post( $this->booking_id ) );

		$this->assertSame(
			'2026-01-01',
			get_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', true ),
			'A POST carrying our field names but no nonce must not write booking meta.'
		);
		$this->assertSame(
			'',
			get_post_meta( $this->booking_id, '_mhmrentiva_special_notes', true )
		);
	}

	/**
	 * A forged/stale nonce value must be refused too, not merely a missing one.
	 */
	public function test_booking_meta_save_is_refused_with_an_invalid_nonce(): void
	{
		update_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', '2026-01-01' );

		$_POST = array(
			'mhmrentiva_booking_meta_main_nonce' => 'not-a-real-nonce',
			'mhmrentiva_edit_pickup_date'                => '2030-12-31',
		);

		BookingMeta::save_meta( $this->booking_id, get_post( $this->booking_id ) );

		$this->assertSame(
			'2026-01-01',
			get_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', true )
		);
	}

	/**
	 * The legitimate path still works -- the fix must not break real saves.
	 */
	public function test_booking_meta_save_succeeds_with_a_valid_nonce(): void
	{
		$_POST = array(
			'mhmrentiva_booking_meta_main_nonce' => wp_create_nonce( 'mhmrentiva_booking_meta_action' ),
			'mhmrentiva_edit_pickup_date'                => '2030-12-31',
			'mhmrentiva_edit_special_notes'              => 'legitimate note',
		);

		BookingMeta::save_meta( $this->booking_id, get_post( $this->booking_id ) );

		$this->assertSame(
			'2030-12-31',
			get_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', true )
		);
		$this->assertSame(
			'legitimate note',
			get_post_meta( $this->booking_id, '_mhmrentiva_special_notes', true )
		);
	}

	/**
	 * The classic editor's own nonce is an accepted alternative.
	 */
	public function test_booking_meta_save_accepts_the_core_update_post_nonce(): void
	{
		$_POST = array(
			'_wpnonce'             => wp_create_nonce( 'update-post_' . $this->booking_id ),
			'mhmrentiva_edit_pickup_date' => '2031-06-15',
		);

		BookingMeta::save_meta( $this->booking_id, get_post( $this->booking_id ) );

		$this->assertSame(
			'2031-06-15',
			get_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', true )
		);
	}

	/**
	 * Phase-1 regression guard: the sibling handler must stay nonce-mandatory.
	 */
	public function test_booking_edit_metabox_save_is_refused_without_a_nonce(): void
	{
		update_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', '2026-01-01' );

		$_POST = array(
			'mhmrentiva_booking_pickup_date' => '2030-12-31',
			'mhmrentiva_edit_pickup_date'    => '2030-12-31',
		);

		BookingEditMetaBox::save_booking_details( $this->booking_id );

		$this->assertSame(
			'2026-01-01',
			get_post_meta( $this->booking_id, '_mhmrentiva_pickup_date', true )
		);
	}
}
