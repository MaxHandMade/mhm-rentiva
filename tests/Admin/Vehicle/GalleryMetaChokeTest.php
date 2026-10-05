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
		$this->assertContains( $foreign, $ids, 'Display does not re-decide ownership.' );

		$method = new \ReflectionMethod( VehicleDetails::class, 'get_gallery' );
		$method->setAccessible( true );
		$details = array_column( $method->invoke( null, $this->vehicle ), 'id' );
		$this->assertContains( $good, $details );
		$this->assertNotContains( $sensitive, $details );
		$this->assertContains( $foreign, $details );

		delete_post_meta( $this->vehicle, self::META_KEY );
		// prefix-rename:ignore-start
		$this->raw_insert( '_mhm_gallery_images', $this->json( $good, $sensitive ) );
		// prefix-rename:ignore-end
		$legacy = array_column( $method->invoke( null, $this->vehicle ), 'id' );
		$this->assertContains( $good, $legacy );
		$this->assertNotContains( $sensitive, $legacy );
	}

	public function test_admin_uploaded_image_is_displayed_and_accepted_for_user_zero(): void
	{
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$adminer = $this->image( $admin );
		$foreign = $this->image( $this->other_id );
		$vetoed  = $this->image( $admin );
		$this->veto( $vetoed );

		$this->raw_insert( self::META_KEY, $this->json( $adminer, $vetoed, $foreign ) );
		$method = new \ReflectionMethod( VehicleDetails::class, 'get_gallery' );
		$method->setAccessible( true );
		foreach ( array(
			array_column( VehicleGallery::get_gallery_for_frontend( $this->vehicle ), 'id' ),
			array_column( $method->invoke( null, $this->vehicle ), 'id' ),
		) as $ids ) {
			$this->assertContains( $adminer, $ids );
			$this->assertNotContains( $vetoed, $ids );
			}

		delete_post_meta( $this->vehicle, self::META_KEY );
		wp_set_current_user( 0 );
		$this->assertNotFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $adminer ) ) );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $adminer, $vetoed ) ) );
	}

	public function test_guards_are_registered_by_the_plugin_constructor_not_only_on_init(): void
	{
		$hooks  = array( 'update_post_metadata', 'add_post_metadata' );
		$guards = array( 'guard_gallery', 'guard_thumbnail' );
		foreach ( $hooks as $hook ) {
			foreach ( $guards as $guard ) {
				remove_filter( $hook, array( VehicleGallery::class, $guard ), 10 );
			}
		}

		// The constructor is what runs on plugins_loaded; init has long fired here.
		$ref      = new \ReflectionClass( \MHMRentiva\Plugin::class );
		$instance = $ref->newInstanceWithoutConstructor();
		$ctor     = $ref->getConstructor();
		$ctor->setAccessible( true );
		$ctor->invoke( $instance );

		try {
			foreach ( $hooks as $hook ) {
				foreach ( $guards as $guard ) {
					$this->assertNotFalse( has_filter( $hook, array( VehicleGallery::class, $guard ) ), "$guard on $hook" );
				}
			}

			$foreign = $this->image( $this->other_id );
			$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $foreign ) ) );
		} finally {
			// Drop the throwaway instance's own hooks so it cannot act for the rest of the run.
			global $wp_filter;
			foreach ( $wp_filter as $tag => $hook_obj ) {
				foreach ( $hook_obj->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $cb ) {
						if ( is_array( $cb['function'] ) && $cb['function'][0] === $instance ) {
							remove_filter( $tag, $cb['function'], $priority );
						}
					}
				}
			}
		}
	}

	public function test_foreign_vendor_image_is_still_refused_for_a_signed_in_vendor(): void
	{
		$foreign = $this->image( $this->other_id );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, $this->json( $foreign ) ) );
	}

	public function test_array_and_invalid_stored_values_do_not_break_readers_or_writers(): void
	{
		$own = $this->image( $this->author_id );
		$ex  = $this->image( $this->author_id );

		// An early write can leave the value as a serialized PHP array.
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			array( 'post_id' => $this->vehicle, 'meta_key' => self::META_KEY, 'meta_value' => maybe_serialize( array( array( 'id' => $own, 'url' => '', 'alt' => 'a', 'title' => 't' ) ) ) ) // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		wp_cache_delete( $this->vehicle, 'post_meta' );

		$this->assertSame( $own, (int) VehicleGallery::get_gallery_images( $this->vehicle )[0]['id'] );
		$this->assertContains( $own, array_column( VehicleGallery::get_gallery_for_frontend( $this->vehicle ), 'id' ) );

		$post = get_post( $this->vehicle );
		ob_start();
		VehicleGallery::render_gallery_meta_box( $post );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( (string) $own, $html );

		// Invalid JSON stored raw.
		delete_post_meta( $this->vehicle, self::META_KEY );
		$this->raw_insert( self::META_KEY, '{not json' );
		$this->assertSame( array(), VehicleGallery::get_gallery_images( $this->vehicle ) );
		$this->assertSame( array(), VehicleGallery::get_gallery_for_frontend( $this->vehicle ) );
		ob_start();
		VehicleGallery::render_gallery_meta_box( $post );
		ob_end_clean();
	}

	public function test_early_array_write_is_still_policy_checked(): void
	{
		$foreign = $this->image( $this->other_id );
		$own     = $this->image( $this->author_id );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, array( array( 'id' => $foreign ) ) ) );
		$this->assertNotFalse( update_post_meta( $this->vehicle, self::META_KEY, array( array( 'id' => $own ) ) ) );
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
