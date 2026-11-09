<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * A remaining-payment order can only take what the booking owes now.
 *
 * An admin re-price moves the remaining amount (function scan H2 face 2), and
 * an order built before that -- or between the admin's check and the write --
 * still asks for the old amount (Codex plan audits r7-r9: a customer could pay
 * 2,050 for a booking re-priced to owe nothing). The order now carries the
 * gross amount it was built for; the pay-time claim refuses an order whose
 * amount is not the booking's current remaining, before the gateway; and a
 * new link is a new order rather than the stale one handed out again. The
 * stale order is not cancelled (cancelling runs the booking's cancellation
 * hooks); it just cannot take money.
 *
 * Plan: docs/superpowers/plans/2026-11-09-h2-yuz-2-fark-kalan.md (r9-r10, T-h′/T-e″).
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler::get_or_create_remaining_order
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::claim_before_pay
 */
final class RemainingOrderStaleGuardTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $booking_id;

	/** @var callable */
	private $no_transactions;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		// get_or_create_remaining_order() takes the booking lock in a
		// transaction; a nested START TRANSACTION would commit the test's.
		// Removed in tearDown() before the framework's own ROLLBACK.
		$this->no_transactions = static function ($query) {
			return preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query) ? 'SELECT 1' : $query;
		};
		add_filter('query', $this->no_transactions);

		$customer         = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		foreach (array(
			'_mhmrentiva_customer_user_id' => $customer,
			'_mhmrentiva_payment_type'     => 'deposit',
			'_mhmrentiva_payment_status'   => 'paid',
			'_mhmrentiva_status'           => 'confirmed',
			'_mhmrentiva_total_price'      => '1500',
			'_mhmrentiva_deposit_amount'   => '500',
			'_mhmrentiva_remaining_amount' => '1000',
		) as $key => $value) {
			update_post_meta($this->booking_id, $key, $value);
		}
		// The deposit, paid through WooCommerce.
		$this->create_paid_order_for_booking($this->booking_id, '500');

		wc_load_cart();
		wc_clear_notices();
	}

	public function tearDown(): void
	{
		remove_filter('query', $this->no_transactions);
		wc_clear_notices();
		parent::tearDown();
	}

	private function link(): \WC_Order
	{
		$order = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);
		$this->assertInstanceOf(\WC_Order::class, $order);

		return $order;
	}

	private function pay(\WC_Order $order): bool
	{
		wc_clear_notices();
		WooCommerceBridge::claim_before_pay(wc_get_order($order->get_id()));

		return 0 === wc_notice_count('error');
	}

	public function test_the_order_carries_the_amount_it_was_built_for(): void
	{
		$order = $this->link();

		$this->assertSame( (string) Money::toMinor(1000), (string) $order->get_meta('_mhmrentiva_remaining_due_minor', true));
	}

	public function test_an_unchanged_booking_reuses_its_pending_order(): void
	{
		$first = $this->link();

		$this->assertSame($first->get_id(), $this->link()->get_id());
	}

	public function test_a_re_priced_booking_gets_a_new_order_and_the_old_one_stays_unpaid(): void
	{
		$old = $this->link();
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '1340');

		$new = $this->link();

		$this->assertNotSame($old->get_id(), $new->get_id(), 'not the stale order again');
		$this->assertSame('pending', wc_get_order($old->get_id())->get_status(), 'not cancelled: that runs the booking cancellation hooks');
		$this->assertSame( (string) Money::toMinor(1340), (string) $new->get_meta('_mhmrentiva_remaining_due_minor', true));
	}

	public function test_a_stale_order_cannot_take_money_and_a_current_one_can(): void
	{
		$old = $this->link();
		// The admin re-prices after the customer built the link.
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '1340');

		$this->assertFalse($this->pay($old), 'the 1,000 order is refused before the gateway');
		$this->assertStringContainsString('no longer current', wp_strip_all_tags( (string) ( wc_get_notices('error')[0]['notice'] ?? '' )));

		$this->assertTrue($this->pay($this->link()), 'the order for 1,340 goes through');
	}

	public function test_an_order_for_a_booking_that_owes_nothing_any_more_is_refused(): void
	{
		$old = $this->link();
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

		$this->assertFalse($this->pay($old));
	}

	public function test_tax_on_the_order_does_not_make_a_current_order_stale(): void
	{
		$order = $this->link();
		// The order's own total recalculated with tax for the billing address.
		$order->set_total('1180');
		$order->save();

		$this->assertTrue($this->pay($order), 'compared with the gross amount it was built for, not its total');
	}

	public function test_an_order_from_before_the_stamp_is_compared_by_its_total(): void
	{
		$order = $this->link();
		$order->delete_meta_data('_mhmrentiva_remaining_due_minor');
		$order->set_total('1000');
		$order->save();
		$this->assertTrue($this->pay($order), 'total = remaining');

		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '1340');
		$this->assertFalse($this->pay($order), 'total ≠ remaining');
	}
}
