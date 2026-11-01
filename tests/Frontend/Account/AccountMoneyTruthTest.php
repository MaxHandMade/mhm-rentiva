<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Booking\Core\Hooks;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsQuery;
use MHMRentiva\Admin\Frontend\Account\AccountDashboardView;
use MHMRentiva\Admin\Frontend\Account\AccountPaymentSummary;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Account\BookingSummaryPage;
use MHMRentiva\Admin\Frontend\Account\BookingTimeline;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsBreakdown;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR D final audits: one payment truth on every account surface, and the status
 * history recorded whoever moves a booking.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountPaymentSummary
 * @covers \MHMRentiva\Admin\Frontend\Account\BookingSummaryPage::paid_rows
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsBreakdown::from_booking
 * @covers \MHMRentiva\Admin\Booking\Core\Hooks::register
 */
final class AccountMoneyTruthTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $customer_id = 0;

	public function setUp(): void
	{
		parent::setUp();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		AccountBookingsQuery::reset();
		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($this->customer_id);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		AccountBookingsQuery::reset();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	private function booking(string $status, string $remaining = '2623.00'): int
	{
		$id     = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$pickup = ( new \DateTimeImmutable('@' . ( time() + 3 * DAY_IN_SECONDS )) )->setTimezone(wp_timezone());
		$return = $pickup->modify('+3 days');

		update_post_meta($id, '_mhmrentiva_customer_user_id', $this->customer_id);
		update_post_meta($id, '_mhmrentiva_status', $status);
		update_post_meta($id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($id, '_mhmrentiva_payment_status', 'paid');
		update_post_meta($id, '_mhmrentiva_total_price', '2915.00');
		update_post_meta($id, '_mhmrentiva_deposit_amount', '292.00');
		update_post_meta($id, '_mhmrentiva_remaining_amount', $remaining);
		update_post_meta($id, '_mhmrentiva_rental_days', '3');
		update_post_meta($id, '_mhmrentiva_pickup_date', $pickup->format('Y-m-d'));
		update_post_meta($id, '_mhmrentiva_start_time', $pickup->format('H:i'));
		update_post_meta($id, '_mhmrentiva_start_ts', (string) $pickup->getTimestamp());
		update_post_meta($id, '_mhmrentiva_dropoff_date', $return->format('Y-m-d'));
		update_post_meta($id, '_mhmrentiva_end_time', $return->format('H:i'));

		return $id;
	}

	private function refund(\WC_Order $order, string $amount): void
	{
		$refund = wc_create_refund(array( 'order_id' => $order->get_id(), 'amount' => $amount ));
		$this->assertNotWPError($refund, 'Fixture guard: the refund exists.');
	}

	/**
	 * @return array<string,mixed>
	 */
	private function history_row(int $booking_id): array
	{
		RequestSimulator::new_request();
		foreach (AccountRenderer::get_payment_history_data(array( 'limit' => '50' ))['data']['payments'] as $payment) {
			if ($payment['booking_id'] === $booking_id) {
				return $payment;
			}
		}
		$this->fail('The booking is not in the payment history.');
	}

	private function detail(int $booking_id): string
	{
		return (string) AccountRenderer::render_booking_detail($booking_id, true);
	}

	public function test_a_partial_refund_on_a_live_booking_is_not_refunded_anywhere(): void
	{
		$this->require_woocommerce();

		$id    = $this->booking('confirmed');
		$order = $this->create_paid_order_for_booking($id, '292');
		$this->refund($order, '50');

		$this->assertSame(AccountPaymentSummary::DEPOSIT, AccountPaymentSummary::for_booking($id, 'confirmed')['kind']);

		$card = AccountDashboardView::next_card($id);
		$this->assertSame('Due at pickup', $card['payment']['label']);

		$detail = wp_strip_all_tags($this->detail($id));
		$this->assertStringContainsString('due at pickup', $detail);
		$this->assertStringContainsString('Refunded', $detail, 'The payment box still lists the refund.');

		$this->assertSame('completed', $this->history_row($id)['status']);
	}

	public function test_a_full_refund_is_refunded_on_every_surface(): void
	{
		$this->require_woocommerce();

		$id    = $this->booking('confirmed');
		$order = $this->create_paid_order_for_booking($id, '292');
		$this->refund($order, '292');

		$this->assertSame(AccountPaymentSummary::REFUNDED, AccountPaymentSummary::for_booking($id, 'confirmed')['kind']);
		$this->assertStringContainsString('refunded', AccountDashboardView::next_card($id)['payment']['label']);
		$this->assertStringContainsString('refunded', wp_strip_all_tags($this->detail($id)));
		$this->assertSame('cancelled', $this->history_row($id)['status']);
	}

	public function test_a_partial_refund_on_a_cancelled_booking_is_refunded_on_every_surface(): void
	{
		$this->require_woocommerce();

		$id    = $this->booking('cancelled');
		$order = $this->create_paid_order_for_booking($id, '292');
		$this->refund($order, '50');

		$this->assertSame(AccountPaymentSummary::REFUNDED, AccountPaymentSummary::for_booking($id, 'cancelled')['kind']);
		$this->assertSame('cancelled', $this->history_row($id)['status']);
	}

	public function test_price_markup_keeps_bdi_in_the_bookings_list_and_the_payment_history(): void
	{
		$this->require_woocommerce();

		$id = $this->booking('confirmed');
		$this->create_paid_order_for_booking($id, '292');

		$this->assertStringContainsString('<bdi>', (string) do_shortcode('[rentiva_my_bookings]'));
		RequestSimulator::new_request();
		$this->assertStringContainsString('<bdi>', (string) do_shortcode('[rentiva_payment_history]'));
	}

	public function test_mixed_currency_paid_row_shows_each_order_as_recorded(): void
	{
		$this->require_woocommerce();

		$id        = $this->booking('confirmed');
		$deposit   = $this->create_paid_order_for_booking($id, '100');
		$remaining = $this->create_paid_order_for_booking($id, '200');
		update_post_meta($id, '_mhmrentiva_remaining_order_id', $remaining->get_id());
		$deposit->set_currency('TRY');
		$deposit->save();
		$remaining->set_currency('EUR');
		$remaining->save();

		$rows = BookingSummaryPage::paid_rows($id, RvsBreakdown::from_booking($id));

		$this->assertSame(array( 'paid' ), array_column($rows, 'kind'));
		$expected = BookingTimeline::recorded_orders_html(array( $deposit->get_id(), $remaining->get_id() ));
		$this->assertNotSame('', $expected);
		$this->assertSame($expected, $rows[0]['html']);
		$this->assertStringContainsString(' + ', $rows[0]['html']);
	}

	public function test_nothing_is_due_at_pickup_on_a_completed_booking(): void
	{
		$this->require_woocommerce();

		$id = $this->booking('completed');
		$this->create_paid_order_for_booking($id, '292');

		$this->assertNull(RvsBreakdown::from_booking($id)['on_pickup']);
		$this->assertStringNotContainsString('due at pickup', strtolower(wp_strip_all_tags($this->detail($id))));
	}

	public function test_a_live_booking_still_shows_due_at_pickup(): void
	{
		$id = $this->booking('confirmed');

		$this->assertNotNull(RvsBreakdown::from_booking($id)['on_pickup']);
	}

	public function test_rental_days_in_the_breakdown_follow_the_locale_number_format(): void
	{
		$id = $this->booking('confirmed');
		update_post_meta($id, '_mhmrentiva_rental_days', '1000');

		$this->assertSame('Rental, ' . number_format_i18n(1000) . ' days', RvsBreakdown::from_booking($id)['rows'][0]['label']);
	}

	public function test_a_status_change_outside_admin_records_one_dated_note(): void
	{
		$this->assertFalse(is_admin(), 'Fixture guard: a front-end context.');
		$this->assertNotFalse(has_action('mhmrentiva_booking_status_changed', array( BookingMeta::class, 'auto_add_status_change_note' )));

		// Registering again (a second boot, an admin request) must not double the note.
		Hooks::register();

		$id     = $this->booking('pending');
		$before = time();
		$this->assertTrue(Status::update_status($id, 'confirmed'));

		$notes = array_values(array_filter(BookingMeta::history_sorted($id), static fn(array $entry): bool => 'status_change' === ( $entry['type'] ?? '' )));

		$this->assertCount(1, $notes);
		$this->assertSame('confirmed', $notes[0]['status_to']);
		$this->assertGreaterThanOrEqual($before, $notes[0]['at']);
		$this->assertLessThanOrEqual(time(), $notes[0]['at']);
	}

	public function test_a_plain_note_carries_a_utc_instant_too(): void
	{
		$id     = $this->booking('pending');
		$before = time();
		BookingMeta::add_history_note($id, 'Plain', 'note');

		$history = BookingMeta::history_sorted($id);
		$this->assertGreaterThanOrEqual($before, end($history)['at']);
	}

	public function test_a_confirmation_taken_back_is_a_step_still_ahead(): void
	{
		$id = $this->booking('pending');
		BookingMeta::auto_add_status_change_note($id, 'pending', 'confirmed');
		BookingMeta::auto_add_status_change_note($id, 'confirmed', 'pending');

		$steps = array_column(BookingTimeline::for_booking($id), null, 'key');

		$this->assertFalse($steps['confirmed']['done']);
		$this->assertNull($steps['confirmed']['at']);
	}

	public function test_deactivation_clears_woocommerces_template_cache(): void
	{
		// The deactivation callback is a closure in the plugin's main file, so
		// only its source can be read here; registering and running it is WordPress's.
		$main = (string) file_get_contents(dirname(__DIR__, 3) . '/mhm-rentiva.php');

		$this->assertMatchesRegularExpression('/register_deactivation_hook\(.*?wc_clear_template_cache\(\).*?flush_rewrite_rules\(\);\s*\}\s*\);/s', $main);
	}
}
