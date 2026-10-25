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

	/**
	 * @return array<string, array{0:string}>
	 */
	public static function zones(): array
	{
		return array(
			'istanbul' => array( 'Europe/Istanbul' ),
			'new york' => array( 'America/New_York' ),
		);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_every_writer_stores_gmt( string $tz ): void
	{
		$this->pin_site_timezone( $tz );
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
		$this->pin_site_timezone( $tz );
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
		$this->pin_site_timezone( $tz );

		$this->assertTrue( DashboardService::is_payment_overdue( gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
		$this->assertFalse( DashboardService::is_payment_overdue( gmdate( 'Y-m-d H:i:s', time() + 60 ) ) );
		$this->assertFalse( DashboardService::is_payment_overdue( '' ) );
	}

	public function test_deposit_metabox_prints_local_time(): void
	{
		$this->pin_site_timezone( 'Europe/Istanbul' );
		$id = $this->booking();

		ob_start();
		BookingDepositMetaBox::render_deposit_management( get_post( $id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '01.11.2026 10:00', $html );
		$this->assertStringNotContainsString( '01.11.2026 07:00', $html );
	}

	public function test_deadline_in_email_is_local(): void
	{
		$this->pin_site_timezone( 'Europe/Istanbul' );
		$id = $this->booking();

		$context = Mailer::getBookingContext( $id );
		$this->assertStringContainsString( '10:00', (string) $context['booking']['payment_deadline'] );
		$this->assertStringStartsWith( '2026-11-01', (string) $context['booking']['payment_deadline'] );

		$info = DepositCalculator::get_booking_payment_info( $id );
		$this->assertStringContainsString( '10:00', (string) $info['payment_deadline'] );
	}
}
