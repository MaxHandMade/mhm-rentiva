<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use WP_Ajax_UnitTestCase;

/**
 * Regression test: switching the checkout payment-type radio from "full"
 * back to "deposit" must restore _mhm_remaining_amount, not leave it at 0.
 *
 * Bug (found 2026-07-02, live on mhmrentiva.com): the checkout page's own
 * JS fires an on-load "sync selected payment type" AJAX call using whatever
 * radio is currently checked (WooCommerceBridge.php ~line 1572) — including
 * a stale "full" selection left over from an unrelated cart item. That call
 * zeroes _mhm_remaining_amount for every booking in the cart
 * (ajax_update_payment_type()'s 'full' branch). When the customer then
 * switches (or the code switches) the radio to "deposit", the 'deposit'
 * branch recalculates the amount to charge now but never restores
 * _mhm_remaining_amount — so the booking is left showing "0 remaining" even
 * though only the deposit was ever paid. Reproduced independently of any
 * payment gateway; this is a pre-existing WooCommerceBridge defect.
 */
final class PaymentTypeRemainingAmountTest extends WP_Ajax_UnitTestCase {

	private int $booking_id;

	/** @var string */
	protected $_last_response;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce not loaded.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_sku( WooCommerceBridge::PRODUCT_SKU );
		$product->set_regular_price( '1' );
		$product->save();

		$this->booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'vehicle_booking',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $this->booking_id, '_mhm_payment_type', 'deposit' );
		update_post_meta( $this->booking_id, '_mhm_total_price', 1000 );
		update_post_meta( $this->booking_id, '_mhm_deposit_amount', 200 );
		update_post_meta( $this->booking_id, '_mhm_remaining_amount', 800 );

		WooCommerceBridge::register();

		WC()->cart->empty_cart();
		$this->assertTrue(
			WooCommerceBridge::add_booking_to_cart( $this->booking_id, 200 ),
			'Failed to add the test booking to the cart.'
		);
	}

	public function tearDown(): void {
		WC()->cart->empty_cart();
		parent::tearDown();
	}

	public function test_switching_back_to_deposit_restores_remaining_amount(): void {
		// Simulate the checkout page's on-load sync firing with 'full'
		// (e.g. a stale session default carried over from a mixed cart).
		$this->dispatch_payment_type( 'full' );
		$this->assertSame(
			0.0,
			(float) get_post_meta( $this->booking_id, '_mhm_remaining_amount', true ),
			'Sanity check: switching to full should zero remaining.'
		);

		// Customer (or the page's own UI) then switches back to deposit.
		$this->dispatch_payment_type( 'deposit' );

		$this->assertSame(
			800.0,
			(float) get_post_meta( $this->booking_id, '_mhm_remaining_amount', true ),
			'Switching back to deposit must restore remaining_amount (total - deposit), not leave it at 0.'
		);
	}

	public function test_deposit_selection_without_prior_full_leaves_remaining_untouched(): void {
		// No prior 'full' toggle at all — remaining_amount should already be
		// correct from booking creation and must survive an explicit
		// (redundant) 'deposit' selection unchanged.
		$this->dispatch_payment_type( 'deposit' );

		$this->assertSame(
			800.0,
			(float) get_post_meta( $this->booking_id, '_mhm_remaining_amount', true )
		);
	}

	private function dispatch_payment_type( string $payment_type ): void {
		$_POST['action']       = 'mhm_update_booking_payment_type';
		$_POST['payment_type'] = $payment_type;
		$_POST['nonce']        = wp_create_nonce( 'mhm_booking_payment_type' );

		try {
			$this->_handleAjax( 'mhm_update_booking_payment_type' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected — the AJAX handler always wp_send_json_*()s + wp_die()s.
		}
	}
}
