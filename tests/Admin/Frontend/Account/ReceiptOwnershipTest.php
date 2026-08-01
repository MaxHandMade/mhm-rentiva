<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use WP_Ajax_UnitTestCase;

/**
 * Found by the fourth pre-ZIP audit round: the receipt endpoints decide who owns
 * a booking by reading post_author, which on this post type is never the
 * customer. Handler::create_booking() hardcodes 'post_author' => 1 for every
 * online booking, and a manually created one belongs to the staff member who
 * entered it. The field that actually tracks the customer is
 * _mhmrentiva_customer_user_id, which is what the rest of the codebase uses.
 *
 * The consequence is not an authorisation hole -- it fails closed -- but the
 * feature does not work: every real customer is denied upload and removal of
 * their own payment receipt, because post_author is 1 and vehicle_booking maps
 * edit_post to manage_options, which no customer has.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::ajax_upload_receipt
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::ajax_remove_receipt
 */
final class ReceiptOwnershipTest extends WP_Ajax_UnitTestCase
{
	private int $customer_id;
	private int $stranger_id;
	private int $booking_id;
	private int $attachment_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->stranger_id = self::factory()->user->create( array( 'role' => 'customer' ) );

		// post_author = 1 is what the production booking path writes.
		$this->booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_author' => 1,
			)
		);
		update_post_meta( $this->booking_id, '_mhmrentiva_customer_user_id', $this->customer_id );

		// A real attachment: a fabricated id makes the handler fail at deletion for
		// reasons unrelated to the ownership gate this test is about.
		$this->attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'receipt.pdf',
				'post_parent'    => $this->booking_id,
				'post_mime_type' => 'application/pdf',
			)
		);
		update_post_meta( $this->booking_id, '_mhmrentiva_receipt_attachment_id', $this->attachment_id );

		AccountController::register();
	}

	public function tearDown(): void
	{
		$_POST  = array();
		$_FILES = array();
		parent::tearDown();
	}

	private function call( string $action ): array
	{
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response is read below.
		}

		$decoded = json_decode( $this->_last_response, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * The customer the booking belongs to must be allowed through the ownership
	 * gate. Anything after it (a missing file) is a different failure.
	 */
	public function test_booking_customer_passes_the_ownership_gate(): void
	{
		wp_set_current_user( $this->customer_id );

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_upload_receipt' ),
			'booking_id' => $this->booking_id,
		);

		$res = $this->call( 'mhmrentiva_remove_receipt' );

		// Assert on what happened, not on the absence of a phrase: a wrong nonce
		// would also produce a message without "not allowed" and let this pass while
		// proving nothing.
		$this->assertNotEmpty( $res, 'Handler produced no JSON response.' );
		$this->assertTrue(
			(bool) ( $res['success'] ?? false ),
			'The booking\'s own customer must get through. Response: ' . $this->_last_response
		);
		$this->assertSame(
			'',
			(string) get_post_meta( $this->booking_id, '_mhmrentiva_receipt_attachment_id', true ),
			'The receipt should have been detached for its own customer.'
		);
	}

	/**
	 * And someone else's customer must still be refused.
	 */
	public function test_another_customer_is_refused(): void
	{
		wp_set_current_user( $this->stranger_id );

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_upload_receipt' ),
			'booking_id' => $this->booking_id,
		);

		$res = $this->call( 'mhmrentiva_remove_receipt' );

		$this->assertFalse( (bool) ( $res['success'] ?? true ) );
		$this->assertSame(
			$this->attachment_id,
			(int) get_post_meta( $this->booking_id, '_mhmrentiva_receipt_attachment_id', true ),
			'A stranger must not be able to detach another customer\'s receipt.'
		);
	}
}
