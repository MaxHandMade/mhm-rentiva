<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Core\MetaKeys;
use WP_UnitTestCase;

/**
 * Task 5 of the customer-booking-link tour. Tasks 1-4 moved every query on
 * the Customers admin screen onto CustomerIdentity's shared ownership
 * predicate (a booking linked to the account by user ID counts, not just one
 * carrying the account's e-mail). The Customers row's "View Bookings" link
 * was left behind: it sends `?mhmrentiva_customer_email=...` to this screen,
 * and BookingColumns::apply_custom_filters() still matched that parameter
 * against the e-mail meta alone. Without this fix the two screens now
 * disagree: the Customers row counts a user-id-linked booking, "View
 * Bookings" does not show it.
 *
 * These tests exercise apply_custom_filters() itself -- not
 * CustomerIdentity::meta_query_owned_by() in isolation -- by building a real
 * WP_Query the way the sibling BookingStatusChipsTest does for
 * apply_status_filter() (same is_admin()+is_main_query() guard): a
 * set_current_screen() admin context, a WP_Query wired as the main query via
 * $GLOBALS['wp_the_query']/['wp_query'], the query var set the way the
 * "View Bookings" link actually sends it, then the filter method invoked
 * directly and its resulting meta_query executed through get_posts() to see
 * which bookings actually match.
 */
final class CustomerEmailFilterOwnershipTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        BookingColumns::register();
    }

    public function tearDown(): void
    {
        set_current_screen('front');
        parent::tearDown();
    }

    private function createBooking(): int
    {
        return self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
    }

    /**
     * @return array{0: \WP_Query, 1: string} The wired query and the URL's
     *         e-mail parameter for the caller's own assertions.
     */
    private function runFilterFor(string $email): \WP_Query
    {
        set_current_screen('edit-mhmrentiva_booking');

        $q = new \WP_Query();
        $q->parse_query(array('post_type' => 'mhmrentiva_booking'));
        $q->set('mhmrentiva_customer_email', $email);
        $GLOBALS['wp_the_query'] = $q;
        $GLOBALS['wp_query']     = $q;

        BookingColumns::apply_custom_filters($q);

        return $q;
    }

    private function matchingBookingIds(\WP_Query $q): array
    {
        return get_posts(array(
            'post_type'      => 'mhmrentiva_booking',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $q->get('meta_query'),
        ));
    }

    /**
     * The new behaviour: the link's e-mail resolves to a WordPress account,
     * and a booking linked to that account by user ID -- not just one
     * carrying its e-mail in booking meta -- must show up. Pre-fix, this
     * booking is invisible: the filter only ever built an e-mail meta
     * clause, and this booking carries no such meta.
     */
    public function test_view_bookings_link_finds_a_booking_linked_only_by_user_id(): void
    {
        $account = self::factory()->user->create(array('user_email' => 'alice@example.test'));

        $linkedByUserId = $this->createBooking();
        update_post_meta($linkedByUserId, MetaKeys::BOOKING_CUSTOMER_USER_ID, (string) $account);

        $linkedByEmail = $this->createBooking();
        update_post_meta($linkedByEmail, MetaKeys::BOOKING_CUSTOMER_EMAIL, 'alice@example.test');

        $unrelated = $this->createBooking();
        update_post_meta($unrelated, MetaKeys::BOOKING_CUSTOMER_EMAIL, 'someone-else@example.test');

        $q     = $this->runFilterFor('alice@example.test');
        $found = $this->matchingBookingIds($q);

        $this->assertContains($linkedByUserId, $found, 'Booking linked only by user ID must match once the e-mail resolves to an account.');
        $this->assertContains($linkedByEmail, $found, 'Booking linked by e-mail meta must still match.');
        $this->assertNotContains($unrelated, $found, 'A booking for a different e-mail must not match.');
    }

    /**
     * The preserved path: no WordPress account carries this e-mail (a guest
     * booking, or a stale/typo address) -- the filter falls back to exactly
     * today's behaviour, matching on the e-mail meta alone. Resolving the
     * account must never widen or narrow this case.
     */
    public function test_no_matching_account_falls_back_to_email_only_match(): void
    {
        $this->assertFalse(get_user_by('email', 'guest@example.test'), 'Fixture assumption: no account has this e-mail.');

        $guestBooking = $this->createBooking();
        update_post_meta($guestBooking, MetaKeys::BOOKING_CUSTOMER_EMAIL, 'guest@example.test');

        $otherBooking = $this->createBooking();
        update_post_meta($otherBooking, MetaKeys::BOOKING_CUSTOMER_EMAIL, 'other@example.test');

        $q     = $this->runFilterFor('guest@example.test');
        $found = $this->matchingBookingIds($q);

        $this->assertContains($guestBooking, $found);
        $this->assertNotContains($otherBooking, $found);
        $this->assertCount(1, $found, 'Guest path must match on e-mail alone, nothing more.');
    }
}
