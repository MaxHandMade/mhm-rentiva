<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_Ajax_UnitTestCase;

/**
 * One acceptance rule for every gallery writer: a new id must be an image the
 * actor owns (or may manage) and that no filter marks as sensitive; an id
 * already in the gallery need only be a displayable image.
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

	/**
	 * Store a gallery row the way a historical install holds it, bypassing the write guard.
	 */
	private function plant( string $value ): void
	{
		remove_filter( 'update_post_metadata', array( VehicleGallery::class, 'guard_gallery' ), 10 );
		update_post_meta( $this->vehicle, self::META_KEY, $value );
		add_filter( 'update_post_metadata', array( VehicleGallery::class, 'guard_gallery' ), 10, 4 );
	}

	private function assert_clean( string $writer, int $foreign, int $owned ): void
	{
		$stored = $this->stored();
		$ids    = array_map( 'intval', array_column( $stored, 'id' ) );
		$this->assertContains( $owned, $ids, $writer . ': owned entry survives' );
		$this->assertNotContains( $foreign, $ids, $writer . ': foreign entry dropped' );
		foreach ( $stored as $entry ) {
			$this->assertSame( (string) ( wp_get_attachment_image_url( (int) $entry['id'], 'medium' ) ?: wp_get_attachment_url( (int) $entry['id'] ) ), $entry['url'], $writer . ': url comes from the server' );
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

	public function test_non_ascii_and_quotes_survive_the_save_and_ajax_writers(): void
	{
		wp_set_current_user( $this->author_id );
		$owned = $this->image( $this->author_id );
		$extra = $this->image( $this->author_id );
		$alt   = 'Şık "araç"';
		$title = 'Ğüzel \\ araç';
		$json  = (string) wp_json_encode( array( array( 'id' => $owned, 'url' => '', 'alt' => $alt, 'title' => $title ) ) );

		$check = function ( string $writer ) use ( $owned, $alt, $title ): void {
			$stored = $this->stored();
			$this->assertNotEmpty( $stored, $writer . ': meta decodes' );
			$this->assertSame( $owned, (int) $stored[0]['id'], $writer );
			$this->assertSame( $alt, $stored[0]['alt'], $writer . ': alt round trip' );
			$this->assertSame( $title, $stored[0]['title'], $writer . ': title round trip' );
		};

		// save: $_POST is slashed by WordPress before it reaches the handler.
		$_POST = array(
			'mhmrentiva_gallery_images_nonce' => wp_create_nonce( 'mhmrentiva_gallery_images' ),
			'mhmrentiva_gallery_images'       => wp_slash( $json ),
		);
		VehicleGallery::save_gallery_images( $this->vehicle );
		$check( 'save' );

		// add rewrites the whole gallery.
		update_post_meta( $this->vehicle, self::META_KEY, wp_slash( $json ) );
		$_POST = array(
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->vehicle,
			'image_ids' => array( $extra ),
		);
		$this->call( 'mhmrentiva_add_gallery_image' );
		$check( 'add' );

		// remove.
		update_post_meta( $this->vehicle, self::META_KEY, wp_slash( $json ) );
		$_POST = array(
			'nonce'    => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'  => $this->vehicle,
			'image_id' => $extra,
		);
		$this->call( 'mhmrentiva_remove_gallery_image' );
		$check( 'remove' );

		// reorder.
		update_post_meta( $this->vehicle, self::META_KEY, wp_slash( $json ) );
		$_POST = array(
			'nonce'       => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'     => $this->vehicle,
			'image_order' => array( $owned ),
		);
		$this->call( 'mhmrentiva_reorder_gallery_images' );
		$check( 'reorder' );
	}

	public function test_string_ids_and_poisoned_gallery_are_filtered_on_every_writer(): void
	{
		wp_set_current_user( $this->author_id );
		$foreign = $this->image( $this->other_id );
		$owned   = $this->image( $this->author_id );
		$extra   = $this->image( $this->author_id );
		// A planted entry is already stored, so it only has to be displayable:
		// a foreign image would be kept like display keeps it. The stored poison
		// is therefore a non-image attachment, which no writer may keep.
		$pdf = $this->image( $this->other_id, 0, 'application/pdf', 'doc.pdf' );

		// 1. save_gallery_images: the posted JSON carries the poison (new to this gallery).
		$_POST = array(
			'mhmrentiva_gallery_images_nonce' => wp_create_nonce( 'mhmrentiva_gallery_images' ),
			'mhmrentiva_gallery_images'       => $this->poison( $foreign, $owned ),
		);
		VehicleGallery::save_gallery_images( $this->vehicle );
		$this->assert_clean( 'save', $foreign, $owned );

		// 2. add: the stored gallery is already poisoned.
		$this->plant( $this->poison( $pdf, $owned ) );
		$_POST = array(
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->vehicle,
			'image_ids' => array( $extra ),
		);
		$this->call( 'mhmrentiva_add_gallery_image' );
		$this->assert_clean( 'add', $pdf, $owned );
		$this->assertContains( $extra, array_map( 'intval', array_column( $this->stored(), 'id' ) ) );

		// 3. remove: a different, valid id.
		$this->plant( $this->poison( $pdf, $owned ) );
		$_POST = array(
			'nonce'    => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'  => $this->vehicle,
			'image_id' => $extra,
		);
		$this->call( 'mhmrentiva_remove_gallery_image' );
		$this->assert_clean( 'remove', $pdf, $owned );

		// 4. reorder: the client even asks for the planted id.
		$this->plant( $this->poison( $pdf, $owned ) );
		$_POST = array(
			'nonce'       => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'     => $this->vehicle,
			'image_order' => array( $pdf, $owned ),
		);
		$this->call( 'mhmrentiva_reorder_gallery_images' );
		$this->assert_clean( 'reorder', $pdf, $owned );
	}

	/**
	 * An admin adds an image they uploaded to a vendor's vehicle; the vendor's
	 * later writes (save, AJAX add/remove/reorder) keep it, because an entry
	 * already in the gallery need only be displayable.
	 */
	public function test_admin_image_already_in_vendor_gallery_survives_every_vendor_writer(): void
	{
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$adminer = $this->image( $admin );
		$owned   = $this->image( $this->author_id );
		$extra   = $this->image( $this->author_id );

		$seed = function () use ( $admin, $adminer, $owned ): void {
			wp_set_current_user( $admin );
			$this->assertNotFalse(
				update_post_meta(
					$this->vehicle,
					self::META_KEY,
					wp_slash( (string) wp_json_encode( array( array( 'id' => $adminer ), array( 'id' => $owned ) ) ) )
				)
			);
			wp_set_current_user( $this->author_id );
		};
		$ids = function (): array {
			return array_map( 'intval', array_column( $this->stored(), 'id' ) );
		};

		// save: the vendor presses Update with the gallery as the meta box shows it.
		$seed();
		$_POST = array(
			'mhmrentiva_gallery_images_nonce' => wp_create_nonce( 'mhmrentiva_gallery_images' ),
			'mhmrentiva_gallery_images'       => wp_slash( (string) wp_json_encode( array( array( 'id' => $adminer ), array( 'id' => $owned ) ) ) ),
		);
		VehicleGallery::save_gallery_images( $this->vehicle );
		$this->assertSame( array( $adminer, $owned ), $ids(), 'save keeps the admin image' );

		// add another image of the vendor's own.
		$seed();
		$_POST = array(
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->vehicle,
			'image_ids' => array( $extra ),
		);
		$this->call( 'mhmrentiva_add_gallery_image' );
		$this->assertSame( array( $adminer, $owned, $extra ), $ids(), 'add keeps the admin image' );

		// remove a different image.
		$_POST = array(
			'nonce'    => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'  => $this->vehicle,
			'image_id' => $extra,
		);
		$this->call( 'mhmrentiva_remove_gallery_image' );
		$this->assertSame( array( $adminer, $owned ), $ids(), 'remove keeps the admin image' );

		// reorder.
		$_POST = array(
			'nonce'       => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'     => $this->vehicle,
			'image_order' => array( $owned, $adminer ),
		);
		$this->call( 'mhmrentiva_reorder_gallery_images' );
		$this->assertSame( array( $owned, $adminer ), $ids(), 'reorder keeps the admin image' );

		// The vendor may still remove the admin image: removal only shortens the list.
		$_POST = array(
			'nonce'    => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'  => $this->vehicle,
			'image_id' => $adminer,
		);
		$this->call( 'mhmrentiva_remove_gallery_image' );
		$this->assertSame( array( $owned ), $ids(), 'vendor can remove the admin image' );
	}

	public function test_vendor_save_cannot_bring_in_a_foreign_image_next_to_kept_ones(): void
	{
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$adminer = $this->image( $admin );
		$foreign = $this->image( $this->other_id );

		wp_set_current_user( $admin );
		update_post_meta( $this->vehicle, self::META_KEY, wp_slash( (string) wp_json_encode( array( array( 'id' => $adminer ) ) ) ) );

		wp_set_current_user( $this->author_id );
		$_POST = array(
			'mhmrentiva_gallery_images_nonce' => wp_create_nonce( 'mhmrentiva_gallery_images' ),
			'mhmrentiva_gallery_images'       => wp_slash( (string) wp_json_encode( array( array( 'id' => $adminer ), array( 'id' => $foreign ) ) ) ),
		);
		VehicleGallery::save_gallery_images( $this->vehicle );
		$this->assertSame( array( $adminer ), array_map( 'intval', array_column( $this->stored(), 'id' ) ) );
	}

	public function test_kept_entry_vetoed_later_is_dropped_by_the_next_write(): void
	{
		wp_set_current_user( $this->author_id );
		$owned     = $this->image( $this->author_id );
		$sensitive = $this->image( $this->author_id );
		update_post_meta( $this->vehicle, self::META_KEY, wp_slash( (string) wp_json_encode( array( array( 'id' => $owned ), array( 'id' => $sensitive ) ) ) ) );

		add_filter(
			'mhmrentiva_gallery_attachment_allowed',
			static fn( $allowed, $att ) => (int) $att === $sensitive ? false : $allowed,
			10,
			2
		);

		$_POST = array(
			'nonce'       => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'     => $this->vehicle,
			'image_order' => array( $sensitive, $owned ),
		);
		$this->call( 'mhmrentiva_reorder_gallery_images' );
		$this->assertSame( array( $owned ), array_map( 'intval', array_column( $this->stored(), 'id' ) ) );
	}
}
