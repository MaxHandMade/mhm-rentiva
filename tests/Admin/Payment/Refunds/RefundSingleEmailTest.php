<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayDouble;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayRegistration;
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
    use WooCommerceRefundGatewayRegistration;

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

    /**
     * The customer mail's actual HTML body -- what wp_mail() is asked to
     * send, not $context. A test that only inspects $context (as this file's
     * earlier mode test does) cannot notice a template that never references
     * the token carrying that value; this is the rendered-output half.
     */
    private function capture_customer_body(callable $operation): string
    {
        $body = '';

        $capture = static function (array $args) use (&$body): array {
            if (($args['to'] ?? '') === 'customer@example.test') {
                $body = (string) ($args['message'] ?? '');
            }
            return $args;
        };

        add_filter('wp_mail', $capture, 999);
        $operation();
        remove_filter('wp_mail', $capture, 999);

        return $body;
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

    /**
     * The context-array test above (test_the_operation_mode_reaches_the_notification)
     * proves the mode value is computed and filtered correctly. It cannot
     * prove a customer ever sees a sentence about it, because RefundNotifications::notify()
     * puts mode_text into $context whether or not any template references
     * that token -- and until this test existed, nothing did. This asserts
     * against the rendered HTML body wp_mail() is actually asked to send,
     * for both an auto-capable leg and a manual one, and checks each body
     * contains its own sentence and NOT the other mode's.
     */
    public function test_the_rendered_customer_body_names_the_mode(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->register_refund_gateway_double();
        try {
            $order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
            $order->save();

            $autoBody = $this->capture_customer_body(function (): void {
                Service::process($this->booking_id, Money::toMinor('20'), 'auto body check');
            });
        } finally {
            $this->unregister_refund_gateway_double();
        }

        $this->assertStringContainsString(
            __('The refund has been sent back to your original payment method.', 'mhm-rentiva'),
            $autoBody,
            'An auto-capable leg must produce the auto sentence in the actual rendered body.'
        );
        $this->assertStringNotContainsString(
            __('The refund will be transferred to you manually; it will not appear on your original payment method automatically.', 'mhm-rentiva'),
            $autoBody
        );

        $manual_booking = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($manual_booking, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($manual_booking, '_mhmrentiva_contact_email', 'customer@example.test');

        $manual_order = $this->create_paid_order_for_booking($manual_booking, '120');
        $manual_order->set_payment_method('mhm_no_such_gateway');
        $manual_order->save();

        $manualBody = $this->capture_customer_body(function () use ($manual_booking): void {
            Service::process($manual_booking, Money::toMinor('20'), 'manual body check');
        });

        $this->assertStringContainsString(
            __('The refund will be transferred to you manually; it will not appear on your original payment method automatically.', 'mhm-rentiva'),
            $manualBody,
            'A gateway that cannot refund must produce the manual sentence in the actual rendered body.'
        );
        $this->assertStringNotContainsString(
            __('The refund has been sent back to your original payment method.', 'mhm-rentiva'),
            $manualBody
        );
    }

    public function test_a_refund_made_from_the_woocommerce_screen_still_gets_its_mail(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        $sent       = 0;
        $manualBody = '';

        $counter = static function (array $args) use (&$sent, &$manualBody): array {
            ++$sent;
            if (($args['to'] ?? '') === 'customer@example.test') {
                $manualBody = (string) ($args['message'] ?? '');
            }
            return $args;
        };

        add_filter('wp_mail', $counter, 999);
        // No Service involved: this is what the WooCommerce admin does when
        // the operator picks "Refund manually" on the order screen -- no
        // money is sent through the gateway.
        wc_create_refund(array(
            'order_id'       => $order->get_id(),
            'amount'         => 20,
            'refund_payment' => false,
        ));
        remove_filter('wp_mail', $counter, 999);

        $this->assertSame(2, $sent, 'With no operation in flight the hook owns the mail.');
        $this->assertStringContainsString(
            __('The refund will be transferred to you manually; it will not appear on your original payment method automatically.', 'mhm-rentiva'),
            $manualBody,
            'refund_payment => false is the operator choosing "Refund manually" -- the mail must say '
                . 'so, not silently default to the auto sentence.'
        );

        // The other direction: the operator picks "Refund via gateway"
        // instead. A second, independent booking/order keeps this free of
        // the first refund's remaining-balance bookkeeping.
        $auto_booking = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($auto_booking, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($auto_booking, '_mhmrentiva_contact_email', 'customer@example.test');

        $autoBody = '';

        $this->register_refund_gateway_double();
        try {
            $auto_order = $this->create_paid_order_for_booking($auto_booking, '120');
            $auto_order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
            $auto_order->save();

            $autoBody = $this->capture_customer_body(static function () use ($auto_order): void {
                wc_create_refund(array(
                    'order_id'       => $auto_order->get_id(),
                    'amount'         => 20,
                    'refund_payment' => true,
                ));
            });
        } finally {
            $this->unregister_refund_gateway_double();
        }

        $this->assertStringContainsString(
            __('The refund has been sent back to your original payment method.', 'mhm-rentiva'),
            $autoBody,
            'refund_payment => true is the operator choosing "Refund via gateway" -- the mail must say '
                . 'the money is already on its way back, not default to manual either.'
        );
        $this->assertNotSame(
            $manualBody,
            $autoBody,
            'The two operator choices on the WooCommerce screen must produce visibly different mails.'
        );
    }

    /**
     * Slice-3 phase-close audit, item 2. Before the fix,
     * WooCommerceBridge::handle_order_refunded() passed $recorded_refunded --
     * the booking's CUMULATIVE refunded total -- into a template whose
     * sentence describes a single event ("A refund of {amount} has been
     * processed"). Paid 100, refund 40 from the WooCommerce order screen
     * (mail correctly says 40), refund 30 more (mail said 70, the running
     * total, for an event in which only 30 moved). This refunds the same
     * order twice and asserts the second mail names ITS OWN amount.
     */
    public function test_a_second_woocommerce_screen_refund_names_its_own_amount_not_the_running_total(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');
        $order = $this->create_paid_order_for_booking($this->booking_id, '100');

        $firstBody = $this->capture_customer_body(function () use ($order): void {
            wc_create_refund(array(
                'order_id'       => $order->get_id(),
                'amount'         => 40,
                'refund_payment' => false,
            ));
        });

        $currency = $order->get_currency();
        $forty    = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('40')),
            \MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
            $currency
        );
        $thirty = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('30')),
            \MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
            $currency
        );
        $seventy = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('70')),
            \MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
            $currency
        );

        $this->assertStringContainsString($forty, $firstBody, 'The first refund must name its own amount, 40.');

        $secondBody = $this->capture_customer_body(function () use ($order): void {
            wc_create_refund(array(
                'order_id'       => $order->get_id(),
                'amount'         => 30,
                'refund_payment' => false,
            ));
        });

        $this->assertStringContainsString(
            $thirty,
            $secondBody,
            'The second mail must name this refund event\'s own amount, 30.'
        );
        $this->assertStringNotContainsString(
            $seventy,
            $secondBody,
            'The second mail must not name the cumulative running total (40 + 30 = 70) -- that figure '
                . 'describes the booking\'s history, not what moved in this event.'
        );
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

    public function test_a_mid_walk_failure_still_mails_once_for_what_actually_moved(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');

        $this->create_paid_order_for_booking($this->booking_id, '30');
        $second = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $second->get_id());

        // Force the second leg's wc_create_refund() to fail. Throwing from
        // woocommerce_refund_created at priority 1 -- before
        // WooCommerceBridge::handle_order_refunded() at its own priority 10 --
        // lands inside wc_create_refund()'s own try/catch (wc-order-functions.php
        // :741-747, WC 11.0.1), which deletes the half-built refund and returns
        // a WP_Error instead of ever letting this leg's refund exist. The first
        // order is untouched by this filter, so its refund goes through clean.
        add_action(
            'woocommerce_refund_created',
            function (int $refund_id, array $args) use ($second): void {
                if ((int) ($args['order_id'] ?? 0) === $second->get_id()) {
                    throw new \RuntimeException('gateway exploded on the second leg');
                }
            },
            1,
            2
        );

        $captured = null;
        add_action(
            'mhmrentiva_email_sent',
            static function (string $key, string $to, bool $ok, string $subject, array $context) use (&$captured): void {
                if ('refund_customer' === $key) {
                    $captured = $context['amount'];
                }
            },
            10,
            5
        );

        $sent = $this->count_mails(function (): void {
            // 30 drains the first order in full; the requested 100 needs the
            // second order too, and that leg is the one made to fail.
            $result = Service::process($this->booking_id, Money::toMinor('100'), 'mid-walk failure');

            $this->assertSame(
                '0',
                $result['mhmrentiva_refund'],
                'The operation as a whole must report failure -- the second leg never refunded.'
            );
        });

        $this->assertSame(
            2,
            $sent,
            'Money moved on the first leg; the customer must hear about it exactly once, not zero times.'
        );

        $currency = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($this->booking_id)->currency() ?: 'TRY';

        $movedAmount = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('30')),
            \MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
            $currency
        );
        $requestedAmount = \MHMRentiva\Admin\Core\CurrencyHelper::format_price(
            (float) Money::toMajor(Money::toMinor('100')),
            \MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals(),
            $currency
        );

        $this->assertSame(
            $movedAmount,
            $captured,
            'The mail must name what actually moved (30), not what was requested (100).'
        );
        $this->assertNotSame($requestedAmount, $captured);
    }
}
