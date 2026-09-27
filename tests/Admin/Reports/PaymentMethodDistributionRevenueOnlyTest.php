<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Reports;

use MHMRentiva\Admin\Reports\Repository\ReportRepository;
use WP_UnitTestCase;

/**
 * The payment-method breakdown sums revenue bookings only.
 *
 * Every other revenue figure in ReportRepository already reads
 * `_mhmrentiva_status IN ('completed', 'confirmed')`; this one summed every
 * booking, so a cancelled order still showed up as money taken by its gateway
 * in Pro's revenue report.
 *
 * @covers \MHMRentiva\Admin\Reports\Repository\ReportRepository::get_payment_method_distribution
 */
final class PaymentMethodDistributionRevenueOnlyTest extends WP_UnitTestCase
{
	private function booking( string $gateway, float $price, ?string $status ): void
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			)
		);
		update_post_meta( $id, '_mhmrentiva_total_price', (string) $price );
		update_post_meta( $id, '_mhmrentiva_payment_gateway', $gateway );
		if ( null !== $status ) {
			update_post_meta( $id, '_mhmrentiva_status', $status );
		}
	}

	public function test_a_cancelled_booking_is_not_gateway_revenue(): void
	{
		$this->booking( 'woocommerce', 100.0, 'confirmed' );
		$this->booking( 'woocommerce', 6185.0, 'cancelled' );
		$this->booking( 'offline', 500.0, 'cancelled' );
		$this->booking( 'offline', 40.0, null );

		$rows = ReportRepository::get_payment_method_distribution( gmdate( 'Y-m-d', strtotime( '-7 days' ) ), gmdate( 'Y-m-d' ) );
		$by   = array();
		foreach ( $rows as $row ) {
			$by[ $row->method ] = array( (float) $row->revenue, (int) $row->count );
		}

		$this->assertSame( array( 'woocommerce' => array( 100.0, 1 ) ), $by, 'Only the confirmed booking is money a gateway took.' );
	}
}
