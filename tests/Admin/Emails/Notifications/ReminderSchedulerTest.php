<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Notifications;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Emails\Notifications\ReminderScheduler;
use WP_UnitTestCase;

/**
 * Reminder emails must not be sent for bookings that are no longer active.
 *
 * The reminder is scheduled at booking creation (pickup − N hours), so by the
 * time the cron event fires the booking may have changed state — e.g. it was
 * auto-cancelled for non-payment. A "your reservation starts soon" reminder for
 * a cancelled booking is the bug this guards against.
 */
class ReminderSchedulerTest extends WP_UnitTestCase
{
    private function booking(string $status): int
    {
        $id = (int) self::factory()->post->create();
        update_post_meta($id, '_mhm_status', $status);
        return $id;
    }

    /** @test */
    public function test_does_not_send_for_cancelled_booking(): void
    {
        $this->assertFalse(ReminderScheduler::should_send_reminder($this->booking(Status::CANCELLED)));
    }

    /** @test */
    public function test_does_not_send_for_terminal_statuses(): void
    {
        $this->assertFalse(ReminderScheduler::should_send_reminder($this->booking(Status::COMPLETED)));
        $this->assertFalse(ReminderScheduler::should_send_reminder($this->booking(Status::REFUNDED)));
        $this->assertFalse(ReminderScheduler::should_send_reminder($this->booking(Status::NO_SHOW)));
    }

    /** @test */
    public function test_sends_for_confirmed_booking(): void
    {
        $this->assertTrue(ReminderScheduler::should_send_reminder($this->booking(Status::CONFIRMED)));
    }
}
