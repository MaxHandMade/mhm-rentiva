<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * Product decision (owner, 2026-10-20): when a booking moves to CANCELLED the
 * customer receives the `booking_cancelled` template INSTEAD of the generic
 * status-changed e-mail -- one e-mail, never two. If `booking_cancelled` is
 * switched off the customer gets nothing for that transition (no fallback to
 * the generic one). Admin notifications are unchanged.
 *
 * Before this, `booking_cancelled` had no sender at all (only the preview
 * used it); every cancel went out as "status updated", and the customer
 * cancel path additionally sent its own raw wp_mail() -- two mails to the
 * customer for one cancellation.
 *
 * Every path to CANCELLED funnels through Status::update_status() (the only
 * writer of _mhmrentiva_status besides Pro's report bulk action, which fires
 * no hook and sends nothing), so the paths tested here are the three ways
 * that funnel is entered with different side mail:
 *   - a plain status change (admin edit screen, booking list, WooCommerce
 *     order cancelled, deposit screen status change),
 *   - CancellationHandler::cancel_booking() (customer account, admin deposit
 *     screen, refund review action) which also sends an admin copy,
 *   - AutoCancel's sweep, which has its own "Auto Cancel Email" template.
 */
final class CancelledEmailRoutingTest extends WP_UnitTestCase
{
    private const CUSTOMER = 'renter@example.test';

    /** @var array<int, array{key: string, to: string}> */
    private array $sent = array();

    /** @var array<int, array<string, mixed>> */
    private array $mails = array();

    public function setUp(): void
    {
        parent::setUp();

        delete_option('mhmrentiva_booking_cancelled_enabled');
        delete_option('mhmrentiva_booking_status_enabled');

        add_filter(
            'pre_wp_mail',
            function ($short, array $atts) {
                $this->mails[] = $atts;
                return true;
            },
            10,
            2
        );
        add_action(
            'mhmrentiva_email_sent',
            function (string $key, string $to): void {
                $this->sent[] = array( 'key' => $key, 'to' => $to );
            },
            10,
            2
        );
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");
        delete_option('mhmrentiva_booking_cancelled_enabled');
        delete_option('mhmrentiva_booking_status_enabled');
        delete_option('mhmrentiva_settings');
        parent::tearDown();
    }

    private function booking(string $status, array $post = array()): int
    {
        $id = (int) self::factory()->post->create(array_merge(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ), $post));
        update_post_meta($id, '_mhmrentiva_customer_email', self::CUSTOMER);
        update_post_meta($id, '_mhmrentiva_customer_first_name', 'Ren');
        update_post_meta($id, '_mhmrentiva_status', $status);
        update_post_meta($id, '_mhmrentiva_payment_status', 'pending');

        return $id;
    }

    /** @return array<int, string> Template keys that went to the customer. */
    private function customer_keys(): array
    {
        $keys = array();
        foreach ($this->sent as $s) {
            if (self::CUSTOMER === $s['to']) {
                $keys[] = $s['key'];
            }
        }
        return $keys;
    }

    /** @return int Every mail that reached the customer, through Mailer or not. */
    private function customer_mail_count(): int
    {
        $n = 0;
        foreach ($this->mails as $m) {
            if (in_array(self::CUSTOMER, (array) $m['to'], true)) {
                ++$n;
            }
        }
        return $n;
    }

    private function admin_keys(): array
    {
        $admin = (string) get_option('admin_email');
        $keys  = array();
        foreach ($this->sent as $s) {
            if ($admin === $s['to']) {
                $keys[] = $s['key'];
            }
        }
        return $keys;
    }

    // --- Path 1: a plain status change to cancelled ---

    public function test_status_change_to_cancelled_sends_the_cancelled_template_only(): void
    {
        $id = $this->booking(Status::CONFIRMED);

        $this->assertTrue(Status::update_status($id, Status::CANCELLED, 1));

        $this->assertSame(array( 'booking_cancelled' ), $this->customer_keys());
        $this->assertSame(1, $this->customer_mail_count());
        $this->assertContains('booking_status_changed_admin', $this->admin_keys(), 'Admin notifications are unchanged.');
    }

    public function test_status_change_to_cancelled_with_the_template_off_sends_the_customer_nothing(): void
    {
        update_option('mhmrentiva_booking_cancelled_enabled', '0');
        $id = $this->booking(Status::CONFIRMED);

        $this->assertTrue(Status::update_status($id, Status::CANCELLED, 1));

        $this->assertSame(array(), $this->customer_keys(), 'No fallback to the generic status mail.');
        $this->assertSame(0, $this->customer_mail_count());
        $this->assertContains('booking_status_changed_admin', $this->admin_keys());
    }

    public function test_the_status_box_does_not_gate_the_cancelled_mail(): void
    {
        update_option('mhmrentiva_booking_status_enabled', '0');
        $id = $this->booking(Status::CONFIRMED);

        Status::update_status($id, Status::CANCELLED, 1);

        $this->assertSame(array( 'booking_cancelled' ), $this->customer_keys());
    }

    public function test_other_transitions_still_send_the_generic_status_mail(): void
    {
        $id = $this->booking(Status::PENDING);

        Status::update_status($id, Status::CONFIRMED, 1);

        $this->assertSame(array( 'booking_status_changed_customer' ), $this->customer_keys());
    }

    // --- Path 2: CancellationHandler::cancel_booking() ---

    public function test_cancel_booking_sends_the_customer_exactly_one_mail(): void
    {
        $id = $this->booking(Status::CONFIRMED);

        $result = CancellationHandler::cancel_booking($id, 0, 'change of plans', true, false, 'customer_account');

        $this->assertNotWPError($result);
        $this->assertSame(Status::CANCELLED, Status::get($id));
        $this->assertSame(array( 'booking_cancelled' ), $this->customer_keys());
        $this->assertSame(1, $this->customer_mail_count(), 'cancel_booking() used to add its own raw customer mail on top.');
    }

    public function test_cancel_booking_with_the_template_off_sends_the_customer_nothing(): void
    {
        update_option('mhmrentiva_booking_cancelled_enabled', '0');
        $id = $this->booking(Status::CONFIRMED);

        $result = CancellationHandler::cancel_booking($id, 0, '', true, false, 'customer_account');

        $this->assertNotWPError($result);
        $this->assertSame(0, $this->customer_mail_count());
    }

    public function test_cancel_booking_keeps_the_admin_copy(): void
    {
        $id = $this->booking(Status::CONFIRMED);

        CancellationHandler::cancel_booking($id, 0, '', true, false, 'admin_deposit');

        $admin   = (string) get_option('admin_email');
        $subjects = array();
        foreach ($this->mails as $m) {
            if (in_array($admin, (array) $m['to'], true)) {
                $subjects[] = (string) $m['subject'];
            }
        }
        $this->assertNotEmpty(
            array_filter($subjects, static fn (string $s): bool => false !== strpos($s, '[ADMIN]')),
            'The admin copy of the cancellation is an admin notification and stays.'
        );
    }

    // --- Path 3: AutoCancel sweep ---

    private function expired_unpaid_booking(): int
    {
        return $this->booking(Status::PENDING, array(
            'post_date' => gmdate('Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS),
        ));
    }

    public function test_auto_cancel_with_its_own_mail_off_sends_the_cancelled_template(): void
    {
        SettingsCore::set('mhmrentiva_booking_auto_cancel_enabled', '1');
        SettingsCore::set('mhmrentiva_send_auto_cancel_email', '0');
        $id = $this->expired_unpaid_booking();

        AutoCancel::run();

        $this->assertSame(Status::CANCELLED, Status::get($id), 'Precondition: the sweep cancelled it.');
        $this->assertSame(array( 'booking_cancelled' ), $this->customer_keys());
    }

    public function test_auto_cancel_with_its_own_mail_on_sends_that_one_only(): void
    {
        SettingsCore::set('mhmrentiva_booking_auto_cancel_enabled', '1');
        SettingsCore::set('mhmrentiva_send_auto_cancel_email', '1');
        $id = $this->expired_unpaid_booking();

        AutoCancel::run();

        $this->assertSame(Status::CANCELLED, Status::get($id));
        $this->assertSame(array( 'auto_cancel' ), $this->customer_keys(), 'One cancellation, one customer mail.');
    }

    public function test_auto_cancel_with_both_customer_mails_off_sends_nothing(): void
    {
        SettingsCore::set('mhmrentiva_booking_auto_cancel_enabled', '1');
        SettingsCore::set('mhmrentiva_send_auto_cancel_email', '0');
        update_option('mhmrentiva_booking_cancelled_enabled', '0');
        $id = $this->expired_unpaid_booking();

        AutoCancel::run();

        $this->assertSame(Status::CANCELLED, Status::get($id));
        $this->assertSame(0, $this->customer_mail_count());
    }
}
