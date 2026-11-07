<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditNotice;
use WP_UnitTestCase;

/**
 * Codex code audit 3b-2, Minor 5: one notice per user AND booking, and the
 * "Booking updated." drop only for the booking the error is about.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditNotice
 */
final class BookingEditNoticeTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		BookingEditNotice::reset();
	}

	public function test_two_bookings_keep_their_own_notice(): void
	{
		BookingEditNotice::queue(101, 'first');
		BookingEditNotice::queue(202, 'second');

		$this->assertSame('first', BookingEditNotice::peek(101));
		$this->assertSame('second', BookingEditNotice::peek(202));
	}

	public function test_the_updated_message_is_dropped_only_for_the_booking_in_error(): void
	{
		BookingEditNotice::queue(101, 'refused');

		$this->assertStringNotContainsString('message=1', apply_filters('redirect_post_location', 'post.php?post=101&action=edit&message=1', 101));
		$this->assertStringContainsString('message=1', apply_filters('redirect_post_location', 'post.php?post=202&action=edit&message=1', 202));
	}

	public function test_a_warning_keeps_the_updated_message(): void
	{
		BookingEditNotice::queue(303, 'heads up', 'warning');

		$this->assertStringContainsString('message=1', apply_filters('redirect_post_location', 'post.php?post=303&action=edit&message=1', 303));
	}
}
