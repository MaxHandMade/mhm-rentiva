<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_Ajax_UnitTestCase;

/**
 * The admin gallery box shows what is stored, and says which entries the site
 * does not show: an entry without a trusted provenance, a vetoed one or a
 * non-image is listed with a mark and without its image (it may be another
 * user's file), so the drop on the next save is announced, not silent. An AJAX
 * add that refuses some of the picked images says so.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::render_gallery_meta_box
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::ajax_add_gallery_image
 */
final class GalleryMetaBoxMarkTest extends WP_Ajax_UnitTestCase
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
		VehicleGallery::register();
	}

	public function tearDown(): void
	{
		$_POST = array();
		remove_all_filters( 'mhmrentiva_gallery_attachment_allowed' );
		parent::tearDown();
	}

	private function image( int $author, string $file ): int
	{
		return self::factory()->attachment->create_object(
			array(
				'file'           => $file,
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $author,
				'post_parent'    => 0,
			)
		);
	}

	private function plant( int ...$ids ): void
	{
		$entries = array();
		foreach ( $ids as $id ) {
			$entries[] = array( 'id' => $id, 'url' => '', 'alt' => '', 'title' => '' );
		}
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			array( 'post_id' => $this->vehicle, 'meta_key' => self::META_KEY, 'meta_value' => (string) wp_json_encode( $entries ) ) // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		wp_cache_delete( $this->vehicle, 'post_meta' );
	}

	private function render(): \DOMXPath
	{
		ob_start();
		VehicleGallery::render_gallery_meta_box( get_post( $this->vehicle ) );
		$html = (string) ob_get_clean();

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		return new \DOMXPath( $dom );
	}

	private function item( \DOMXPath $xpath, int $id ): \DOMElement
	{
		$nodes = $xpath->query( sprintf( '//div[contains(@class,"mhm-gallery-item ") or @class="mhm-gallery-item"][@data-image-id="%d"]', $id ) );
		$this->assertSame( 1, $nodes->length, "item $id is listed once" );
		return $nodes->item( 0 );
	}

	public function test_untrusted_and_vetoed_entries_are_listed_marked_and_without_their_image(): void
	{
		$own     = $this->image( $this->author_id, 'own.jpg' );
		$foreign = $this->image( $this->other_id, 'foreign-secret.jpg' );
		$vetoed  = $this->image( $this->author_id, 'vetoed-secret.jpg' );
		add_filter(
			'mhmrentiva_gallery_attachment_allowed',
			static fn( $allowed, $att ) => (int) $att === $vetoed ? false : $allowed,
			10,
			2
		);
		$this->plant( $own, $foreign, $vetoed );

		$xpath = $this->render();

		$shown = $this->item( $xpath, $own );
		$this->assertStringNotContainsString( 'is-hidden-on-site', $shown->getAttribute( 'class' ) );
		$this->assertSame( 1, $xpath->query( './/img', $shown )->length );

		foreach ( array( $foreign, $vetoed ) as $id ) {
			$item = $this->item( $xpath, $id );
			$this->assertStringContainsString( 'is-hidden-on-site', $item->getAttribute( 'class' ) );
			$this->assertSame( 0, $xpath->query( './/img', $item )->length, 'no image of a file the site does not show' );
			$this->assertStringContainsString( 'Not shown on the site', $item->textContent );
		}

		// Neither the grid nor the hidden save input carries the hidden files' URLs.
		$hidden = $xpath->query( '//input[@id="mhmrentiva_gallery_images"]' )->item( 0 )->getAttribute( 'value' );
		$html   = $xpath->document->saveHTML();
		foreach ( array( 'foreign-secret', 'vetoed-secret' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $html );
			$this->assertStringNotContainsString( $secret, $hidden );
		}
		$this->assertSame( array( $own, $foreign, $vetoed ), array_column( json_decode( $hidden, true ), 'id' ) );
	}

	public function test_ajax_add_says_when_picked_images_are_refused(): void
	{
		$own     = $this->image( $this->author_id, 'own.jpg' );
		$foreign = $this->image( $this->other_id, 'foreign.jpg' );

		$_POST = array(
			'action'    => 'mhmrentiva_add_gallery_image',
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->vehicle,
			'image_ids' => array( $own, $foreign ),
		);
		try {
			$this->_handleAjax( 'mhmrentiva_add_gallery_image' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertSame( array( $own ), array_column( $response['data']['gallery_images'], 'id' ) );
		$this->assertSame( 1, $response['data']['rejected'] );
		$this->assertStringContainsString( 'could not be added', $response['data']['message'] );
	}

	public function test_ajax_add_without_refusals_keeps_the_success_message(): void
	{
		$own = $this->image( $this->author_id, 'own.jpg' );

		$_POST = array(
			'action'    => 'mhmrentiva_add_gallery_image',
			'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
			'post_id'   => $this->vehicle,
			'image_ids' => array( $own ),
		);
		try {
			$this->_handleAjax( 'mhmrentiva_add_gallery_image' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertSame( 0, $response['data']['rejected'] );
		$this->assertSame( 'Images successfully added', $response['data']['message'] );
	}
}
