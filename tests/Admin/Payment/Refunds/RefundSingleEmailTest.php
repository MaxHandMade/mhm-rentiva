<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * N-02, first half: the in-flight flag.
 *
 * Two rules from spec §5.2 are part of the contract, not implementation
 * detail:
 *
 * (a) the flag is scoped per booking, not globally -- a cron run can refund
 *     several bookings in one request and a global flag would swallow the
 *     other bookings' e-mails;
 * (b) it is released in finally -- if an exception escapes without clearing
 *     it, every later refund in the same request goes out silently.
 */
final class RefundSingleEmailTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
    }

    public function test_the_flag_is_raised_during_the_operation_and_only_for_this_booking(): void
    {
        $other = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        $this->create_paid_order_for_booking($this->booking_id, '120');

        $seen = array();

        add_action(
            'woocommerce_refund_created',
            function () use ($other, &$seen): void {
                $seen['self']  = Service::isRefundInFlight($this->booking_id);
                $seen['other'] = Service::isRefundInFlight($other);
            },
            5,
            2
        );

        Service::process($this->booking_id, Money::toMinor('20'), 'flag scope');

        $this->assertTrue($seen['self'], 'The flag must be up while this booking is being refunded.');
        $this->assertFalse($seen['other'], 'A second booking in the same request must not be flagged.');
    }

    public function test_the_flag_is_lowered_when_the_operation_ends(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::process($this->booking_id, Money::toMinor('20'), 'flag release');

        $this->assertFalse(Service::isRefundInFlight($this->booking_id));
    }

    public function test_the_flag_is_lowered_even_when_the_operation_throws(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        // The throw has to happen where Service can see it. An exception raised
        // from woocommerce_refund_created would NOT work: that action fires
        // inside wc_create_refund()'s own try/catch (wc-order-functions.php
        // :745-750, WC 11.0.1), which swallows it and returns a WP_Error --
        // Service would never unwind and this test would pass without ever
        // exercising the finally block.
        //
        // woocommerce_order_get_total is read by PaymentState::forBooking(),
        // which Service calls directly. That is inside Service's try and
        // outside WooCommerce's.
        $escaped = false;

        add_filter(
            'woocommerce_order_get_total',
            static function (): void {
                throw new \RuntimeException('gateway exploded mid-operation');
            }
        );

        try {
            Service::process($this->booking_id, Money::toMinor('20'), 'flag finally');
        } catch (\RuntimeException $e) {
            $escaped = true;
        }

        $this->assertTrue(
            $escaped,
            'Negative control: if nothing threw, this test proves nothing about finally.'
        );
        $this->assertFalse(
            Service::isRefundInFlight($this->booking_id),
            'A flag left up by an exception silences every later refund in the same request.'
        );
    }
}
