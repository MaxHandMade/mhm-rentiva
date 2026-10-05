<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_Ajax_UnitTestCase;

/**
 * One acceptance rule for every gallery writer: the id must be an image the
 * actor owns (or may manage) and that no filter marks as sensitive.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::is_allowed_image
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::filter_allowed_entries
 */
final class GalleryAllowedImageTest extends WP_Ajax_UnitTestCase
{
	private const META_KEY = '_mhmrentiva_gallery_images';

	private int $author_id;
	private int $other_id;
	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();

		$this->author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->other_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->vehicle   = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_author' => $this->author_id,
			)
		);

		VehicleGallery::register();
	}

	public function tearDown(): void
	{
		$_POST = array();
		remove_all_filters( 'mhmrentiva_gallery_attachment_allowed' );
		parent::tearDown();
	}

	private function image( int $author, int $parent = 0, string $mime = 'image/jpeg', string $file = 'photo.jpg' ): int
	{
		return self::factory()->attachment->create_object(
			array(
				'file'           => $file,
				'post_mime_type' => $mime,
				'post_author'    => $author,
				'post_parent'    => $parent,
			)
		);
	}

	private function call( string $action ): void
	{
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the meta assertions are the check.
		}
	}

	private function stored(): array
	{
		$decoded = json_decode( (string) get_post_meta( $this->vehicle, self::META_KEY, true ), true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private function poison( int $foreign, int $owned ): string
	{
		return (string) wp_json_encode(
			array(
				array( 'id' => (string) $foreign, 'url' => 'http://evil.example/x.pdf', 'alt' => '', 'title' => '' ),
				array( 'id' => $owned, 'url' => 'http://evil.example/own.jpg', 'alt' => '<b>a</b>', 'title' => 't' ),
			)
		);
	}

	private function assert_clean( string $writer, int $foreign, int $owned ): void
	{
		$stored = $this->stored();
		$ids    = array_map( 'intval', array_column( $stored, 'id' ) );
		$this->assertContains( $owned, $ids, $writer . ': owned entry survives' );
		$this->assertNotContains( $foreign, $ids, $writer . ': foreign entry dropped' );
		foreach ( $stored as $entry ) {
			$this->assertSame( wp_get_attachment_url( (int) $entry['id'] ), $entry['url'], $writer . ': url comes from the server' );
			$this->assertStringNotContainsString( '<', (string) $entry['alt'], $writer . ': alt sanitized' );
		}
	}

	public function test_foreign_vendor_image_is_rejected(): void
	{
		$foreign = $this->image( $this->other_id );
		$this->assertFalse( VehicleGallery::is_allowed_image( $this->vehicle, $foreign, $this->author_id ) );
	}

	public function test_own_upload_or_child_of_vehicle_is_accepted(): void
	{
		$own   = $this->image( $this->author_id );
		$child = $this->image( $this->other_id, $this->vehicle );
		$this->assertTrue( VehicleGallery::is_allowed_image( $this->vehicle, $own, $this->author_id ) );
		$this->assertTrue( VehicleGallery::is_allowed_image( $this->vehicle, $child, $this->author_id ) );
	}

	public function test_admin_with_edit_others_posts_accepts_any_image(): void
	{
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$foreign = $this->image( $this->other_id );
		$this->assertTrue( VehicleGallery::is_allowed_image( $this->vehicle, $foreign, $admin ) );
	}

	public function test_non_image_attachment_is_rejected(): void
	{
		$pdf = $this->image( $this->author_id, 0, 'application/pdf', 'doc.pdf' );
		$this->assertFalse( VehicleGallery::is_allowed_image( $this->vehicle, $pdf, $this->author_id ) );
		$this->assertFalse( VehicleGallery::is_allowed_image( $this->vehicle, $this->vehicle, $this->author_id ), 'A non-attachment post is not an image.' );
	}

	public function test_filter_false_rejects(): void
	{
		$own = $this->image( $this->author_id );
		add_filter( 'mhmrentiva_gallery_attachment_allowed', '__return_false' );
		$this->assertFalse( VehicleGallery::is_allowed_image( $this->vehicle, $own, $this->author_id ) );
	}

	public function test_filter_receives_id_vehicle_and_actor(): void
	{
		$own  = $this->image( $this->author_id );
		$seen = null;
		add_filter(
			'mhmrentiva_gallery_attachment_allowed',
			function ( $allowed, $id, $vehicle_id, $actor_id ) use ( &$seen ) {
				$seen = array( $allowed, $id, $vehicle_id, $actor_id );
				return $allowed;
			},
			10,
			4
		);
		VehicleGallery::is_allowed_image( $this->vehicle, $own, $this->author_id );
		$this->assertSame( array( true, $own, $this->vehicle, $this->author_id ), $seen );
	}

	public function test_actor_parameter_overrides_current_user(): void
	{
		$own = $this->image( $this->author_id );
		wp_set_current_user( $this->other_id );
		$this->assertFalse( VehicleGallery::is_allowed_image( $this->vehicle, $own ), 'Current user is the default actor.' );
		$this->assertTrue( VehicleGallery::is_allowed_image( $this->vehicle, $own, $this->author_id ) );
	}

	public function test_string_ids_and_poisoned_gallery_are_filtered_on_every_writer(): void
	{
		wp_set_current_user( $this->author_id );
		$foreign = $this->image( $this->other_id );
		$owned   = $this->image( $this->author_id );
		$extra   = $this->image( $this->author_id );

		// 1. save_gallery_images: the posted JSON carries the poison.
		$_POST = array(
			'mhmrentiva_gallery_images_nonce' => wp_create_nonce( 'mhmrentiva_gallery_images' ),
			'mhmrentiva_gallery_images'       => $this->poison( $foreign, $owned ),
		);
		VehicleGallery::save_gallery_images( $this->vehicle );
		$this->assert_clean( 'save', $foreign, $owned );

		// 2. add: the stored gallery is already poisoned.
		update_post_meta( $this->vehicle, self::META_KEY, $this->poison( $foreign, $owned ) );
		$_POST = array(
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->vehicle,
			'image_ids' => array( $extra ),
		);
		$this->call( 'mhmrentiva_add_gallery_image' );
		$this->assert_clean( 'add', $foreign, $owned );
		$this->assertContains( $extra, array_map( 'intval', array_column( $this->stored(), 'id' ) ) );

		// 3. remove: a different, valid id.
		update_post_meta( $this->vehicle, self::META_KEY, $this->poison( $foreign, $owned ) );
		$_POST = array(
			'nonce'    => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'  => $this->vehicle,
			'image_id' => $extra,
		);
		$this->call( 'mhmrentiva_remove_gallery_image' );
		$this->assert_clean( 'remove', $foreign, $owned );

		// 4. reorder: the client even asks for the foreign id.
		update_post_meta( $this->vehicle, self::META_KEY, $this->poison( $foreign, $owned ) );
		$_POST = array(
			'nonce'       => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'     => $this->vehicle,
			'image_order' => array( $foreign, $owned ),
		);
		$this->call( 'mhmrentiva_reorder_gallery_images' );
		$this->assert_clean( 'reorder', $foreign, $owned );
	}
}
