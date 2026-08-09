<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Pre-submission privacy audit (2026-08-09): payment receipts were uploaded with
 * media_handle_upload( 'receipt', 0, ... ). The 0 makes the attachment parentless
 * and its status stays the default 'inherit', which WordPress core treats as
 * public for a parentless attachment. The receipt therefore appeared in the
 * unauthenticated collection at /wp-json/wp/v2/media -- anyone could enumerate
 * customers' payment documents without logging in.
 *
 * The fix marks the receipt attachment private and re-parents it to its booking,
 * so the core REST media collection stops returning it to anonymous callers
 * while administrators (who hold read_private_posts) still see it.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::harden_receipt_attachment
 */
final class ReceiptVisibilityTest extends WP_UnitTestCase
{
	private int $booking_id;
	private int $attachment_id;

	public function setUp(): void
	{
		parent::setUp();

		do_action( 'rest_api_init' );

		$this->booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);

		// The vulnerable shape the old handler produced: parentless, status
		// 'inherit'. A fabricated id is not enough -- the REST media controller
		// filters on real post rows.
		$this->attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'receipt.pdf',
				'post_parent'    => 0,
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
			)
		);
	}

	/**
	 * @param int $user_id 0 for an anonymous request.
	 * @return int[] attachment ids the /wp/v2/media collection returns.
	 */
	private function media_ids_visible_to( int $user_id ): array
	{
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );

		return array_map(
			static fn( array $item ): int => (int) $item['id'],
			$response->get_data()
		);
	}

	/**
	 * The whole point of the finding: before hardening, an anonymous caller can
	 * list the receipt. If this assertion ever fails, the test below proves
	 * nothing, so it is asserted explicitly rather than assumed.
	 */
	public function test_the_parentless_inherit_receipt_is_exposed_before_hardening(): void
	{
		$this->assertContains(
			$this->attachment_id,
			$this->media_ids_visible_to( 0 ),
			'Baseline: a parentless inherit attachment must be anonymously listable, or this test is vacuous.'
		);
	}

	/**
	 * After hardening, the same anonymous caller must no longer see it.
	 */
	public function test_anonymous_caller_cannot_list_a_hardened_receipt(): void
	{
		AccountController::harden_receipt_attachment( $this->attachment_id, $this->booking_id );

		$this->assertNotContains(
			$this->attachment_id,
			$this->media_ids_visible_to( 0 ),
			'A hardened receipt must not appear in the anonymous /wp/v2/media collection.'
		);
	}

	/**
	 * @param int $user_id 0 for an anonymous request.
	 */
	private function fetch_single_media_status( int $user_id ): int
	{
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/media/' . $this->attachment_id );
		$response = rest_get_server()->dispatch( $request );

		return $response->get_status();
	}

	/**
	 * ...but an administrator still must be able to retrieve it, so hardening
	 * restricted visibility rather than destroying the receipt. The private
	 * status keeps it out of the default (inherit-only) collection for everyone,
	 * so retrievability is proven on the single-item endpoint, which honours
	 * read_private_posts.
	 */
	public function test_administrator_can_still_retrieve_a_hardened_receipt(): void
	{
		AccountController::harden_receipt_attachment( $this->attachment_id, $this->booking_id );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertSame(
			200,
			$this->fetch_single_media_status( $admin_id ),
			'An administrator holding read_private_posts must still be able to retrieve the receipt.'
		);
	}

	/**
	 * And an anonymous caller must be refused the single-item endpoint too, not
	 * merely dropped from the collection.
	 */
	public function test_anonymous_caller_is_refused_the_single_receipt_endpoint(): void
	{
		AccountController::harden_receipt_attachment( $this->attachment_id, $this->booking_id );

		$this->assertNotSame(
			200,
			$this->fetch_single_media_status( 0 ),
			'A hardened receipt must not be retrievable by an anonymous caller.'
		);
	}

	/**
	 * The stored shape: private status and re-parented to the booking.
	 */
	public function test_hardening_sets_private_status_and_booking_parent(): void
	{
		AccountController::harden_receipt_attachment( $this->attachment_id, $this->booking_id );

		$attachment = get_post( $this->attachment_id );

		$this->assertSame( 'private', $attachment->post_status );
		$this->assertSame( $this->booking_id, (int) $attachment->post_parent );
	}
}
