<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Notifications;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Emails\Notifications\ReminderScheduler;
use MHMRentiva\Tests\Support\SiteClock;
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
    use SiteClock;

    private function booking(string $status): int
    {
        $id = (int) self::factory()->post->create();
        update_post_meta($id, '_mhmrentiva_status', $status);
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

    /**
     * The reminder is due N hours before the local pickup TIME, not before
     * the UTC midnight that strtotime() makes of the pickup date.
     *
     * Next year's 10 November, so the fixture never drifts into the past
     * (scheduling refuses a moment already gone).
     */
    public function test_reminder_fires_relative_to_pickup_time(): void
    {
        $this->pin_site_timezone('Europe/Istanbul');

        $date = ((int) gmdate('Y') + 1) . '-11-10';
        $id   = $this->booking(Status::CONFIRMED);
        update_post_meta($id, '_mhmrentiva_pickup_date', $date);
        update_post_meta($id, '_mhmrentiva_pickup_time', '14:00');

        try {
            ReminderScheduler::schedule_reminder($id);

            $this->assertSame(
                SiteTime::instant($date, '14:00') - DAY_IN_SECONDS,
                wp_next_scheduled('mhmrentiva_send_booking_reminder', array( $id ))
            );
        } finally {
            wp_clear_scheduled_hook('mhmrentiva_send_booking_reminder', array( $id ));
        }
    }
}
