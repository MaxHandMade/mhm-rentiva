<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking;

use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * PR T -- `_mhmrentiva_payment_deadline` has ONE shape: a GMT MySQL string.
 *
 * Two writers stored a local string and every reader assumed GMT, so on an
 * east-of-UTC site a deadline could look already passed. Every writer now
 * stores GMT and every display converts to the site zone.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardService
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox
 * @covers \MHMRentiva\Admin\Emails\Core\Mailer
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator
 */
final class PaymentDeadlineShapeTest extends WP_UnitTestCase
{
	use SiteClock;

	private const GMT_DEADLINE = '2026-11-01 07:00:00';

	public function tearDown(): void
	{
		$this->release_site_clock();
		parent::tearDown();
	}

	private function booking(): int
	{
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, '_mhmrentiva_status', 'pending' );
		update_post_meta( $id, '_mhmrentiva_payment_type', 'deposit' );
		update_post_meta( $id, '_mhmrentiva_payment_deadline', self::GMT_DEADLINE );
		return $id;
	}

	private const FIXED_PLUS_3 = 'UTC+3';

	private function zone( string $zone ): void
	{
		if ( self::FIXED_PLUS_3 === $zone ) {
			$this->pin_site_offset( 3 );
			return;
		}
		$this->pin_site_timezone( $zone );
	}

	/**
	 * Zone => local rendering of GMT 2026-11-01 07:00:00, written as literals
	 * (New York left DST at 06:00Z that day; Auckland is on NZDT, +13).
	 *
	 * @return array<string, array{0:string,1:string,2:string}>
	 */
	public static function zones(): array
	{
		return array(
			'istanbul' => array( 'Europe/Istanbul', '01.11.2026 10:00', '2026-11-01 10:00:00' ),
			'new york' => array( 'America/New_York', '01.11.2026 02:00', '2026-11-01 02:00:00' ),
			'auckland' => array( 'Pacific/Auckland', '01.11.2026 20:00', '2026-11-01 20:00:00' ),
			'fixed +3' => array( self::FIXED_PLUS_3, '01.11.2026 10:00', '2026-11-01 10:00:00' ),
		);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_every_writer_stores_gmt( string $tz ): void
	{
		$this->zone( $tz );
		update_option( 'mhmrentiva_settings', array( 'mhmrentiva_booking_payment_deadline_minutes' => 30 ) );

		$writers = array(
			'form'   => new \ReflectionMethod( BookingForm::class, 'get_payment_deadline' ),
			'bridge' => new \ReflectionMethod( WooCommerceBridge::class, 'get_payment_deadline' ),
		);

		foreach ( $writers as $name => $method ) {
			$method->setAccessible( true );
			$before = time();
			$value  = 'form' === $name ? $method->invoke( null, 'woocommerce' ) : $method->invoke( null );
			$this->assertLessThanOrEqual(
				2,
				abs( (int) strtotime( $value . ' UTC' ) - ( $before + 30 * 60 ) ),
				"The {$name} writer must store GMT ({$tz}), got {$value}."
			);
		}
	}

	/**
	 * The Bridge's own fallback (used only when Handler is missing) must agree.
	 *
	 * @dataProvider zones
	 */
	public function test_bridge_fallback_writer_stores_gmt( string $tz ): void
	{
		$this->zone( $tz );
		update_option( 'mhmrentiva_settings', array( 'mhmrentiva_booking_payment_deadline_minutes' => 30 ) );

		$method = new \ReflectionMethod( WooCommerceBridge::class, 'fallback_payment_deadline' );
		$method->setAccessible( true );
		$before = time();
		$value  = (string) $method->invoke( null );

		$this->assertLessThanOrEqual( 2, abs( (int) strtotime( $value . ' UTC' ) - ( $before + 30 * 60 ) ), "Fallback writer ({$tz}): {$value}" );
	}

	/**
	 * @dataProvider zones
	 */
	public function test_dashboard_overdue_uses_gmt_now( string $tz ): void
	{
		$this->zone( $tz );

		$this->assertTrue( DashboardService::is_payment_overdue( gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
		$this->assertFalse( DashboardService::is_payment_overdue( gmdate( 'Y-m-d H:i:s', time() + 60 ) ) );
		$this->assertFalse( DashboardService::is_payment_overdue( '' ) );
	}

	/**
	 * The real caller: a pending payment row is marked overdue from the GMT meta.
	 *
	 * @dataProvider zones
	 */
	public function test_pending_payments_row_is_marked_overdue( string $tz ): void
	{
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is required.' );
		}
		$this->zone( $tz );

		$order = wc_create_order();
		$order->set_total( '100' );
		$order->set_status( 'pending' );
		$order->save();

		$late = $this->booking();
		update_post_meta( $late, '_mhmrentiva_payment_deadline', gmdate( 'Y-m-d H:i:s', time() - 60 ) );
		update_post_meta( $late, '_mhmrentiva_wc_order_id', $order->get_id() );

		$order2 = wc_create_order();
		$order2->set_total( '100' );
		$order2->set_status( 'pending' );
		$order2->save();
		$ontime = $this->booking();
		update_post_meta( $ontime, '_mhmrentiva_payment_deadline', gmdate( 'Y-m-d H:i:s', time() + 600 ) );
		update_post_meta( $ontime, '_mhmrentiva_wc_order_id', $order2->get_id() );

		$flags = array();
		foreach ( DashboardService::get_pending_payments() as $row ) {
			$flags[ (int) $row['booking_id'] ] = (bool) $row['is_overdue'];
		}

		$this->assertArrayHasKey( $late, $flags, "Fixture guard ({$tz}): the late row must be listed." );
		$this->assertTrue( $flags[ $late ], "Past GMT deadline must be overdue ({$tz})." );
		$this->assertArrayHasKey( $ontime, $flags );
		$this->assertFalse( $flags[ $ontime ], "Future GMT deadline must not be overdue ({$tz})." );
	}

	/**
	 * @dataProvider zones
	 */
	public function test_deposit_metabox_prints_local_time( string $tz, string $local_dmy_hi ): void
	{
		$this->zone( $tz );
		$id = $this->booking();

		ob_start();
		BookingDepositMetaBox::render_deposit_management( get_post( $id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<strong>Deadline:</strong> ' . $local_dmy_hi, $html, $tz );
	}

	/**
	 * @dataProvider zones
	 */
	public function test_deadline_in_email_is_local( string $tz, string $unused, string $local_mysql ): void
	{
		$this->zone( $tz );
		$id = $this->booking();

		$context = Mailer::getBookingContext( $id );
		$this->assertSame( $local_mysql, $context['booking']['payment_deadline'], "Email context ({$tz})" );

		$info = DepositCalculator::get_booking_payment_info( $id );
		$this->assertSame( $local_mysql, $info['payment_deadline'], "Deposit summary ({$tz})" );
	}
}
