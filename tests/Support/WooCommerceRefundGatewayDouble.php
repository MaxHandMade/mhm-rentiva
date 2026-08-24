<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * A minimal WC_Payment_Gateway that supports refunds, for tests that need
 * RefundValidator::modeForOrder() to answer MODE_AUTO.
 *
 * WooCommerceFixtures::create_paid_order_for_booking() never sets a payment
 * method, so wc_get_payment_gateway_by_order() resolves to false for every
 * order it creates and modeForOrder() always answers MODE_MANUAL -- correct
 * for that fixture's own purpose, but it means no test built purely on it can
 * ever prove the auto path or a mixed auto/manual operation. This double is
 * how a test opts one order into the auto path deliberately: register it via
 * WooCommerceRefundGatewayRegistration, then $order->set_payment_method(self::ID).
 */
class WooCommerceRefundGatewayDouble extends \WC_Payment_Gateway
{
	public const ID = 'mhmrentiva_test_auto_refund_gateway';

	/**
	 * What process_refund() returns, one value per call, in order. Left empty,
	 * every call succeeds -- the ordinary case. A test that needs a specific
	 * call to fail (a partial multi-order failure, after an earlier leg
	 * already succeeded) pushes false onto this queue for that call only.
	 *
	 * @var array<int, bool>
	 */
	public static array $results = array();

	public function __construct()
	{
		$this->id       = self::ID;
		$this->supports = array( 'products', 'refunds' );
	}

	/**
	 * wc_refund_payment() throws when this returns falsy, which is the exact
	 * failure the old hardcoded `refund_payment => true` produced against a
	 * gateway-less test order ("The payment gateway for this order does not
	 * exist") -- a double that could not report success would prove nothing
	 * that fixture-only test did not already show.
	 *
	 * @param int        $order_id Unused: this double does not call out to a
	 *                              real payment processor.
	 * @param float|null $amount   Unused, for the same reason.
	 * @param string     $reason   Unused, for the same reason.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): bool
	{
		if ( array() === self::$results ) {
			return true;
		}

		return array_shift( self::$results );
	}
}
