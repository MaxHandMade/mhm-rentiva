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
 * unwritten, a surface that still reads it reports 0. What EmailTemplates:367
 * and :376 actually protect is narrower than the rendered customer mail:
 * build_context() is reached only from EmailAjaxHandler's manage_options-gated
 * preview and test-send actions, so these bindings fix what an admin sees
 * when previewing or test-sending a refund template. RefundNotifications:82's
 * bound value reaches EmailLog::handle_email_sent(), which JSON-encodes the
 * whole context into the _mhmrentiva_email_context audit record -- also not
 * the rendered mail. refund-customer.html.php / refund-admin.html.php never
 * read booking.payment.amount; they echo the top-level $data['amount'],
 * built from notify()'s own $amount_kurus parameter, which was correct
 * before this task and stays correct after it. Before this task, the admin
 * preview/test-send output and the audit log both silently recorded 0 next
 * to a real figure -- a contradiction the class
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
        // shown in EmailAjaxHandler's admin preview/test-send output (both
        // manage_options-gated) and recorded into the _mhmrentiva_email_context
        // audit log by EmailLog::handle_email_sent(). Bound to the retired key
        // it formatted 0 there -- not in the customer-facing mail itself, which
        // reads notify()'s own $amount_kurus parameter instead.
        $ctx = EmailTemplates::build_context('refund_customer', $this->booking_id);

        $this->assertNotSame(
            0,
            $ctx['booking']['payment']['amount'],
            'The admin preview/test-send output and the e-mail audit log read this key; a zero here reached both, though never the customer-facing mail.'
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
