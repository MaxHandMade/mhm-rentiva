<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Booking\Core\PaymentView;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The WooCommerce order-status entry point runs one PaymentView scope per
 * booking on the order (spec PR 3a, plan r4 R9 / Task 5).
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::handle_order_status_change
 */
final class PaymentHistoryOrderScopesTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		PaymentView::reset();
		WooCommerceBridge::register();
	}

	public function tearDown(): void
	{
		PaymentView::reset();
		parent::tearDown();
	}

	private function booking(string $payment_status, float $remaining): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($id, '_mhmrentiva_status', 'pending');
		update_post_meta($id, '_mhmrentiva_payment_status', $payment_status);
		update_post_meta($id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($id, '_mhmrentiva_total_price', 100.0);
		update_post_meta($id, '_mhmrentiva_deposit_amount', 30.0);
		update_post_meta($id, '_mhmrentiva_remaining_amount', $remaining);
		update_post_meta($id, '_mhmrentiva_start_ts', time() - DAY_IN_SECONDS);
		update_post_meta($id, '_mhmrentiva_end_ts', time() + DAY_IN_SECONDS);
		return $id;
	}

	/**
	 * @param list<int> $booking_ids
	 */
	private function order(array $booking_ids, bool $is_remaining, string $total): \WC_Order
	{
		$product = $this->ensure_booking_product($total);
		$order   = wc_create_order(array( 'status' => 'pending' ));
		foreach ($booking_ids as $booking_id) {
			$item = new \WC_Order_Item_Product();
			$item->set_product($product);
			$item->set_quantity(1);
			$item->set_subtotal((float) $total);
			$item->set_total((float) $total);
			$item->add_meta_data('_mhmrentiva_booking_id', $booking_id, true);
			$order->add_item($item);
		}
		if ($is_remaining) {
			$order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		}
		$order->calculate_totals();
		$order->save();
		return $order;
	}

	/** @return list<array{0: string, 1: string}> */
	private function rows(int $id): array
	{
		$history = get_post_meta($id, '_mhmrentiva_booking_history', true);
		$out     = array();
		foreach (is_array($history) ? $history : array() as $e) {
			if ('payment_update' === ( $e['type'] ?? '' )) {
				$out[] = array( (string) $e['payment_from'], (string) $e['payment_to'] );
			}
		}
		return $out;
	}

	public function test_a_paid_deposit_order_writes_one_row(): void
	{
		$id = $this->booking('pending', 70.0);
		$this->order(array( $id ), false, '30')->update_status('processing');
		$this->assertSame(array( array( 'pending', 'partially_paid' ) ), $this->rows($id));
	}

	public function test_the_balance_paid_through_woocommerce_writes_one_row(): void
	{
		$id = $this->booking('paid', 70.0);
		$this->order(array( $id ), true, '70')->update_status('completed');
		$this->assertSame(array( array( 'partially_paid', 'paid' ) ), $this->rows($id));
	}

	public function test_a_repeated_notification_writes_no_second_row(): void
	{
		$id    = $this->booking('pending', 70.0);
		$order = $this->order(array( $id ), false, '30');
		$order->update_status('processing');
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'processing', 'processing', $order);
		$this->assertCount(1, $this->rows($id));
	}

	public function test_two_bookings_on_one_order_write_one_row_each(): void
	{
		$a = $this->booking('pending', 70.0);
		$b = $this->booking('pending', 70.0);
		$this->order(array( $a, $b ), false, '30')->update_status('processing');
		$this->assertSame(array( array( 'pending', 'partially_paid' ) ), $this->rows($a));
		$this->assertSame(array( array( 'pending', 'partially_paid' ) ), $this->rows($b));
	}

	public function test_on_hold_from_unpaid_writes_one_row_and_from_pending_none(): void
	{
		$unpaid = $this->booking('unpaid', 70.0);
		$this->order(array( $unpaid ), false, '30')->update_status('on-hold');
		$this->assertSame(array( array( 'unpaid', 'pending' ) ), $this->rows($unpaid));

		$pending = $this->booking('pending', 70.0);
		$this->order(array( $pending ), false, '30')->update_status('on-hold');
		$this->assertSame(array(), $this->rows($pending));
	}
}
