<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

/**
 * Fixtures mirror real production writes.
 *
 * DepositCalculator::calculate_booking_deposit() writes `_mhmrentiva_deposit_amount`
 * = the FULL total (and `_mhmrentiva_remaining_amount` = 0) for `payment_type ===
 * 'full'` bookings -- see its `full` branch, and
 * \MHMRentiva\Admin\Booking\Core\Handler::create_booking() (~line 366) which
 * persists `$deposit_result['deposit_amount']` verbatim regardless of
 * payment type. The previous version of this suite instead omitted
 * `_mhmrentiva_deposit_amount` entirely on its "full-payment" fixture -- a fixture
 * that does not occur in production -- so the suite stayed green while
 * `deposit_blocked` silently counted full-payment bookings as deposits held.
 */
final class DashboardPaymentsSummaryTest extends WP_UnitTestCase {

	/**
	 * A full-payment booking exactly as Handler::create_booking() +
	 * DepositCalculator::calculate_booking_deposit()'s 'full' branch write it.
	 */
	private function full_payment_booking( string $status, float $total, ?string $date = null ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
			'post_date'   => $date ?? gmdate( 'Y-m-15 10:00:00' ),
		) );
		update_post_meta( $id, '_mhmrentiva_status', $status );
		update_post_meta( $id, '_mhmrentiva_payment_type', 'full' );
		// 'full' branch: deposit_amount = total_amount, remaining_amount = 0.
		update_post_meta( $id, '_mhmrentiva_deposit_amount', (string) $total );
		update_post_meta( $id, '_mhmrentiva_remaining_amount', '0' );
		update_post_meta( $id, '_mhmrentiva_total_price', (string) $total );
		return $id;
	}

	/**
	 * A deposit booking exactly as the 'deposit' branch writes it.
	 */
	private function deposit_booking( string $status, float $deposit, float $remaining, ?string $date = null ): int {
		$id = self::factory()->post->create( array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
			'post_date'   => $date ?? gmdate( 'Y-m-15 10:00:00' ),
		) );
		update_post_meta( $id, '_mhmrentiva_status', $status );
		update_post_meta( $id, '_mhmrentiva_payment_type', 'deposit' );
		update_post_meta( $id, '_mhmrentiva_deposit_amount', (string) $deposit );
		update_post_meta( $id, '_mhmrentiva_remaining_amount', (string) $remaining );
		update_post_meta( $id, '_mhmrentiva_total_price', (string) ( $deposit + $remaining ) );
		return $id;
	}

	/**
	 * Fix A regression guard. A full-payment booking's `_mhmrentiva_deposit_amount`
	 * equals its FULL total (per DepositCalculator::calculate_booking_deposit()),
	 * so if `deposit_blocked` ever loses its `_mhmrentiva_payment_type = 'deposit'`
	 * join, this test fails by picking up the full-payment booking's 2000 on
	 * top of the deposit booking's 500.
	 */
	public function test_deposit_blocked_counts_only_deposit_type_bookings(): void {
		$this->deposit_booking( 'confirmed', 500.0, 1500.0 );  // counts: 500
		$this->full_payment_booking( 'confirmed', 2000.0 );     // must NOT count

		$s = DashboardService::get_payments_summary();

		$this->assertEqualsWithDelta( 500.0, $s['deposit_blocked'], 0.01 );
	}

	public function test_deposit_blocked_excludes_non_blocking_statuses(): void {
		$this->deposit_booking( 'cancelled', 999.0, 0.0 );
		$this->deposit_booking( 'completed', 300.0, 0.0 );

		$s = DashboardService::get_payments_summary();

		$this->assertEqualsWithDelta( 0.0, $s['deposit_blocked'], 0.01 );
	}

	public function test_deposit_blocked_includes_in_progress(): void {
		$this->deposit_booking( 'in_progress', 750.0, 0.0 );

		$s = DashboardService::get_payments_summary();

		$this->assertEqualsWithDelta( 750.0, $s['deposit_blocked'], 0.01 );
	}

	public function test_this_month_collected_key_removed(): void {
		$s = DashboardService::get_payments_summary();

		$this->assertArrayNotHasKey( 'this_month_collected', $s );
	}

	/**
	 * COVERAGE GAP -- reported, not silently accepted.
	 *
	 * `pending_total` is now sourced from get_pending_payments()'s shared
	 * WC-order-status scan (Fix B): it only ever contributes an amount when
	 * `wc_get_order()` exists AND resolves a real WC_Order in
	 * ('pending','on-hold') status. This Docker/PHPUnit environment does not
	 * load WooCommerce at all -- see
	 * tests/Integrations/WooCommerce/RemainingPaymentLinkTest.php, which
	 * `markTestSkipped()`s all 7 of its assertions via
	 * `class_exists('WooCommerce')` for the same reason. Here,
	 * `function_exists('wc_get_order')` is false, so no booking -- regardless
	 * of its `_mhmrentiva_*_order_id` meta -- can ever contribute to `pending_total`.
	 *
	 * The assertion below documents what IS true in this environment (the
	 * value is deterministically 0.0, and fixture data with attached
	 * order-id meta does not crash or spuriously leak an amount into the
	 * total). It does NOT exercise the actual WC-order-total arithmetic that
	 * Fix B introduces -- that requires a WooCommerce-loaded test
	 * environment, which does not currently exist for this plugin's suite.
	 */
	public function test_pending_total_is_zero_without_a_loaded_woocommerce(): void {
		$this->deposit_booking( 'confirmed', 500.0, 1500.0 );

		// Attach order-id meta the way RemainingPaymentHandler would, to prove
		// the mere presence of an order id does not spuriously inflate the
		// total when WooCommerce cannot resolve it.
		$id = $this->deposit_booking( 'pending', 200.0, 800.0 );
		update_post_meta( $id, '_mhmrentiva_woocommerce_order_id', 999999 );

		$s = DashboardService::get_payments_summary();

		$this->assertSame( 0.0, $s['pending_total'] );
	}
}
