<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditNotice;
use WP_UnitTestCase;

/**
 * A payment that arrived for an amount that was no longer current stays in
 * front of the operator until someone has looked at it (PR-2 A3).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditNotice::render_payment_mismatch
 */
final class BookingEditNoticeMismatchTest extends WP_UnitTestCase
{
	private int $booking_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		$GLOBALS['post'] = get_post($this->booking_id);
	}

	public function tearDown(): void
	{
		unset($GLOBALS['post']);
		parent::tearDown();
	}

	private function rendered(): string
	{
		ob_start();
		BookingEditNotice::render_payment_mismatch();

		return (string) ob_get_clean();
	}

	public function test_a_flagged_booking_shows_the_notice_with_a_dismiss_link(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		update_post_meta($this->booking_id, '_mhmrentiva_payment_mismatch', array(
			'order_id'      => 4242,
			'due_minor'     => 134000,
			'stamped_minor' => 100000,
			'at'            => time(),
		));

		$html = $this->rendered();

		$this->assertStringContainsString('#4242', $html);
		$this->assertStringContainsString(BookingEditNotice::DISMISS_MISMATCH_ACTION, $html);
		$this->assertStringContainsString('_wpnonce=', $html);
	}

	public function test_an_unflagged_booking_shows_nothing(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

		$this->assertSame('', $this->rendered());
	}

	public function test_a_user_who_cannot_edit_the_booking_sees_nothing(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'subscriber' )));
		update_post_meta($this->booking_id, '_mhmrentiva_payment_mismatch', array( 'order_id' => 4242 ));

		$this->assertSame('', $this->rendered());
	}
}
