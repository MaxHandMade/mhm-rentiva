<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Spec §4.4: amounts are derived, statuses are stored.
 *
 * The point of this test is behavioural, not textual: with the retired key
 * unwritten, a surface that still reads it reports 0. Before this task a
 * refund e-mail said "amount paid: 0.00" next to a real refunded figure --
 * two contradicting numbers on one screen, which is the class
 * feedback_semantics_consistency_audit exists for.
 *
 * The two SQL sort keys in BookingColumns are NOT bound here and cannot be:
 * a derived value has no column to ORDER BY. Spec §4.4's decision moves that
 * sort to _mhmrentiva_total_price.
 */
final class PaymentAmountMetaRetiredTest extends WP_UnitTestCase
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
        $this->create_paid_order_for_booking($this->booking_id, '120');
    }

    public function test_the_email_context_reports_the_money_that_actually_arrived(): void
    {
        // build_context( string $key, int $booking_id ) -- the key comes first.
        $ctx = EmailTemplates::build_context('refund_customer', $this->booking_id);

        $this->assertSame(
            Money::toMinor('120'),
            $ctx['booking']['payment']['amount'],
            'Reading the retired key here reports 0 for every booking on every site.'
        );
        $this->assertSame(120.0, $ctx['booking']['total_price']);
    }

    public function test_the_refund_mail_body_no_longer_formats_a_zero(): void
    {
        // build_context()'s refund_customer / refund_admin branch formats
        // $ctx['booking']['payment']['amount'] into the human-readable figure
        // the customer reads. Bound to the retired key it formatted 0 next to a
        // real refunded amount -- two contradicting numbers in one e-mail.
        $ctx = EmailTemplates::build_context('refund_customer', $this->booking_id);

        $this->assertNotSame(
            0,
            $ctx['booking']['payment']['amount'],
            'The refund branch reads this key; a zero here reaches the customer.'
        );
    }

    public function test_the_booking_list_sort_no_longer_targets_the_retired_key(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/src/Admin/Booking/ListTable/BookingColumns.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            "'key'     => '_mhmrentiva_payment_amount',",
            $source,
            'A derived amount cannot be ORDER BY-ed; spec §4.4 moves the sort to total_price.'
        );
    }
}
