<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Being attached to a vehicle makes an attachment a trusted source for that
 * vehicle's gallery and cover. WordPress checks the new parent when an upload
 * is created, but not when an existing attachment is re-parented (REST
 * `POST /wp/v2/media/{id}` with `post`, the media modal's save-attachment,
 * wp_update_post()). Moving an attachment onto a vehicle therefore needs
 * edit_post on that vehicle, on every path.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::guard_attachment_parent
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::guard_rest_attachment_parent
 */
final class AttachmentReparentGuardTest extends WP_UnitTestCase
{
	private int $vendor_a;
	private int $vendor_b;
	private int $vehicle_a;
	private int $vehicle_b;
	private int $upload_a;

	public function setUp(): void
	{
		parent::setUp();

		$this->vendor_a  = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->vendor_b  = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->vehicle_a = $this->vehicle( $this->vendor_a );
		$this->vehicle_b = $this->vehicle( $this->vendor_b );
		$this->upload_a  = self::factory()->attachment->create_object(
			array(
				'file'           => 'photo.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $this->vendor_a,
				'post_parent'    => 0,
			)
		);
		wp_set_current_user( $this->vendor_a );
	}

	private function vehicle( int $author ): int
	{
		return self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_author' => $author,
			)
		);
	}

	private function parent_of( int $id ): int
	{
		clean_post_cache( $id );
		return (int) get_post( $id )->post_parent;
	}

	private function rest_reparent( int $parent ): \WP_REST_Response
	{
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $this->upload_a );
		$request->set_param( 'post', $parent );
		return rest_do_request( $request );
	}

	public function test_rest_cannot_move_an_upload_onto_another_vendors_vehicle(): void
	{
		$response = $this->rest_reparent( $this->vehicle_b );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'mhmrentiva_cannot_attach_to_vehicle', $response->as_error()->get_error_code() );
		$this->assertSame( 0, $this->parent_of( $this->upload_a ) );
	}

	public function test_rest_may_move_an_upload_onto_the_vendors_own_vehicle(): void
	{
		$response = $this->rest_reparent( $this->vehicle_a );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->vehicle_a, $this->parent_of( $this->upload_a ) );
	}

	public function test_rest_change_of_other_fields_with_an_unchanged_parent_is_not_refused(): void
	{
		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => $this->vehicle_a ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		// The vehicle is reassigned; the upload stays attached to it.
		wp_update_post( array( 'ID' => $this->vehicle_a, 'post_author' => $this->vendor_b ) );
		wp_set_current_user( $this->vendor_a );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $this->upload_a );
		$request->set_param( 'post', $this->vehicle_a );
		$request->set_param( 'title', 'Renamed' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->vehicle_a, $this->parent_of( $this->upload_a ) );
	}

	public function test_every_write_path_keeps_the_old_parent(): void
	{
		// wp_update_post() is what the media modal's save-attachment and the
		// attachment edit screen end in.
		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => $this->vehicle_b ) );
		$this->assertSame( 0, $this->parent_of( $this->upload_a ) );

		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => $this->vehicle_a ) );
		$this->assertSame( $this->vehicle_a, $this->parent_of( $this->upload_a ) );

		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => $this->vehicle_b ) );
		$this->assertSame( $this->vehicle_a, $this->parent_of( $this->upload_a ) );

		// Detaching, or moving onto something that is not a vehicle, is not this guard's business.
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_author' => $this->vendor_b ) );
		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => 0 ) );
		$this->assertSame( 0, $this->parent_of( $this->upload_a ) );
		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => $page ) );
		$this->assertSame( $page, $this->parent_of( $this->upload_a ) );
	}

	public function test_the_moved_upload_never_becomes_trusted_on_the_other_vehicle(): void
	{
		// A's upload has no trusted provenance on B (an administrator may still have
		// put it into B's gallery), and A cannot give it one by moving the upload.
		$this->assertFalse( VehicleGallery::is_displayable_image( $this->vehicle_b, $this->upload_a ) );
		$this->rest_reparent( $this->vehicle_b );
		$this->assertFalse( VehicleGallery::is_displayable_image( $this->vehicle_b, $this->upload_a ) );
	}

	public function test_editors_and_writes_without_a_user_are_not_restricted(): void
	{
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => $this->vehicle_b ) );
		$this->assertSame( $this->vehicle_b, $this->parent_of( $this->upload_a ) );

		wp_set_current_user( 0 );
		wp_update_post( array( 'ID' => $this->upload_a, 'post_parent' => $this->vehicle_a ) );
		$this->assertSame( $this->vehicle_a, $this->parent_of( $this->upload_a ) );
	}

	public function test_new_uploads_are_left_to_their_own_checks(): void
	{
		// Creation paths (Pro's vendor upload, media_handle_upload) choose the parent
		// in code after their own checks; the guard only judges a change.
		$new = self::factory()->attachment->create_object(
			array(
				'file'           => 'new.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $this->vendor_a,
				'post_parent'    => $this->vehicle_b,
			)
		);
		$this->assertSame( $this->vehicle_b, $this->parent_of( $new ) );
	}

	public function test_guards_are_registered_when_the_plugin_file_loads(): void
	{
		$class = 'MHMRentiva\Admin\Vehicle\Meta\VehicleGallery';
		$this->assertSame( 10, has_filter( 'wp_insert_attachment_data', array( $class, 'guard_attachment_parent' ) ) );
		$this->assertSame( 10, has_filter( 'rest_pre_insert_attachment', array( $class, 'guard_rest_attachment_parent' ) ) );
	}
}
