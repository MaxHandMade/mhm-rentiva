<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Core;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\PaymentView;
use WP_Ajax_UnitTestCase;

/**
 * Every payment entry point runs inside a PaymentView scope, so a change of
 * the booking's payment view leaves exactly one history row (spec PR 3a,
 * plan r4 Task 5).
 *
 * @covers \MHMRentiva\Admin\Booking\Core\PaymentView
 */
final class PaymentHistoryScopesTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $admin;

	public function setUp(): void
	{
		parent::setUp();
		PaymentView::reset();
		$this->admin = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		wp_set_current_user($this->admin);
		DepositManagementAjax::register();
	}

	public function tearDown(): void
	{
		PaymentView::reset();
		$_POST = array();
		parent::tearDown();
	}

	private function booking(string $payment_status, string $type, float $remaining, string $status = 'pending'): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($id, '_mhmrentiva_status', $status);
		update_post_meta($id, '_mhmrentiva_payment_status', $payment_status);
		update_post_meta($id, '_mhmrentiva_payment_type', $type);
		update_post_meta($id, '_mhmrentiva_remaining_amount', $remaining);
		update_post_meta($id, '_mhmrentiva_total_price', 1000);
		update_post_meta($id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', time() + 5 * DAY_IN_SECONDS));
		update_post_meta($id, '_mhmrentiva_dropoff_time', '10:00');
		return $id;
	}

	/** @return list<array<string, mixed>> */
	private function rows(int $id): array
	{
		$history = get_post_meta($id, '_mhmrentiva_booking_history', true);
		return array_values(array_filter(
			is_array($history) ? $history : array(),
			static fn (array $e): bool => 'payment_update' === ( $e['type'] ?? '' )
		));
	}

	private function ajax(string $action, int $booking_id): void
	{
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
			'booking_id' => $booking_id,
		);
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}
		$this->_last_response = '';
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	private function assertOneRow(array $rows, string $from, string $to): void
	{
		$this->assertCount(1, $rows, wp_json_encode($rows));
		$this->assertSame(array( $from, $to ), array( $rows[0]['payment_from'], $rows[0]['payment_to'] ));
	}

	public function test_deposit_approval_writes_one_row(): void
	{
		$id = $this->booking('pending', 'deposit', 500);
		$this->ajax('mhmrentiva_approve_payment', $id);
		$this->assertOneRow($this->rows($id), 'pending', 'partially_paid');
	}

	public function test_remaining_payment_by_hand_writes_one_row_across_the_nested_status_change(): void
	{
		// process_remaining_payment() writes the meta, then Status::update_status()
		// fires the WooCommerce sync and order hook inside the same request.
		$id = $this->booking('paid', 'deposit', 500, 'confirmed');
		$this->ajax('mhmrentiva_process_remaining_payment', $id);
		$this->assertOneRow($this->rows($id), 'partially_paid', 'paid');
	}

	public function test_a_refused_request_writes_nothing_and_leaves_no_scope_open(): void
	{
		$id = $this->booking('paid', 'full', 0);
		$this->ajax('mhmrentiva_process_remaining_payment', $id); // not a deposit booking
		$this->assertSame(array(), $this->rows($id));

		$other = $this->booking('pending', 'deposit', 500);
		$this->ajax('mhmrentiva_approve_payment', $other);
		$this->assertOneRow($this->rows($other), 'pending', 'partially_paid');
		$this->assertSame(array(), $this->rows($id), 'No stray row on the first booking.');
	}
}
