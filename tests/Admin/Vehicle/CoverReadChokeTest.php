<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_UnitTestCase;

/**
 * A vehicle's featured image is read through one choke (`post_thumbnail_id`),
 * so every reader -- VehicleDetails, the grids and lists, the account
 * templates, WooCommerce and any theme -- obeys the same rule as the gallery:
 * a stored `_thumbnail_id` is shown only when it is_displayable_image().
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::filter_thumbnail_id
 */
final class CoverReadChokeTest extends WP_UnitTestCase
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
	}

	public function tearDown(): void
	{
		remove_all_filters( 'mhmrentiva_gallery_attachment_allowed' );
		parent::tearDown();
	}

	private function image( int $author, int $parent = 0, string $mime = 'image/jpeg' ): int
	{
		return self::factory()->attachment->create_object(
			array(
				'file'           => 'image/jpeg' === $mime ? 'photo.jpg' : 'doc.pdf',
				'post_mime_type' => $mime,
				'post_author'    => $author,
				'post_parent'    => $parent,
			)
		);
	}

	/** Bypasses the write guard: the row a migration, an old version or an import leaves. */
	private function plant_cover( int $post_id, int $id ): void
	{
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			array( 'post_id' => $post_id, 'meta_key' => '_thumbnail_id', 'meta_value' => (string) $id ) // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		wp_cache_delete( $post_id, 'post_meta' );
	}

	private function details_cover_url(): string
	{
		$method = new \ReflectionMethod( VehicleDetails::class, 'get_featured_image' );
		$method->setAccessible( true );
		return (string) $method->invoke( null, $this->vehicle )['url'];
	}

	public function test_untrusted_stored_cover_is_hidden_from_every_core_reader(): void
	{
		$foreign = $this->image( $this->other_id );
		$this->plant_cover( $this->vehicle, $foreign );

		$this->assertSame( 0, get_post_thumbnail_id( $this->vehicle ) );
		$this->assertSame( 0, get_post_thumbnail_id( get_post( $this->vehicle ) ) );
		$this->assertFalse( has_post_thumbnail( $this->vehicle ) );
		$this->assertFalse( get_the_post_thumbnail_url( $this->vehicle, 'medium' ) );
		$this->assertSame( '', get_the_post_thumbnail( $this->vehicle ) );
		$this->assertSame( '', $this->details_cover_url() );

		// The stored row itself is left alone: the admin box and the guards read it raw.
		$this->assertSame( (string) $foreign, get_post_meta( $this->vehicle, '_thumbnail_id', true ) );
	}

	public function test_vetoed_and_non_image_covers_are_hidden(): void
	{
		$own = $this->image( $this->author_id );
		$this->plant_cover( $this->vehicle, $own );
		add_filter(
			'mhmrentiva_gallery_attachment_allowed',
			static fn( $allowed, $att ) => (int) $att === $own ? false : $allowed,
			10,
			2
		);
		$this->assertSame( 0, get_post_thumbnail_id( $this->vehicle ) );
		$this->assertSame( '', $this->details_cover_url() );

		remove_all_filters( 'mhmrentiva_gallery_attachment_allowed' );
		delete_post_meta( $this->vehicle, '_thumbnail_id' );
		$pdf = $this->image( $this->author_id, $this->vehicle, 'application/pdf' );
		$this->plant_cover( $this->vehicle, $pdf );
		$this->assertSame( 0, get_post_thumbnail_id( $this->vehicle ) );
	}

	public function test_trusted_cover_is_returned_unchanged(): void
	{
		$own = $this->image( $this->author_id );
		$this->plant_cover( $this->vehicle, $own );

		$this->assertSame( $own, get_post_thumbnail_id( $this->vehicle ) );
		$this->assertTrue( has_post_thumbnail( $this->vehicle ) );
		$this->assertSame( (string) wp_get_attachment_image_url( $own, 'large' ), $this->details_cover_url() );

		$attached = $this->image( $this->other_id, $this->vehicle );
		delete_post_meta( $this->vehicle, '_thumbnail_id' );
		$this->plant_cover( $this->vehicle, $attached );
		$this->assertSame( $attached, get_post_thumbnail_id( $this->vehicle ) );
	}

	public function test_other_post_types_and_empty_covers_are_untouched(): void
	{
		$post    = self::factory()->post->create( array( 'post_author' => $this->author_id ) );
		$foreign = $this->image( $this->other_id );
		$this->plant_cover( $post, $foreign );
		$this->assertSame( $foreign, get_post_thumbnail_id( $post ) );

		$this->assertSame( 0, get_post_thumbnail_id( $this->vehicle ) );
		$this->assertSame( 0, VehicleGallery::filter_thumbnail_id( 0, $this->vehicle ) );
		$this->assertFalse( VehicleGallery::filter_thumbnail_id( false, null ) );
	}

	/**
	 * Core's featured-image box reads the raw meta, so without the filter it would
	 * render another user's file to the vendor. It shows the mark instead, and its
	 * field asks the save to clear the cover (-1), as the gallery box announces.
	 */
	public function test_admin_featured_image_box_marks_a_hidden_cover_without_its_image(): void
	{
		require_once ABSPATH . 'wp-admin/includes/post.php';
		wp_set_current_user( $this->author_id );

		$foreign = self::factory()->attachment->create_object(
			array(
				'file'           => 'foreign-secret.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $this->other_id,
				'post_parent'    => 0,
			)
		);
		$this->plant_cover( $this->vehicle, $foreign );

		$html = _wp_post_thumbnail_html( $foreign, $this->vehicle );
		$this->assertStringNotContainsString( 'foreign-secret', $html );
		$this->assertStringContainsString( 'Not shown on the site', $html );
		$this->assertMatchesRegularExpression( '/name="_thumbnail_id"[^>]*value="-1"/', $html );

		$own = $this->image( $this->author_id );
		$this->assertStringContainsString( 'value="' . $own . '"', _wp_post_thumbnail_html( $own, $this->vehicle ) );
		$this->assertStringNotContainsString( 'Not shown on the site', _wp_post_thumbnail_html( $own, $this->vehicle ) );
	}

	/**
	 * Without a shown cover the details page used to print an <img> with an empty
	 * src (a broken image). The first shown gallery image takes the main slot.
	 */
	public function test_details_page_without_a_shown_cover_prints_no_empty_image(): void
	{
		$foreign = $this->image( $this->other_id );
		$own     = $this->image( $this->author_id );
		$this->plant_cover( $this->vehicle, $foreign );
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			array( 'post_id' => $this->vehicle, 'meta_key' => '_mhmrentiva_gallery_images', 'meta_value' => (string) wp_json_encode( array( array( 'id' => $own ) ) ) ) // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		wp_cache_delete( $this->vehicle, 'post_meta' );

		$html = do_shortcode( '[rentiva_vehicle_details vehicle_id="' . $this->vehicle . '"]' );

		$this->assertDoesNotMatchRegularExpression( '/<img[^>]*\bsrc=""/', $html );
		$this->assertMatchesRegularExpression(
			'/<img[^>]*src="' . preg_quote( esc_url( (string) wp_get_attachment_image_url( $own, 'large' ) ), '/' ) . '"[^>]*class="rv-featured-image"/',
			$html
		);

		// Neither cover nor gallery: no main image at all.
		delete_post_meta( $this->vehicle, '_mhmrentiva_gallery_images' );
		$html = do_shortcode( '[rentiva_vehicle_details vehicle_id="' . $this->vehicle . '"]' );
		$this->assertDoesNotMatchRegularExpression( '/<img[^>]*\bsrc=""/', $html );
	}

	public function test_choke_is_registered_when_the_plugin_file_loads(): void
	{
		$class = 'MHMRentiva\Admin\Vehicle\Meta\VehicleGallery';
		$this->assertSame( 10, has_filter( 'post_thumbnail_id', array( $class, 'filter_thumbnail_id' ) ) );
		$this->assertSame( 10, has_filter( 'admin_post_thumbnail_html', array( $class, 'filter_admin_thumbnail_html' ) ) );
	}
}
