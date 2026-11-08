<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Backslash class (2026-11-08): a WooCommerce refund copies its reason to the
 * booking and appends a log row. The reason (typed by the shop manager) and the
 * booking's older log rows -- the log array is rewritten whole on every append
 * and the meta API unslashes it deeply -- keep their backslashes.
 */
final class RefundReasonKeepsBackslashTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	public function test_the_refund_reason_and_older_log_rows_keep_their_backslash(): void
	{
		$this->require_woocommerce();

		$booking_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		update_post_meta($booking_id, '_mhmrentiva_booking_logs', wp_slash(array(
			array( 'action' => 'older', 'timestamp' => '2026-01-01 00:00:00', 'user_id' => 0, 'data' => array( 'reference' => 'ESKI\1' ) ),
		)));

		$order = $this->create_paid_order_for_booking($booking_id, '100.00');
		$refund = wc_create_refund(array(
			'order_id' => $order->get_id(),
			'amount'   => '10.00',
			'reason'   => 'Hasar C:\foto\3',
		));
		$this->assertNotWPError($refund);
		$this->assertSame('Hasar C:\foto\3', $refund->get_reason(), 'Fixture: WooCommerce itself keeps the reason.');

		$this->assertSame('Hasar C:\foto\3', get_post_meta($booking_id, '_mhmrentiva_refund_reason', true));
		$logs = (array) get_post_meta($booking_id, '_mhmrentiva_booking_logs', true);
		$this->assertSame('ESKI\1', $logs[0]['data']['reference'] ?? null, 'An older row keeps its backslash.');
		$this->assertSame('wc_refund_created', end($logs)['action'] ?? null);
	}
}
