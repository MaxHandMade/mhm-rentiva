<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Booking\Core\PaymentView;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Every refund path leaves exactly one payment row (spec PR 3a, plan r4
 * Task 5): Service::process (partial, WooCommerce or offline),
 * Service::processFullRefund (the cancellation path), and a refund an
 * operator makes on the WooCommerce order screen (wc_create_refund, no
 * Service), whose status hook writes no payment meta and whose refund hook
 * does.
 */
final class PaymentHistoryRefundScopesTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $booking;

	private int $admin;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		PaymentView::reset();
		WooCommerceBridge::register();
		$this->admin   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$this->booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($this->booking, '_mhmrentiva_payment_status', 'paid');
	}

	public function tearDown(): void
	{
		PaymentView::reset();
		parent::tearDown();
	}

	/** @return list<array{0: string, 1: string}> */
	private function rows(): array
	{
		$history = get_post_meta($this->booking, '_mhmrentiva_booking_history', true);
		$out     = array();
		foreach (is_array($history) ? $history : array() as $e) {
			if ('payment_update' === ( $e['type'] ?? '' )) {
				$out[] = array( (string) $e['payment_from'], (string) $e['payment_to'] );
			}
		}
		return $out;
	}

	/**
	 * @return list<array{0: string, 1: string}>
	 */
	private function new_rows(callable $act): array
	{
		$before = count($this->rows());
		$act();
		return array_slice($this->rows(), $before);
	}

	public function test_a_partial_woocommerce_refund_writes_one_row(): void
	{
		$this->create_paid_order_for_booking($this->booking, '120');
		$rows = $this->new_rows(fn () => Service::process($this->booking, Money::toMinor('20'), 'partial', $this->admin));
		$this->assertSame(array( array( 'paid', 'partially_refunded' ) ), $rows);
	}

	public function test_a_full_refund_writes_one_row(): void
	{
		$this->create_paid_order_for_booking($this->booking, '120');
		$rows = $this->new_rows(fn () => Service::processFullRefund($this->booking, 'full', $this->admin));
		$this->assertSame(array( array( 'paid', 'refunded' ) ), $rows);
	}

	public function test_an_offline_partial_refund_writes_one_row(): void
	{
		update_post_meta($this->booking, '_mhmrentiva_total_price', '80');
		update_post_meta($this->booking, '_mhmrentiva_remaining_amount', '0');
		$rows = $this->new_rows(fn () => Service::process($this->booking, Money::toMinor('30'), 'offline', $this->admin));
		$this->assertSame(array( array( 'paid', 'partially_refunded' ) ), $rows);
	}

	public function test_a_refund_on_the_woocommerce_order_screen_writes_one_row(): void
	{
		$order = $this->create_paid_order_for_booking($this->booking, '120');
		$rows  = $this->new_rows(static function () use ($order): void {
			wc_create_refund(array(
				'order_id' => $order->get_id(),
				'amount'   => '120',
				'reason'   => 'order screen',
			));
		});
		$this->assertSame(array( array( 'paid', 'refunded' ) ), $rows);
	}
}
