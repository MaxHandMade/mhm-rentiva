<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Notifications\BookingNotifications;
use MHMRentiva\Admin\Emails\Notifications\RefundNotifications;
use MHMRentiva\Admin\Emails\Notifications\ReminderScheduler;
use MHMRentiva\Admin\Payment\Core\Money;
use WP_UnitTestCase;

/**
 * The email-templates screen draws an "Enabled" checkbox for seven templates
 * and saves it ('1' ticked, '0' unticked), but no sending code ever read it:
 * an operator who unticked "Booking Status Change Email" still had it sent.
 *
 * The screen shows a never-saved box as TICKED (get_option( $key, '1' )), so
 * "never saved" must keep sending -- turning the gate on must not silently
 * switch e-mails off on sites that never opened the screen.
 *
 * @covers \MHMRentiva\Admin\Emails\Core\Mailer::is_template_enabled
 */
final class TemplateEnabledToggleTest extends WP_UnitTestCase
{
    /**
     * Template key => the option the settings screen writes for its box.
     * Measured from EmailTemplates::save_booking_notifications() /
     * save_refund_emails() and the two render screens.
     */
    private const TOGGLES = array(
        'booking_created_customer'        => 'mhmrentiva_booking_created_enabled',
        'booking_status_changed_customer' => 'mhmrentiva_booking_status_enabled',
        'booking_created_admin'           => 'mhmrentiva_booking_admin_enabled',
        'booking_cancelled'               => 'mhmrentiva_booking_cancelled_enabled',
        'booking_reminder_customer'       => 'mhmrentiva_booking_reminder_enabled',
        'refund_customer'                 => 'mhmrentiva_refund_customer_enabled',
        'refund_admin'                    => 'mhmrentiva_refund_admin_enabled',
    );

    /** @var array<int, array{key: string, to: string}> */
    private array $sent = array();

    /** @var array<int, array<string, mixed>> */
    private array $mails = array();

    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        foreach (self::TOGGLES as $option) {
            delete_option($option);
        }

        $this->sent  = array();
        $this->mails = array();

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

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($this->booking_id, '_mhmrentiva_customer_email', 'renter@example.test');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_first_name', 'Ren');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_last_name', 'Ter');
        update_post_meta($this->booking_id, '_mhmrentiva_status', Status::PENDING);
    }

    public function tearDown(): void
    {
        foreach (self::TOGGLES as $option) {
            delete_option($option);
        }
        delete_option('mhmrentiva_settings');
        parent::tearDown();
    }

    /** @return array<int, string> */
    private function sent_keys(): array
    {
        return array_map(static fn (array $s): string => $s['key'], $this->sent);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public function toggles(): array
    {
        $out = array();
        foreach (self::TOGGLES as $key => $option) {
            $out[ $key ] = array( $key, $option );
        }
        return $out;
    }

    /** @dataProvider toggles */
    public function test_a_never_saved_box_reads_as_enabled(string $key, string $option): void
    {
        $this->assertFalse(get_option($option), 'Precondition: never saved.');
        $this->assertTrue(Mailer::is_template_enabled($key), "{$option} never saved must keep {$key} sending (the screen shows it ticked).");
    }

    /** @dataProvider toggles */
    public function test_an_unticked_box_stops_that_template(string $key, string $option): void
    {
        update_option($option, '0');

        $this->assertFalse(Mailer::is_template_enabled($key));
        $this->assertFalse(Mailer::send($key, 'renter@example.test', Mailer::getBookingContext($this->booking_id) ?? array()));
        $this->assertSame(array(), $this->mails, "{$key} was sent although {$option} is '0'.");
    }

    /** @dataProvider toggles */
    public function test_a_ticked_box_sends(string $key, string $option): void
    {
        update_option($option, '1');

        $this->assertTrue(Mailer::send($key, 'renter@example.test', Mailer::getBookingContext($this->booking_id) ?? array()));
        $this->assertCount(1, $this->mails);
    }

    public function test_a_template_without_a_box_is_not_gated(): void
    {
        $this->assertTrue(Mailer::is_template_enabled('booking_status_changed_admin'));
        $this->assertTrue(Mailer::is_template_enabled('vendor_approved'));
    }

    /**
     * Same class, one screen over: Email settings -> "Send Booking
     * Notifications" ("Send booking confirmation, reminder, and cancellation
     * emails to customers") was checked against the keys 'booking_confirmation',
     * 'booking_reminder' and 'booking_cancellation' -- no template has those
     * keys, so unticking it stopped nothing. Likewise the welcome check named
     * 'welcome' instead of 'welcome_customer'.
     *
     * @return array<string, array{0: string}>
     */
    public function booking_notification_keys(): array
    {
        return array(
            'confirmation' => array( 'booking_created_customer' ),
            'reminder'     => array( 'booking_reminder_customer' ),
            'cancellation' => array( 'booking_cancelled' ),
            'auto cancel'  => array( 'auto_cancel' ),
        );
    }

    /** @dataProvider booking_notification_keys */
    public function test_the_global_booking_notifications_toggle_reaches_the_real_keys(string $key): void
    {
        \MHMRentiva\Admin\Settings\Core\SettingsCore::set('mhmrentiva_customer_booking_notifications', '0');

        $ctx = Mailer::getBookingContext($this->booking_id) ?? array();
        $this->assertFalse(Mailer::send($key, 'renter@example.test', $ctx), "{$key} ignored the global toggle.");
        $this->assertSame(array(), $this->mails);
    }

    public function test_the_global_welcome_toggle_reaches_the_real_key(): void
    {
        \MHMRentiva\Admin\Settings\Core\SettingsCore::set('mhmrentiva_customer_welcome_email', '0');

        $ctx = Mailer::getBookingContext($this->booking_id) ?? array();
        $this->assertFalse(Mailer::send('welcome_customer', 'renter@example.test', $ctx));
        $this->assertSame(array(), $this->mails);
    }

    public function test_the_global_booking_toggle_leaves_status_mail_alone(): void
    {
        \MHMRentiva\Admin\Settings\Core\SettingsCore::set('mhmrentiva_customer_booking_notifications', '0');

        $ctx = Mailer::getBookingContext($this->booking_id) ?? array();
        $this->assertTrue(Mailer::send('booking_status_changed_customer', 'renter@example.test', $ctx));
    }

    // --- The real send paths, each reached the way production reaches it. ---

    public function test_booking_created_path_respects_both_boxes(): void
    {
        update_option('mhmrentiva_booking_created_enabled', '0');

        BookingNotifications::on_created($this->booking_id);

        $this->assertNotContains('booking_created_customer', $this->sent_keys());
        $this->assertContains('booking_created_admin', $this->sent_keys(), 'Only the customer box was unticked.');

        $this->sent = array();
        update_option('mhmrentiva_booking_created_enabled', '1');
        update_option('mhmrentiva_booking_admin_enabled', '0');

        BookingNotifications::on_created($this->booking_id);

        $this->assertContains('booking_created_customer', $this->sent_keys());
        $this->assertNotContains('booking_created_admin', $this->sent_keys());
    }

    public function test_status_change_path_respects_the_status_box(): void
    {
        update_option('mhmrentiva_booking_status_enabled', '0');

        $this->assertTrue(Status::update_status($this->booking_id, Status::CONFIRMED));

        $this->assertNotContains('booking_status_changed_customer', $this->sent_keys());
        $this->assertContains('booking_status_changed_admin', $this->sent_keys(), 'The admin status mail has no box and must still go.');
    }

    public function test_reminder_cron_path_respects_the_reminder_box(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_status', Status::CONFIRMED);
        update_option('mhmrentiva_booking_reminder_enabled', '0');

        ReminderScheduler::send_reminder($this->booking_id);
        $this->assertNotContains('booking_reminder_customer', $this->sent_keys());

        update_option('mhmrentiva_booking_reminder_enabled', '1');
        ReminderScheduler::send_reminder($this->booking_id);
        $this->assertContains('booking_reminder_customer', $this->sent_keys());
    }

    public function test_refund_path_respects_both_refund_boxes(): void
    {
        update_option('mhmrentiva_refund_customer_enabled', '0');

        RefundNotifications::notify($this->booking_id, Money::toMinor('20'), 'USD', 'partially_refunded');

        $this->assertNotContains('refund_customer', $this->sent_keys());
        $this->assertContains('refund_admin', $this->sent_keys());

        $this->sent = array();
        update_option('mhmrentiva_refund_customer_enabled', '1');
        update_option('mhmrentiva_refund_admin_enabled', '0');

        RefundNotifications::notify($this->booking_id, Money::toMinor('20'), 'USD', 'partially_refunded');

        $this->assertContains('refund_customer', $this->sent_keys());
        $this->assertNotContains('refund_admin', $this->sent_keys());
    }

    /**
     * Preview and "send test" are the operator looking at a template; the
     * box decides what customers receive, not what the operator may inspect.
     */
    public function test_a_disabled_template_can_still_be_previewed_and_test_sent(): void
    {
        update_option('mhmrentiva_booking_cancelled_enabled', '0');

        $ctx  = \MHMRentiva\Admin\Emails\Core\EmailTemplates::build_context('booking_cancelled', 0);
        $html = \MHMRentiva\Admin\Emails\Core\Templates::render_body('booking_cancelled', $ctx);
        $this->assertStringContainsString('<html', $html);

        $this->assertTrue(Mailer::send_test('booking_cancelled', 'operator@example.test', $ctx));
        $this->assertCount(1, $this->mails);
    }
}
