<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Booking;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use WP_UnitTestCase;

/**
 * Customer self-cancellation: who owns a booking.
 *
 * CancellationHandler resolves ownership from post meta twice — once in
 * user_can_cancel(), which is the gate that decides whether the Cancel button,
 * the deadline message and the refund-policy panel render at all, and once in
 * cancel_booking(), which authorises the operation itself. Both read
 * `_mhmrentiva_customer_id`.
 *
 * Nothing writes that key. Not in Lite, not in the add-on. The key every writer
 * actually uses is `_mhmrentiva_customer_user_id`
 * (BookingPortalMetaBox.php:152/191, ManualBookingMetaBox.php:784,
 * WooCommerceBridge.php:1144), so both reads resolve to 0, every comparison
 * against a real user ID fails, and the only branch that ever succeeds is the
 * `manage_options` one. A customer has never been able to cancel their own
 * booking.
 *
 * It is not an August rename regression: the pre-rename reader carried the old
 * prefix and had no writer either. (The literal is deliberately not spelled out
 * here — bin/check-prefix-inventory.php scans for old identifiers as text and
 * cannot tell a historical mention in a docblock from a live one in code, so
 * writing it would keep that gate red for a sentence nobody executes.) The
 * feature has never run, so there is nothing to migrate — the fix is to read
 * the key that is written.
 *
 * There were no tests for this class at all, which is why a phantom key survived
 * a full prefix rename and several review rounds. These are the first.
 */
final class CancellationOwnershipTest extends WP_UnitTestCase
{
    private int $customer_id;
    private int $stranger_id;
    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer_id = (int) self::factory()->user->create(array('role' => 'customer'));
        $this->stranger_id = (int) self::factory()->user->create(array('role' => 'customer'));
        $this->booking_id  = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));

        // The key every writer in both repos actually uses.
        update_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', $this->customer_id);

        update_post_meta($this->booking_id, '_mhmrentiva_status', Status::CONFIRMED);

        // Well clear of the cancellation deadline (default 24 h before pickup),
        // so nothing but ownership can decide these assertions.
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+30 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_time', '10:00');

        // cancel_booking() releases the vehicle's availability after the
        // permission check and errors out without these. They are fixture, not
        // subject: without them the operation test would fail one stage past
        // the thing it is measuring.
        update_post_meta(
            $this->booking_id,
            '_mhmrentiva_vehicle_id',
            self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'))
        );
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+34 days')));
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }

    // --- user_can_cancel(): the gate that renders the button ----------------

    public function test_the_owner_of_a_booking_may_cancel_it(): void
    {
        wp_set_current_user($this->customer_id);

        $this->assertTrue(
            CancellationHandler::user_can_cancel($this->booking_id, $this->customer_id),
            'The customer the booking belongs to must be allowed to cancel it.'
        );
    }

    public function test_another_customer_may_not_cancel_someone_elses_booking(): void
    {
        wp_set_current_user($this->stranger_id);

        $this->assertFalse(
            CancellationHandler::user_can_cancel($this->booking_id, $this->stranger_id),
            'A different customer must not be able to cancel this booking.'
        );
    }

    public function test_an_administrator_may_always_cancel(): void
    {
        $admin_id = (int) self::factory()->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);

        $this->assertTrue(CancellationHandler::user_can_cancel($this->booking_id, $admin_id));
    }

    public function test_the_gate_reads_the_key_that_is_actually_written(): void
    {
        // The regression that this whole file exists for. A booking carrying only
        // the phantom key — the shape the code used to look for — must NOT make
        // its holder an owner, because no writer produces that shape. If this
        // ever passes, someone has reintroduced the dead key.
        $phantom_booking = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($phantom_booking, '_mhmrentiva_customer_id', $this->customer_id);
        update_post_meta($phantom_booking, '_mhmrentiva_status', Status::CONFIRMED);
        update_post_meta($phantom_booking, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+30 days')));
        update_post_meta($phantom_booking, '_mhmrentiva_pickup_time', '10:00');

        wp_set_current_user($this->customer_id);

        $this->assertFalse(
            CancellationHandler::user_can_cancel($phantom_booking, $this->customer_id),
            'Ownership must not be readable from a key nothing writes.'
        );
    }

    // --- cancel_booking(): the operation ------------------------------------

    public function test_the_owner_can_actually_cancel(): void
    {
        wp_set_current_user($this->customer_id);

        $result = CancellationHandler::cancel_booking($this->booking_id, $this->customer_id, 'change of plans');

        $this->assertNotWPError($result, 'The owner must be able to complete the cancellation.');
        $this->assertSame(Status::CANCELLED, Status::get($this->booking_id));
    }

    public function test_a_stranger_is_refused_with_permission_denied(): void
    {
        wp_set_current_user($this->stranger_id);

        $result = CancellationHandler::cancel_booking($this->booking_id, $this->stranger_id, 'not mine');

        $this->assertWPError($result);
        $this->assertSame('permission_denied', $result->get_error_code());
        $this->assertSame(
            Status::CONFIRMED,
            Status::get($this->booking_id),
            'A refused cancellation must leave the booking untouched.'
        );
    }
}
