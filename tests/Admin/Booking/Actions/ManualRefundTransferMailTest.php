<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * #12 (administrator walkthrough, 11-11) -- when the operator confirms the
 * hand transfer, the customer is told the money has been sent.
 *
 * The refund mail at cancellation says "will be transferred to you
 * manually". Confirming the transfer ("Confirm hand transfer", with a
 * transfer reference) used to send nothing, so the customer never learned
 * the money had left. The confirmation now sends the customer the refund
 * mail once more, saying the amount was transferred and naming the
 * reference. The operator, who pressed the button, gets no mail.
 *
 * @covers \MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::close_manual_refund
 * @covers \MHMRentiva\Admin\Emails\Notifications\RefundNotifications::notify_transferred
 */
final class ManualRefundTransferMailTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceFixtures;

	private int $booking_id;
	private int $admin_id;

	/** @var array<int, array{key: string, context: array<string, mixed>}> */
	private array $sent = array();

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

		DepositManagementAjax::register();
	}

	public function tearDown(): void
	{
		remove_action('mhmrentiva_email_sent', array( $this, 'record' ), 10);
		$_POST = array();

		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

		parent::tearDown();
	}

	/** @param array<string, mixed> $context */
	public function record(string $key, string $to = '', bool $ok = true, string $subject = '', array $context = array()): void
	{
		$this->sent[] = array( 'key' => $key, 'context' => $context );
	}

	/** A booking whose 100 was refunded and is waiting to be handed over. */
	private function refund_waiting_for_the_transfer(): void
	{
		$this->create_paid_order_for_booking($this->booking_id, '100');

		$result = Service::processFullRefund($this->booking_id, 'cancelled', $this->admin_id);
		$this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);
		$this->assertSame(RefundStatus::MANUAL_PENDING, RefundStatus::get($this->booking_id));

		// Only what the confirmation sends from here on.
		add_action('mhmrentiva_email_sent', array( $this, 'record' ), 10, 5);
	}

	/** @return array<string, mixed> */
	private function confirm(string $reference): array
	{
		wp_set_current_user($this->admin_id);
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
			'booking_id' => $this->booking_id,
			'reference'  => $reference,
		);
		$this->_last_response = '';

		try {
			$this->_handleAjax('mhmrentiva_close_manual_refund');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates.
		}

		$decoded = json_decode($this->_last_response, true);

		return is_array($decoded) ? $decoded : array();
	}

	/** @return array<int, string> */
	private function keys(): array
	{
		return array_column($this->sent, 'key');
	}

	public function test_confirming_the_transfer_tells_the_customer_the_money_was_sent(): void
	{
		$this->refund_waiting_for_the_transfer();

		$response = $this->confirm('EFT-4417');
		$this->assertTrue((bool) ( $response['success'] ?? false ), 'Raw: ' . $this->_last_response);

		$this->assertSame(array( 'refund_customer' ), $this->keys(), 'One mail, to the customer; the operator pressed the button.');

		$context = $this->sent[0]['context'];
		$amount  = CurrencyHelper::format_price(100.0, CurrencyHelper::get_price_decimals(), null);

		$this->assertSame('transferred', $context['mode']);
		$this->assertSame($amount, $context['amount'], 'The amount handed over.');
		$this->assertStringContainsString($amount, (string) $context['mode_text']);
		$this->assertStringContainsString('EFT-4417', (string) $context['mode_text']);
		$this->assertStringNotContainsString('will be transferred', (string) $context['mode_text'], 'It is done, not coming.');
	}

	public function test_a_confirmation_without_a_reference_still_tells_the_customer(): void
	{
		$this->refund_waiting_for_the_transfer();

		$this->confirm('');

		$this->assertSame(array( 'refund_customer' ), $this->keys());
		$this->assertStringNotContainsString('Reference', (string) $this->sent[0]['context']['mode_text']);
	}

	public function test_no_hand_refund_amount_sends_no_transfer_mail(): void
	{
		// A booking marked as waiting for a hand transfer with no refund
		// behind it: "0.00 has been transferred" would be false.
		update_post_meta($this->booking_id, RefundStatus::META_KEY, RefundStatus::MANUAL_PENDING);
		add_action('mhmrentiva_email_sent', array( $this, 'record' ), 10, 5);

		$response = $this->confirm('EFT-1');

		$this->assertTrue((bool) ( $response['success'] ?? false ), 'The transfer is still recorded. Raw: ' . $this->_last_response);
		$this->assertSame(array(), $this->keys());
	}

	public function test_a_second_click_sends_nothing(): void
	{
		$this->refund_waiting_for_the_transfer();

		$this->confirm('EFT-4417');
		$this->confirm('EFT-4417');

		$this->assertSame(array( 'refund_customer' ), $this->keys(), 'The transfer was announced once.');
	}
}
