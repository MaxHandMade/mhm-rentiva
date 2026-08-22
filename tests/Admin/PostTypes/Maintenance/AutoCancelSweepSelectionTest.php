<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Negative controls for a destructive sweep.
 *
 * AutoCancel's two sweeps cancel bookings and their WooCommerce orders with
 * no human in the loop, so what they DON'T select is as much a part of the
 * contract as what they do. Nothing measured that before: every existing test
 * asserts a booking WAS cancelled, which a sweep that selects far too much
 * satisfies just as happily as a correct one. A destructive sweep without a
 * negative control has no test.
 *
 * The negative controls here are the ways the queries used to overreach
 * (spec v3 §7.2.2-4), each stated in the past tense because each one is now
 * fixed and this file is what keeps it fixed: a deliberately part-refunded
 * booking, where the operator kept a cancellation fee, which is a settled
 * outcome and not "unpaid"; a `completed` booking, which
 * Status::can_transition() gives no CANCELLED edge, so it was selected and
 * refused on every single tick forever, and the refusal logs at warning
 * level, which the default log level drops -- a silent infinite loop; a
 * booking already parked in `needs_review`, which a human owns and which both
 * sweeps kept walking back over every five minutes; and an order somebody had
 * already paid for, reached through sync_orphan_wc_orders(). Task 12 (slice
 * 5) added a sibling to the `needs_review` control: any booking whose
 * refund_status has already reached a terminal state (RefundStatus::
 * terminalStates()) -- including one an operator just dismissed OUT of
 * needs_review via review_dismiss() -- which used to fall straight back into
 * an ordinary unpaid-and-past-deadline selection the moment it left
 * `needs_review`, silently, for the reason not_parked_for_review()'s own
 * docblock explains.
 *
 * Every one of those is satisfied by a sweep that selects nothing at all, so
 * the positive controls are not optional decoration: a genuinely unpaid
 * past-pickup booking must still be swept, and so must one whose status meta
 * is empty -- the value the rest of the plugin reads as `pending`. They are
 * the reason a destructive sweep cannot be "fixed" by breaking it.
 *
 * Neither list is numbered on purpose. A count in a docblock is one more
 * thing to keep true, and the last one stopped being true the first time a
 * control was added.
 *
 * The selection probe is `sync_stale_past_bookings()['cancelled']`, which
 * sweep #2 increments once per SELECTED booking, before it knows whether the
 * cancellation will succeed. That makes it the sharpest available witness of
 * selection -- and, on the two paths where the booking is parked or refused
 * rather than cancelled, a misnomer. Pre-existing; not renamed here because
 * the key is a public return-shape.
 */
final class AutoCancelSweepSelectionTest extends WP_UnitTestCase
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

        // Same trap, second door. SettingsCore::set() rewrites the whole
        // mhmrentiva_settings option, and the tests below use it to raise
        // mhmrentiva_log_level and to enable the auto-cancel sweep.
        //
        // Prophylactic, and said so on purpose: measured on this tree, those
        // writes DO roll back today -- the stored option is byte-identical
        // after this file runs with or without this line. What the line
        // guards is that the rollback is not guaranteed. Locker::withLock()
        // opens its own START TRANSACTION (Locker.php:25), which MySQL treats
        // as an implicit COMMIT of everything the test has done up to that
        // point; a probe that called SettingsCore::set() and then
        // Locker::withLock() left the setting sitting in the database after
        // the test ended. Nothing in this file reaches Locker at the moment,
        // and one listener added to mhmrentiva_booking_status_changed would
        // be enough to change that. The failure would be silent -- a plugin
        // setting quietly altered for the rest of the run, which is how this
        // dev install's price_num_decimals went from 3 to 2. Deleting this
        // option is the suite's standard tearDown pattern for any test that
        // writes a setting; a count of the files doing it would only go stale.
        delete_option( 'mhmrentiva_settings' );

        parent::tearDown();
    }

    /**
     * `partially_refunded` is the payment status of a booking somebody already
     * settled: the operator refunded part of the money and deliberately kept
     * the rest as a cancellation fee. Sweep #2's `NOT IN` list did not name it,
     * so the sweep read that settled state as "still unpaid" and went after the
     * booking -- pickup date in the past, `confirmed`, money in the till.
     *
     * Two witnesses, because they fail for different reasons. The count says
     * the query selected the booking; RefundStatus says it got as far as
     * parking it. Only the second one distinguishes the fix under test (the
     * query) from Task 4's guard (which stops the money from moving after the
     * booking has already been selected) -- with the count alone, a K6 park
     * would look like a clean miss.
     */
    public function test_a_deliberately_part_refunded_confirmed_booking_past_pickup_is_not_swept(): void
    {
        $booking_id = $this->make_past_pickup_booking( 'partially_refunded', 'confirmed' );
        $order      = $this->create_paid_order_for_booking( $booking_id, '800' );

        $refunded_before = $order->get_total_refunded();

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame(
            0,
            $result['cancelled'],
            'A booking whose refund was already settled by hand is not an unpaid booking; sweep #2 has no'
                . ' business selecting it.'
        );
        $this->assertSame(
            '',
            RefundStatus::get( $booking_id ),
            'The query is the fix under test. If the booking reaches needs_review, it WAS selected and only'
                . " Task 4's K6 guard stopped it -- which is the second line of defence, not this one."
        );
        $this->assertSame( 'confirmed', get_post_meta( $booking_id, '_mhmrentiva_status', true ) );

        $order_after = wc_get_order( $order->get_id() );
        $this->assertSame( 'processing', $order_after->get_status() );
        $this->assertSame(
            $refunded_before,
            $order_after->get_total_refunded(),
            'No unattended path may move money.'
        );
    }

    /**
     * COMPLETED has exactly two outgoing edges in Status::can_transition() --
     * REFUNDED and IN_PROGRESS -- so a completed booking can never be
     * cancelled. Sweep #2 selected it anyway, cancel_booking_with_orders()
     * refused, and the whole thing repeated on the next tick and the tick
     * after that. The refusal is logged through AdvancedLogger::warning(),
     * which should_skip_log() drops under the default `mhmrentiva_log_level`
     * of 'error', so on a stock install the loop is completely silent.
     *
     * The log level is raised here precisely so that silence becomes visible:
     * without it the refusal leaves no trace and the second witness below
     * would pass no matter what the query did.
     *
     * payment_status is 'pending' rather than 'partially_refunded' on purpose:
     * it keeps this control measuring the booking-status half of the query
     * alone, so reverting only the `completed` entry still turns it red.
     */
    public function test_a_completed_booking_past_pickup_is_not_swept(): void
    {
        SettingsCore::set( 'mhmrentiva_log_level', 'warning' );

        $booking_id = $this->make_past_pickup_booking( 'pending', 'completed' );

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame(
            0,
            $result['cancelled'],
            'A completed booking has no CANCELLED edge, so selecting it can only ever end in a refusal.'
        );
        $this->assertSame( 'completed', get_post_meta( $booking_id, '_mhmrentiva_status', true ) );
        $this->assertFalse(
            $this->log_exists( 'Auto-cancel refused for booking #' . $booking_id ),
            'A refusal entry proves the booking was selected. That is the silent infinite loop: every tick'
                . ' picks it up, every tick refuses, and at the default log level nobody ever sees either half.'
        );
    }

    /**
     * The allowlist's own control: a status this plugin does not recognise.
     *
     * `_mhmrentiva_status` is free-form post meta -- among other writers,
     * `ajax_create_booking` stores whatever status the request sends with no
     * allowlist of its own -- so a value outside `Status::allowed()` is
     * reachable, and legacy data is the second way in.
     *
     * Under a `NOT IN` denylist the sweep read every unrecognised value as
     * "not one of the three I refuse" and went after the booking. What
     * happened next is the point: `Status::get()` coerces an unknown value to
     * PENDING, PENDING does have a CANCELLED edge, so `update_status()`
     * succeeded and OVERWROTE the value nobody could read. An unattended job
     * destroyed the only record of a state it did not understand.
     *
     * The allowlist inverts that default (spec v3 §7.2.3): selection is
     * limited to the statuses the transition matrix names. The meta value is
     * the witness -- if the sweep runs, `bogus_legacy_value` is gone.
     */
    public function test_a_booking_in_an_unrecognised_status_is_not_swept(): void
    {
        $booking_id = $this->make_past_pickup_booking( 'pending', 'bogus_legacy_value' );

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame(
            0,
            $result['cancelled'],
            'A destructive unattended sweep may not select a booking whose status it cannot describe.'
        );
        $this->assertSame(
            'bogus_legacy_value',
            get_post_meta( $booking_id, '_mhmrentiva_status', true ),
            'The sweep did not merely select the booking, it overwrote the unrecognised status with'
                . ' `cancelled`: Status::get() coerced it to PENDING, and PENDING has a CANCELLED edge.'
        );
    }

    /**
     * A booking in `needs_review` is one a human has been asked to look at,
     * and its booking status is deliberately left untouched -- which is
     * exactly what keeps sweep #2's query selecting it, tick after tick, while
     * that human works.
     *
     * The transition matrix already stops the second visit from doing damage
     * (`needs_review -> needs_review` returns false, so no notification and no
     * event: spec §7.2.2). The query bar is the cheap one and it goes in too,
     * because "harmless once it gets there" is not the same as "not selected"
     * -- as the foreign lock below shows.
     *
     * That lock is the witness. A refund the operator is running right now
     * holds the booking's lock, so the re-selection cannot even take it, and
     * Task 4's refusal branch writes an error-level entry every five minutes
     * for a booking whose state is entirely correct. The alarm that fires when
     * nothing is wrong is the one nobody reads when something is.
     */
    public function test_a_booking_already_in_needs_review_is_not_reselected(): void
    {
        $booking_id = $this->make_past_pickup_booking( 'pending', 'pending' );
        $order      = $this->create_paid_order_for_booking( $booking_id, '600' );

        $this->park_for_review( $booking_id );
        $this->plant_foreign_lock( $booking_id );

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame(
            0,
            $result['cancelled'],
            'A booking parked for human review is not a candidate for an unattended sweep.'
        );
        $this->assertFalse(
            $this->log_exists( 'Refund lock refused for booking #' . $booking_id ),
            'This entry only gets written if the sweep selected the booking and tried to park it again.'
        );
        $this->assertSame( RefundStatus::NEEDS_REVIEW, RefundStatus::get( $booking_id ) );
        $this->assertSame(
            'processing',
            wc_get_order( $order->get_id() )->get_status(),
            'No unattended path may move money.'
        );
    }

    /**
     * The same bar, on the other sweep. Sweep #1 selects on post_date and the
     * pending/pending meta pair, none of which a park changes either, so a
     * booking held for review is walked over by both sweeps -- and sweep #1 is
     * the one that runs on the cron schedule AND inside an admin page render
     * (VehicleColumns::maybe_run_autocancel()).
     *
     * Sweep #1 returns nothing, so the planted lock is the only witness here:
     * with it in place, a re-selection is forced to leave an error-level trace
     * it cannot leave if the booking was never selected.
     *
     * No pickup_date meta is set, which keeps sweep #2 (run() calls it last)
     * out of this measurement entirely -- its query requires that key.
     */
    public function test_a_booking_already_in_needs_review_is_not_reselected_by_the_deadline_sweep(): void
    {
        $booking_id = $this->make_expired_unpaid_booking();
        $order      = $this->create_paid_order_for_booking( $booking_id, '600' );

        $this->park_for_review( $booking_id );
        $this->plant_foreign_lock( $booking_id );

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        AutoCancel::run();

        $this->assertFalse(
            $this->log_exists( 'Refund lock refused for booking #' . $booking_id ),
            'Sweep #1 re-selected a booking a human already owns and logged an error about a lock it had no'
                . ' reason to want.'
        );
        $this->assertSame( RefundStatus::NEEDS_REVIEW, RefundStatus::get( $booking_id ) );
        $this->assertSame(
            'processing',
            wc_get_order( $order->get_id() )->get_status(),
            'No unattended path may move money.'
        );
    }

    /**
     * Task 12 (slice 5), correction #3: the barrier used to exclude only
     * NEEDS_REVIEW. A booking whose refund_status has already reached ANY
     * terminal state -- not_required, completed_externally, completed,
     * completed_manually -- is not a candidate for an unattended sweep
     * either: a terminal refund_status exists only because a human or a
     * flow already closed that booking's money question. Before the fix, a
     * booking an operator dismissed to `not_required` fell straight back
     * into this sweep's ordinary unpaid-and-past-deadline selection on the
     * very next tick, and cancel_booking_with_orders() found the paid order
     * again and tried `RefundStatus::transition( ..., NEEDS_REVIEW )` from a
     * terminal `$from` the matrix has no key for -- transition() returned
     * false and the whole notify/log block was skipped in total silence.
     *
     * Driven off RefundStatus::terminalStates() itself, not a literal list
     * restated here, so this test grows with the matrix instead of silently
     * missing a status the day a new terminal one is added.
     * RefundStatusTransitionTest pins that the derivation itself matches the
     * four states named in the matrix's own docblock; this is the
     * consumer-side proof that the sweep's query actually uses it.
     *
     * @dataProvider provide_every_terminal_refund_status
     */
    public function test_a_terminal_refund_status_past_pickup_is_not_swept( string $terminal_status ): void
    {
        $booking_id = $this->make_past_pickup_booking( 'pending', 'pending' );

        // Bypasses RefundStatus::transition() on purpose, matching
        // BookingRefundMetaBoxStatusRowTest's precedent: several of these
        // states have no single-hop edge from '', and this test is about
        // the sweep's own query, not the transition machine that lands a
        // booking here in production.
        update_post_meta( $booking_id, RefundStatus::META_KEY, $terminal_status );

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame(
            0,
            $result['cancelled'],
            "A booking whose refund_status is already terminal ({$terminal_status}) must not be selected by an unattended sweep."
        );
        $this->assertSame( 'pending', get_post_meta( $booking_id, '_mhmrentiva_status', true ) );
        $this->assertSame( $terminal_status, RefundStatus::get( $booking_id ) );

        // Also (controller ruling, fix round 1): not_parked_for_review() is
        // shared by TWO call sites -- sweep #2 above (:292), and sweep #1,
        // the deadline sweep (:230), via AutoCancel::run(). Everything above
        // only exercised sweep #2; this closes the other half with the same
        // data set, cheaply. make_expired_unpaid_booking() carries no
        // pickup_date, which keeps sweep #2 (run() calls it last) out of
        // this half of the measurement entirely -- its own docblock says so.
        $deadline_booking_id = $this->make_expired_unpaid_booking();
        update_post_meta( $deadline_booking_id, RefundStatus::META_KEY, $terminal_status );

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        AutoCancel::run();

        $this->assertSame(
            'pending',
            get_post_meta( $deadline_booking_id, '_mhmrentiva_status', true ),
            "Sweep #1 (the deadline sweep, :230) must also not select a booking whose refund_status is already terminal ({$terminal_status})."
        );
        $this->assertSame( $terminal_status, RefundStatus::get( $deadline_booking_id ) );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function provide_every_terminal_refund_status(): array
    {
        $cases = array();

        foreach ( RefundStatus::terminalStates() as $status ) {
            $cases[ $status ] = array( $status );
        }

        return $cases;
    }

    /**
     * T12-R3's own motivating scenario, exercised through the same
     * lock-and-transition mechanics DepositManagementAjax::review_dismiss()
     * itself uses: needs_review -> (operator dismisses) -> not_required must
     * not fall back into an ordinary unpaid-and-past-deadline selection.
     * Before the fix, `not_required` was a plain string to
     * not_parked_for_review()'s `!=` compare -- excluded from nothing -- and
     * the booking was reselected on the very next tick, silently, for the
     * reason the class docblock above explains.
     */
    public function test_a_booking_dismissed_out_of_needs_review_is_not_reselected(): void
    {
        $booking_id = $this->make_past_pickup_booking( 'pending', 'pending' );
        $order      = $this->create_paid_order_for_booking( $booking_id, '600' );

        $this->park_for_review( $booking_id );

        $this->assertTrue( RefundLock::acquire( $booking_id ) );
        $this->assertTrue(
            RefundStatus::transition( $booking_id, RefundStatus::NOT_REQUIRED, array( 'surface' => 'review_action' ) )
        );
        RefundLock::release( $booking_id );

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame(
            0,
            $result['cancelled'],
            'A booking an operator already closed as not_required must not be swept back into cancellation.'
        );
        $this->assertSame( RefundStatus::NOT_REQUIRED, RefundStatus::get( $booking_id ) );
        $this->assertSame(
            'processing',
            wc_get_order( $order->get_id() )->get_status(),
            'No unattended path may move money.'
        );
    }

    /**
     * `sync_orphan_wc_orders()` is a separate body with its own query and its
     * own cancel call, so K6 has to be stated there separately -- the guard
     * Task 4 put in cancel_booking_with_orders() never runs on this path.
     *
     * Its pre-cancel gate is `has_status(['pending','on-hold','failed',
     * 'processing'])`, and `processing` is the status of a typical PAID order.
     * So the backfill an operator runs by hand to tidy up stale orders could
     * cancel an order somebody had paid for. Measured, not theoretical.
     *
     * The same predicate answers this question in both places
     * (AutoCancel::is_paid()), rather than a second bare get_date_paid() --
     * two predicates answering one question is the defect class this slice
     * exists to remove.
     */
    public function test_sync_orphan_wc_orders_does_not_cancel_a_paid_order(): void
    {
        $booking_id = (int) self::factory()->post->create( array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ) );
        update_post_meta( $booking_id, '_mhmrentiva_status', 'cancelled' );

        $order           = $this->create_paid_order_for_booking( $booking_id, '450' );
        $refunded_before = $order->get_total_refunded();

        $result = AutoCancel::sync_orphan_wc_orders();

        $order_after = wc_get_order( $order->get_id() );
        $this->assertSame(
            'processing',
            $order_after->get_status(),
            'A backfill does not get to cancel an order somebody paid; `processing` is what a paid order'
                . ' normally looks like, and the old gate let it straight through.'
        );
        $this->assertSame(
            $refunded_before,
            $order_after->get_total_refunded(),
            'No unattended path may move money.'
        );
        $this->assertSame( 0, $result['cancelled'] );
        $this->assertSame( 1, $result['skipped'] );
    }

    /**
     * The allowlist's OTHER control: a status this plugin does recognise, and
     * which it stores as the empty string.
     *
     * `''` is not an unrecognised value, it is the absence of a value, and
     * every canonical reader in this plugin resolves it to `pending`:
     * `Status::get()` falls back to PENDING for anything outside
     * `Status::allowed()` (Status.php:39), `DashboardService::
     * get_status_breakdown()` buckets it with `COALESCE(NULLIF(meta_value,
     * ''), 'pending')`, and `BookingColumns` filters on the same priority.
     * A sweep that skipped it would be the one place in the plugin reading
     * this data differently from everywhere else.
     *
     * It is reachable, not hypothetical: the manual-booking form's own AJAX
     * payload reads `#mhmrentiva_manual_status`, an id that does not exist
     * (the select's id is `mhmrentiva_manual_booking_status`; only its `name`
     * matches), so jQuery sends `status=` and the handler stores `''`. A
     * manually created, unpaid booking whose pickup date has passed is the
     * exact case sweep #2 exists for, and sweep #1 cannot reach it -- its
     * clause is `IN ('pending','pending_payment')`. The id mismatch is a
     * separate, tracked defect; this test pins the sweep's half.
     */
    public function test_a_booking_with_an_empty_status_is_still_swept(): void
    {
        $booking_id = $this->make_past_pickup_booking( 'pending', '' );

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame(
            1,
            $result['cancelled'],
            'An empty `_mhmrentiva_status` reads as `pending` everywhere else in this plugin, so the sweep'
                . ' that exists to catch unpaid past-pickup bookings has to read it that way too.'
        );
        $this->assertSame(
            'cancelled',
            get_post_meta( $booking_id, '_mhmrentiva_status', true ),
            'Selection alone is not the contract: the sweep has to reach Status::update_status(), which'
                . ' resolves the empty value to PENDING and finds the CANCELLED edge.'
        );
    }

    /**
     * The too-narrow direction. Every assertion above is satisfied by a sweep
     * that selects nothing at all, so without this one the whole file could go
     * green on a query that had been broken outright -- which is the failure
     * mode a negative-control suite invites.
     *
     * `AutoCancelOrderKeyLookupTest::test_sync_orphan_uses_woocommerce_order_id_meta`
     * is the matching guard for the sync_orphan half: it backfills an unpaid
     * `on-hold` order, so an is_paid() that answered true too often turns it red.
     */
    public function test_a_genuinely_unpaid_past_pickup_booking_is_still_swept(): void
    {
        $booking_id = $this->make_past_pickup_booking( 'pending', 'pending' );

        $order = wc_create_order( array( 'status' => 'pending' ) );
        $order->set_total( '250.00' );
        $order->save();
        update_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id() );

        $result = AutoCancel::sync_stale_past_bookings();

        $this->assertSame( 1, $result['cancelled'] );
        $this->assertSame( 'cancelled', get_post_meta( $booking_id, '_mhmrentiva_status', true ) );
        $this->assertSame( 'cancelled', wc_get_order( $order->get_id() )->get_status() );
    }

    /**
     * Park the booking the way production does -- through the lock and the
     * transition matrix -- so the meta value under test is the one
     * RefundStatus actually writes, not a string this file made up.
     */
    private function park_for_review( int $booking_id ): void
    {
        $this->assertTrue( RefundLock::acquire( $booking_id ) );
        $this->assertTrue(
            RefundStatus::transition( $booking_id, RefundStatus::NEEDS_REVIEW, array( 'surface' => 'test_fixture' ) )
        );
        RefundLock::release( $booking_id );
    }

    /**
     * A lock row this request does not own, stamped now so RefundLock cannot
     * steal it as stale (TTL is 300s). acquire() therefore refuses, and Task
     * 4's refusal branch writes the error-level entry these tests watch for.
     */
    private function plant_foreign_lock( int $booking_id ): void
    {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            'mhmrentiva_refund_lock_' . $booking_id,
            'someone-else:' . time()
        ) );
    }

    private function log_exists( string $needle ): bool
    {
        $logs = get_posts( array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );

        foreach ( $logs as $log ) {
            if ( str_contains( $log->post_content, $needle ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * A booking sweep #2 selects on: pickup_date a week in the past, plus the
     * payment_status / booking status pair the caller wants to measure.
     */
    private function make_past_pickup_booking( string $payment_status, string $status ): int
    {
        $booking_id = (int) self::factory()->post->create( array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ) );

        update_post_meta( $booking_id, '_mhmrentiva_pickup_date', wp_date( 'Y-m-d', strtotime( '-7 days' ) ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_status', $payment_status );
        update_post_meta( $booking_id, '_mhmrentiva_status', $status );

        return $booking_id;
    }

    /**
     * A booking sweep #1 selects on: post_date backdated two hours, past the
     * default 30-minute payment deadline, with the pending/pending meta pair
     * that sweep's meta_query requires. No pickup_date, so sweep #2 -- which
     * run() calls last -- cannot see it.
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
