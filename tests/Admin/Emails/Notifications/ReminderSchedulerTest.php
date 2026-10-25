<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Notifications;

use MHMRentiva\Admin\Booking\Core\Status;
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
     * The four site clocks every zone test runs under, with the UTC offset
     * each has on 10 November (any year: US DST has ended by then, NZ DST
     * has begun).
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function zones(): array
    {
        return array(
            'Istanbul'       => array( 'Europe/Istanbul', 3 ),
            'New York (EST)' => array( 'America/New_York', -5 ),
            'Auckland (DT)'  => array( 'Pacific/Auckland', 13 ),
            'fixed +03:00'   => array( '+03:00', 3 ),
        );
    }

    private function pin_zone(string $zone): void
    {
        if ('+03:00' === $zone) {
            $this->pin_site_offset(3);
            return;
        }
        $this->pin_site_timezone($zone);
    }

    /**
     * Next year's 10 November, so the fixture never drifts into the past
     * (scheduling refuses a moment already gone).
     */
    private static function pickup_date(): string
    {
        return ((int) gmdate('Y') + 1) . '-11-10';
    }

    /**
     * The expected reminder moment: $hour:$minute local on the pickup date,
     * minus the default 24 hours -- built from the literal offset, not from
     * the code under test.
     */
    private static function expected(int $offset, int $hour, int $minute = 0): int
    {
        return gmmktime($hour, $minute, 0, 11, 10, (int) gmdate('Y') + 1) - $offset * HOUR_IN_SECONDS - DAY_IN_SECONDS;
    }

    /**
     * @param array<string, string> $meta
     */
    private function scheduled_for(array $meta): int|false
    {
        $id = $this->booking(Status::CONFIRMED);
        update_post_meta($id, '_mhmrentiva_pickup_date', self::pickup_date());
        foreach ($meta as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        try {
            ReminderScheduler::schedule_reminder($id);

            return wp_next_scheduled('mhmrentiva_send_booking_reminder', array( $id ));
        } finally {
            wp_clear_scheduled_hook('mhmrentiva_send_booking_reminder', array( $id ));
        }
    }

    /**
     * The reminder is due N hours before the local pickup TIME, not before
     * the UTC midnight that strtotime() makes of the pickup date.
     *
     * @dataProvider zones
     */
    public function test_reminder_fires_relative_to_pickup_time(string $zone, int $offset): void
    {
        $this->pin_zone($zone);

        $this->assertSame(self::expected($offset, 14), $this->scheduled_for(array( '_mhmrentiva_pickup_time' => '14:00' )));
    }

    /**
     * `_start_time` is read before `_pickup_time`, as parse_datetimes() does.
     *
     * @dataProvider zones
     */
    public function test_reminder_start_time_wins_over_pickup_time(string $zone, int $offset): void
    {
        $this->pin_zone($zone);

        $this->assertSame(
            self::expected($offset, 15, 30),
            $this->scheduled_for(array(
                '_mhmrentiva_start_time'  => '15:30',
                '_mhmrentiva_pickup_time' => '14:00',
            ))
        );
    }

    /**
     * No time at all: the filtered default pickup time (10:00 unless a site
     * changes it), not midnight.
     *
     * @dataProvider zones
     */
    public function test_reminder_without_time_uses_default_pickup_time(string $zone, int $offset): void
    {
        $this->pin_zone($zone);

        $this->assertSame(self::expected($offset, 10), $this->scheduled_for(array()));

        add_filter('mhmrentiva_default_pickup_time', static fn (): string => '09:00');
        $this->assertSame(self::expected($offset, 9), $this->scheduled_for(array()));
    }
}
