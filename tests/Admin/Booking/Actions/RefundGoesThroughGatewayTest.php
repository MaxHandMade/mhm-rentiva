<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use WP_Ajax_UnitTestCase;

/**
 * Independent review of the 6.0.3 package, finding C-01: the admin "process
 * refund" button never refunded anything.
 *
 * The live admin JS posts to mhmrentiva_deposit_process_refund, which reached
 * DepositManagementAjax::process_refund(). That method computed an amount from
 * the cancellation policy, wrote _mhmrentiva_payment_status = refunded plus
 * three bookkeeping metas, and answered "Refund completed successfully" -- with
 * no call to wc_create_refund(), no gateway request, and nothing verifying that
 * money had moved. An operator could mark a booking refunded, see success, and
 * have the customer never receive a penny.
 *
 * This is the same shape as the booking-lock defect fixed earlier in 6.0.3: the
 * correct implementation existed and had no live caller. MHMRentiva\Admin\Payment\
 * Refunds\Service validates, calls the gateway, checks the result and only then
 * writes meta -- and grep found zero callers of it anywhere in either edition.
 *
 * The fix routes the AJAX handler through that service. These tests pin the part
 * that matters and that the suite can actually reach without WooCommerce: a
 * refund that does NOT succeed must not leave the booking looking refunded, and
 * must not answer success. The gateway failure is produced honestly -- the
 * booking carries no payment gateway, so RefundValidator refuses it, which is
 * exactly the "refund did not happen" branch.
 *
 * @covers \MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::process_refund
 */
final class RefundGoesThroughGatewayTest extends WP_Ajax_UnitTestCase
{
	private int $booking_id;
	private int $admin_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->admin_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		// A booking in the only state the handler accepts: paid and cancelled.
		update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
		update_post_meta($this->booking_id, '_mhmrentiva_status', 'cancelled');
		update_post_meta($this->booking_id, '_mhmrentiva_deposit_amount', 500);
		update_post_meta($this->booking_id, '_mhmrentiva_total_price', 1000);
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 500);
		// Inside the cancellation deadline, so policy grants a refund.
		update_post_meta($this->booking_id, '_mhmrentiva_cancellation_deadline', gmdate('Y-m-d H:i:s', strtotime('+2 days')));

		DepositManagementAjax::register();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function call_refund(): array
	{
		wp_set_current_user($this->admin_id);

		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
			'booking_id' => $this->booking_id,
		);

		try {
			$this->_handleAjax('mhmrentiva_deposit_process_refund');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates.
		}

		$decoded = json_decode($this->_last_response, true);

		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * RED before the fix: the handler answers success and stamps the booking
	 * refunded although no gateway refund was ever attempted.
	 */
	public function test_refund_that_did_not_happen_is_not_reported_as_success(): void
	{
		$response = $this->call_refund();

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'A refund the gateway never performed must not be reported as completed. Raw: ' . $this->_last_response
		);
	}

	/**
	 * The money half: booking state must not claim a refund that did not occur.
	 */
	public function test_failed_refund_leaves_the_booking_not_refunded(): void
	{
		$this->call_refund();

		$this->assertSame(
			'paid',
			(string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true),
			'Payment status must stay "paid" when no refund was actually made.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
			'No refunded amount may be recorded for a refund that did not happen.'
		);
	}

	/**
	 * Negative control for the policy branch, which must keep working and must
	 * not be turned into an error by the new failure path: past the cancellation
	 * deadline the policy grants nothing, so there is no refund to attempt and
	 * the handler still reports the (unchanged) outcome rather than failing.
	 */
	public function test_policy_denied_refund_still_reports_without_touching_state(): void
	{
		update_post_meta($this->booking_id, '_mhmrentiva_cancellation_deadline', gmdate('Y-m-d H:i:s', strtotime('-2 days')));

		$response = $this->call_refund();

		$this->assertTrue(
			(bool) ( $response['success'] ?? false ),
			'A policy decision of "no refund due" is a normal outcome, not a failure. Raw: ' . $this->_last_response
		);
		$this->assertSame(
			'paid',
			(string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true),
			'A booking owed nothing back must not be marked refunded.'
		);
	}
}
