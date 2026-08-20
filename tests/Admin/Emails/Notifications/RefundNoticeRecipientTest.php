<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Notifications;

use MHMRentiva\Admin\Emails\Notifications\RefundNotifications;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use WP_UnitTestCase;

/**
 * Addition A, measured on the dev database: of 33 bookings, 28 carry
 * _mhmrentiva_customer_email and exactly ONE carries _mhmrentiva_contact_email
 * -- the key notify() reads. The only writer of that key in the tree
 * (ContactForm:530) writes it on a contact message, not on a booking.
 *
 * So Slice 3's "exactly one customer e-mail per operation" was, in practice,
 * zero e-mails, and the miss was silent: `if ( $email )` with no else. This
 * slice is the one that makes refunds actually happen, so it is the one that
 * has to make the notice arrive.
 */
final class RefundNoticeRecipientTest extends WP_UnitTestCase
{
    private int $booking_id;

    /** @var array<int, array<string, mixed>> */
    private array $sent = array();

    public function setUp(): void
    {
        parent::setUp();

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        $this->sent = array();

        add_filter(
            'wp_mail',
            function (array $args): array {
                $this->sent[] = $args;

                return $args;
            }
        );
    }

    /**
     * @return array<int, string>
     */
    private function recipients(): array
    {
        $to = array();

        foreach ($this->sent as $mail) {
            foreach ((array) $mail['to'] as $address) {
                $to[] = strtolower((string) $address);
            }
        }

        return $to;
    }

    public function test_the_notice_goes_to_the_key_bookings_actually_carry(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_customer_email', 'renter@example.test');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_name', 'Renter');

        RefundNotifications::notify($this->booking_id, Money::toMinor('20'), 'USD', 'partially_refunded');

        $this->assertContains(
            'renter@example.test',
            $this->recipients(),
            'The customer notice must reach the address the booking actually stores.'
        );
    }

    public function test_the_legacy_contact_key_still_works(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'legacy@example.test');

        RefundNotifications::notify($this->booking_id, Money::toMinor('20'), 'USD', 'partially_refunded');

        $this->assertContains(
            'legacy@example.test',
            $this->recipients(),
            'The resolver falls back to the old key; this task widens, it does not swap.'
        );
    }

    public function test_a_booking_with_no_address_leaves_a_record(): void
    {
        RefundNotifications::notify($this->booking_id, Money::toMinor('20'), 'USD', 'partially_refunded');

        $logs = get_posts(array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => 5,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ));

        $found = false;
        foreach ($logs as $log) {
            if (false !== strpos($log->post_content, 'no customer address')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'A refund the customer is never told about must not be an invisible outcome.'
        );
    }
}
