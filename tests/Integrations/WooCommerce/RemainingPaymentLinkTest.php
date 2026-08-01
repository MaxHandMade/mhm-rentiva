<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use WP_Ajax_UnitTestCase;

/**
 * Covers RemainingPaymentHandler::get_or_create_remaining_order() — the
 * shared order-creation logic extracted from the customer-facing AJAX
 * handler so it can also be called from an admin-initiated flow (Task 2).
 */
final class RemainingPaymentLinkTest extends WP_Ajax_UnitTestCase {

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

		update_post_meta( $this->booking_id, '_mhmrentiva_payment_type', 'deposit' );
		update_post_meta( $this->booking_id, '_mhmrentiva_total_price', 1000 );
		update_post_meta( $this->booking_id, '_mhmrentiva_deposit_amount', 200 );
		update_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', 800 );
		update_post_meta( $this->booking_id, '_mhmrentiva_customer_user_id', 0 );

		RemainingPaymentHandler::register();
	}

	public function test_get_or_create_remaining_order_creates_order_for_remaining_amount(): void {
		$order = RemainingPaymentHandler::get_or_create_remaining_order( $this->booking_id );

		$this->assertNotInstanceOf( \WP_Error::class, $order );
		$this->assertInstanceOf( \WC_Order::class, $order );
		$this->assertSame( 800.0, (float) $order->get_total() );
		$this->assertSame( '1', $order->get_meta( '_mhmrentiva_is_remaining_payment' ) );
		$this->assertSame(
			$order->get_id(),
			(int) get_post_meta( $this->booking_id, '_mhmrentiva_remaining_order_id', true )
		);
	}

	public function test_get_or_create_remaining_order_reuses_existing_pending_order(): void {
		$first  = RemainingPaymentHandler::get_or_create_remaining_order( $this->booking_id );
		$second = RemainingPaymentHandler::get_or_create_remaining_order( $this->booking_id );

		$this->assertInstanceOf( \WC_Order::class, $first );
		$this->assertInstanceOf( \WC_Order::class, $second );
		$this->assertSame(
			$first->get_id(),
			$second->get_id(),
			'A second call must reuse the pending order, not create a duplicate.'
		);
	}

	public function test_get_or_create_remaining_order_rejects_non_deposit_booking(): void {
		update_post_meta( $this->booking_id, '_mhmrentiva_payment_type', 'full' );

		$order = RemainingPaymentHandler::get_or_create_remaining_order( $this->booking_id );

		$this->assertInstanceOf( \WP_Error::class, $order );
	}

	public function test_get_or_create_remaining_order_rejects_zero_remaining(): void {
		update_post_meta( $this->booking_id, '_mhmrentiva_remaining_amount', 0 );

		$order = RemainingPaymentHandler::get_or_create_remaining_order( $this->booking_id );

		$this->assertInstanceOf( \WP_Error::class, $order );
	}

	public function test_customer_ajax_create_remaining_order_still_works_after_refactor(): void {
		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		update_post_meta( $this->booking_id, '_mhmrentiva_customer_user_id', $customer_id );
		wp_set_current_user( $customer_id );

		$_POST['action']     = 'mhmrentiva_pay_remaining';
		$_POST['nonce']      = wp_create_nonce( 'mhmrentiva_pay_remaining_' . $this->booking_id );
		$_POST['booking_id'] = $this->booking_id;

		try {
			$this->_handleAjax( 'mhmrentiva_pay_remaining' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected — wp_send_json_*() always wp_die()s.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		$this->assertNotEmpty( $response['data']['payment_url'] );
	}

	public function test_admin_send_remaining_payment_link_returns_payment_url(): void {
		DepositManagementAjax::register();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['action']     = 'mhmrentiva_send_remaining_payment_link';
		$_POST['nonce']      = wp_create_nonce( 'mhmrentiva_deposit_management_action' );
		$_POST['booking_id'] = $this->booking_id;

		try {
			$this->_handleAjax( 'mhmrentiva_send_remaining_payment_link' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected — wp_send_json_*() always wp_die()s.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		$this->assertNotEmpty( $response['data']['payment_url'] );

		$logs = get_post_meta( $this->booking_id, '_mhmrentiva_booking_logs', true );
		$this->assertIsArray( $logs );
		$this->assertSame( 'remaining_payment_link_sent', end( $logs )['action'] );
	}

	public function test_admin_send_remaining_payment_link_rejects_non_deposit_booking(): void {
		DepositManagementAjax::register();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_post_meta( $this->booking_id, '_mhmrentiva_payment_type', 'full' );

		$_POST['action']     = 'mhmrentiva_send_remaining_payment_link';
		$_POST['nonce']      = wp_create_nonce( 'mhmrentiva_deposit_management_action' );
		$_POST['booking_id'] = $this->booking_id;

		try {
			$this->_handleAjax( 'mhmrentiva_send_remaining_payment_link' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'] );
	}
}
