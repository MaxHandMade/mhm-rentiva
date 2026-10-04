<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Booking\Core\Hooks;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersOptimizer;
use WP_UnitTestCase;

/**
 * A cancelled booking is not money the customer spent.
 *
 * The Customers screen filtered bookings on `post_status` alone and never read
 * `_mhmrentiva_status`, so a booking cancelled before it was paid still added
 * its full price to "Total Spent", made the customer "Active" for 90 days,
 * counted toward VIP and raised the "Avg. Spend" card. Found on the dev site:
 * one cancelled $6,185 booking produced "1 booking · $6,185.00 · Active".
 *
 * The screen now uses the definition the dashboard revenue cards and the
 * customer report already use -- only `completed` and `confirmed` are money
 * (Status::revenue_statuses()). The booking COUNT stays a count of every
 * booking, because the detail panel lists every booking (with its status) and
 * pages through that same number.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomersOptimizer
 */
final class CustomerSpendCountsRevenueOnlyTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		CustomerIdentity::flush_memo();
		CustomersOptimizer::clear_cache();
	}

	public function tearDown(): void
	{
		CustomersOptimizer::clear_cache();
		parent::tearDown();
	}

	/**
	 * A customer registered long enough ago that the "new" tag cannot mask
	 * the "active" one this file is about.
	 */
	private function makeOldCustomer(): int
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_update_user(
			array(
				'ID'              => $user,
				'user_registered' => gmdate( 'Y-m-d H:i:s', strtotime( '-400 days' ) ),
			)
		);
		clean_user_cache( $user );

		return $user;
	}

	private function makeBooking( int $user, float $price, ?string $status ): int
	{
		$booking = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
			)
		);
		update_post_meta( $booking, '_mhmrentiva_customer_user_id', $user );
		update_post_meta( $booking, '_mhmrentiva_total_price', (string) $price );
		if ( null !== $status ) {
			update_post_meta( $booking, '_mhmrentiva_status', $status );
		}

		return $booking;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function listedRow( int $user ): array
	{
		foreach ( CustomersOptimizer::get_customers_optimized( 1, 100 )['customers'] as $row ) {
			if ( (int) $row['id'] === $user ) {
				return $row;
			}
		}
		$this->fail( "User {$user} is not listed." );
	}

	public function test_the_revenue_statuses_are_the_dashboards(): void
	{
		$this->assertSame( array( Status::COMPLETED, Status::CONFIRMED ), Status::revenue_statuses() );
	}

	/**
	 * The dev-site case, verbatim: one cancelled booking and nothing else.
	 */
	public function test_a_cancelled_booking_is_listed_but_not_spent(): void
	{
		$user = $this->makeOldCustomer();
		$this->makeBooking( $user, 6185.0, Status::CANCELLED );

		$row = $this->listedRow( $user );
		$this->assertSame( 1, $row['booking_count'], 'The booking still exists and is still listed.' );
		$this->assertSame( CurrencyHelper::format_price( 0.0, 2 ), $row['total_spent'] );
		$this->assertSame( 'none', $row['status'], 'A cancelled booking is not activity.' );

		$detail = CustomersOptimizer::get_customer_details_optimized( $user );
		$this->assertSame( 1, $detail['booking_count'] );
		$this->assertSame( CurrencyHelper::format_price( 0.0, 2 ), $detail['total_spent'] );
		$this->assertSame( 'none', $detail['status'] );

		$stats = CustomersOptimizer::get_customer_stats_optimized();
		$this->assertSame( 0, $stats['active_90d'] );
		$this->assertSame( 0.0, (float) $stats['avg_spend'] );
	}

	/**
	 * Every non-revenue status, and a booking with no status meta at all --
	 * the dashboard's INNER JOIN on the status meta leaves that one out too.
	 *
	 * @dataProvider provideUnpaidStatuses
	 */
	public function test_no_unpaid_status_is_spent( ?string $status ): void
	{
		$user = $this->makeOldCustomer();
		$this->makeBooking( $user, 250.0, $status );

		$row = $this->listedRow( $user );
		$this->assertSame( CurrencyHelper::format_price( 0.0, 2 ), $row['total_spent'] );
		$this->assertSame( 'none', $row['status'] );
	}

	/**
	 * @return array<string, array{0: ?string}>
	 */
	public static function provideUnpaidStatuses(): array
	{
		return array(
			'cancelled'       => array( Status::CANCELLED ),
			'refunded'        => array( Status::REFUNDED ),
			'no show'         => array( Status::NO_SHOW ),
			'pending'         => array( Status::PENDING ),
			'pending payment' => array( Status::PENDING_PAYMENT ),
			'draft'           => array( Status::DRAFT ),
			'no status meta'  => array( null ),
		);
	}

	public function test_only_revenue_bookings_are_summed_when_both_kinds_exist(): void
	{
		$user = $this->makeOldCustomer();
		$this->makeBooking( $user, 100.0, Status::CONFIRMED );
		$this->makeBooking( $user, 40.0, Status::COMPLETED );
		$this->makeBooking( $user, 6185.0, Status::CANCELLED );

		$row = $this->listedRow( $user );
		$this->assertSame( 3, $row['booking_count'] );
		$this->assertSame( CurrencyHelper::format_price( 140.0, 2 ), $row['total_spent'] );
		$this->assertSame( 'active', $row['status'] );

		$stats = CustomersOptimizer::get_customer_stats_optimized();
		$this->assertSame( 1, $stats['active_90d'] );
		$this->assertSame( 140.0, (float) $stats['avg_spend'] );
	}

	public function test_the_active_filter_ignores_a_customer_whose_only_recent_booking_was_cancelled(): void
	{
		$cancelled_only = $this->makeOldCustomer();
		$this->makeBooking( $cancelled_only, 500.0, Status::CANCELLED );
		$paying = $this->makeOldCustomer();
		$this->makeBooking( $paying, 500.0, Status::CONFIRMED );

		$result = CustomersOptimizer::get_customers_optimized( 1, 100, '', 'last_booking', 'desc', 'active' );
		$ids    = array_map( static fn( $r ): int => (int) $r['id'], $result['customers'] );

		$this->assertSame( array( $paying ), $ids );
		$this->assertSame( 1, $result['total'], 'The pager total must agree with the rows.' );
	}

	public function test_cancelled_bookings_do_not_make_a_vip(): void
	{
		$user = $this->makeOldCustomer();
		for ( $i = 0; $i < CustomersOptimizer::get_vip_min_bookings(); $i++ ) {
			$this->makeBooking( $user, 10.0, Status::CANCELLED );
		}

		$this->assertNotSame( 'vip', $this->listedRow( $user )['status'] );

		$result = CustomersOptimizer::get_customers_optimized( 1, 100, '', 'last_booking', 'desc', 'vip' );
		$this->assertSame( 0, $result['total'] );
	}

	/**
	 * The panel shows every booking, so each one says whether it was counted.
	 */
	public function test_recent_bookings_carry_their_status(): void
	{
		$user = $this->makeOldCustomer();
		$this->makeBooking( $user, 6185.0, Status::CANCELLED );

		$bookings = CustomersOptimizer::get_customer_details_optimized( $user )['recent_bookings'];

		$this->assertCount( 1, $bookings );
		$this->assertSame( Status::CANCELLED, $bookings[0]['status'] );
		$this->assertSame( Status::get_label( Status::CANCELLED ), $bookings[0]['status_label'] );
		$this->assertFalse( $bookings[0]['counted'] );
	}

	/**
	 * The figures are cached; a status change must not leave the old ones up.
	 * PHPUnit does not boot the plugin's own hooks, so the test registers them.
	 */
	public function test_a_status_change_clears_the_cached_figures(): void
	{
		Hooks::register();

		$user    = $this->makeOldCustomer();
		$booking = $this->makeBooking( $user, 300.0, Status::CONFIRMED );

		$this->assertSame( CurrencyHelper::format_price( 300.0, 2 ), $this->listedRow( $user )['total_spent'] );

		update_post_meta( $booking, '_mhmrentiva_status', Status::CANCELLED );
		do_action( 'mhmrentiva_booking_status_changed', $booking, Status::CONFIRMED, Status::CANCELLED );

		$this->assertSame( CurrencyHelper::format_price( 0.0, 2 ), $this->listedRow( $user )['total_spent'] );
	}

	/**
	 * AutoCancel / AutoComplete change up to fifty statuses per run; the
	 * invalidation on that path is a stamp bump, never the options-table scan
	 * clear_cache_by_type() runs. Other listeners
	 * on the same action (vehicle caches) are not this test's subject.
	 */
	public function test_the_status_change_invalidation_does_not_scan_the_options_table(): void
	{
		Hooks::register();
		$user    = $this->makeOldCustomer();
		$booking = $this->makeBooking( $user, 300.0, Status::CONFIRMED );

		$scans   = array();
		$capture = static function ( string $sql ) use ( &$scans ): string {
			if ( false !== stripos( $sql, 'option_name LIKE' ) && false !== stripos( $sql, 'customers' ) ) {
				$scans[] = $sql;
			}
			return $sql;
		};
		add_filter( 'query', $capture );

		try {
			do_action( 'mhmrentiva_booking_status_changed', $booking, Status::CONFIRMED, Status::CANCELLED );
		} finally {
			remove_filter( 'query', $capture );
		}

		$this->assertSame( array(), $scans );
	}
}
