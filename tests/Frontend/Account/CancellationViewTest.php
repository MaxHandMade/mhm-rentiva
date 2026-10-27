<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\CancellationView;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

if (class_exists('WC_Payment_Gateway') && ! class_exists(CancellationViewRefundingGateway::class)) {
	/**
	 * A gateway that refunds by itself (MODE_AUTO), for the automatic sentence.
	 */
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- test double.
	class CancellationViewRefundingGateway extends \WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id       = 'mhm_test_refunding';
			$this->enabled  = 'yes';
			$this->supports = array( 'products', 'refunds' );
		}
	}
}

/**
 * PR D Task 4: the booking detail's cancellation section says what the rule
 * does (R10, R11, M3, K-D2).
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\CancellationView
 */
final class CancellationViewTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		update_option('timezone_string', 'Europe/Istanbul');
		update_option('date_format', 'j F Y');
		update_option('time_format', 'H:i');
		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($this->customer_id);
	}

	public function tearDown(): void
	{
		remove_all_filters('woocommerce_payment_gateways');
		WC()->payment_gateways()->init();
		update_option('timezone_string', '');
		parent::tearDown();
	}

	private function booking(string $status, string $payment_status, int $pickup_in_seconds = 5 * DAY_IN_SECONDS): int
	{
		$id     = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$pickup = ( new \DateTimeImmutable('@' . ( time() + $pickup_in_seconds )) )->setTimezone(wp_timezone());

		update_post_meta($id, '_mhmrentiva_customer_user_id', $this->customer_id);
		update_post_meta($id, '_mhmrentiva_status', $status);
		update_post_meta($id, '_mhmrentiva_payment_status', $payment_status);
		update_post_meta($id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($id, '_mhmrentiva_total_price', '2915.00');
		update_post_meta($id, '_mhmrentiva_deposit_amount', '292.00');
		update_post_meta($id, '_mhmrentiva_remaining_amount', '2623.00');
		update_post_meta($id, '_mhmrentiva_pickup_date', $pickup->format('Y-m-d'));
		update_post_meta($id, '_mhmrentiva_start_time', $pickup->format('H:i'));

		return $id;
	}

	private function refunding_gateway(): void
	{
		add_filter(
			'woocommerce_payment_gateways',
			static function (array $gateways): array {
				$gateways[] = CancellationViewRefundingGateway::class;
				return $gateways;
			}
		);
		WC()->payment_gateways()->init();
	}

	public function test_open_with_refund(): void
	{
		$this->refunding_gateway();
		$id    = $this->booking('confirmed', 'paid');
		$order = $this->create_paid_order_for_booking($id, '292');
		$order->set_payment_method('mhm_test_refunding');
		$order->save();

		$view = CancellationView::for_booking($id, $this->customer_id);

		$this->assertSame('open', $view['state']);
		$this->assertTrue($view['can_cancel']);
		$this->assertNotNull($view['refund_html']);
		$this->assertStringContainsString('292', $view['refund_html']);
		$this->assertFalse($view['refund_manual'], 'The gateway refunds by itself.');
		$this->assertStringContainsString('What you paid (', $view['message_html']);
		$this->assertStringContainsString('is refunded.', $view['message_html']);
		$this->assertStringNotContainsString('contact you', $view['message_html']);
		$this->assertStringContainsString('will be refunded', (string) $view['dialog_html']);
		$this->assertSame('After this time, contact us to cancel.', $view['note']);
	}

	public function test_open_without_payment_has_no_refund_sentence(): void
	{
		$view = CancellationView::for_booking($this->booking('pending_payment', 'pending'), $this->customer_id);

		$this->assertSame('open', $view['state']);
		$this->assertTrue($view['can_cancel']);
		$this->assertNull($view['refund_html']);
		$this->assertStringStartsWith('Free cancellation until ', $view['message_html']);
		$this->assertStringNotContainsString('refund', $view['message_html']);
		$this->assertStringNotContainsString('refund', (string) $view['dialog_html']);
	}

	public function test_pending_payment_status_with_paid_order_has_no_refund_sentence(): void
	{
		// A paid order, but the booking's payment status still says pending:
		// RefundValidator refuses the refund, so none is promised.
		$id = $this->booking('pending', 'pending');
		$this->create_paid_order_for_booking($id, '292');

		$view = CancellationView::for_booking($id, $this->customer_id);

		$this->assertSame('open', $view['state']);
		$this->assertNull($view['refund_html']);
		$this->assertTrue($view['can_cancel'], 'The button stays.');
	}

	public function test_manual_refund_sentence(): void
	{
		// Paid offline (no order): total - remaining = 292 arrived by hand.
		$view = CancellationView::for_booking($this->booking('confirmed', 'paid'), $this->customer_id);

		$this->assertSame('open', $view['state']);
		$this->assertTrue($view['refund_manual']);
		$this->assertStringContainsString('292', (string) $view['refund_html']);
		$this->assertStringContainsString('is refunded; we will contact you to arrange it.', $view['message_html']);
		$this->assertStringContainsString('we will contact you to arrange it.', (string) $view['dialog_html']);
	}

	public function test_closed_after_deadline(): void
	{
		$view = CancellationView::for_booking($this->booking('confirmed', 'paid', 2 * HOUR_IN_SECONDS), $this->customer_id);

		$this->assertSame('closed', $view['state']);
		$this->assertFalse($view['can_cancel']);
		$this->assertNull($view['refund_html']);
		$this->assertSame('The free cancellation period has ended. Contact us to cancel.', $view['message_html']);
	}

	public function test_none_for_completed_and_cancelled(): void
	{
		foreach (array( 'completed', 'cancelled', 'refunded', 'in_progress', 'no_show' ) as $status) {
			$view = CancellationView::for_booking($this->booking($status, 'paid'), $this->customer_id);
			$this->assertSame('none', $view['state'], $status);
			$this->assertFalse($view['can_cancel'], $status);
		}
	}

	public function test_deadline_text_uses_site_formats(): void
	{
		$id = $this->booking('confirmed', 'pending');
		update_post_meta($id, '_mhmrentiva_pickup_date', '2031-03-10');
		update_post_meta($id, '_mhmrentiva_start_time', '14:00');

		$view = CancellationView::for_booking($id, $this->customer_id);

		$this->assertSame('9 March 2031 · 14:00', $view['deadline_text']);
		$this->assertStringContainsString('9 March 2031 · 14:00', $view['message_html']);
	}

	public function test_another_customer_gets_no_button(): void
	{
		$stranger = (int) self::factory()->user->create(array( 'role' => 'customer' ));

		$this->assertFalse(CancellationView::for_booking($this->booking('confirmed', 'pending'), $stranger)['can_cancel']);
	}
}
