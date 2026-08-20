<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\RefundLock;
use WP_UnitTestCase;

/**
 * Spec §5.4: the money step needs a mutex, and the two obvious primitives were
 * both measured and rejected -- Locker commits PHPUnit's transaction, and
 * add_option() is INSERT ... ON DUPLICATE KEY UPDATE (WP 7.0.4
 * option.php:1140), so the second racer overwrites the first lock instead of
 * being refused.
 *
 * What is testable in one process is stated plainly: this class can prove that
 * a row written by "another request" refuses a second acquire, that the same
 * request may re-enter, and that a stale row is stolen. It CANNOT prove
 * cross-process exclusion -- one PHPUnit process shares one MySQL connection.
 * That limit is the reason GET_LOCK() was rejected as well: it is re-entrant
 * per connection, so the second acquire in this very process would answer
 * "acquired" and the test would be a false green.
 */
final class RefundLockTest extends WP_UnitTestCase
{
    private const BOOKING = 4242;

    public function tearDown(): void
    {
        // Deviation from the brief (documented in task-1-report.md): a single
        // release() only undoes one acquire(). test_the_same_request_may_re_enter()
        // acquires twice in its body and never releases, which leaves this
        // request's static depth counter at 1 for BOOKING. That counter is
        // plain PHP process memory -- unlike the row deleted below, it is NOT
        // reset by PHPUnit's per-test transaction rollback -- so it bleeds
        // into whichever test runs next and makes its first acquire() a
        // silent re-entrant no-op instead of a real INSERT. release() is a
        // documented no-op once depth reaches zero, so draining it a few
        // extra times here is harmless and restores true per-test isolation.
        RefundLock::release(self::BOOKING);
        RefundLock::release(self::BOOKING);
        RefundLock::release(self::BOOKING);

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    /**
     * Simulates another request: the row exists and this request's static maps
     * know nothing about it.
     */
    private function plant_foreign_lock(int $booking_id, int $age_seconds = 0): string
    {
        global $wpdb;

        $token = 'someone-else:' . (time() - $age_seconds);

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            'mhmrentiva_refund_lock_' . $booking_id,
            $token
        ));

        return $token;
    }

    private function stored_token(int $booking_id): string
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            'mhmrentiva_refund_lock_' . $booking_id
        ));
    }

    public function test_a_second_request_cannot_take_a_held_lock(): void
    {
        $this->plant_foreign_lock(self::BOOKING);

        $this->assertFalse(
            RefundLock::acquire(self::BOOKING),
            'A lock held by another request must not be handed out twice.'
        );
    }

    public function test_the_same_request_may_re_enter(): void
    {
        $this->assertTrue(RefundLock::acquire(self::BOOKING));
        $this->assertTrue(
            RefundLock::acquire(self::BOOKING),
            'The cancellation flow holds this lock while calling Service, which takes it again.'
        );
    }

    public function test_one_release_does_not_free_a_re_entered_lock(): void
    {
        RefundLock::acquire(self::BOOKING);
        RefundLock::acquire(self::BOOKING);
        RefundLock::release(self::BOOKING);

        $this->assertNotSame(
            '',
            $this->stored_token(self::BOOKING),
            'The inner release must not drop the outer holder lock.'
        );

        RefundLock::release(self::BOOKING);

        $this->assertSame('', $this->stored_token(self::BOOKING));
    }

    public function test_release_does_not_remove_another_owners_row(): void
    {
        $foreign = $this->plant_foreign_lock(self::BOOKING);

        RefundLock::release(self::BOOKING);

        $this->assertSame(
            $foreign,
            $this->stored_token(self::BOOKING),
            'A request that never acquired must not be able to unlock somebody else.'
        );
    }

    public function test_a_stale_lock_is_stolen(): void
    {
        $this->plant_foreign_lock(self::BOOKING, 3600);

        $this->assertTrue(
            RefundLock::acquire(self::BOOKING),
            'A lock left behind by a process that died must not block the booking forever.'
        );
        $this->assertStringStartsNotWith(
            'someone-else',
            $this->stored_token(self::BOOKING),
            'Stealing means replacing the row, not co-existing with it.'
        );
    }

    /**
     * The ruling behind this class, kept as a measurement rather than a
     * comment: the statement WordPress uses inside add_option() overwrites,
     * the statement this class uses refuses.
     */
    public function test_the_statement_add_option_uses_would_have_overwritten_the_lock(): void
    {
        global $wpdb;

        $name = 'mhmrentiva_refund_lock_probe';

        $first = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $name,
            'first'
        ));
        $second = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $name,
            'second'
        ));

        $this->assertSame(1, (int) $first);
        $this->assertSame(0, (int) $second, 'INSERT IGNORE refuses the second writer.');
        $this->assertSame('first', $this->raw_value($name));

        // WP 7.0.4 wp-includes/option.php:1140 -- what both racers run once
        // they are past add_option()'s non-atomic get_option() check.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            $name,
            'second'
        ));

        $this->assertSame(
            'second',
            $this->raw_value($name),
            'The options API cannot be the lock: the loser of the race wins the row.'
        );
    }

    private function raw_value(string $name): string
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $name
        ));
    }
}
