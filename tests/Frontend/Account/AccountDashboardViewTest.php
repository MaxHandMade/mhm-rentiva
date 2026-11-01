<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountBookingsQuery;
use MHMRentiva\Admin\Frontend\Account\AccountDashboardView;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR D, Task 3: the dashboard's next rental, the line under the greeting and
 * the card's payment cell.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountDashboardView
 */
final class AccountDashboardViewTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();
		update_option('timezone_string', 'Europe/Istanbul');
		update_option('date_format', 'j F Y');
		update_option('time_format', 'H:i');
		AccountBookingsQuery::reset();
		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($this->customer_id);
	}

	public function tearDown(): void
	{
		AccountBookingsQuery::reset();
		update_option('timezone_string', '');
		update_option('date_format', 'F j, Y');
		parent::tearDown();
	}

	private function booking(string $status, int $pickup_in_seconds, string $payment_type = 'deposit', string $remaining = '2623.00'): int
	{
		$id     = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$pickup = ( new \DateTimeImmutable('@' . ( time() + $pickup_in_seconds )) )->setTimezone(wp_timezone());
		$return = $pickup->modify('+3 days');

		update_post_meta($id, '_mhmrentiva_customer_user_id', $this->customer_id);
		update_post_meta($id, '_mhmrentiva_status', $status);
		update_post_meta($id, '_mhmrentiva_payment_type', $payment_type);
		update_post_meta($id, '_mhmrentiva_total_price', '2915.00');
		update_post_meta($id, '_mhmrentiva_deposit_amount', '292.00');
		update_post_meta($id, '_mhmrentiva_remaining_amount', $remaining);
		update_post_meta($id, '_mhmrentiva_pickup_date', $pickup->format('Y-m-d'));
		update_post_meta($id, '_mhmrentiva_start_time', $pickup->format('H:i'));
		update_post_meta($id, '_mhmrentiva_start_ts', (string) $pickup->getTimestamp());
		update_post_meta($id, '_mhmrentiva_dropoff_date', $return->format('Y-m-d'));
		update_post_meta($id, '_mhmrentiva_end_time', $return->format('H:i'));

		return $id;
	}

	/** @param array<string, mixed> $c */
	private static function cand(int $id, string $status, int $ts): array
	{
		return array( 'id' => $id, 'status' => $status, 'pickup_ts' => $ts );
	}

	public function test_in_progress_wins(): void
	{
		$now = 1_800_000_000;
		$pick = AccountDashboardView::pick_next(
			array(
				self::cand(1, 'confirmed', $now + 3600),
				self::cand(2, 'in_progress', $now - 86400),
			),
			$now
		);

		$this->assertSame(2, $pick);
	}

	public function test_earliest_upcoming_picked(): void
	{
		$now = 1_800_000_000;
		$pick = AccountDashboardView::pick_next(
			array(
				self::cand(1, 'confirmed', $now + 5 * DAY_IN_SECONDS),
				self::cand(2, 'pending', $now + 2 * DAY_IN_SECONDS),
				self::cand(3, 'pending_payment', $now + 9 * DAY_IN_SECONDS),
			),
			$now
		);

		$this->assertSame(2, $pick);
	}

	public function test_past_pickup_not_picked(): void
	{
		$now = 1_800_000_000;

		$this->assertNull(AccountDashboardView::pick_next(array( self::cand(1, 'confirmed', $now - 60) ), $now));
		$this->assertNull(AccountDashboardView::pick_next(array(), $now));
		$this->assertSame(
			2,
			AccountDashboardView::pick_next(array( self::cand(1, 'confirmed', $now - 60), self::cand(2, 'confirmed', $now + 60) ), $now)
		);
	}

	public function test_next_rental_found_beyond_first_ten(): void
	{
		$ids = array();
		for ($i = 12; $i >= 1; $i--) {
			// Created in descending order: the earliest pickup is the 12th post.
			$ids[ $i ] = $this->booking('confirmed', $i * DAY_IN_SECONDS);
		}
		AccountBookingsQuery::reset();

		$this->assertSame($ids[1], AccountDashboardView::next_booking_id($this->customer_id));
	}

	public function test_subline_days_today_in_progress_none(): void
	{
		$now = ( new \DateTimeImmutable('2026-10-20 10:00', wp_timezone()) )->getTimestamp();
		$at  = static fn(string $when): int => ( new \DateTimeImmutable($when, wp_timezone()) )->getTimestamp();

		$this->assertSame('You have no upcoming rentals.', AccountDashboardView::subline(null, $now));
		$this->assertSame(
			'Your next rental starts today.',
			AccountDashboardView::subline(array( 'status' => 'confirmed', 'pickup_ts' => $at('2026-10-20 18:00') ), $now)
		);
		$this->assertSame(
			'Your next rental starts in 3 days.',
			AccountDashboardView::subline(array( 'status' => 'confirmed', 'pickup_ts' => $at('2026-10-23 08:00') ), $now)
		);
		$this->assertSame(
			'Your next rental starts in 1 day.',
			AccountDashboardView::subline(array( 'status' => 'pending', 'pickup_ts' => $at('2026-10-21 08:00') ), $now)
		);
		$this->assertSame(
			'Your rental is in progress. Return: 24 October 2026 · 10:00',
			AccountDashboardView::subline(array( 'status' => 'in_progress', 'pickup_ts' => $at('2026-10-19 10:00'), 'return_text' => '24 October 2026 · 10:00' ), $now)
		);

		// Midnight boundary: 23:30 -> 08:00 next day is 1 day, not today.
		$late = $at('2026-10-20 23:30');
		$this->assertSame(
			'Your next rental starts in 1 day.',
			AccountDashboardView::subline(array( 'status' => 'confirmed', 'pickup_ts' => $at('2026-10-21 08:00') ), $late)
		);
	}

	public function test_greeting_name_falls_back_to_display_name(): void
	{
		$with    = new \WP_User(self::factory()->user->create(array( 'first_name' => 'Ayse', 'display_name' => 'ayse42' )));
		$without = new \WP_User(self::factory()->user->create(array( 'display_name' => 'ayse42' )));

		$this->assertSame('Ayse', AccountDashboardView::greeting_name($with));
		$this->assertSame('ayse42', AccountDashboardView::greeting_name($without));
	}

	public function test_payment_cell_states(): void
	{
		$this->require_woocommerce();

		// Awaiting payment: nothing arrived.
		$awaiting = $this->booking('pending_payment', 3 * DAY_IN_SECONDS);
		$card     = AccountDashboardView::next_card($awaiting);
		$this->assertSame('Awaiting payment', $card['payment']['label']);
		$this->assertNull($card['payment']['amount_html']);

		// Deposit received, balance due at pickup.
		$deposit = $this->booking('confirmed', 3 * DAY_IN_SECONDS);
		update_post_meta($deposit, '_mhmrentiva_payment_status', 'paid');
		$this->create_paid_order_for_booking($deposit, '292');
		$card = AccountDashboardView::next_card($deposit);
		$this->assertSame('Due at pickup', $card['payment']['label']);
		$this->assertStringContainsString('2,623', (string) $card['payment']['amount_html']);
		$this->assertSame('Deposit paid', $card['payment']['note']);

		// Paid in full.
		$full = $this->booking('confirmed', 3 * DAY_IN_SECONDS, 'full', '0');
		update_post_meta($full, '_mhmrentiva_payment_status', 'paid');
		$this->create_paid_order_for_booking($full, '2915');
		$card = AccountDashboardView::next_card($full);
		$this->assertSame('Paid in full', $card['payment']['label']);
		$this->assertNull($card['payment']['amount_html']);
		$this->assertNull($card['payment']['note']);
	}

	public function test_pending_payment_with_paid_order_says_deposit_paid_like_the_detail(): void
	{
		$this->require_woocommerce();

		$id = $this->booking('pending_payment', 3 * DAY_IN_SECONDS);
		$this->create_paid_order_for_booking($id, '292');

		$card = AccountDashboardView::next_card($id);
		$this->assertSame('Due at pickup', $card['payment']['label']);
		$this->assertStringContainsString('2,623', (string) $card['payment']['amount_html']);
		$this->assertSame('Deposit paid', $card['payment']['note']);

		// The detail page's payment line reads the same summary.
		$this->assertSame('deposit', \MHMRentiva\Admin\Frontend\Account\AccountPaymentSummary::for_booking($id, 'pending_payment')['kind']);
		$detail = \MHMRentiva\Admin\Core\Utilities\Templates::render('account/booking-detail', \MHMRentiva\Admin\Frontend\Account\AccountRenderer::get_booking_detail_data($id), true);
		$this->assertStringContainsString('Deposit paid, ', $detail);
		$this->assertStringNotContainsString('Awaiting payment', $detail);
	}

	public function test_in_progress_without_a_return_date_omits_the_return_part(): void
	{
		$now = time();

		$this->assertSame(
			'Your rental is in progress.',
			AccountDashboardView::subline(array( 'status' => 'in_progress', 'pickup_ts' => $now - 3600, 'return_text' => '' ), $now)
		);
		$this->assertSame(
			'Your rental is in progress.',
			AccountDashboardView::subline(array( 'status' => 'in_progress', 'pickup_ts' => $now - 3600 ), $now)
		);
	}

	public function test_free_cancel_line_only_when_cancellable(): void
	{
		$open = $this->booking('confirmed', 5 * DAY_IN_SECONDS);
		$this->assertStringStartsWith('Free cancellation until ', (string) AccountDashboardView::next_card($open)['free_cancel']);

		// Under way: nothing left to cancel.
		$running = $this->booking('in_progress', -DAY_IN_SECONDS);
		$this->assertNull(AccountDashboardView::next_card($running)['free_cancel']);
	}

	public function test_card_moments_use_one_date_time_format(): void
	{
		$id   = $this->booking('confirmed', 4 * DAY_IN_SECONDS);
		$card = AccountDashboardView::next_card($id);

		$this->assertMatchesRegularExpression('/^\d{1,2} \p{L}+ \d{4} · \d{2}:\d{2}$/u', $card['pickup']);
		$this->assertMatchesRegularExpression('/^\d{1,2} \p{L}+ \d{4} · \d{2}:\d{2}$/u', $card['return']);
		$this->assertStringStartsWith('Upcoming · #', $card['kicker']);
	}

	public function test_dashboard_renders_greeting_card_and_back_compat_data(): void
	{
		$this->booking('confirmed', 4 * DAY_IN_SECONDS);

		$data = AccountRenderer::get_dashboard_data(array())['data'];
		$this->assertNotNull($data['next_rental']);
		$this->assertArrayHasKey('recent_bookings', $data);
		$this->assertNotEmpty($data['recent_bookings']);

		$html = AccountRenderer::render_dashboard(array());
		$this->assertStringContainsString('data-testid="account-next-rental"', $html);
		$this->assertStringContainsString('View all', $html);
		$this->assertStringNotContainsString('id="delete-account"', $html, 'No privacy add-on: no dead buttons.');
	}
}
