<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

/**
 * The booking detail offers one neutral place for an add-on to print extra
 * rows beside the pick-up and return times. With nobody listening the page
 * must be exactly what it was before the hook existed.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer::render_booking_detail
 */
final class BookingDetailRowsHookTest extends WP_UnitTestCase
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
		remove_all_actions( 'mhmrentiva_booking_detail_rows' );
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

	/**
	 * Generated element ids differ between two renders; nothing else may.
	 */
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
			'mhmrentiva_booking_detail_rows',
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
			'mhmrentiva_booking_detail_rows',
			static function (): void {
				echo '<!--EXTRA-ROW-->';
			}
		);
		$with_row = $this->normalise( AccountRenderer::render_booking_detail( $booking_id, true ) );

		$this->assertStringContainsString( '<!--EXTRA-ROW-->', $with_row );
		$this->assertSame( $plain, $this->normalise( str_replace( '<!--EXTRA-ROW-->', '', $with_row ) ), 'The hook adds only what a subscriber prints.' );
		$this->assertStringNotContainsString( 'mhmrentiva_booking_detail_rows', $plain );
	}

	public function test_row_prints_inside_the_pickup_return_list(): void
	{
		$booking_id = $this->create_booking();

		add_action(
			'mhmrentiva_booking_detail_rows',
			static function (): void {
				echo '<!--EXTRA-ROW-->';
			}
		);
		$html = AccountRenderer::render_booking_detail( $booking_id, true );

		$this->assertMatchesRegularExpression( '#<!--EXTRA-ROW-->\s*</dl>#', $html );
	}
}
