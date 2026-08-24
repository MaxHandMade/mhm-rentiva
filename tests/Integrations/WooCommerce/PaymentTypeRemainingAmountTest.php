<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * Regression test: switching the checkout payment-type radio from "full"
 * back to "deposit" must restore _mhmrentiva_remaining_amount, not leave it at 0.
 *
 * Bug (found 2026-07-02, live on mhmrentiva.com): the checkout page's own
 * JS fires an on-load "sync selected payment type" AJAX call using whatever
 * radio is currently checked (WooCommerceBridge.php ~line 1572) — including
 * a stale "full" selection left over from an unrelated cart item. That call
 * zeroes _mhmrentiva_remaining_amount for every booking in the cart
 * (ajax_update_payment_type()'s 'full' branch). When the customer then
 * switches (or the code switches) the radio to "deposit", the 'deposit'
 * branch recalculates the amount to charge now but never restores
 * _mhmrentiva_remaining_amount — so the booking is left showing "0 remaining" even
 * though only the deposit was ever paid. Reproduced independently of any
 * payment gateway; this is a pre-existing WooCommerceBridge defect.
 */
final class PaymentTypeRemainingAmountTest extends WP_Ajax_UnitTestCase {
	use WooCommerceFixtures;


	private int $booking_id;

	/** @var string */
	protected $_last_response;

	public function setUp(): void {
		parent::setUp();

		$this->require_woocommerce();

		$this->ensure_booking_product();

		$this->booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $this->booking_id, '_mhmrentiva_payment_type', 'deposit' );
		update_post_meta( $this->booking_id, '_mhmrentiva_total_price', 1000 );
		update_post_meta( $this->booking_id, '_mhmrentiva_deposit_amount', 200 );
		update_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', 800 );

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
			(float) get_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', true ),
			'Sanity check: switching to full should zero remaining.'
		);

		// Customer (or the page's own UI) then switches back to deposit.
		$this->dispatch_payment_type( 'deposit' );

		$this->assertSame(
			800.0,
			(float) get_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', true ),
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
			(float) get_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', true )
		);
	}

	private function dispatch_payment_type( string $payment_type ): void {
		$_POST['action']       = 'mhmrentiva_update_booking_payment_type';
		$_POST['payment_type'] = $payment_type;
		$_POST['nonce']        = wp_create_nonce( 'mhmrentiva_booking_payment_type' );

		try {
			$this->_handleAjax( 'mhmrentiva_update_booking_payment_type' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected — the AJAX handler always wp_send_json_*()s + wp_die()s.
		}
	}

	public function test_full_payment_only_item_keeps_full_label_when_cart_selects_deposit(): void {
		// A second booking whose own configuration is full-payment-only
		// (deposit_amount === total_price, e.g. a VIP transfer created while
		// the transfer deposit setting is "Full Payment Required" —
		// TransferCartIntegration.php's full_payment branch).
		$transfer_booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $transfer_booking_id, '_mhmrentiva_payment_type', 'full' );
		update_post_meta( $transfer_booking_id, '_mhmrentiva_total_price', 1025 );
		update_post_meta( $transfer_booking_id, '_mhmrentiva_deposit_amount', 1025 );
		update_post_meta( $transfer_booking_id, '_mhmrentiva_remaining_amount', 0 );

		$this->assertTrue(
			WooCommerceBridge::add_booking_to_cart( $transfer_booking_id, 1025 ),
			'Failed to add the full-payment-only booking to the cart.'
		);

		// Checkout-wide radio selects "deposit" for the whole cart (the
		// mixed-cart scenario from the 2026-07-02 live test).
		$this->dispatch_payment_type( 'deposit' );

		$this->assertSame(
			'deposit',
			get_post_meta( $this->booking_id, '_mhmrentiva_payment_type', true ),
			'The genuinely deposit-eligible booking must still become deposit.'
		);
		$this->assertSame(
			'full',
			get_post_meta( $transfer_booking_id, '_mhmrentiva_payment_type', true ),
			'A full-payment-only booking (deposit_amount === total_price) must not be relabeled deposit just because the cart-wide radio selected it.'
		);
		$this->assertSame(
			0.0,
			(float) get_post_meta( $transfer_booking_id, '_mhmrentiva_remaining_amount', true )
		);
	}

	public function test_pending_cart_item_full_payment_only_keeps_full_label(): void {
		// Mirrors TransferCartIntegration's "Full Payment Required" branch:
		// a not-yet-created booking held as raw cart data with
		// deposit_amount === total_price.
		$booking_data = array(
			'vehicle_id'       => 0,
			'total_price'      => 1025.0,
			'deposit_amount'   => 1025.0,
			'remaining_amount' => 0.0,
			'payment_type'     => 'full',
		);

		$this->assertTrue(
			WooCommerceBridge::add_booking_data_to_cart( $booking_data, 1025.0 ),
			'Failed to add the pending full-payment-only booking data to the cart.'
		);

		$this->dispatch_payment_type( 'deposit' );

		$pending_item = null;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['mhmrentiva_booking_pending'] ) && $cart_item['mhmrentiva_booking_pending'] ) {
				$pending_item = $cart_item;
				break;
			}
		}

		$this->assertNotNull( $pending_item, 'Pending cart item not found.' );
		$this->assertSame( 'full', $pending_item['mhmrentiva_booking_data']['payment_type'] );
		$this->assertSame( 0.0, (float) $pending_item['mhmrentiva_booking_data']['remaining_amount'] );
	}
}
