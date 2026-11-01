<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Account\BookingTimeline;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * PR D, Task 6: the payment history tells the truth -- status follows the money,
 * the title is the car, the number the one every screen shows, the bank receipt
 * control follows the single rule (R13, y10).
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer
 */
final class PaymentHistoryTruthTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	private int $user = 0;

	public function setUp(): void
	{
		parent::setUp();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->user = ShortcodeFixtures::customer();
		wp_set_current_user($this->user);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->restore_sandboxed_options();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function payments(): array
	{
		RequestSimulator::new_request();
		$data = AccountRenderer::get_payment_history_data(array( 'limit' => '50' ));
		return $data['data']['payments'];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function row(int $booking_id): array
	{
		foreach ($this->payments() as $payment) {
			if ($payment['booking_id'] === $booking_id) {
				return $payment;
			}
		}
		$this->fail("No payment row for booking $booking_id.");
	}

	private function html(): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_payment_history]');
	}

	/**
	 * Browser round B6: the date is when money arrived, else when the booking was
	 * made -- never the pickup date.
	 */
	public function test_date_is_payment_date_else_created_date(): void
	{
		$this->require_woocommerce();

		$created = '2026-03-04 09:30:00';
		$unpaid  = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'pending', '100', 40);
		$paid    = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '200', 50);
		foreach (array( $unpaid, $paid ) as $id) {
			wp_update_post(
				array(
					'ID'            => $id,
					'post_date'     => $created,
					'post_date_gmt' => $created,
				)
			);
		}
		update_post_meta($unpaid, '_mhmrentiva_payment_status', 'pending');
		delete_post_meta($unpaid, '_mhmrentiva_paid_amount');

		$order_id = $this->create_paid_order_for_booking($paid, '200');
		$order    = wc_get_order($order_id);
		$paid_at  = strtotime('2026-05-06 12:00:00 UTC');
		$order->set_date_paid($paid_at);
		$order->save();

		$this->assertSame(BookingTimeline::moment_text( (int) strtotime($created . ' UTC')), $this->row($unpaid)['date']);
		$this->assertSame(BookingTimeline::moment_text($paid_at), $this->row($paid)['date']);
		$this->assertSame($paid_at, $this->row($paid)['sort_ts'], 'The list sorts by the date it shows.');
	}

	/**
	 * Browser round B6: unpaid rows count too, so the line says records.
	 */
	public function test_count_line_says_records(): void
	{
		ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'pending', '100');
		ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'pending', '100');

		$html = $this->html();
		$this->assertStringContainsString('2 records', $html);
		$this->assertStringNotContainsString('payments total', $html);
	}

	public function test_completed_booking_paid_order_is_not_pending(): void
	{
		$this->require_woocommerce();

		$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '2915');
		update_post_meta($id, '_mhmrentiva_payment_status', 'pending');
		update_post_meta($id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($id, '_mhmrentiva_deposit_amount', '292');
		update_post_meta($id, '_mhmrentiva_remaining_amount', '2623');
		$this->create_paid_order_for_booking($id, '292');

		$row = $this->row($id);
		$this->assertSame('completed', $row['status'], 'Money received is completed, whatever the stored status says.');
		$this->assertSame('deposit', $row['type']);
		$this->assertStringContainsString('292', wp_strip_all_tags($row['amount_html']), 'The amount that moved, as recorded.');

		$html = $this->html();
		$this->assertStringContainsString('rvs-status--done', $html);
		$this->assertStringNotContainsString('rvs-status--payment', $html);
	}

	public function test_unpaid_pending_row_is_pending_and_shows_the_expected_amount(): void
	{
		$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'pending_payment', '500');
		update_post_meta($id, '_mhmrentiva_payment_status', 'pending');
		update_post_meta($id, '_mhmrentiva_payment_type', 'full');

		$row = $this->row($id);
		$this->assertSame('pending', $row['status']);
		$this->assertStringContainsString('500', wp_strip_all_tags($row['amount_html']));
		$this->assertStringContainsString('rvs-status--payment', $this->html());
	}

	public function test_cancelled_booking_nobody_paid_is_cancelled(): void
	{
		$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'cancelled', '500');
		update_post_meta($id, '_mhmrentiva_payment_status', 'pending');

		$this->assertSame('cancelled', $this->row($id)['status']);
	}

	public function test_title_is_vehicle_name_not_post_title(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$id      = ShortcodeFixtures::booking($this->user, $vehicle, 'confirmed', '300');

		$row = $this->row($id);
		$this->assertSame(get_the_title($vehicle), $row['booking_title']);
		$this->assertNotSame('Fixture Booking', $row['booking_title']);
		$this->assertSame((string) mhmrentiva_get_display_id($id), $row['number']);

		$html = $this->html();
		$this->assertStringContainsString('#' . mhmrentiva_get_display_id($id), $html);
		$this->assertStringNotContainsString('Fixture Booking', $html);
	}

	public function test_upload_only_when_unpaid_and_pending(): void
	{
		$this->require_woocommerce();
		$vehicle = ShortcodeFixtures::vehicle();

		$pending = ShortcodeFixtures::booking($this->user, $vehicle, 'pending_payment', '300');
		update_post_meta($pending, '_mhmrentiva_payment_status', 'pending');
		$paid    = ShortcodeFixtures::booking($this->user, $vehicle, 'pending_payment', '300', 9);
		$closed  = ShortcodeFixtures::booking($this->user, $vehicle, 'confirmed', '300', 11);
		$this->create_paid_order_for_booking($paid, '300');

		$this->assertTrue($this->row($pending)['can_upload_receipt']);
		$this->assertFalse($this->row($paid)['can_upload_receipt'], 'Money arrived: no slip.');
		$this->assertFalse($this->row($closed)['can_upload_receipt'], 'Not waiting any more: no slip.');
		$this->assertSame(
			AccountController::receipt_upload_allowed($pending),
			$this->row($pending)['can_upload_receipt'],
			'One rule.'
		);

		$html = $this->html();
		$this->assertSame(1, substr_count($html, 'mhm-upload-receipt'), 'Exactly one upload control.');
		$this->assertStringContainsString('data-booking-id="' . $pending . '"', $html);
		$this->assertStringContainsString('Upload bank receipt', $html);
		$this->assertStringNotContainsString('Upload Receipt', $html);
	}

	public function test_uploaded_receipt_status_always_visible(): void
	{
		$this->require_woocommerce();
		$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'pending_payment', '300');
		$this->create_paid_order_for_booking($id, '300');

		$attachment = self::factory()->attachment->create_object(
			array(
				'file'           => 'receipt.pdf',
				'post_parent'    => $id,
				'post_mime_type' => 'application/pdf',
			)
		);
		update_post_meta($id, '_mhmrentiva_receipt_attachment_id', $attachment);
		update_post_meta($id, '_mhmrentiva_receipt_status', 'submitted');

		$this->assertFalse(AccountController::receipt_upload_allowed($id));

		$html = $this->html();
		$this->assertStringContainsString('Bank receipt: Submitted', $html, 'The status shows although money arrived.');
		$this->assertStringContainsString(AccountController::get_receipt_url($id), html_entity_decode($html), 'The view link too.');
		$this->assertStringNotContainsString('remove-receipt-btn', $html, 'No removal once the slip can no longer be replaced.');
		$this->assertStringNotContainsString('mhm-upload-receipt', $html);
	}

	public function test_show_payment_method_0_hides_column(): void
	{
		ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '300');
		RequestSimulator::new_request();

		$on  = do_shortcode('[rentiva_payment_history]');
		$off = do_shortcode('[rentiva_payment_history show_payment_method="0"]');

		$this->assertStringContainsString('rvs-account-payments__method', $on);
		$this->assertStringContainsString('>Payment Method<', $on);
		$this->assertStringNotContainsString('rvs-account-payments__method', $off);
		$this->assertStringNotContainsString('>Payment Method<', $off);
	}

	public function test_table_is_accessible_markup(): void
	{
		ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '300');
		$html = $this->html();

		$this->assertStringContainsString('class="rvs-table', $html);
		$this->assertStringContainsString('<th scope="col" role="columnheader">', $html);
		$this->assertStringContainsString('role="cell"', $html);
		$this->assertMatchesRegularExpression('#<td[^>]*data-label="[^"]+"#', $html);
		$this->assertStringContainsString('aria-hidden="true"', $html, 'The status icon is decoration.');
		$this->assertStringNotContainsString('style="', $html, 'No inline styles.');
	}

	public function test_partial_refund_on_a_live_rental_stays_completed(): void
	{
		$this->require_woocommerce();
		$id    = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '120');
		$order = $this->create_paid_order_for_booking($id, '120');
		wc_create_refund(array( 'order_id' => $order->get_id(), 'amount' => '20' ));
		update_post_meta($id, '_mhmrentiva_payment_status', 'partially_refunded');

		$this->assertSame('completed', $this->row($id)['status'], 'Money is still held: not Cancelled.');
		$this->assertStringContainsString('rvs-status--done', $this->html());
	}

	public function test_full_refund_is_cancelled(): void
	{
		$this->require_woocommerce();
		$id    = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '120');
		$order = $this->create_paid_order_for_booking($id, '120');
		wc_create_refund(array( 'order_id' => $order->get_id(), 'amount' => '120' ));

		$this->assertSame('cancelled', $this->row($id)['status']);
	}

	public function test_stored_terminal_status_beats_money_received(): void
	{
		$this->require_woocommerce();
		$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'cancelled', '120');
		$this->create_paid_order_for_booking($id, '120');
		update_post_meta($id, '_mhmrentiva_payment_status', 'cancelled');

		$this->assertSame('cancelled', $this->row($id)['status']);
	}

	public function test_mixed_currency_shows_each_order_as_recorded(): void
	{
		$this->require_woocommerce();
		$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '300');
		update_post_meta($id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($id, '_mhmrentiva_remaining_amount', '0');
		$deposit   = $this->create_paid_order_for_booking($id, '100');
		$remaining = $this->create_paid_order_for_booking($id, '200');
		update_post_meta($id, '_mhmrentiva_remaining_order_id', $remaining->get_id());
		$deposit->set_currency('TRY');
		$deposit->save();
		$remaining->set_currency('EUR');
		$remaining->save();

		$row  = $this->row($id);
		$text = wp_strip_all_tags($row['amount_html']);
		$this->assertSame('completed', $row['status']);
		$this->assertStringContainsString('100', $text);
		$this->assertStringContainsString('200', $text);
		$this->assertMatchesRegularExpression("/(TRY|\x{20BA}).*\+.*(EUR|\x{20AC})/su", html_entity_decode($text), "One recorded figure per order, in its own currency.");
	}

	public function test_phone_line_has_no_dot_separators_to_dangle(): void
	{
		$css = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/css/frontend/rvs-account.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringNotContainsString('rvs-account-payments__type::before', $css);
		$this->assertStringNotContainsString('rvs-account-payments__type::after', $css);
		$this->assertStringNotContainsString('rvs-account-bookings__dates::before', $css);
	}

	public function test_full_refund_row_reads_refunded_with_the_cancelled_colour(): void
	{
		$this->require_woocommerce();
		$id    = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '120');
		$order = $this->create_paid_order_for_booking($id, '120');
		wc_create_refund(array( 'order_id' => $order->get_id(), 'amount' => '120' ));

		$row = $this->row($id);
		$this->assertSame('cancelled', $row['status']);
		$this->assertSame(\MHMRentiva\Admin\Booking\Core\Status::get_label('refunded'), $row['status_label']);
		$html = $this->html();
		$this->assertStringContainsString('rvs-status--cancelled', $html);
		$this->assertStringContainsString(esc_html(\MHMRentiva\Admin\Booking\Core\Status::get_label('refunded')), $html);
	}

	public function test_cancelled_row_without_refund_still_reads_cancelled(): void
	{
		$this->require_woocommerce();
		$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'cancelled', '120');
		$this->create_paid_order_for_booking($id, '120');
		update_post_meta($id, '_mhmrentiva_payment_status', 'cancelled');

		$this->assertSame('Cancelled', $this->row($id)['status_label']);
	}

	/**
	 * Amount sort follows the displayed amount: a 200 booking fully paid with a
	 * 20 deposit meta sorts as 200, above a 100 full payment.
	 *
	 * @return array{0:int,1:int}
	 */
	private function paid_deposit_and_full_booking(): array
	{
		$deposit_paid = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '200');
		update_post_meta($deposit_paid, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($deposit_paid, '_mhmrentiva_deposit_amount', '20');
		$this->create_paid_order_for_booking($deposit_paid, '200');

		$full = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '100');
		update_post_meta($full, '_mhmrentiva_payment_type', 'full');
		$this->create_paid_order_for_booking($full, '100');

		return array( $deposit_paid, $full );
	}

	/**
	 * @return list<int>
	 */
	private function sorted_ids(string $order): array
	{
		RequestSimulator::new_request();
		$data = AccountRenderer::get_payment_history_data(array( 'limit' => '50', 'orderby' => 'amount', 'order' => $order ));
		return array_map('intval', array_column($data['data']['payments'], 'booking_id'));
	}

	public function test_amount_sort_uses_the_displayed_paid_total(): void
	{
		$this->require_woocommerce();
		list( $deposit_paid, $full ) = $this->paid_deposit_and_full_booking();

		$this->assertSame(200.0, (float) $this->row($deposit_paid)['amount'], 'The sort figure is the paid total, not the 20 deposit meta.');
		$this->assertSame(array( $full, $deposit_paid ), $this->sorted_ids('asc'));
		$this->assertSame(array( $deposit_paid, $full ), $this->sorted_ids('desc'));
	}

	public function test_mixed_currency_row_sorts_last_in_both_orders(): void
	{
		$this->require_woocommerce();
		list( $deposit_paid, $full ) = $this->paid_deposit_and_full_booking();

		$mixed     = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '300');
		$first     = $this->create_paid_order_for_booking($mixed, '100');
		$remaining = $this->create_paid_order_for_booking($mixed, '200');
		update_post_meta($mixed, '_mhmrentiva_remaining_order_id', $remaining->get_id());
		$first->set_currency('TRY');
		$first->save();
		$remaining->set_currency('EUR');
		$remaining->save();

		$row = $this->row($mixed);
		$this->assertFalse($row['has_amount'], 'No single comparable amount exists.');
		$this->assertStringContainsString(' + ', $row['amount_html']);
		$this->assertSame(array( $full, $deposit_paid, $mixed ), $this->sorted_ids('asc'));
		$this->assertSame(array( $deposit_paid, $full, $mixed ), $this->sorted_ids('desc'));
	}

	/**
	 * Prices entered ex tax, cart shows incl. tax (20%): an unpaid 3900 row
	 * displays 4680, so it sorts as 4680 -- above a paid row recorded at 4560 (3800 + 20%), not below it.
	 */
	public function test_amount_sort_uses_the_tax_inclusive_expected_amount(): void
	{
		$this->require_woocommerce();
		$this->sandbox_option('woocommerce_calc_taxes', 'yes');
		$this->sandbox_option('woocommerce_prices_include_tax', 'no');
		$this->sandbox_option('woocommerce_tax_based_on', 'base');
		$this->sandbox_option('woocommerce_default_customer_address', 'base');
		$this->sandbox_option('woocommerce_tax_display_cart', 'incl');
		$rate_id = (int) \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '20.0000',
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 1,
				'tax_rate_class'    => '',
			)
		);

		try {
			$unpaid = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'pending_payment', '3900');
			update_post_meta($unpaid, '_mhmrentiva_payment_status', 'pending');
			update_post_meta($unpaid, '_mhmrentiva_payment_type', 'full');

			$paid = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '3800');
			update_post_meta($paid, '_mhmrentiva_payment_type', 'full');
			$this->create_paid_order_for_booking($paid, '3800');

			$row = $this->row($unpaid);
			$this->assertEqualsWithDelta(4680.0, (float) $row['amount'], 0.01, 'The sort figure is the one the row shows.');
			$this->assertStringContainsString('4,680', wp_strip_all_tags($row['amount_html']));
			$this->assertEqualsWithDelta(4560.0, (float) $this->row($paid)['amount'], 0.01);
			$this->assertSame(array( $paid, $unpaid ), $this->sorted_ids('asc'));
			$this->assertSame(array( $unpaid, $paid ), $this->sorted_ids('desc'));
		} finally {
			\WC_Tax::_delete_tax_rate($rate_id);
		}
	}
}
