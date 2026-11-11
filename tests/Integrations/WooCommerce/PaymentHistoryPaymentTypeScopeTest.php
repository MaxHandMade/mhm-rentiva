<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Booking\Core\PaymentView;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * The checkout payment-type switch writes the booking's type and remaining
 * amount for an existing booking in the cart (WooCommerceBridge:3609-3618,
 * the `mhmrentiva_booking_id` branch); that write is a PaymentView scope
 * (spec PR 3a, plan r4 R1 / Codex r1 #3).
 */
final class PaymentHistoryPaymentTypeScopeTest extends WP_Ajax_UnitTestCase {
	use WooCommerceFixtures;

	private int $booking_id;

	/** @var string */
	protected $_last_response;

	public function setUp(): void {
		parent::setUp();
		$this->require_woocommerce();
		PaymentView::reset();
		$this->ensure_booking_product();

		$this->booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->booking_id, '_mhmrentiva_payment_status', 'paid' );
		update_post_meta( $this->booking_id, '_mhmrentiva_payment_type', 'deposit' );
		update_post_meta( $this->booking_id, '_mhmrentiva_total_price', 1000 );
		update_post_meta( $this->booking_id, '_mhmrentiva_deposit_amount', 200 );
		update_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', 800 );

		WooCommerceBridge::register();
		WC()->cart->empty_cart();
		$this->assertTrue( WooCommerceBridge::add_booking_to_cart( $this->booking_id, 200 ) );
	}

	public function tearDown(): void {
		WC()->cart->empty_cart();
		PaymentView::reset();
		$_POST = array();
		parent::tearDown();
	}

	private function dispatch( string $payment_type ): void {
		$_POST['action']       = 'mhmrentiva_update_booking_payment_type';
		$_POST['payment_type'] = $payment_type;
		$_POST['nonce']        = wp_create_nonce( 'mhmrentiva_booking_payment_type' );
		try {
			$this->_handleAjax( 'mhmrentiva_update_booking_payment_type' );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		$this->_last_response = '';
	}

	/** @return list<array{0: string, 1: string}> */
	private function rows(): array {
		$history = get_post_meta( $this->booking_id, '_mhmrentiva_booking_history', true );
		$out     = array();
		foreach ( is_array( $history ) ? $history : array() as $e ) {
			if ( 'payment_update' === ( $e['type'] ?? '' ) ) {
				$out[] = array( (string) $e['payment_from'], (string) $e['payment_to'] );
			}
		}
		return $out;
	}

	/**
	 * PR-2 A4 changed this case. The fixture's booking already holds money
	 * (payment status paid), and the checkout's type switch no longer rewrites
	 * such a booking: it used to set its remaining amount from total - deposit,
	 * which reopened a settled balance. Until PR-2 this asserted the one
	 * partially_paid -> paid row that rewrite produced. Nothing is written now.
	 * (The branch is legacy: add_booking_to_cart() has no production caller,
	 * BookingQueryHelper.php:335.)
	 */
	public function test_switching_a_booking_that_holds_money_writes_nothing(): void {
		$this->dispatch( 'full' );
		$this->assertSame( array(), $this->rows() );
		$this->assertSame( 'deposit', (string) get_post_meta( $this->booking_id, '_mhmrentiva_payment_type', true ) );
		$this->assertEquals( 800, (float) get_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', true ) );
	}

	public function test_switching_a_booking_still_waiting_for_payment_rewrites_it(): void {
		update_post_meta( $this->booking_id, '_mhmrentiva_payment_status', 'pending' );

		$this->dispatch( 'full' );

		$this->assertSame( 'full', (string) get_post_meta( $this->booking_id, '_mhmrentiva_payment_type', true ) );
		$this->assertEquals( 0, (float) get_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', true ) );
		$this->assertLessThanOrEqual( 1, count( $this->rows() ), 'still one payment-history scope' );
	}

	public function test_the_same_choice_again_writes_nothing(): void {
		$this->dispatch( 'deposit' );
		$this->assertSame( array(), $this->rows() );
	}
}
