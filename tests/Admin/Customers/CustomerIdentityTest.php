<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use WP_UnitTestCase;

/**
 * WordPress.org T8 #2 — the customer REST endpoints accepted any user ID and
 * gated only on the blanket delete_users / edit_users capability, so "delete
 * these customers" would happily delete an editor or a second administrator.
 *
 * The fix needs a definition of customer, and the plugin did not have one. The
 * Customers screen's own query starts FROM wp_users and LEFT JOINs the bookings,
 * so it filters nothing: every account except ID 1 and the login literally named
 * 'admin' shows up there. Mirroring that list would have produced a guard that
 * lets exactly the accounts through that the review is about.
 *
 * So this is the definition, and it is deliberately the union of the two ways a
 * customer can come into existence in this plugin:
 *   - a booking points at them (_mhmrentiva_customer_user_id, or
 *     _mhmrentiva_customer_email matching their account email), or
 *   - Rentiva itself created/managed the account and left its own user meta on
 *     it (AddCustomerPage and BookingPortalMetaBox write mhmrentiva_phone /
 *     mhmrentiva_address).
 *
 * The second arm is not optional: an admin who adds a customer by hand has an
 * account with no bookings yet, and a guard built on bookings alone would make
 * that customer permanently undeletable.
 */
final class CustomerIdentityTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The per-request memo outlives a test: WP_UnitTestCase rolls its
        // transaction back but MySQL does not rewind AUTO_INCREMENT, so today
        // the IDs happen not to collide and every test passes for a reason that
        // is not the one under test. Clear it so a future ID reuse fails loudly
        // instead of reading a previous test's answer.
        CustomerIdentity::flush_memo();
    }

    public function test_a_user_a_booking_points_at_by_id_is_a_customer(): void
    {
        $user_id    = self::factory()->user->create(array('role' => 'subscriber'));
        $booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $user_id);

        $this->assertTrue(CustomerIdentity::is_customer($user_id));
    }

    public function test_a_user_a_booking_points_at_by_email_is_a_customer(): void
    {
        $user_id    = self::factory()->user->create(array('user_email' => 'renter@example.com'));
        $booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($booking_id, '_mhmrentiva_customer_email', 'renter@example.com');

        $this->assertTrue(CustomerIdentity::is_customer($user_id));
    }

    public function test_a_rentiva_created_account_with_no_bookings_is_a_customer(): void
    {
        // AddCustomerPage's shape: the account exists because someone typed it
        // into Rentiva's own "Add Customer" screen, and has not booked yet.
        $user_id = self::factory()->user->create(array('role' => 'subscriber'));
        update_user_meta($user_id, 'mhmrentiva_phone', '+90 555 000 0000');

        $this->assertTrue(CustomerIdentity::is_customer($user_id));
    }

    public function test_an_account_wearing_the_customer_role_is_a_customer(): void
    {
        // The marker AddCustomerPage assigns, and the one three older REST test
        // files have been building their targets with since earlier review
        // rounds. Without this arm the guard refuses to delete a customer who
        // was created through Rentiva's own screen and has not booked yet.
        $user_id = self::factory()->user->create(array('role' => 'customer'));

        $this->assertTrue(CustomerIdentity::is_customer($user_id));
    }

    public function test_an_administrator_with_no_rentiva_footprint_is_not_a_customer(): void
    {
        // The account the review is about: a real WordPress user, not ID 1, whose
        // login is not 'admin', and which the Customers screen therefore lists.
        $user_id = self::factory()->user->create(
            array(
                'role'       => 'administrator',
                'user_login' => 'site_owner',
            )
        );

        $this->assertFalse(CustomerIdentity::is_customer($user_id));
    }

    public function test_an_editor_with_no_rentiva_footprint_is_not_a_customer(): void
    {
        $user_id = self::factory()->user->create(array('role' => 'editor'));

        $this->assertFalse(CustomerIdentity::is_customer($user_id));
    }

    public function test_a_trashed_booking_does_not_make_its_user_a_customer(): void
    {
        $user_id    = self::factory()->user->create(array('role' => 'subscriber'));
        $booking_id = self::factory()->post->create(
            array(
                'post_type'   => 'mhmrentiva_booking',
                'post_status' => 'trash',
            )
        );
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $user_id);

        $this->assertFalse(CustomerIdentity::is_customer($user_id));
    }

    public function test_a_nonexistent_user_is_not_a_customer(): void
    {
        $this->assertFalse(CustomerIdentity::is_customer(0));
        $this->assertFalse(CustomerIdentity::is_customer(999999));
    }

    public function test_the_email_arm_does_not_match_an_empty_account_email(): void
    {
        // Guard against the degenerate join: a booking row whose customer email
        // meta is empty must not turn every account with an empty email into a
        // customer. Empty-string equality is exactly how a permissive guard
        // silently becomes no guard at all.
        $user_id    = self::factory()->user->create(array('role' => 'subscriber'));
        $booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($booking_id, '_mhmrentiva_customer_email', '');

        $this->assertFalse(CustomerIdentity::is_customer($user_id));
    }
}
