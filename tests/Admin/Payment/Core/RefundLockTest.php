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
        // Deviation from the brief (documented in task-1-report.md, Finding 2
        // of the review round): RefundLock's $depth/$tokens maps are plain
        // PHP process memory, not DB state, so PHPUnit's per-test transaction
        // rollback never touches them -- a test whose body leaves this
        // request's depth above zero bleeds that count into whichever test
        // runs next. Reset via reflection rather than calling release() a
        // fixed number of times: a fixed count is sized to today's deepest
        // test and silently under-drains a future test with more unmatched
        // acquire() calls, and looping release() until the DB row disappears
        // is unsafe too -- a test that only plants a *foreign* lock (this
        // process never acquired it) would loop forever, since release() is
        // permanently a no-op when isset($depth[...]) is false. Reaching
        // into the static maps directly is correct regardless of how many
        // times this test acquired, and regardless of whether it acquired
        // at all.
        $this->reset_lock_state(self::BOOKING);

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    private function reset_lock_state(int $booking_id): void
    {
        foreach (['depth', 'tokens'] as $property_name) {
            $property = new \ReflectionProperty(RefundLock::class, $property_name);
            $property->setAccessible(true);

            $value = $property->getValue();
            unset($value[$booking_id]);
            $property->setValue($value);
        }
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
     * Review finding: strrpos() on a value with no colon returns false, and
     * substr($value, false + 1) reads as substr($value, 1) -- an
     * unparseable row would look like a near-zero timestamp and be stolen
     * instantly instead of refused. Nothing but this class writes these rows
     * today, so this is a fail-closed guard against a currently-unreachable
     * shape, not a live production path.
     */
    public function test_an_unparseable_lock_is_not_stolen(): void
    {
        global $wpdb;

        $malformed = 'not-a-token-with-no-colon-in-it';

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            'mhmrentiva_refund_lock_' . self::BOOKING,
            $malformed
        ));

        $this->assertFalse(
            RefundLock::acquire(self::BOOKING),
            'A row this class cannot parse must fail closed -- refused, not stolen.'
        );
        $this->assertSame(
            $malformed,
            $this->stored_token(self::BOOKING),
            'A closed refusal must not touch the row it could not parse.'
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
