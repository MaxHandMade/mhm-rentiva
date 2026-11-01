<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * PR D, Task 6 (M10, y10): the server refuses a bank receipt once money has
 * arrived or the booking is no longer waiting; the button being hidden is not
 * the gate.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::ajax_upload_receipt
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::receipt_upload_allowed
 */
final class ReceiptUploadGateTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceFixtures;

	private const REFUSAL = 'A receipt cannot be uploaded for this booking.';

	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($this->customer_id);
		AccountController::register();
	}

	public function tearDown(): void
	{
		$_POST  = array();
		$_FILES = array();
		parent::tearDown();
	}

	private function booking(string $status): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_author' => 1,
			)
		);
		update_post_meta($id, '_mhmrentiva_customer_user_id', $this->customer_id);
		update_post_meta($id, '_mhmrentiva_status', $status);

		return $id;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function upload(int $booking_id): array
	{
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_upload_receipt'),
			'booking_id' => $booking_id,
		);

		try {
			$this->_handleAjax('mhmrentiva_upload_receipt');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates; the response is read below.
		}

		$decoded = json_decode($this->_last_response, true);
		return is_array($decoded) ? $decoded : array();
	}

	public function test_paid_booking_is_refused(): void
	{
		$this->require_woocommerce();
		$id = $this->booking('pending_payment');
		$this->create_paid_order_for_booking($id, '292');

		$res = $this->upload($id);

		$this->assertFalse((bool) ( $res['success'] ?? true ));
		$this->assertSame(self::REFUSAL, $res['data']['message'] ?? null);
		$this->assertFalse(AccountController::receipt_upload_allowed($id));
	}

	public function test_booking_no_longer_waiting_is_refused(): void
	{
		$id = $this->booking('confirmed');

		$res = $this->upload($id);

		$this->assertFalse((bool) ( $res['success'] ?? true ));
		$this->assertSame(self::REFUSAL, $res['data']['message'] ?? null);
	}

	/**
	 * @dataProvider waiting_statuses
	 */
	public function test_waiting_unpaid_booking_passes_the_gate(string $status): void
	{
		$id = $this->booking($status);

		$res = $this->upload($id);

		// Past the gate the handler stops at the missing file: a different message.
		$this->assertTrue(AccountController::receipt_upload_allowed($id));
		$this->assertNotSame(self::REFUSAL, $res['data']['message'] ?? null);
		$this->assertSame('No file uploaded.', $res['data']['message'] ?? null);
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function waiting_statuses(): array
	{
		return array(
			'pending_payment' => array( 'pending_payment' ),
			'pending'         => array( 'pending' ),
		);
	}

	public function test_offline_paid_booking_is_refused(): void
	{
		$id = $this->booking('pending_payment');
		update_post_meta($id, '_mhmrentiva_payment_status', 'paid');
		update_post_meta($id, '_mhmrentiva_total_price', '300');
		update_post_meta($id, '_mhmrentiva_remaining_amount', '100');

		$res = $this->upload($id);

		$this->assertSame(self::REFUSAL, $res['data']['message'] ?? null);
	}

	public function test_mixed_currency_booking_is_refused(): void
	{
		$this->require_woocommerce();
		$id        = $this->booking('pending_payment');
		$deposit   = $this->create_paid_order_for_booking($id, '100');
		$remaining = $this->create_paid_order_for_booking($id, '200');
		update_post_meta($id, '_mhmrentiva_remaining_order_id', $remaining->get_id());
		$deposit->set_currency('TRY');
		$deposit->save();
		$remaining->set_currency('EUR');
		$remaining->save();

		$this->assertSame(self::REFUSAL, $this->upload($id)['data']['message'] ?? null);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function remove(int $booking_id): array
	{
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_upload_receipt'),
			'booking_id' => $booking_id,
		);

		try {
			$this->_handleAjax('mhmrentiva_remove_receipt');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// The response is read below.
		}

		$decoded = json_decode($this->_last_response, true);
		return is_array($decoded) ? $decoded : array();
	}

	private function with_receipt(int $booking_id): int
	{
		$attachment = self::factory()->attachment->create_object(
			array(
				'file'           => 'receipt.pdf',
				'post_parent'    => $booking_id,
				'post_mime_type' => 'application/pdf',
			)
		);
		update_post_meta($booking_id, '_mhmrentiva_receipt_attachment_id', $attachment);

		return $attachment;
	}

	public function test_owner_cannot_remove_the_slip_once_paid(): void
	{
		$this->require_woocommerce();
		$id         = $this->booking('pending_payment');
		$attachment = $this->with_receipt($id);
		$this->create_paid_order_for_booking($id, '292');

		$res = $this->remove($id);

		$this->assertFalse((bool) ( $res['success'] ?? true ));
		$this->assertSame(self::REFUSAL, $res['data']['message'] ?? null);
		$this->assertSame($attachment, (int) get_post_meta($id, '_mhmrentiva_receipt_attachment_id', true), 'The slip is still attached.');
	}

	public function test_owner_can_remove_the_slip_while_waiting(): void
	{
		$id = $this->booking('pending_payment');
		$this->with_receipt($id);

		$res = $this->remove($id);

		$this->assertTrue((bool) ( $res['success'] ?? false ), $this->_last_response);
		$this->assertSame('', (string) get_post_meta($id, '_mhmrentiva_receipt_attachment_id', true));
	}

	public function test_staff_keep_removing_after_payment(): void
	{
		$this->require_woocommerce();
		$id = $this->booking('pending_payment');
		$this->with_receipt($id);
		$this->create_paid_order_for_booking($id, '292');
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

		$res = $this->remove($id);

		$this->assertTrue((bool) ( $res['success'] ?? false ), $this->_last_response);
	}
}
