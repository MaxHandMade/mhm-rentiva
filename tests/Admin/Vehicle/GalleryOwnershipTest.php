<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_Ajax_UnitTestCase;

/**
 * WP.org T6, third and fourth instance of the same defect, found by the second
 * pre-ZIP audit round after a systematic sweep of every AJAX handler.
 *
 * The three gallery handlers checked the blanket edit_posts and then wrote
 * _mhmrentiva_gallery_images on whichever post_id the request named. Because
 * the vehicle CPT registers with map_meta_cap and the default 'post'
 * capability_type, a stock Author who owns one listing holds edit_posts and is
 * handed a genuine nonce on their own vehicle's edit screen -- so they could
 * inject, delete or reorder images in any other vendor's public gallery.
 *
 * Unlike the fleet-wide blocked-dates operation, these touch only the single
 * named vehicle, so edit_post on that vehicle is the correct and sufficient
 * gate -- the same check save_meta_box() in this class already used.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::ajax_add_gallery_image
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::ajax_remove_gallery_image
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::ajax_reorder_gallery_images
 */
final class GalleryOwnershipTest extends WP_Ajax_UnitTestCase
{
	private const META_KEY = '_mhmrentiva_gallery_images';

	private int $author_id;
	private int $own_vehicle;
	private int $other_vehicle;
	private string $original;

	public function setUp(): void
	{
		parent::setUp();

		$this->author_id   = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->own_vehicle = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_author' => $this->author_id,
			)
		);
		$this->other_vehicle = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_author' => self::factory()->user->create( array( 'role' => 'administrator' ) ),
			)
		);

		update_post_meta(
			$this->other_vehicle,
			self::META_KEY,
			(string) wp_json_encode( array( array( 'id' => 4242, 'url' => 'http://example.org/a.jpg' ) ) )
		);

		// Read the stored form back: WordPress unslashes meta on the way through, so
		// comparing against the wp_json_encode() output would fail on escaping alone
		// and hide whether the content actually survived.
		$this->original = (string) get_post_meta( $this->other_vehicle, self::META_KEY, true );

		VehicleGallery::register();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	private function call( string $action ): void
	{
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the meta assertions are the check.
		}
	}

	public function test_author_cannot_add_images_to_another_vendors_gallery(): void
	{
		wp_set_current_user( $this->author_id );
		$this->assertTrue( current_user_can( 'edit_posts' ), 'Precondition: the old gate would pass.' );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->other_vehicle,
			'image_ids' => array( 9999 ),
		);
		$this->call( 'mhmrentiva_add_gallery_image' );

		$this->assertSame(
			$this->original,
			get_post_meta( $this->other_vehicle, self::META_KEY, true )
		);
	}

	public function test_author_cannot_remove_images_from_another_vendors_gallery(): void
	{
		wp_set_current_user( $this->author_id );

		$_POST = array(
			'nonce'    => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'  => $this->other_vehicle,
			'image_id' => 4242,
		);
		$this->call( 'mhmrentiva_remove_gallery_image' );

		$this->assertSame(
			$this->original,
			get_post_meta( $this->other_vehicle, self::META_KEY, true )
		);
	}

	public function test_author_cannot_reorder_another_vendors_gallery(): void
	{
		wp_set_current_user( $this->author_id );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->other_vehicle,
			'image_ids' => array( 1, 2, 3 ),
		);
		$this->call( 'mhmrentiva_reorder_gallery_images' );

		$this->assertSame(
			$this->original,
			get_post_meta( $this->other_vehicle, self::META_KEY, true )
		);
	}

	/**
	 * The owner of a vehicle must still be able to manage its own gallery.
	 */
	public function test_author_can_still_manage_their_own_gallery(): void
	{
		wp_set_current_user( $this->author_id );

		$_POST = array(
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->own_vehicle,
			'image_ids' => array( self::factory()->attachment->create() ),
		);
		$this->call( 'mhmrentiva_add_gallery_image' );

		$this->assertNotEmpty(
			get_post_meta( $this->own_vehicle, self::META_KEY, true ),
			'The vehicle owner must still be able to add to their own gallery.'
		);
	}
}
