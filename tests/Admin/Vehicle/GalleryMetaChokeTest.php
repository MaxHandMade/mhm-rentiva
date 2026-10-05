<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_UnitTestCase;

/**
 * Direct writes of the gallery meta and every front-end read obey the image
 * policy, so an importer or a historical row cannot expose a foreign or
 * sensitive attachment.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::guard_gallery
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::get_gallery_for_frontend
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::filter_allowed_entries
 */
final class GalleryMetaChokeTest extends WP_UnitTestCase
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

	private function json( int ...$ids ): string
	{
		$entries = array();
		foreach ( $ids as $id ) {
			$entries[] = array( 'id' => $id, 'url' => '', 'alt' => '', 'title' => '' );
		}
		return (string) wp_json_encode( $entries );
	}

	private function raw_insert( string $key, string $value ): void
	{
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			array( 'post_id' => $this->vehicle, 'meta_key' => $key, 'meta_value' => $value ) // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		wp_cache_delete( $this->vehicle, 'post_meta' );
	}

	private function veto( int $id ): void
	{
		add_filter(
			'mhmrentiva_gallery_attachment_allowed',
			static fn( $allowed, $att ) => (int) $att === $id ? false : $allowed,
			10,
			2
		);
	}

	public function test_update_and_add_with_foreign_image_are_refused(): void
	{
		$foreign = $this->image( $this->other_id );
		$own     = $this->image( $this->author_id );

		$this->assertFalse( add_post_meta( $this->vehicle, self::META_KEY, $this->json( $foreign ), true ) );
		$this->assertSame( '', get_post_meta( $this->vehicle, self::META_KEY, true ) );

		$this->assertNotFalse( add_post_meta( $this->vehicle, self::META_KEY, $this->json( $own ), true ) );
		$before = get_post_meta( $this->vehicle, self::META_KEY, true );

		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $own, $foreign ) ) );
		$this->assertSame( $before, get_post_meta( $this->vehicle, self::META_KEY, true ) );
	}

	public function test_vetoed_image_is_refused_even_without_a_current_user(): void
	{
		$sensitive = $this->image( $this->author_id );
		$this->veto( $sensitive );

		wp_set_current_user( 0 );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $sensitive ) ) );
		$this->assertSame( '', get_post_meta( $this->vehicle, self::META_KEY, true ) );
	}

	public function test_allowed_gallery_is_stored_for_user_and_for_no_user(): void
	{
		$own = $this->image( $this->author_id );
		$this->assertNotFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $own ) ) );
		$this->assertSame( $own, (int) json_decode( (string) get_post_meta( $this->vehicle, self::META_KEY, true ), true )[0]['id'] );

		wp_set_current_user( 0 );
		$again = $this->image( $this->author_id );
		$this->assertNotFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $own, $again ) ) );
		$this->assertCount( 2, json_decode( (string) get_post_meta( $this->vehicle, self::META_KEY, true ), true ) );
	}

	public function test_other_keys_and_post_types_are_untouched(): void
	{
		$foreign = $this->image( $this->other_id );
		$this->assertNotFalse( update_post_meta( $this->vehicle, '_some_other_key', $this->json( $foreign ) ) );

		$post = self::factory()->post->create();
		$this->assertNotFalse( update_post_meta( $post, self::META_KEY, $this->json( $foreign ) ) );
	}

	public function test_frontend_reads_drop_vetoed_ids_including_legacy_keys(): void
	{
		$good      = $this->image( $this->author_id );
		$sensitive = $this->image( $this->author_id );
		$foreign   = $this->image( $this->other_id );
		$this->veto( $sensitive );

		$this->raw_insert( self::META_KEY, $this->json( $good, $sensitive, $foreign ) );
		$ids = array_column( VehicleGallery::get_gallery_for_frontend( $this->vehicle ), 'id' );
		$this->assertContains( $good, $ids );
		$this->assertNotContains( $sensitive, $ids );
		$this->assertNotContains( $foreign, $ids );

		$method = new \ReflectionMethod( VehicleDetails::class, 'get_gallery' );
		$method->setAccessible( true );
		$details = array_column( $method->invoke( null, $this->vehicle ), 'id' );
		$this->assertContains( $good, $details );
		$this->assertNotContains( $sensitive, $details );
		$this->assertNotContains( $foreign, $details );

		delete_post_meta( $this->vehicle, self::META_KEY );
		$this->raw_insert( '_mhm_gallery_images', $this->json( $good, $sensitive ) );
		$legacy = array_column( $method->invoke( null, $this->vehicle ), 'id' );
		$this->assertContains( $good, $legacy );
		$this->assertNotContains( $sensitive, $legacy );
	}

	public function test_stored_url_is_the_medium_rendition(): void
	{
		$id = $this->image( $this->author_id );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 1600,
				'height' => 1200,
				'file'   => 'photo.jpg',
				'sizes'  => array(
					'medium' => array( 'file' => 'photo-300x225.jpg', 'width' => 300, 'height' => 225, 'mime-type' => 'image/jpeg' ),
				),
			)
		);

		$entries = VehicleGallery::filter_allowed_entries( $this->vehicle, array( array( 'id' => $id ) ) );
		$this->assertSame( wp_get_attachment_image_url( $id, 'medium' ), $entries[0]['url'] );
		$this->assertStringContainsString( 'photo-300x225.jpg', $entries[0]['url'] );
	}
}
