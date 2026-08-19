<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The single read surface for "where is this booking's money".
 *
 * Written for 6.1.0 to replace _mhmrentiva_payment_amount, a meta key with
 * zero writers in production code and sixteen read points across Lite and
 * Pro -- every one of them reading 0, which is why refunds were refused with
 * "Paid amount not found" and why Pro's reports showed a zero paid column.
 *
 * Three rules hold this class together:
 *
 * 1. The WooCommerce refund base is read, not derived. refundableAuto() reads
 *    WooCommerce's own get_remaining_refund_amount() instead of computing
 *    wc_paid - wc_refunded here. As of WooCommerce 11.0.1 that method IS
 *    literally get_total() - get_total_refunded() (includes/class-wc-order.php
 *    :2493-2495) -- so reading it produces the same number a local
 *    subtraction would, and does not shield a coupon or a hand-edited order
 *    total from skewing it. What reading buys instead: this class stays
 *    bound to WooCommerce's own definition of "still refundable", and
 *    follows it if that definition ever changes. The protection that does
 *    hold, and is real: no amount here is ever produced by differencing the
 *    WooCommerce and offline channels against each other -- that is what
 *    would let a booking report money nobody paid.
 * 2. Amounts are derived, statuses are stored. Booking lists filter on
 *    payment_status with meta_query and a derived value cannot be queried in
 *    SQL; no query anywhere filters on an amount.
 * 3. The offline channel's base IS derived -- from booking meta, total minus
 *    remaining -- but only once payment_status proves the money actually
 *    arrived. It is live only when the booking has no paid WooCommerce order,
 *    and both directions, paid and refunded, share that same liveness gate
 *    (see resolveOfflineChannel()): a payment_status outside the proof set
 *    now zeroes both legs together, instead of only one going dark while the
 *    other stays lit. This does NOT make refunded() <= paid() a general
 *    invariant -- once the gate is open, "total - remaining" and the stored
 *    _mhmrentiva_refunded_amount are still two independently maintained
 *    values with no arithmetic tie between them. refundableManual() is
 *    refunded by hand: there is no gateway behind it to send the refund
 *    through.
 *
 * @since 6.1.0
 */
final class PaymentState {

	/**
	 * @param int[] $order_ids Paid WooCommerce orders, in payment order.
	 */
	private function __construct(
		private readonly array  $order_ids,
		private readonly int    $wc_paid,
		private readonly int    $wc_refunded,
		private readonly int    $wc_refundable,
		private readonly string $currency,
		private readonly int    $offline_paid,
		private readonly int    $offline_refunded,
	) {
	}

	/**
	 * Resolve one consistent snapshot of a booking's payment state.
	 *
	 * Named forBooking() rather than the spec's for(): `for` is a reserved
	 * word and, while PHP 7+ accepts it as a method name, it reads badly at
	 * every call site.
	 */
	public static function forBooking(int $booking_id): self
	{
		$order_ids = self::resolvePaidOrders($booking_id);

		$paid       = 0;
		$refunded   = 0;
		$refundable = 0;
		$currency   = '';

		foreach ($order_ids as $order_id) {
			$order = wc_get_order($order_id);

			if (! $order instanceof \WC_Order) {
				continue;
			}

			$paid     += Money::toMinor($order->get_total());
			$refunded += Money::toMinor($order->get_total_refunded());
			// get_remaining_refund_amount() returns a MAJOR-unit string.
			$refundable += Money::toMinor($order->get_remaining_refund_amount());

			if ($currency === '') {
				$currency = (string) $order->get_currency();
			}
		}

		if ($currency === '') {
			$currency = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '';
		}

		list( $offline_paid, $offline_refunded ) = self::resolveOfflineChannel($booking_id, $order_ids);

		return new self(
			$order_ids,
			$paid,
			$refunded,
			$refundable,
			$currency,
			$offline_paid,
			$offline_refunded
		);
	}

	// The booking id is deliberately NOT kept as a property. PHPStan level 5
	// catches write-only state, and nothing in this slice ever reads it back --
	// resolveOfflineChannel() takes it as a parameter from this scope instead.
	// When a later slice needs the object to carry its own identity, it gets
	// added together with the caller that needs it.

	/**
	 * The orders whose money actually arrived, original first.
	 *
	 * `resolve_wc_order_id()` knows four legacy keys but not the
	 * remaining-payment order, which is why the refund subsystem never saw the
	 * second half of a deposit booking's money.
	 *
	 * @return int[]
	 */
	private static function resolvePaidOrders(int $booking_id): array
	{
		if (! function_exists('wc_get_order')) {
			return array();
		}

		$candidates = array(
			BookingQueryHelper::resolve_wc_order_id($booking_id),
			(int) get_post_meta($booking_id, '_mhmrentiva_remaining_order_id', true),
		);

		$paid = array();

		foreach ($candidates as $order_id) {
			if ($order_id <= 0 || in_array($order_id, $paid, true)) {
				continue;
			}

			$order = wc_get_order($order_id);

			// get_date_paid() rather than is_paid(): is_paid() is status-based
			// and a fully refunded order sits in `refunded`, which would drop
			// it from the set and take its refund history with it.
			if ($order instanceof \WC_Order && $order->get_date_paid() !== null) {
				$paid[] = $order_id;
			}
		}

		return $paid;
	}

	/**
	 * Money taken outside WooCommerce, both directions, behind one gate.
	 *
	 * Both legs share a single predicate on purpose. They used to differ:
	 * offline_paid demanded a payment_status that proves money arrived, while
	 * offline_refunded only demanded the absence of a WooCommerce order. A
	 * booking cancelled by AutoCancel while carrying a refund record therefore
	 * reported refunded() > paid() -- money returned that was never received.
	 *
	 * The gate itself: _mhmrentiva_remaining_amount is written for deposit
	 * bookings only, so "total - remaining" on a full-payment offline booking
	 * would read the entire price as paid before anyone had paid a lira.
	 * 'cancelled' is not proof of payment, and it is not proof of a refund.
	 *
	 * Sharing the gate does not make refunded() <= paid() a general
	 * invariant. Once the gate is open (a proof status is present),
	 * "total - remaining" and _mhmrentiva_refunded_amount are still two
	 * independently maintained values with no arithmetic tie between them --
	 * a 'paid' deposit booking whose full amount is still sitting in
	 * remaining, alongside an unrelated refund record, reports paid() === 0
	 * and refunded() > 0 by the same arithmetic. What this method fixes is
	 * liveness parity: a status outside the proof set now zeroes both legs
	 * together, rather than only one of them.
	 *
	 * @param int[] $order_ids
	 * @return array{0: int, 1: int} paid, refunded -- both in minor units.
	 */
	private static function resolveOfflineChannel(int $booking_id, array $order_ids): array
	{
		// With a paid WooCommerce order present, WooCommerce is the authority
		// and the offline channel is not live at all.
		if (! empty($order_ids)) {
			return array( 0, 0 );
		}

		$status = (string) get_post_meta($booking_id, '_mhmrentiva_payment_status', true);

		if (! in_array($status, array( 'paid', 'partially_refunded', 'refunded' ), true)) {
			return array( 0, 0 );
		}

		$total     = Money::toMinor( (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true));
		$remaining = Money::toMinor( (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true));
		$refunded  = max(0, (int) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true));

		return array( max(0, $total - $remaining), $refunded );
	}

	/**
	 * @return int[]
	 */
	public function orders(): array
	{
		return $this->order_ids;
	}

	/**
	 * Money that arrived, across both channels. A reporting figure -- never a
	 * refund amount.
	 *
	 * Refunds never read paid() itself: they read refundableAuto() for the
	 * WooCommerce channel and refundableManual() for the offline one, each
	 * bound to that channel's own record of what is still owed back.
	 */
	public function paid(): int
	{
		return $this->wc_paid + $this->offline_paid;
	}

	public function refunded(): int
	{
		return $this->wc_refunded + $this->offline_refunded;
	}

	/**
	 * What WooCommerce itself says is still refundable.
	 */
	public function refundableAuto(): int
	{
		return $this->wc_refundable;
	}

	/**
	 * Offline money still owed back. It cannot go through a gateway, so the
	 * refund flow classifies it as a manual refund.
	 */
	public function refundableManual(): int
	{
		return max(0, $this->offline_paid - $this->offline_refunded);
	}

	public function refundable(): int
	{
		return $this->refundableAuto() + $this->refundableManual();
	}

	public function currency(): string
	{
		return $this->currency;
	}

	public function isFullyRefunded(): bool
	{
		return $this->paid() > 0 && $this->refunded() >= $this->paid();
	}
}
