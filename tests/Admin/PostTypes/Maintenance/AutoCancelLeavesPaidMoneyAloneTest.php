<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * K6: no unattended path moves money. A paid order sitting inside
 * AutoCancel's candidate set is either a data inconsistency (booking meta
 * says pending/pending while WooCommerce says paid) or a real refund
 * obligation -- and the sweep's own trigger can itself be the bug, exactly
 * as OnHoldChainDoesNotFeedAutoCancelTest proved for a related path. So
 * instead of cancelling the booking and its order, the sweep parks the
 * booking in needs_review and returns, leaving the order and its money
 * untouched for a human to act on.
 */
final class AutoCancelLeavesPaidMoneyAloneTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();
    }

    public function tearDown(): void
    {
        // RefundLock rows are written with a raw $wpdb->query(), and this
        // suite is known to commit its transaction from elsewhere
        // (Locker.php), so a planted lock can outlive the rollback and block
        // every later test that touches the same booking id.
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'" );

        parent::tearDown();
    }

    public function test_a_paid_order_stops_the_sweep_and_is_announced_once(): void
    {
        $booking_id = $this->make_expired_unpaid_booking();
        $order      = $this->create_paid_order_for_booking( $booking_id, '1200' );

        $refunded_before = $order->get_total_refunded();

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        $announced = 0;
        add_action(
            'mhmrentiva_refund_status_changed',
            static function () use ( &$announced ): void {
                ++$announced;
            }
        );

        AutoCancel::run();

        $order_after = wc_get_order( $order->get_id() );
        $this->assertSame(
            'processing',
            $order_after->get_status(),
            'A paid order must not be cancelled by an unattended sweep.'
        );
        $this->assertSame(
            $refunded_before,
            $order_after->get_total_refunded(),
            'No unattended path may move money -- the refunded total must not change.'
        );
        $this->assertNotSame( Status::CANCELLED, Status::get( $booking_id ) );
        $this->assertSame( RefundStatus::NEEDS_REVIEW, RefundStatus::get( $booking_id ) );
        $this->assertSame( 1, $announced, 'A paid order found by the sweep must be announced exactly once.' );

        // Sanity check: the early return must not have touched the very meta
        // sweep #1's query selects on. If it had, the second run below would
        // pass whether or not RefundStatus::transition() correctly refuses a
        // no-op needs_review -> needs_review write -- it would just prove the
        // booking silently fell out of the query.
        $this->assertSame( 'pending', get_post_meta( $booking_id, '_mhmrentiva_payment_status', true ) );
        $this->assertSame( 'pending', get_post_meta( $booking_id, '_mhmrentiva_status', true ) );

        AutoCancel::run();

        $this->assertSame(
            1,
            $announced,
            'The second sweep must not re-announce a booking already parked in review.'
        );
    }

    /**
     * Parking the booking is only half the feature: the notification is the
     * only thing that tells a human a paid order was left behind. Nothing
     * asserted its contents before -- the previous test counted
     * mhmrentiva_refund_status_changed and stopped there -- which is how a
     * link that is always empty on the one path that actually runs shipped
     * green.
     *
     * The link is the assertion that matters. AutoCancel::run() has two
     * production callers: the WP-Cron hook it is registered on
     * (AutoCancel.php:54), and VehicleColumns::maybe_run_autocancel()
     * (VehicleColumns.php:1460, called from :1417), a 60s-throttled fallback
     * for unreliable cron that runs inside an admin request with a logged-in
     * user. This test pins the cron one: there is no logged-in user, and
     * get_edit_post_link() returns null when current_user_can( 'edit_post',
     * $id ) is false (wp-includes/link-template.php:1473-1475 in the installed
     * core). So on that call path the "where to go" line was blank -- and
     * then, once a fallback existed, pointed at the booking list instead of
     * at the booking the sentence had just named.
     */
    public function test_the_review_notification_says_what_happened_and_where_to_go(): void
    {
        // Set explicitly rather than leaning on the harness default, so a
        // future setUp() that logs an admin in cannot quietly turn this into
        // an admin-context test and hide the very regression it pins.
        wp_set_current_user( 0 );

        $booking_id = $this->make_expired_unpaid_booking();
        $order      = $this->create_paid_order_for_booking( $booking_id, '1200' );

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        $mails = array();
        add_filter(
            'wp_mail',
            static function ( array $args ) use ( &$mails ): array {
                $mails[] = $args;

                return $args;
            }
        );

        AutoCancel::run();

        $this->assertCount( 1, $mails, 'Parking a booking for review must send exactly one notification.' );

        $mail = $mails[0];

        $this->assertSame( get_option( 'admin_email' ), $mail['to'] );
        $this->assertSame(
            'A cancelled reservation still holds paid money',
            $mail['subject'],
            'The subject is what an operator scans an inbox for; it is part of the contract, not decoration.'
        );
        $this->assertStringContainsString(
            '#' . $booking_id,
            $mail['message'],
            'A notification that does not name the booking sends the reader hunting.'
        );
        $this->assertStringContainsString(
            '1200',
            $mail['message'],
            'The amount left sitting in WooCommerce is the reason this email exists.'
        );
        $this->assertStringContainsString( $order->get_currency(), $mail['message'] );
        $this->assertMatchesRegularExpression(
            '#^Review the booking: https?://\S*post=' . $booking_id . '\S*$#m',
            $mail['message'],
            'In cron there is no current user, so get_edit_post_link() returns null and the fallback is the'
                . ' branch that always runs. It must deep-link to this booking; a URL that merely exists --'
                . ' the plugin-wide booking list, say -- makes the notification name a booking it then refuses'
                . ' to open.'
        );
    }

    /**
     * Parking the booking and telling a human are two steps, and only the
     * first one is durable. send_refund_needs_review_email() returns false
     * without throwing when admin_email does not validate
     * (NotificationHelper.php:64-66) and when wp_mail() itself fails, and
     * src/ registers no wp_mail_failed listener -- so a discarded return
     * value leaves the booking parked, the operator uninformed, and nothing
     * anywhere recording that the second half never happened. That reads
     * healthier than the lock refusal below, because the part that did work
     * is the part that leaves evidence.
     *
     * admin_email is broken through pre_option_admin_email rather than
     * update_option(): core's sanitize_option() restores the previous value
     * whenever the new one fails is_email() (formatting.php:4936-4941 with
     * :5181), so update_option() cannot produce this state at all.
     */
    public function test_a_notification_that_could_not_be_sent_leaves_a_trace(): void
    {
        $booking_id = $this->make_expired_unpaid_booking();
        $order      = $this->create_paid_order_for_booking( $booking_id, '1500' );

        add_filter( 'pre_option_admin_email', static fn (): string => 'not-an-email' );

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        $mails = array();
        add_filter(
            'wp_mail',
            static function ( array $args ) use ( &$mails ): array {
                $mails[] = $args;

                return $args;
            }
        );

        AutoCancel::run();

        $this->assertSame(
            array(),
            $mails,
            'The premise of this test is that no notification goes out; if one did, the branch under test was'
                . ' never reached and everything below is measuring nothing.'
        );

        // The park itself must still have succeeded -- an undeliverable
        // e-mail is no reason to leave the booking in a state that lets the
        // next sweep cancel a paid order.
        $this->assertSame( RefundStatus::NEEDS_REVIEW, RefundStatus::get( $booking_id ) );
        $this->assertSame(
            'processing',
            wc_get_order( $order->get_id() )->get_status(),
            'A failed notification must not weaken K6: the paid order stays untouched either way.'
        );

        $traced = false;
        $linked = false;

        foreach ( $this->all_log_entries() as $log ) {
            if ( str_contains( $log->post_content, 'Refund review notification failed for booking #' . $booking_id ) ) {
                $traced = true;
            }

            if (
                str_contains( $log->post_content, 'notification e-mail could not be sent' )
                && $booking_id === (int) get_post_meta( $log->ID, '_mhmrentiva_log_booking_id', true )
            ) {
                $linked = true;
            }
        }

        $this->assertTrue(
            $traced,
            'send_refund_needs_review_email() returns false silently; with its return value discarded, a booking'
                . ' parked for review that nobody was told about is indistinguishable from one that was.'
        );
        $this->assertTrue(
            $linked,
            'AdvancedLogger::error() passes no booking_id to log(), so its entry gets no'
                . ' _mhmrentiva_log_booking_id and the admin Logs table shows an em dash where the operator'
                . ' needs the link back to the booking (LogColumns.php:98).'
        );
    }

    /**
     * The lock is the other way this feature can fail silently. If another
     * request (or a row left behind by one that died) holds the booking's
     * refund lock, the whole park-and-notify block is skipped: no status, no
     * email, and -- until this test -- no trace at all. A stuck lock
     * therefore blocked both the cancellation and its notification
     * indefinitely and told the operator nothing. Same class as ruling T2-R3.
     *
     * The trace has to be an error(): the sibling Status::update_status()
     * refusal logs through warning(), which should_skip_log() drops under the
     * default mhmrentiva_log_level of 'error', so copying that branch would
     * have written an audit trail nobody can read.
     */
    public function test_a_lock_held_elsewhere_leaves_the_money_alone_and_leaves_a_trace(): void
    {
        $booking_id = $this->make_expired_unpaid_booking();
        $order      = $this->create_paid_order_for_booking( $booking_id, '900' );

        $refunded_before = $order->get_total_refunded();

        // A row this request does not own, stamped now so RefundLock cannot
        // steal it as stale (TTL is 300s) -- acquire() therefore refuses.
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            'mhmrentiva_refund_lock_' . $booking_id,
            'someone-else:' . time()
        ) );

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        AutoCancel::run();

        $order_after = wc_get_order( $order->get_id() );
        $this->assertSame(
            'processing',
            $order_after->get_status(),
            'A refused lock must not downgrade the guard: the paid order still may not be cancelled.'
        );
        $this->assertSame(
            $refunded_before,
            $order_after->get_total_refunded(),
            'No unattended path may move money -- least of all one that could not even take the lock.'
        );
        $this->assertNotSame( Status::CANCELLED, Status::get( $booking_id ) );
        $this->assertSame(
            '',
            (string) get_post_meta( $booking_id, '_mhmrentiva_refund_status', true ),
            'A request that never held the lock has no standing to describe this booking\'s refund state (T2-R2).'
        );

        $traced = false;
        $linked = false;

        foreach ( $this->all_log_entries() as $log ) {
            if ( str_contains( $log->post_content, 'Refund lock refused for booking #' . $booking_id ) ) {
                $traced = true;
            }

            if (
                str_contains( $log->post_content, 'could not park the booking for review' )
                && $booking_id === (int) get_post_meta( $log->ID, '_mhmrentiva_log_booking_id', true )
            ) {
                $linked = true;
            }
        }

        $this->assertTrue(
            $traced,
            'A lock refusal skips both the status write and the email; without a log entry nothing records that'
                . ' the sweep saw paid money and walked away.'
        );
        $this->assertTrue(
            $linked,
            'AdvancedLogger::error() never passes a booking_id to log(), so the admin Logs list table renders'
                . ' an em dash in its Booking column for it -- the entry an operator most needs to trace back to'
                . ' a booking would be the one with no link. Same regression the Task 2 review caught.'
        );
    }

    /**
     * @return array<int, \WP_Post>
     */
    private function all_log_entries(): array
    {
        return get_posts( array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );
    }

    /**
     * post_date is backdated two hours, past the default 30-minute payment
     * deadline, so AutoCancel::run()'s sweep #1 date_query selects it; the
     * payment_status/status pair is the pending/pending combination that same
     * sweep's meta_query selects on.
     */
    private function make_expired_unpaid_booking(): int
    {
        $booking_id = (int) self::factory()->post->create( array(
            'post_type' => 'mhmrentiva_booking',
            'post_date' => gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ),
        ) );

        update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'pending' );
        update_post_meta( $booking_id, '_mhmrentiva_status', 'pending' );

        return $booking_id;
    }
}
