<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

/**
 * The booking detail offers a neutral place, just above the vehicle section,
 * where an add-on prints one complete notice block. With nobody listening the
 * page must be exactly what it was before the hook existed.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer::render_booking_detail
 */
final class BookingDetailNoticeHookTest extends WP_UnitTestCase
{
	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->customer_id );
	}

	public function tearDown(): void
	{
		remove_all_actions( 'mhmrentiva_booking_detail_notice' );
		parent::tearDown();
	}

	private function create_booking(): int
	{
		$booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );

		update_post_meta( $booking_id, '_mhmrentiva_customer_user_id', $this->customer_id );
		update_post_meta( $booking_id, '_mhmrentiva_status', 'confirmed' );
		update_post_meta( $booking_id, '_mhmrentiva_total_price', '300.00' );

		return $booking_id;
	}

	private function normalise( string $html ): string
	{
		$html = (string) preg_replace( '/rvs-acc-\d+/', 'rvs-acc-N', $html );

		return (string) preg_replace( '/\s+/', ' ', $html );
	}

	public function test_hook_fires_with_booking_id(): void
	{
		$booking_id = $this->create_booking();
		$received   = array();

		add_action(
			'mhmrentiva_booking_detail_notice',
			static function ( $id ) use ( &$received ): void {
				$received[] = $id;
			}
		);

		AccountRenderer::render_booking_detail( $booking_id, true );

		$this->assertSame( array( $booking_id ), $received );
	}

	public function test_markup_unchanged_without_subscribers(): void
	{
		$booking_id = $this->create_booking();

		$plain = $this->normalise( AccountRenderer::render_booking_detail( $booking_id, true ) );

		add_action(
			'mhmrentiva_booking_detail_notice',
			static function (): void {
				echo '<!--NOTICE-->';
			}
		);
		$with_notice = $this->normalise( AccountRenderer::render_booking_detail( $booking_id, true ) );

		$this->assertStringContainsString( '<!--NOTICE-->', $with_notice );
		$this->assertSame( $plain, $this->normalise( str_replace( '<!--NOTICE-->', '', $with_notice ) ) );
		$this->assertStringNotContainsString( 'mhmrentiva_booking_detail_notice', $plain );
	}

	public function test_notice_prints_before_the_vehicle_section(): void
	{
		$booking_id = $this->create_booking();

		add_action(
			'mhmrentiva_booking_detail_notice',
			static function (): void {
				echo '<!--NOTICE-->';
			}
		);
		$html = AccountRenderer::render_booking_detail( $booking_id, true );

		$this->assertMatchesRegularExpression( '#<!--NOTICE-->\s*<section class="rvs-account-detail__vehicle"#', $html );
	}
}
