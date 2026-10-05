<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use WP_UnitTestCase;

/**
 * The featured image of a vehicle goes through one choke at the meta layer, so
 * every writer (admin, REST, front end, a third-party add-on) is covered.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::guard_thumbnail
 */
final class ThumbnailChokeTest extends WP_UnitTestCase
{
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
		wp_set_current_user( $this->author_id );
	}

	public function tearDown(): void
	{
		remove_all_filters( 'mhmrentiva_gallery_attachment_allowed' );
		parent::tearDown();
	}

	private function image( int $author, int $parent = 0 ): int
	{
		return self::factory()->attachment->create_object(
			array(
				'file'           => 'photo.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $author,
				'post_parent'    => $parent,
			)
		);
	}

	public function test_foreign_thumbnail_is_blocked_on_update_and_add(): void
	{
		$foreign = $this->image( $this->other_id );
		$own     = $this->image( $this->author_id );

		// Add path: vehicle without a stored thumbnail.
		$this->assertFalse( add_post_meta( $this->vehicle, '_thumbnail_id', $foreign, true ) );
		$this->assertSame( '', get_post_meta( $this->vehicle, '_thumbnail_id', true ) );

		// Update path: vehicle that already has a thumbnail.
		update_post_meta( $this->vehicle, '_thumbnail_id', $own );
		update_post_meta( $this->vehicle, '_thumbnail_id', $foreign );
		$this->assertSame( (string) $own, get_post_meta( $this->vehicle, '_thumbnail_id', true ) );

		// Core's own setter goes through the same layer.
		$this->assertFalse( (bool) set_post_thumbnail( $this->vehicle, $foreign ) );
		$this->assertSame( (string) $own, get_post_meta( $this->vehicle, '_thumbnail_id', true ) );
	}

	public function test_allowed_thumbnail_is_actually_written(): void
	{
		$own = $this->image( $this->author_id );
		update_post_meta( $this->vehicle, '_thumbnail_id', $own );
		$this->assertSame( (string) $own, get_post_meta( $this->vehicle, '_thumbnail_id', true ) );

		$child = $this->image( $this->other_id, $this->vehicle );
		update_post_meta( $this->vehicle, '_thumbnail_id', $child );
		$this->assertSame( (string) $child, get_post_meta( $this->vehicle, '_thumbnail_id', true ) );
	}

	public function test_sensitive_image_vetoed_by_filter_is_blocked(): void
	{
		$own = $this->image( $this->author_id );
		add_filter( 'mhmrentiva_gallery_attachment_allowed', '__return_false' );
		update_post_meta( $this->vehicle, '_thumbnail_id', $own );
		$this->assertSame( '', get_post_meta( $this->vehicle, '_thumbnail_id', true ) );
	}

	public function test_user_zero_passes(): void
	{
		$foreign = $this->image( $this->other_id );
		wp_set_current_user( 0 );
		update_post_meta( $this->vehicle, '_thumbnail_id', $foreign );
		$this->assertSame( (string) $foreign, get_post_meta( $this->vehicle, '_thumbnail_id', true ) );
	}

	public function test_delete_post_meta_thumbnail_is_not_blocked(): void
	{
		$own = $this->image( $this->author_id );
		update_post_meta( $this->vehicle, '_thumbnail_id', $own );
		$this->assertTrue( delete_post_meta( $this->vehicle, '_thumbnail_id' ) );
		$this->assertSame( '', get_post_meta( $this->vehicle, '_thumbnail_id', true ) );
	}

	public function test_choke_is_active_outside_admin(): void
	{
		$this->assertFalse( is_admin(), 'REST-like request: not in wp-admin' );
		$foreign = $this->image( $this->other_id );
		update_post_meta( $this->vehicle, '_thumbnail_id', $foreign );
		$this->assertSame( '', get_post_meta( $this->vehicle, '_thumbnail_id', true ) );
	}

	public function test_other_post_types_and_keys_untouched(): void
	{
		$foreign = $this->image( $this->other_id );
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_post_meta( $page, '_thumbnail_id', $foreign );
		$this->assertSame( (string) $foreign, get_post_meta( $page, '_thumbnail_id', true ) );

		update_post_meta( $this->vehicle, '_some_other_key', $foreign );
		$this->assertSame( (string) $foreign, get_post_meta( $this->vehicle, '_some_other_key', true ) );
	}

	public function test_pass_returns_incoming_check_unchanged(): void
	{
		$own = $this->image( $this->author_id );
		$this->assertNull( \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::guard_thumbnail( null, $this->vehicle, '_thumbnail_id', $own ) );
		$this->assertSame( 'x', \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::guard_thumbnail( 'x', $this->vehicle, '_thumbnail_id', $own ) );
	}
}
