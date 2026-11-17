<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

/**
 * Manager-lens findings #2 and #3 (2026-11-11), product decisions 2026-11-17.
 *
 * - #2 "Pending payment" read only bookings whose WooCommerce order was still
 *   pending, so a balance left after a deposit, or an offline booking nobody
 *   had paid, showed as 0. It now sums what each live booking still owes, read
 *   the way the booking list's Payment column reads it (PaymentView).
 * - #3 The bookings count and "this month" counted cancelled bookings; they
 *   count bookings that are not cancelled or refunded. The money card is the
 *   value of booked rentals (confirmed, in progress, completed) -- an active
 *   rental used to drop out of it while on the road.
 * - #3 "Recent bookings" is the newest bookings first, not the latest pickups.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardService
 */
final class DashboardFiguresTest extends WP_UnitTestCase {

	/**
	 * @param array<string,string> $meta
	 */
	private function booking( string $status, float $total, array $meta = array(), ?string $date = null ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			// Never ahead of the real clock: wp_insert_post() turns a future
			// post_date into post_status 'future' (see DashboardPaymentsSummaryTest).
			'post_date'   => $date ?? gmdate( 'Y-m-d H:i:s' ),
		) );
		update_post_meta( $id, '_mhmrentiva_status', $status );
		update_post_meta( $id, '_mhmrentiva_total_price', (string) $total );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	private function deposit_paid( string $status, float $total, float $remaining ): int {
		return $this->booking( $status, $total, array(
			'_mhmrentiva_payment_type'     => 'deposit',
			'_mhmrentiva_payment_status'   => 'paid',
			'_mhmrentiva_deposit_amount'   => (string) ( $total - $remaining ),
			'_mhmrentiva_remaining_amount' => (string) $remaining,
		) );
	}

	private function fully_paid( string $status, float $total ): int {
		return $this->booking( $status, $total, array(
			'_mhmrentiva_payment_type'     => 'full',
			'_mhmrentiva_payment_status'   => 'paid',
			'_mhmrentiva_deposit_amount'   => (string) $total,
			'_mhmrentiva_remaining_amount' => '0',
		) );
	}

	public function test_pending_payment_is_what_live_bookings_still_owe(): void {
		$this->deposit_paid( 'confirmed', 4000.0, 3600.0 );   // balance after a deposit: 3600
		$this->deposit_paid( 'completed', 1000.0, 250.0 );    // returned, balance still open: 250
		$this->booking( 'pending', 5950.0, array(             // offline, nothing paid yet: 5950
			'_mhmrentiva_payment_type'   => 'deposit',
			'_mhmrentiva_payment_method' => 'offline',
			'_mhmrentiva_payment_status' => 'pending',
			'_mhmrentiva_remaining_amount' => '5355',
		) );
		$this->booking( 'confirmed', 700.0 );                 // no payment status at all: unpaid, 700

		// Owes nothing: paid in full, refunded, or the booking is off.
		$this->fully_paid( 'confirmed', 8580.0 );
		$this->booking( 'confirmed', 3325.0, array( '_mhmrentiva_payment_status' => 'partially_refunded', '_mhmrentiva_remaining_amount' => '0' ) );
		$this->deposit_paid( 'cancelled', 3125.0, 2812.5 );
		$this->deposit_paid( 'refunded', 900.0, 800.0 );

		$s = DashboardService::get_payments_summary();

		$this->assertEqualsWithDelta( 3600.0 + 250.0 + 5950.0 + 700.0, $s['pending_total'], 0.01 );
	}

	public function test_booking_value_counts_confirmed_in_progress_and_completed(): void {
		$this->fully_paid( 'confirmed', 100.0 );
		$this->fully_paid( 'in_progress', 20.0 );
		$this->fully_paid( 'completed', 3.0 );
		$this->booking( 'pending', 5000.0 );
		$this->booking( 'cancelled', 7000.0 );

		$m = DashboardService::get_dashboard_metrics();

		$this->assertEqualsWithDelta( 123.0, $m['total_revenue'], 0.01 );
		$this->assertEqualsWithDelta( 123.0, $m['monthly_revenue'], 0.01 );
		$this->assertEqualsWithDelta( 123.0, DashboardService::booking_value_between( null, null ), 0.01 );
	}

	public function test_booking_value_between_respects_the_creation_window(): void {
		$this->fully_paid( 'completed', 50.0 );
		$this->booking( 'completed', 900.0, array(), gmdate( 'Y-m-d H:i:s', strtotime( '-400 days' ) ) );

		$from = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
		$to   = gmdate( 'Y-m-d 23:59:59' );

		$this->assertEqualsWithDelta( 50.0, DashboardService::booking_value_between( $from, $to ), 0.01 );
		$this->assertEqualsWithDelta( 950.0, DashboardService::booking_value_between( null, null ), 0.01 );
	}

	public function test_booking_counts_leave_out_cancelled_and_refunded(): void {
		$this->booking( 'confirmed', 1.0 );
		$this->booking( 'pending', 1.0 );
		$this->booking( 'completed', 1.0 );
		$this->booking( 'cancelled', 1.0 );
		$this->booking( 'refunded', 1.0 );
		// A booking without a status is pending (Status::get()'s fallback): counted.
		$bare = (int) self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish', 'post_date' => gmdate( 'Y-m-d H:i:s' ) ) );
		$this->assertGreaterThan( 0, $bare );

		$m = DashboardService::get_dashboard_metrics();

		$this->assertSame( 4, $m['total_bookings'] );
		$this->assertSame( 4, $m['bookings_this_month'] );
		$this->assertSame( 'abs', DashboardService::get_metric_deltas()['bookings']['format'] );
		$this->assertSame( 4, DashboardService::get_metric_deltas()['bookings']['value'] );
	}

	public function test_renting_this_month_leaves_out_people_whose_only_booking_is_cancelled(): void {
		$this->booking( 'confirmed', 1.0, array( '_mhmrentiva_customer_email' => 'a@example.test' ) );
		$this->booking( 'cancelled', 1.0, array( '_mhmrentiva_customer_email' => 'b@example.test' ) );

		$this->assertSame( 1, DashboardService::get_dashboard_metrics()['total_customers_this_month'] );
	}

	/**
	 * Codex audit 2026-11-17 (Q4): a payment status this reader does not know
	 * is shown as owed, not hidden -- only settled statuses owe nothing.
	 */
	public function test_an_unknown_payment_status_is_owed_not_hidden(): void {
		$this->booking( 'confirmed', 640.0, array( '_mhmrentiva_payment_status' => 'awaiting_bank' ) );

		$this->assertEqualsWithDelta( 640.0, DashboardService::get_payments_summary()['pending_total'], 0.01 );
	}

	/**
	 * Codex audit 2026-11-17 (Q2): a booking carrying a meta key twice is one
	 * booking -- counted once, its price summed once, its debt summed once.
	 */
	public function test_a_duplicated_meta_row_does_not_count_a_booking_twice(): void {
		$id = $this->deposit_paid( 'confirmed', 100.0, 60.0 );
		add_post_meta( $id, '_mhmrentiva_status', 'confirmed' );
		add_post_meta( $id, '_mhmrentiva_total_price', '100' );
		add_post_meta( $id, '_mhmrentiva_remaining_amount', '60' );

		$m = DashboardService::get_dashboard_metrics();

		$this->assertSame( 1, $m['total_bookings'] );
		$this->assertEqualsWithDelta( 100.0, $m['total_revenue'], 0.01 );
		$this->assertEqualsWithDelta( 60.0, DashboardService::get_payments_summary()['pending_total'], 0.01 );
	}

	/**
	 * Codex audit 2026-11-17 (Q3): "all time" has no date condition, so a row
	 * whose post_date is the zero date is not dropped by a sentinel bound.
	 */
	public function test_all_time_has_no_date_bound(): void {
		global $wpdb;
		$id = $this->fully_paid( 'completed', 75.0 );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_date = '0000-00-00 00:00:00' WHERE ID = %d", $id ) );

		$m = DashboardService::get_dashboard_metrics();

		$this->assertSame( 1, $m['total_bookings'] );
		$this->assertEqualsWithDelta( 75.0, $m['total_revenue'], 0.01 );
	}

	/**
	 * Codex PR review (P2, #203): the debt total must not load the metadata of
	 * every settled booking in the site's history -- only bookings that can
	 * still owe are read.
	 */
	public function test_settled_bookings_are_not_read_for_the_debt_total(): void {
		$paid     = $this->fully_paid( 'completed', 500.0 );
		$refunded = $this->booking( 'confirmed', 300.0, array( '_mhmrentiva_payment_status' => 'refunded' ) );
		$owing    = $this->deposit_paid( 'confirmed', 400.0, 360.0 );
		foreach ( array( $paid, $refunded, $owing ) as $id ) {
			wp_cache_delete( $id, 'post_meta' );
		}

		$this->assertEqualsWithDelta( 360.0, DashboardService::get_payments_summary()['pending_total'], 0.01 );
		$this->assertFalse( wp_cache_get( $paid, 'post_meta' ), 'A booking paid in full is not loaded.' );
		$this->assertFalse( wp_cache_get( $refunded, 'post_meta' ), 'A refunded booking is not loaded.' );
		$this->assertNotFalse( wp_cache_get( $owing, 'post_meta' ), 'A booking that owes is read.' );
	}

	public function test_recent_bookings_are_the_newest_bookings_first(): void {
		$older = $this->booking( 'confirmed', 1.0, array( '_mhmrentiva_pickup_date' => '2099-01-01' ), gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ) );
		$newer = $this->booking( 'confirmed', 1.0, array( '_mhmrentiva_pickup_date' => '2027-01-01' ), gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );

		$ids = array_map( static fn( array $b ): int => (int) $b['id'], DashboardService::get_recent_bookings_paginated( 1, 5 )['items'] );

		$this->assertSame( array( $newer, $older ), array_values( array_intersect( $ids, array( $newer, $older ) ) ) );
	}
}
