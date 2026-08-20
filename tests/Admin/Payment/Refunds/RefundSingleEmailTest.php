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

    /**
     * @return int number of mails wp_mail was asked to send
     */
    private function count_mails(callable $operation): int
    {
        $count = 0;

        $counter = static function (array $args) use (&$count): array {
            ++$count;
            return $args;
        };

        add_filter('wp_mail', $counter, 999);
        $operation();
        remove_filter('wp_mail', $counter, 999);

        return $count;
    }

    public function test_a_service_driven_refund_sends_one_customer_mail_not_two(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $sent = $this->count_mails(function (): void {
            Service::process($this->booking_id, Money::toMinor('20'), 'one mail');
        });

        // One customer mail + one admin mail = 2. Before this task the hook
        // and the service each sent both, so the figure was 4.
        $this->assertSame(2, $sent, 'The hook must stay silent while Service owns the operation.');
    }

    public function test_a_refund_made_from_the_woocommerce_screen_still_gets_its_mail(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        $sent = $this->count_mails(static function () use ($order): void {
            // No Service involved: this is what the WooCommerce admin does.
            wc_create_refund(array(
                'order_id'       => $order->get_id(),
                'amount'         => 20,
                'refund_payment' => false,
            ));
        });

        $this->assertSame(2, $sent, 'With no operation in flight the hook owns the mail.');
    }

    public function test_the_operation_mode_reaches_the_notification(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');
        $order->set_payment_method('mhm_no_such_gateway');
        $order->save();

        $modes = array();

        add_filter(
            'mhmrentiva_refund_notification_mode',
            static function (string $mode) use (&$modes): string {
                $modes[] = $mode;
                return $mode;
            },
            10,
            1
        );

        Service::process($this->booking_id, Money::toMinor('20'), 'mode delivery');

        $this->assertSame(
            array( \MHMRentiva\Admin\Payment\Refunds\RefundValidator::MODE_MANUAL ),
            $modes,
            'An order whose gateway cannot refund produces a manual-mode message.'
        );
    }
}
