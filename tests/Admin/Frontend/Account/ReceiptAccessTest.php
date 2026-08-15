<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use WP_UnitTestCase;

/**
 * Finding H-01 (independent review of the 6.0.2 package), which matches a debt
 * this project had already recorded from audit round R18: marking a receipt
 * attachment `private` hides it from the REST media collection but puts no
 * authentication in front of the file itself. media_handle_upload() stores it in
 * the normal uploads tree, so /wp-content/uploads/YYYY/MM/<name> is served
 * straight off disk by the web server, whatever the post status says.
 *
 * R18 closed the enumeration vector. What stayed open was the guess: a receipt
 * uploaded as "dekont.pdf" lands at a fully predictable URL.
 *
 * Two changes are covered here:
 *
 *  1. can_access_receipt() -- the ownership decision, split out as a pure method
 *     so it is testable. The delivery handler around it ends in exit, which
 *     PHPUnit cannot catch (see feedback_admin_handler_test_seam), so the thin
 *     boundary stays untested by design and the decision it defers to does not.
 *
 *  2. randomize_receipt_filename() -- stored names carry crypto-random entropy,
 *     so the direct URL cannot be guessed from the customer's original filename.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::can_access_receipt
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::randomize_receipt_filename
 */
final class ReceiptAccessTest extends WP_UnitTestCase
{
	private int $customer_id;
	private int $stranger_id;
	private int $admin_id;
	private int $booking_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$this->stranger_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$this->admin_id    = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

		// post_author = 1 is what the production booking path writes; ownership
		// is tracked in _mhmrentiva_customer_user_id, never in post_author.
		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'post_author' => 1,
		));
		update_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', $this->customer_id);
	}

	public function test_booking_owner_may_access_their_receipt(): void
	{
		$this->assertTrue(
			AccountController::can_access_receipt($this->booking_id, $this->customer_id),
			'The customer the booking belongs to must be able to fetch their own receipt.'
		);
	}

	public function test_another_customer_may_not_access_the_receipt(): void
	{
		$this->assertFalse(
			AccountController::can_access_receipt($this->booking_id, $this->stranger_id),
			'A different customer must not be able to fetch someone else\'s receipt.'
		);
	}

	public function test_anonymous_visitor_may_not_access_the_receipt(): void
	{
		$this->assertFalse(
			AccountController::can_access_receipt($this->booking_id, 0),
			'An unauthenticated visitor must not be able to fetch a receipt.'
		);
	}

	public function test_administrator_may_access_the_receipt(): void
	{
		$this->assertTrue(
			AccountController::can_access_receipt($this->booking_id, $this->admin_id),
			'Staff must still be able to review a submitted receipt.'
		);
	}

	public function test_access_is_refused_for_a_post_that_is_not_a_booking(): void
	{
		$page_id = (int) self::factory()->post->create(array( 'post_type' => 'page' ));

		$this->assertFalse(
			AccountController::can_access_receipt($page_id, $this->customer_id),
			'A non-booking post ID must not resolve to a receipt.'
		);
	}

	/**
	 * The stored filename must not be derivable from what the customer uploaded.
	 */
	public function test_stored_filename_does_not_keep_the_original_name(): void
	{
		$stored = AccountController::randomize_receipt_filename(wp_upload_dir()['path'], 'dekont.pdf', '.pdf');

		$this->assertNotSame('dekont.pdf', $stored, 'The original filename must not survive as the stored name.');
		$this->assertStringEndsWith('.pdf', $stored, 'The extension must be preserved so the file stays servable.');
		$this->assertDoesNotMatchRegularExpression(
			'/dekont/i',
			$stored,
			'No part of the customer-supplied name may remain guessable in the stored name.'
		);
	}

	/**
	 * Entropy check: two uploads of the same filename must not collide, or the
	 * "unguessable" property degrades to "unguessable once".
	 */
	public function test_two_uploads_of_the_same_name_get_different_stored_names(): void
	{
		$dir = wp_upload_dir()['path'];

		$first  = AccountController::randomize_receipt_filename($dir, 'receipt.pdf', '.pdf');
		$second = AccountController::randomize_receipt_filename($dir, 'receipt.pdf', '.pdf');

		$this->assertNotSame($first, $second, 'Stored receipt names must not repeat for identical uploads.');
		$this->assertGreaterThanOrEqual(
			20,
			strlen(pathinfo($first, PATHINFO_FILENAME)),
			'The random component must be long enough that the URL cannot be brute-forced.'
		);
	}

	/**
	 * A decision method nothing calls protects nothing. The delivery action has to
	 * actually be registered, or receipts keep being served by the bare uploads
	 * URL and can_access_receipt() is decoration.
	 */
	public function test_receipt_delivery_action_is_registered(): void
	{
		AccountController::register();

		$this->assertNotFalse(
			has_action('wp_ajax_mhmrentiva_view_receipt'),
			'The authenticated receipt delivery endpoint must be wired up.'
		);
		$this->assertFalse(
			has_action('wp_ajax_nopriv_mhmrentiva_view_receipt'),
			'Receipt delivery must not be exposed to logged-out visitors.'
		);
	}

	/**
	 * The URL handed to the customer must point at the guarded endpoint and carry
	 * a booking-scoped nonce -- not at the raw file in the uploads tree.
	 */
	public function test_receipt_url_points_at_the_guarded_endpoint(): void
	{
		$url = AccountController::get_receipt_url($this->booking_id);

		$this->assertStringContainsString('action=mhmrentiva_view_receipt', $url);
		$this->assertStringContainsString('booking_id=' . $this->booking_id, $url);
		$this->assertStringContainsString('_wpnonce=', $url);
		$this->assertStringNotContainsString('/uploads/', $url, 'The customer must never be handed the raw uploads path.');
	}

	public function test_receipt_url_is_empty_for_an_invalid_booking(): void
	{
		$this->assertSame('', AccountController::get_receipt_url(0));
	}

	/**
	 * The callback is handed to WordPress, not called by us, so it has to satisfy
	 * core's own contract: wp_unique_filename() invokes it as
	 * ($dir, $name, $ext) and uses the return value verbatim. Calling it through
	 * core here proves the signature matches what core will pass.
	 */
	public function test_callback_satisfies_core_unique_filename_contract(): void
	{
		$stored = wp_unique_filename(
			wp_upload_dir()['path'],
			'dekont.pdf',
			array( AccountController::class, 'randomize_receipt_filename' )
		);

		$this->assertNotSame('dekont.pdf', $stored, 'Core must apply our callback when producing the stored name.');
		$this->assertStringEndsWith('.pdf', $stored);
	}
}
