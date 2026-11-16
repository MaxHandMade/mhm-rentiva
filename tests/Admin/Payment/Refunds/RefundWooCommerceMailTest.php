<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayDouble;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayRegistration;
use WP_UnitTestCase;

/**
 * #12 (administrator walkthrough, 11-11) -- a refund paid back by hand must
 * not reach the customer as "your order has been refunded".
 *
 * A booking paid by bank transfer was cancelled; the refund could not go
 * through a gateway, so the money still had to be transferred by hand. The
 * customer got WooCommerce's "your order has been refunded" twice and
 * Rentiva's "the refund will be transferred to you manually" -- two mails
 * that contradict each other. On a rental order WooCommerce's mail is kept
 * for a refund whose money really went back through the gateway, and turned
 * off when it did not: Rentiva's own refund mail tells that customer the
 * truth. A shop that turned Rentiva's refund mail off keeps WooCommerce's,
 * so the customer still hears something.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::refunded_mail_only_when_money_moved
 */
final class RefundWooCommerceMailTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceRefundGatewayRegistration;

	private const WC_REFUNDED_MAILS = array( 'customer_refunded_order', 'customer_partially_refunded_order' );

	private int $booking_id;
	private int $admin_id;

	/** @var array<int, string> WooCommerce e-mail ids that went out. */
	private array $wc_sent = array();

	/** @var array<int, string> Rentiva template keys that went out. */
	private array $rentiva_sent = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		$this->admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
		update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');

		$this->register_refund_gateway_double();

		// The plugin registers this at boot; the test suite's own bootstrap
		// may have run before WooCommerce was loaded, so name it here too.
		add_filter('woocommerce_email_enabled_customer_refunded_order', array( WooCommerceBridge::class, 'refunded_mail_only_when_money_moved' ), 10, 3);
		add_filter('woocommerce_email_enabled_customer_partially_refunded_order', array( WooCommerceBridge::class, 'refunded_mail_only_when_money_moved' ), 10, 3);

		add_action('woocommerce_email_sent', array( $this, 'record_wc_mail' ), 10, 2);
		add_action('mhmrentiva_email_sent', array( $this, 'record_rentiva_mail' ), 10, 1);

		// WooCommerce builds its e-mail classes, and hooks their triggers,
		// once, when the mailer is first used. The suite puts the hooks back
		// as they were after every test, so a mailer built in an earlier test
		// has lost its triggers: build it again.
		$instance = new \ReflectionProperty(\WC_Emails::class, 'instance');
		$instance->setAccessible(true);
		$instance->setValue(null, null);
		WC()->mailer();
	}

	public function tearDown(): void
	{
		remove_action('woocommerce_email_sent', array( $this, 'record_wc_mail' ), 10);
		remove_action('mhmrentiva_email_sent', array( $this, 'record_rentiva_mail' ), 10);
		$this->unregister_refund_gateway_double();
		delete_option('mhmrentiva_refund_customer_enabled');

		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

		parent::tearDown();
	}

	/** @param mixed $sent */
	public function record_wc_mail($sent, string $id = ''): void
	{
		if ($sent) {
			$this->wc_sent[] = $id;
		}
	}

	public function record_rentiva_mail(string $key): void
	{
		$this->rentiva_sent[] = $key;
	}

	private function order(string $total, string $payment_method = ''): \WC_Order
	{
		$order = $this->create_paid_order_for_booking($this->booking_id, $total);
		$order->set_billing_email('customer@example.test');
		if ('' !== $payment_method) {
			$order->set_payment_method($payment_method);
		}
		$order->save();

		return $order;
	}

	/** @return array<int, string> */
	private function wc_refunded_mails(): array
	{
		return array_values(array_intersect($this->wc_sent, self::WC_REFUNDED_MAILS));
	}

	public function test_a_refund_paid_back_by_hand_reaches_the_customer_once_and_truthfully(): void
	{
		$deposit   = $this->order('30');
		$remaining = $this->order('70');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());
		unset($deposit);

		$result = Service::processFullRefund($this->booking_id, 'cancelled', $this->admin_id);
		$this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);

		$this->assertSame(array(), $this->wc_refunded_mails(), 'No "your order has been refunded" for money still to be transferred by hand.');
		$this->assertContains('refund_customer', $this->rentiva_sent, 'Rentiva tells the customer the transfer is coming.');
	}

	public function test_a_refund_the_gateway_paid_back_keeps_the_woocommerce_mail(): void
	{
		$this->order('100', WooCommerceRefundGatewayDouble::ID);

		$result = Service::processFullRefund($this->booking_id, 'cancelled', $this->admin_id);
		$this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);

		$this->assertSame(array( 'customer_refunded_order' ), $this->wc_refunded_mails(), 'The money did go back: WooCommerce says so.');
	}

	public function test_a_hand_refund_from_the_order_screen_is_also_kept_quiet(): void
	{
		$order = $this->order('100');

		$refund = wc_create_refund(array(
			'order_id'       => $order->get_id(),
			'amount'         => '40',
			'refund_payment' => false,
		));
		$this->assertNotWPError($refund);

		$this->assertSame(array(), $this->wc_refunded_mails());
		$this->assertContains('refund_customer', $this->rentiva_sent);
	}

	public function test_a_shop_that_turned_the_rentiva_refund_mail_off_keeps_the_woocommerce_one(): void
	{
		update_option('mhmrentiva_refund_customer_enabled', '0');
		$this->order('100');

		$result = Service::processFullRefund($this->booking_id, 'cancelled', $this->admin_id);
		$this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);

		$this->assertSame(array( 'customer_refunded_order' ), $this->wc_refunded_mails(), 'The customer must hear about the refund from someone.');
	}

	public function test_a_booking_without_an_address_keeps_the_woocommerce_mail(): void
	{
		// Rentiva finds the customer on the booking, then on its main order's
		// billing address; here neither has one. Only the remaining order
		// carries an address, and WooCommerce writes to that one.
		delete_post_meta($this->booking_id, '_mhmrentiva_contact_email');
		$deposit = $this->order('30');
		$deposit->set_billing_email('');
		$deposit->save();
		$remaining = $this->order('70');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());

		$result = Service::processFullRefund($this->booking_id, 'cancelled', $this->admin_id);
		$this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);

		$this->assertNotContains('refund_customer', $this->rentiva_sent, 'Rentiva has nobody to write to.');
		$this->assertCount(1, $this->wc_refunded_mails(), 'WooCommerce writes to the remaining order\'s billing address.');
	}

	public function test_an_order_without_a_rental_follows_woocommerce(): void
	{
		$order = wc_create_order(array( 'status' => 'processing' ));
		$order->set_billing_email('shopper@example.test');
		$item = new \WC_Order_Item_Fee();
		$item->set_name('Item');
		$item->set_total('20');
		$order->add_item($item);
		$order->calculate_totals();
		$order->save();

		$refund = wc_create_refund(array(
			'order_id'       => $order->get_id(),
			'amount'         => '20',
			'refund_payment' => false,
		));
		$this->assertNotWPError($refund);

		$this->assertSame(array( 'customer_refunded_order' ), $this->wc_refunded_mails());
	}
}
