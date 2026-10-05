<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_UnitTestCase;

/**
 * Direct writes of the gallery meta and every front-end read obey the image
 * policy: a historical row cannot expose a sensitive attachment, and a direct
 * write cannot store a foreign one.
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

	public function test_guards_are_registered_when_the_plugin_file_loads(): void
	{
		if ( ! function_exists( 'shell_exec' ) || '' === trim( (string) shell_exec( 'command -v wp 2>/dev/null' ) ) ) {
			$this->markTestSkipped( 'WP-CLI is not available to boot WordPress in a child process.' );
		}

		// A fresh WordPress boot: observe at plugins_loaded priority -100, i.e. after
		// every plugin file was included but before any plugin bootstrap hook ran.
		$probe = tempnam( sys_get_temp_dir(), 'probe' ) . '.php';
		file_put_contents(
			$probe,
			'<?php WP_CLI::add_wp_hook( "plugins_loaded", function () { $o = array(); foreach ( array( "update_post_metadata", "add_post_metadata" ) as $h ) { foreach ( array( "guard_gallery", "guard_thumbnail" ) as $g ) { $o[] = $h . ":" . $g . "=" . ( false !== has_filter( $h, array( "MHMRentiva\\Admin\\Vehicle\\Meta\\VehicleGallery", $g ) ) ? "1" : "0" ); } } echo "PROBE " . implode( ",", $o ) . "\n"; }, -100 );'
		);
		$out = (string) shell_exec( 'wp --allow-root --path=' . escapeshellarg( ABSPATH ) . ' --skip-plugins=mhm-rentiva-pro --require=' . escapeshellarg( $probe ) . ' eval "" 2>&1' );
		unlink( $probe );

		$this->assertStringContainsString( 'PROBE ', $out, $out );
		$this->assertStringNotContainsString( '=0', $out );
		$this->assertStringContainsString( 'update_post_metadata:guard_gallery=1', $out );
		$this->assertStringContainsString( 'add_post_metadata:guard_thumbnail=1', $out );
	}

	public function test_guards_are_hooked_in_the_running_site(): void
	{
		foreach ( array( 'update_post_metadata', 'add_post_metadata' ) as $hook ) {
			foreach ( array( 'guard_gallery', 'guard_thumbnail' ) as $guard ) {
				$this->assertSame( 10, has_filter( $hook, array( VehicleGallery::class, $guard ) ), "$guard on $hook" );
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

	public function test_scalar_id_forms_are_checked_by_the_write_guard(): void
	{
		$foreign = $this->image( $this->other_id );
		$own     = $this->image( $this->author_id );

		// An early write: the field and its sanitizer are not registered yet.
		// (The test case restores the hook table afterwards.)
		remove_all_filters( 'sanitize_post_meta_' . self::META_KEY );
		remove_all_filters( 'sanitize_post_meta_' . self::META_KEY . '_for_mhmrentiva_vehicle' );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, array( $foreign ) ) );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, array( (string) $foreign ) ) );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, array( 'abc' ) ) );
		$this->assertFalse( update_post_meta( $this->vehicle, self::META_KEY, array( array( 'alt' => 'no id' ) ) ) );
		$this->assertSame( '', get_post_meta( $this->vehicle, self::META_KEY, true ) );

		$this->assertNotFalse( update_post_meta( $this->vehicle, self::META_KEY, array( $own ) ) );

		$method = new \ReflectionMethod( VehicleDetails::class, 'get_gallery' );
		$method->setAccessible( true );
		$this->assertContains( $own, array_column( $method->invoke( null, $this->vehicle ), 'id' ) );
	}

	public function test_entries_missing_alt_title_url_raise_no_warnings(): void
	{
		$own = $this->image( $this->author_id );
		$this->raw_insert( self::META_KEY, (string) wp_json_encode( array( array( 'id' => $own ) ) ) );

		$errors = array();
		set_error_handler(
			static function ( int $no, string $str ) use ( &$errors ): bool {
				$errors[] = $str;
				return true;
			}
		);
		try {
			$front = VehicleGallery::get_gallery_for_frontend( $this->vehicle );
			ob_start();
			VehicleGallery::render_gallery_meta_box( get_post( $this->vehicle ) );
			ob_end_clean();
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $errors );
		$this->assertSame( $own, (int) $front[0]['id'] );
	}

	public function test_bare_id_forms_render_and_survive_a_save_round_trip(): void
	{
		$own = $this->image( $this->author_id );

		foreach ( array( array( $own ), array( (string) $own ) ) as $stored ) {
			delete_post_meta( $this->vehicle, self::META_KEY );
			$this->raw_insert( self::META_KEY, (string) wp_json_encode( $stored ) );

			$errors = array();
			set_error_handler(
				static function ( int $no, string $str ) use ( &$errors ): bool {
					$errors[] = $str;
					return true;
				}
			);
			try {
				ob_start();
				VehicleGallery::render_gallery_meta_box( get_post( $this->vehicle ) );
				$html = (string) ob_get_clean();
			} finally {
				restore_error_handler();
			}

			$this->assertSame( array(), $errors );
			$this->assertStringContainsString( 'data-image-id="' . $own . '"', $html );
		}

		// Save round trip: the bare-id gallery is kept, stored as full entries.
		$_POST = array(
			'mhmrentiva_gallery_images_nonce' => wp_create_nonce( 'mhmrentiva_gallery_images' ),
			'mhmrentiva_gallery_images'       => wp_slash( (string) wp_json_encode( array( $own ) ) ),
		);
		VehicleGallery::save_gallery_images( $this->vehicle );
		$_POST  = array();
		$stored = json_decode( (string) get_post_meta( $this->vehicle, self::META_KEY, true ), true );
		$this->assertSame( $own, (int) $stored[0]['id'] );
		$this->assertArrayHasKey( 'url', $stored[0] );
		$this->assertSame( '', $stored[0]['alt'] );
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
