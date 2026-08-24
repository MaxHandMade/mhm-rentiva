<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Wires WooCommerceRefundGatewayDouble into WC_Payment_Gateways for one test.
 *
 * WC_Payment_Gateways::payment_gateways() is served from a property built
 * once inside init(), itself populated from the woocommerce_payment_gateways
 * filter -- adding the filter alone does nothing once WC has already booted,
 * which it has by the time a test runs. register_refund_gateway_double() adds
 * the filter and re-runs init() to force the rebuild; unregister_refund_gateway_double()
 * removes the filter (so any later init() elsewhere in the run does not keep
 * picking the double back up) and drains the double's result queue.
 */
trait WooCommerceRefundGatewayRegistration
{
	protected function register_refund_gateway_double(): void
	{
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_refund_gateway_double' ) );
		WC()->payment_gateways()->init();
	}

	protected function unregister_refund_gateway_double(): void
	{
		remove_filter( 'woocommerce_payment_gateways', array( $this, 'add_refund_gateway_double' ) );

		WooCommerceRefundGatewayDouble::$results = array();
	}

	/**
	 * @param array<int, string|\WC_Payment_Gateway> $gateways
	 * @return array<int, string|\WC_Payment_Gateway>
	 */
	public function add_refund_gateway_double( array $gateways ): array
	{
		$gateways[] = WooCommerceRefundGatewayDouble::class;

		return $gateways;
	}
}
