<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Remaining-payment order safety (PR-2, narrow scope).
 *
 * A remaining-payment order is the customer's second leg of a deposit booking.
 * Five things went wrong with it, each measured on main 1a4d15e7:
 *  - its failure or cancellation cancelled the whole booking when no other
 *    WooCommerce payment existed (WooCommerceBridge.php:3216);
 *  - an order that was no longer the booking's link, or was cancelled, could
 *    still take money (claim_order_bookings never looked at the pointer);
 *  - a late payment zeroed the remaining amount whatever it had been paid
 *    against (:3111-3114, :3123-3125);
 *  - a paid remaining order that was later cancelled lost its only reference,
 *    so PaymentState stopped seeing the money;
 *  - "send the link again" on a failed order built a new one beside it.
 *
 * Plan: docs/superpowers/plans/2026-11-11-pr2-kalan-odeme-guvenligi.md (r4 A1-A3, r5).
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::handle_order_status_change
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::claim_before_pay
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler::get_or_create_remaining_order
 */
final class RemainingOrderSafetyTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	/** @var callable */
	private $no_transactions;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		// The booking lock opens a transaction; a nested START TRANSACTION would
		// commit the test's own. Removed in tearDown() before its ROLLBACK.
		$this->no_transactions = static function ($query) {
			return preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query) ? 'SELECT 1' : $query;
		};
		add_filter('query', $this->no_transactions);

		wc_load_cart();
		wc_clear_notices();
	}

	public function tearDown(): void
	{
		remove_filter('query', $this->no_transactions);
		wc_clear_notices();
		parent::tearDown();
	}

	/**
	 * A deposit booking owing $remaining. With $deposit_order the deposit was
	 * paid through WooCommerce; without it the booking holds no proven payment
	 * (a manual booking whose link was sent before anything was collected).
	 */
	private function booking(string $remaining = '1000', bool $deposit_order = true): int
	{
		$customer   = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		foreach (array(
			'_mhmrentiva_customer_user_id' => $customer,
			'_mhmrentiva_payment_type'     => 'deposit',
			'_mhmrentiva_payment_status'   => $deposit_order ? 'paid' : 'pending',
			'_mhmrentiva_status'           => 'confirmed',
			'_mhmrentiva_total_price'      => '1500',
			'_mhmrentiva_deposit_amount'   => '500',
			'_mhmrentiva_remaining_amount' => $remaining,
		) as $key => $value) {
			update_post_meta($booking_id, $key, $value);
		}
		if ($deposit_order) {
			$this->create_paid_order_for_booking($booking_id, '500');
		} else {
			// The link is built on the booking product, which the paid-order
			// fixture otherwise creates as a side effect.
			$this->ensure_booking_product();
		}

		return $booking_id;
	}

	private function link(int $booking_id): \WC_Order
	{
		$order = RemainingPaymentHandler::get_or_create_remaining_order($booking_id);
		$this->assertInstanceOf(\WC_Order::class, $order);

		return $order;
	}

	private function pay(\WC_Order $order): bool
	{
		wc_clear_notices();
		WooCommerceBridge::claim_before_pay(wc_get_order($order->get_id()));

		return 0 === wc_notice_count('error');
	}

	private function move(\WC_Order $order, string $status): void
	{
		$fresh = wc_get_order($order->get_id());
		$fresh->update_status($status);
	}

	private function remaining(int $booking_id): string
	{
		wp_cache_delete($booking_id, 'post_meta');

		return (string) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);
	}

	private function mismatch(int $booking_id): mixed
	{
		wp_cache_delete($booking_id, 'post_meta');

		return get_post_meta($booking_id, '_mhmrentiva_payment_mismatch', true);
	}

	// --- A1: a remaining order's failure never cancels the booking ---------

	public function test_a_failed_remaining_order_does_not_cancel_a_booking_with_no_other_payment(): void
	{
		$booking_id = $this->booking('1000', false);
		$order      = $this->link($booking_id);

		$this->move($order, 'failed');

		$this->assertSame('confirmed', (string) get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	public function test_a_failed_remaining_order_with_no_paid_sibling_says_so_and_sends_no_sibling_mail(): void
	{
		// The sibling e-mail says "another order on the same
		// booking is still paid" -- false when nothing was ever collected.
		$booking_id = $this->booking('1000', false);
		$order      = $this->link($booking_id);
		$subjects   = array();
		$capture    = static function ($short, $atts) use (&$subjects) {
			$subjects[] = (string) ( $atts['subject'] ?? '' );
			return true;
		};
		add_filter('pre_wp_mail', $capture, 10, 2);

		$this->move($order, 'failed');
		remove_filter('pre_wp_mail', $capture, 10);

		$this->assertSame(array(), array_filter($subjects, static fn($s) => str_contains($s, 'still paid')));
		$notes = array_map(static fn($n) => $n->content, wc_get_order_notes(array( 'order_id' => $order->get_id() )));
		$this->assertNotEmpty(array_filter($notes, static fn($n) => str_contains($n, 'The booking was kept')));
	}

	public function test_a_cancelled_unpaid_remaining_order_does_not_cancel_the_booking_and_clears_the_link(): void
	{
		$booking_id = $this->booking('1000', false);
		$order      = $this->link($booking_id);

		$this->move($order, 'cancelled');

		$this->assertSame('confirmed', (string) get_post_meta($booking_id, '_mhmrentiva_status', true));
		wp_cache_delete($booking_id, 'post_meta');
		$this->assertSame('', (string) get_post_meta($booking_id, '_mhmrentiva_remaining_order_id', true));
	}

	public function test_the_unpaid_primary_order_dying_still_releases_a_stale_reservation(): void
	{
		// The other half of A1: WooCommerce's unpaid-order cron cancels the
		// deposit order of a stale reservation too, and that order's own
		// transition still cancels the booking.
		$booking_id = $this->booking('1000', false);
		update_post_meta($booking_id, '_mhmrentiva_status', 'pending');
		$this->link($booking_id);
		$product = $this->ensure_booking_product('500');
		$primary = wc_create_order(array( 'status' => 'pending' ));
		$item    = new \WC_Order_Item_Product();
		$item->set_product($product);
		$item->set_quantity(1);
		$item->set_total(500);
		$item->add_meta_data('_mhmrentiva_booking_id', $booking_id, true);
		$primary->add_item($item);
		$primary->calculate_totals();
		$primary->save();
		update_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', $primary->get_id());

		$this->move($primary, 'cancelled');

		$this->assertSame('cancelled', (string) get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	public function test_a_failed_remaining_order_stays_the_link_and_can_be_paid_again(): void
	{
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);

		$this->move($order, 'failed');

		wp_cache_delete($booking_id, 'post_meta');
		$this->assertSame($order->get_id(), (int) get_post_meta($booking_id, '_mhmrentiva_remaining_order_id', true));
		$this->assertTrue($this->pay($order), 'a declined card is retried on the same order');
	}

	public function test_sending_the_link_again_reuses_a_failed_order(): void
	{
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		$this->move($order, 'failed');

		$this->assertSame($order->get_id(), $this->link($booking_id)->get_id());
	}

	public function test_a_paid_remaining_order_cancelled_later_stays_in_the_payment_history(): void
	{
		$booking_id = $this->booking('1000', false);
		$order      = $this->link($booking_id);
		$this->move($order, 'processing');

		$this->move($order, 'cancelled');

		$ids = array_map('intval', PaymentState::forBooking($booking_id)->orders());
		$this->assertContains($order->get_id(), $ids, 'cancelled is not a refund: the money is still on that order');
	}

	// --- A2: only the booking's live link can take money -----------------

	public function test_an_order_that_is_no_longer_the_link_cannot_take_money(): void
	{
		$booking_id = $this->booking();
		$old        = $this->link($booking_id);
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1340');
		$new = $this->link($booking_id);
		// Back to the old amount: the old order's stamp matches again, but it
		// is not the link the business sent last.
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000');

		$this->assertNotSame($old->get_id(), $new->get_id());
		$this->assertFalse($this->pay($old));
	}

	public function test_a_cancelled_remaining_order_cannot_take_money(): void
	{
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		$this->move($order, 'cancelled');

		$this->assertFalse($this->pay($order));
	}

	public function test_the_live_link_still_takes_money(): void
	{
		$booking_id = $this->booking();

		$this->assertTrue($this->pay($this->link($booking_id)));
	}

	// --- A4: the checkout's type switch leaves a paid booking alone --------

	public function test_the_checkout_type_switch_may_not_retype_a_booking_that_holds_money(): void
	{
		$this->assertFalse(WooCommerceBridge::may_retype_booking($this->booking()));
	}

	public function test_the_checkout_type_switch_may_not_retype_a_booking_paid_offline(): void
	{
		$booking_id = $this->booking('1000', false);
		update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');

		$this->assertFalse(WooCommerceBridge::may_retype_booking($booking_id));
	}

	public function test_the_checkout_type_switch_may_retype_a_booking_still_waiting_for_payment(): void
	{
		$this->assertTrue(WooCommerceBridge::may_retype_booking($this->booking('1000', false)));
	}

	// --- A3: a late payment is not applied blindly -------------------------

	public function test_a_payment_matching_the_debt_clears_it(): void
	{
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);

		$this->move($order, 'processing');

		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
		$this->assertSame('', $this->mismatch($booking_id));
	}

	public function test_a_balance_write_that_did_not_hold_is_not_marked_applied(): void
	{
		// PR #195 review (P2): the applied mark ends every later attempt, so it
		// may only follow a write that held. A write that failed stays
		// retryable on the next transition.
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		$refuse     = static function ($check, $object_id, $meta_key) use ($booking_id) {
			return ( (int) $object_id === $booking_id && '_mhmrentiva_remaining_amount' === $meta_key ) ? false : $check;
		};
		add_filter('update_post_metadata', $refuse, 10, 3);
		$this->move($order, 'processing');
		remove_filter('update_post_metadata', $refuse, 10);

		$this->assertEquals(1000.0, (float) $this->remaining($booking_id));
		$this->assertSame('', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_applied', true));

		$this->move($order, 'completed');

		$this->assertEquals(0.0, (float) $this->remaining($booking_id), 'the next transition settles it');
		$this->assertNotSame('', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_applied', true));
	}

	public function test_a_cleared_balance_stored_as_another_zero_is_applied_and_ends_the_retry(): void
	{
		// After a first read failure (retry mark), a clear that
		// a sanitize filter stores as '0.00' must count as held -- otherwise
		// the next transition took the paid order for an unjudged one and
		// flagged a false mismatch.
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		add_post_meta($booking_id, '_mhmrentiva_remaining_amount', '999'); // first read fails
		$this->move($order, 'processing');
		delete_post_meta($booking_id, '_mhmrentiva_remaining_amount', '999');
		$this->assertSame('1', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_retry', true));

		$two_places = static fn($value) => '0' === (string) $value ? '0.00' : $value;
		add_filter('sanitize_post_meta__mhmrentiva_remaining_amount', $two_places);
		$this->move($order, 'completed');
		remove_filter('sanitize_post_meta__mhmrentiva_remaining_amount', $two_places);

		$fresh = wc_get_order($order->get_id());
		$this->assertNotSame('', (string) $fresh->get_meta('_mhmrentiva_remaining_applied', true));
		$this->assertSame('', (string) $fresh->get_meta('_mhmrentiva_remaining_retry', true));

		$this->move($order, 'processing'); // stepped back; no new money
		$this->assertSame('', $this->mismatch($booking_id));
		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
	}

	public function test_a_payment_against_an_old_amount_does_not_zero_the_new_debt(): void
	{
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		// The amount moved after the customer started paying.
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1340');

		$this->move($order, 'processing');

		$this->assertEquals(1340.0, (float) $this->remaining($booking_id));
		$flag = $this->mismatch($booking_id);
		$this->assertIsArray($flag);
		$this->assertSame($order->get_id(), (int) $flag['order_id']);
	}

	public function test_processing_then_completed_is_one_payment_not_a_mismatch(): void
	{
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);

		$this->move($order, 'processing');
		$this->move($order, 'completed');

		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
		$this->assertSame('', $this->mismatch($booking_id));
	}

	public function test_a_payment_after_the_debt_was_settled_by_hand_is_a_mismatch(): void
	{
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		// The operator marked the remaining paid offline in the meantime.
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '0');

		$this->move($order, 'processing');

		$this->assertIsArray($this->mismatch($booking_id), 'the customer has now paid twice: someone must be told');
	}

	public function test_a_mismatch_is_decided_once_on_the_order(): void
	{
		// The next paid transition must not re-decide it. The
		// operator corrects the balance by hand and dismisses the notice; the
		// corrected figure happens to equal what the order was built for.
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1340');
		$this->move($order, 'processing');
		$this->assertIsArray($this->mismatch($booking_id));
		delete_post_meta($booking_id, '_mhmrentiva_payment_mismatch');
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000');

		$this->move($order, 'completed');

		$this->assertEquals(1000.0, (float) $this->remaining($booking_id), 'a balance a human set is not cleared by the same payment again');
		$this->assertSame('', $this->mismatch($booking_id), 'nor flagged again');
	}

	public function test_a_payment_whose_first_read_failed_is_still_judged_on_the_next_transition(): void
	{
		// "paid before, nothing owed now" is the pre-upgrade
		// exception, not proof. An order whose own first settle could not read
		// the balance was never judged, so it is judged now: the operator has
		// settled the balance by hand meanwhile, and the customer paid twice.
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		add_post_meta($booking_id, '_mhmrentiva_remaining_amount', '999'); // two rows that disagree: unreadable
		$this->move($order, 'processing');
		$this->assertSame('', $this->mismatch($booking_id), 'nothing decided on an unreadable balance');

		delete_post_meta($booking_id, '_mhmrentiva_remaining_amount');
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '0');
		$this->move($order, 'completed');

		$this->assertIsArray($this->mismatch($booking_id));
	}

	public function test_a_read_failure_after_the_first_judgement_does_not_reopen_an_applied_order(): void
	{
		// An order the old code applied before the upgrade (no
		// applied mark) whose later transition fails to read was judged already;
		// the retry mark must not take away its pre-upgrade exception.
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		$this->move($order, 'processing');
		$fresh = wc_get_order($order->get_id());
		$fresh->delete_meta_data('_mhmrentiva_remaining_applied'); // applied by the pre-upgrade code
		$fresh->save();
		add_post_meta($booking_id, '_mhmrentiva_remaining_order_id', '999999'); // the link read fails once
		$this->move($order, 'completed');
		delete_post_meta($booking_id, '_mhmrentiva_remaining_order_id', '999999');

		$this->move($order, 'processing'); // an operator steps it back; no new money

		$this->assertSame('', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_retry', true));
		$this->assertSame('', $this->mismatch($booking_id));
		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
	}

	public function test_a_late_payment_on_an_order_that_is_no_longer_the_link_does_not_settle(): void
	{
		// The amount matches, but the money lands on an order
		// PaymentState no longer reads (it reads the link), so clearing the
		// balance would hide it. A human decides.
		$booking_id = $this->booking();
		$old        = $this->link($booking_id);
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1340');
		$this->link($booking_id);
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000');

		// A late gateway return, or "paid" set by hand: it does not pass
		// claim_before_pay().
		$this->move($old, 'processing');

		$this->assertEquals(1000.0, (float) $this->remaining($booking_id));
		$flag = $this->mismatch($booking_id);
		$this->assertIsArray($flag);
		$this->assertSame($old->get_id(), (int) $flag['order_id']);
		$this->assertSame('not_link', (string) ( $flag['reason'] ?? '' ));
	}

	public function test_an_order_from_before_the_stamp_paid_against_another_amount_does_not_zero_the_debt(): void
	{
		// An unstamped order is judged by its total: it asks 1000, the booking
		// now owes 1340, so the payment is flagged for a human rather than
		// writing off the 340.
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1340');

		$this->move($order, 'processing');

		$this->assertEquals(1340.0, (float) $this->remaining($booking_id));
		$this->assertIsArray($this->mismatch($booking_id));
	}

	public function test_an_order_from_before_the_stamp_that_is_no_longer_the_link_does_not_settle(): void
	{
		$booking_id = $this->booking();
		$old        = $this->unstamped_link($booking_id);
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1340');
		$this->link($booking_id);
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000');

		$this->move($old, 'processing'); // a late gateway return

		$this->assertEquals(1000.0, (float) $this->remaining($booking_id));
		$flag = $this->mismatch($booking_id);
		$this->assertIsArray($flag);
		$this->assertSame('not_link', (string) ( $flag['reason'] ?? '' ));
	}

	private function unstamped_link(int $booking_id): \WC_Order
	{
		$order = $this->link($booking_id);
		$fresh = wc_get_order($order->get_id());
		$fresh->delete_meta_data('_mhmrentiva_remaining_due_minor');
		$fresh->save();
		return $fresh;
	}

	public function test_an_order_from_before_the_stamp_clears_once_and_leaves_a_later_debt(): void
	{
		// The order's one payment clears the balance it was paid for. A debt
		// added after that (an extra day, a damage charge) is not paid by the
		// same order when it moves on to completed.
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);

		$this->move($order, 'processing');
		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
		$this->assertNotSame('', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_applied', true));

		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '500');
		$this->move($order, 'completed');

		$this->assertEquals(500.0, (float) $this->remaining($booking_id));
	}

	/**
	 * Paid before the upgrade: the old code cleared the balance and left no
	 * applied mark. A debt added since is not paid by that order coming back
	 * to a paid status -- on to completed, or back from on-hold or cancelled.
	 *
	 * @dataProvider return_paths
	 */
	public function test_an_order_from_before_the_stamp_the_old_code_applied_does_not_clear_a_later_debt(string $via): void
	{
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);
		$this->move($order, 'processing');
		$fresh = wc_get_order($order->get_id());
		$fresh->delete_meta_data('_mhmrentiva_remaining_applied'); // applied by the pre-upgrade code
		$fresh->save();

		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '500');
		if ('' !== $via) {
			$this->move($fresh, $via);
		}
		$this->move($fresh, '' !== $via ? 'processing' : 'completed');

		$this->assertEquals(500.0, (float) $this->remaining($booking_id));
		$this->assertIsArray($this->mismatch($booking_id), 'flagged for a human');
	}

	/**
	 * The same, when the new debt happens to equal what the order asked: its
	 * amount fits, but the money it carries was taken days ago, before the
	 * applied mark existed. WooCommerce keeps that day in date_paid.
	 *
	 * @dataProvider return_paths
	 */
	public function test_an_order_paid_days_ago_without_the_applied_mark_does_not_clear_an_equal_new_debt(string $via): void
	{
		foreach (array( true, false ) as $stamped) {
			$booking_id = $this->booking();
			$order      = $stamped ? $this->link($booking_id) : $this->unstamped_link($booking_id);
			$this->move($order, 'processing');
			$fresh = wc_get_order($order->get_id());
			$fresh->delete_meta_data('_mhmrentiva_remaining_applied'); // applied by the pre-upgrade code
			$fresh->set_date_paid(time() - 3 * DAY_IN_SECONDS);
			$fresh->save();

			update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000'); // equal to the order's amount
			if ('' !== $via) {
				$this->move($fresh, $via);
			}
			$this->move($fresh, '' !== $via ? 'processing' : 'completed');

			$label = $stamped ? 'stamped' : 'unstamped';
			$this->assertEquals(1000.0, (float) $this->remaining($booking_id), $label);
			$flag = $this->mismatch($booking_id);
			$this->assertIsArray($flag, $label);
		}
	}

	public function test_a_read_failure_on_an_order_paid_days_ago_does_not_open_it_again(): void
	{
		// The retry mark is for an order never judged. One paid on an earlier
		// day, back from on-hold while the balance cannot be read, does not get
		// it -- otherwise an equal new debt would be cleared on completed.
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);
		$this->move($order, 'processing');
		$fresh = wc_get_order($order->get_id());
		$fresh->delete_meta_data('_mhmrentiva_remaining_applied'); // applied by the pre-upgrade code
		$fresh->set_date_paid(time() - 3 * DAY_IN_SECONDS);
		$fresh->save();

		$this->move($fresh, 'on-hold');
		add_post_meta($booking_id, '_mhmrentiva_remaining_amount', '999'); // two rows that disagree: unreadable
		$this->move($fresh, 'processing');
		$this->assertSame('', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_retry', true));

		delete_post_meta($booking_id, '_mhmrentiva_remaining_amount');
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000'); // equal to the order's amount
		$this->move($fresh, 'completed');

		$this->assertEquals(1000.0, (float) $this->remaining($booking_id));
		$this->assertIsArray($this->mismatch($booking_id));
	}

	/**
	 * @dataProvider return_paths
	 */
	public function test_a_clear_that_could_not_be_read_back_is_not_retried_against_a_later_debt(string $via): void
	{
		// The write held, but neither read-back could say so. A retry mark
		// would let the next transition clear a debt added since, with no new
		// money; without it, a balance still owed is flagged for a human.
		$booking_id = $this->booking();
		$order      = $this->link($booking_id);
		$written    = false;
		$break      = static function (string $query) use (&$written): string {
			if (false !== strpos($query, '_mhmrentiva_remaining_amount')) {
				if (0 === stripos(ltrim($query), 'UPDATE') || 0 === stripos(ltrim($query), 'INSERT')) {
					$written = true;
				} elseif ($written && 0 === stripos(ltrim($query), 'SELECT')) {
					return 'SELECT * FROM mhmrentiva_no_such_table';
				}
			}
			return $query;
		};
		add_filter('query', $break);
		$this->move($order, 'processing');
		remove_filter('query', $break);

		$this->assertTrue($written, 'premise: the clear was written');
		$this->assertEquals(0.0, (float) $this->remaining($booking_id), 'premise: and it held');
		$fresh = wc_get_order($order->get_id());
		$this->assertSame('', (string) $fresh->get_meta('_mhmrentiva_remaining_retry', true));
		$this->assertSame('', (string) $fresh->get_meta('_mhmrentiva_remaining_applied', true));

		// In the same request: the order's date_paid is not older than it.
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000'); // a new debt, equal to the order's amount
		if ('' !== $via) {
			$this->move($fresh, $via);
		}
		$this->move($fresh, '' !== $via ? 'processing' : 'completed');

		$this->assertEquals(1000.0, (float) $this->remaining($booking_id));
		$this->assertIsArray($this->mismatch($booking_id));
	}

	public function test_an_order_paid_days_ago_with_nothing_owed_is_left_alone(): void
	{
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);
		$this->move($order, 'processing');
		$fresh = wc_get_order($order->get_id());
		$fresh->delete_meta_data('_mhmrentiva_remaining_applied');
		$fresh->set_date_paid(time() - 3 * DAY_IN_SECONDS);
		$fresh->save();

		$this->move($fresh, 'on-hold');
		$this->move($fresh, 'processing');

		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
		$this->assertSame('', $this->mismatch($booking_id), 'a settled booking is not flagged');
	}

	public function test_a_first_payment_whose_read_failed_days_ago_is_still_judged(): void
	{
		// The retry mark says the order was never judged: an old date_paid
		// does not turn it into one the old code applied.
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);
		add_post_meta($booking_id, '_mhmrentiva_remaining_amount', '999'); // unreadable
		$this->move($order, 'processing');
		$fresh = wc_get_order($order->get_id());
		$fresh->set_date_paid(time() - 3 * DAY_IN_SECONDS);
		$fresh->save();
		delete_post_meta($booking_id, '_mhmrentiva_remaining_amount');
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000');

		$this->move($fresh, 'completed');

		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
	}

	/** @return array<string, array{string}> */
	public static function return_paths(): array
	{
		return array(
			'on to completed'      => array( '' ),
			'back from on-hold'    => array( 'on-hold' ),
			'back from cancelled'  => array( 'cancelled' ),
		);
	}

	public function test_an_order_from_before_the_stamp_whose_balance_write_did_not_hold_stays_retryable(): void
	{
		// The applied mark ends every later attempt, so on an unstamped order
		// too it follows only a write that held.
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);
		$refuse     = static function ($check, $object_id, $meta_key) use ($booking_id) {
			return ( (int) $object_id === $booking_id && '_mhmrentiva_remaining_amount' === $meta_key ) ? false : $check;
		};
		add_filter('update_post_metadata', $refuse, 10, 3);
		$this->move($order, 'processing');
		remove_filter('update_post_metadata', $refuse, 10);

		$this->assertEquals(1000.0, (float) $this->remaining($booking_id));
		$this->assertSame('', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_applied', true));

		// The next transition may come days later: the order still says it
		// was never judged.
		$fresh = wc_get_order($order->get_id());
		$fresh->set_date_paid(time() - 3 * DAY_IN_SECONDS);
		$fresh->save();
		$this->move($fresh, 'completed');

		$this->assertEquals(0.0, (float) $this->remaining($booking_id), 'the next transition settles it');
		$this->assertNotSame('', (string) wc_get_order($order->get_id())->get_meta('_mhmrentiva_remaining_applied', true));
	}

	public function test_an_order_from_before_the_stamp_whose_first_read_failed_still_clears_on_the_next_transition(): void
	{
		$booking_id = $this->booking();
		$order      = $this->unstamped_link($booking_id);
		add_post_meta($booking_id, '_mhmrentiva_remaining_amount', '999'); // two rows that disagree: unreadable
		$this->move($order, 'processing');

		delete_post_meta($booking_id, '_mhmrentiva_remaining_amount');
		update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '1000');
		$this->move($order, 'completed');

		$this->assertEquals(0.0, (float) $this->remaining($booking_id));
		$fresh = wc_get_order($order->get_id());
		$this->assertNotSame('', (string) $fresh->get_meta('_mhmrentiva_remaining_applied', true));
		$this->assertSame('', (string) $fresh->get_meta('_mhmrentiva_remaining_retry', true));
	}
}
