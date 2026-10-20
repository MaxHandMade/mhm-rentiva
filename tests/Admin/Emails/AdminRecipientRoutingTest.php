<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Notifications\RefundNotifications;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use MHMRentiva\Helpers\NotificationHelper;
use WP_UnitTestCase;

/**
 * The email-templates screen offers two admin-recipient fields --
 * "Admin Email" under Booking Notifications (mhmrentiva_booking_admin_to)
 * and under Refund Emails (mhmrentiva_refund_admin_to) -- and saves them,
 * but no sending code read either: every admin e-mail went to the site's
 * admin_email whatever the operator typed there.
 *
 * Booking-related admin mail now goes to the booking field, refund-related
 * admin mail to the refund field; an empty or invalid field falls back to
 * admin_email, exactly as before.
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\EmailSettings::admin_recipient
 */
final class AdminRecipientRoutingTest extends WP_UnitTestCase
{
    private const SITE_ADMIN    = 'site-admin@example.test';
    private const BOOKING_ADMIN = 'bookings-desk@example.test';
    private const REFUND_ADMIN  = 'refunds-desk@example.test';

    /** @var array<int, array<string, mixed>> */
    private array $mails = array();

    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        update_option('admin_email', self::SITE_ADMIN);
        delete_option('mhmrentiva_booking_admin_to');
        delete_option('mhmrentiva_refund_admin_to');
        delete_option('mhmrentiva_settings');

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
        delete_option('mhmrentiva_booking_admin_to');
        delete_option('mhmrentiva_refund_admin_to');
        delete_option('mhmrentiva_settings');
        parent::tearDown();
    }

    private function configure_both(): void
    {
        update_option('mhmrentiva_booking_admin_to', self::BOOKING_ADMIN);
        update_option('mhmrentiva_refund_admin_to', self::REFUND_ADMIN);
    }

    /** @return array<int, string> */
    private function recipients(): array
    {
        $to = array();
        foreach ($this->mails as $mail) {
            foreach ((array) $mail['to'] as $address) {
                $to[] = strtolower(trim((string) $address));
            }
        }
        return $to;
    }

    // ---- The resolver -------------------------------------------------

    /** @return array<string, array{0: string, 1: string}> */
    public function kinds(): array
    {
        return array(
            'booking' => array( 'booking', 'mhmrentiva_booking_admin_to' ),
            'refund'  => array( 'refund', 'mhmrentiva_refund_admin_to' ),
        );
    }

    /** @dataProvider kinds */
    public function test_a_never_saved_field_falls_back_to_admin_email(string $kind, string $option): void
    {
        $this->assertFalse(get_option($option), 'Precondition: never saved.');
        $this->assertSame(self::SITE_ADMIN, EmailSettings::admin_recipient($kind));
    }

    /** @dataProvider kinds */
    public function test_a_saved_field_is_the_recipient(string $kind, string $option): void
    {
        update_option($option, 'desk@example.test');
        $this->assertSame('desk@example.test', EmailSettings::admin_recipient($kind));
    }

    /** @dataProvider kinds */
    public function test_an_empty_field_falls_back_to_admin_email(string $kind, string $option): void
    {
        // What the screen stores when the operator clears the box
        // (sanitize_email('') === '').
        update_option($option, '');
        $this->assertSame(self::SITE_ADMIN, EmailSettings::admin_recipient($kind));
    }

    /** @dataProvider kinds */
    public function test_an_invalid_field_falls_back_to_admin_email(string $kind, string $option): void
    {
        update_option($option, 'not-an-address');
        $this->assertSame(self::SITE_ADMIN, EmailSettings::admin_recipient($kind));
    }

    public function test_the_two_fields_do_not_bleed_into_each_other(): void
    {
        $this->configure_both();
        $this->assertSame(self::BOOKING_ADMIN, EmailSettings::admin_recipient('booking'));
        $this->assertSame(self::REFUND_ADMIN, EmailSettings::admin_recipient('refund'));
    }

    public function test_an_unknown_kind_is_admin_email(): void
    {
        $this->configure_both();
        $this->assertSame(self::SITE_ADMIN, EmailSettings::admin_recipient('vendor'));
    }

    // ---- Booking-related admin sends ----------------------------------

    public function test_new_booking_admin_alert_goes_to_the_booking_field(): void
    {
        $this->configure_both();

        $this->assertTrue(Mailer::sendBookingEmail('booking_created_admin', $this->booking_id, 'admin'));
        $this->assertSame(array( self::BOOKING_ADMIN ), $this->recipients());
    }

    public function test_status_change_admin_alert_goes_to_the_booking_field(): void
    {
        $this->configure_both();

        $this->assertTrue(Mailer::sendBookingEmail('booking_status_changed_admin', $this->booking_id, 'admin', array(
            'status' => array( 'old' => Status::PENDING, 'new' => Status::CONFIRMED ),
        )));
        $this->assertSame(array( self::BOOKING_ADMIN ), $this->recipients());
    }

    public function test_admin_copy_of_a_cancellation_goes_to_the_booking_field(): void
    {
        $this->configure_both();

        $method = new \ReflectionMethod(CancellationHandler::class, 'send_cancellation_email');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $this->booking_id, 'changed plans'));
        $this->assertSame(array( self::BOOKING_ADMIN ), $this->recipients());
    }

    public function test_order_cancelled_on_live_booking_alert_goes_to_the_booking_field(): void
    {
        $this->configure_both();

        $this->assertTrue(NotificationHelper::send_order_cancelled_on_live_booking_email($this->booking_id, 4242));
        $this->assertSame(array( self::BOOKING_ADMIN ), $this->recipients());
    }

    // ---- Refund-related admin sends -----------------------------------

    public function test_refund_admin_notice_goes_to_the_refund_field(): void
    {
        $this->configure_both();

        RefundNotifications::notify($this->booking_id, Money::toMinor('20'), 'USD', 'partially_refunded');

        $this->assertContains(self::REFUND_ADMIN, $this->recipients());
        $this->assertNotContains(self::SITE_ADMIN, $this->recipients());
        $this->assertNotContains(self::BOOKING_ADMIN, $this->recipients());
    }

    /** @return array<string, array{0: string}> */
    public function refund_alerts(): array
    {
        return array(
            'needs review'   => array( 'send_refund_needs_review_email' ),
            'refund failed'  => array( 'send_refund_failed_email' ),
            'mixed currency' => array( 'send_refund_mixed_currency_review_email' ),
        );
    }

    /** @dataProvider refund_alerts */
    public function test_refund_alerts_go_to_the_refund_field(string $method): void
    {
        $this->configure_both();

        $this->assertTrue(NotificationHelper::$method($this->booking_id));
        $this->assertSame(array( self::REFUND_ADMIN ), $this->recipients());
    }

    /** @dataProvider refund_alerts */
    public function test_refund_alerts_fall_back_to_admin_email(string $method): void
    {
        $this->assertTrue(NotificationHelper::$method($this->booking_id));
        $this->assertSame(array( self::SITE_ADMIN ), $this->recipients());
    }

    // ---- Unchanged behaviour ------------------------------------------

    public function test_an_unconfigured_site_still_mails_admin_email(): void
    {
        $this->assertTrue(Mailer::sendBookingEmail('booking_created_admin', $this->booking_id, 'admin'));
        $this->assertSame(array( self::SITE_ADMIN ), $this->recipients());
    }

    private function enable_test_mode(): void
    {
        update_option('mhmrentiva_settings', array(
            'mhmrentiva_email_test_mode'    => '1',
            'mhmrentiva_email_test_address' => 'qa@example.test',
        ));
    }

    public function test_test_mode_still_redirects_a_configured_admin_alert(): void
    {
        $this->configure_both();
        $this->enable_test_mode();

        $this->assertTrue(Mailer::sendBookingEmail('booking_created_admin', $this->booking_id, 'admin'));
        $this->assertSame(array( 'qa@example.test' ), $this->recipients());
    }

    // ---- Test mode for the direct wp_mail() admin sends ----------------
    //
    // Email settings -> "Production Sandbox (Test Mode)": "Redirect all
    // outgoing mails to the test address below." Mailer::deliver() did; the
    // five admin alerts that call wp_mail() themselves did not, so a site in
    // the sandbox still mailed its real administrators.

    public function test_sandbox_recipient_is_identity_outside_test_mode(): void
    {
        $this->assertSame('someone@example.test', EmailSettings::sandbox_recipient('someone@example.test'));
    }

    public function test_sandbox_recipient_is_the_test_address_in_test_mode(): void
    {
        $this->enable_test_mode();
        $this->assertSame('qa@example.test', EmailSettings::sandbox_recipient('someone@example.test'));
    }

    public function test_test_mode_redirects_the_admin_copy_of_a_cancellation(): void
    {
        $this->configure_both();
        $this->enable_test_mode();

        $method = new \ReflectionMethod(CancellationHandler::class, 'send_cancellation_email');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $this->booking_id, 'changed plans'));
        $this->assertSame(array( 'qa@example.test' ), $this->recipients());
    }

    /** @return array<string, array{0: string, 1: array<int, int>}> */
    public function direct_admin_alerts(): array
    {
        return array(
            'needs review'           => array( 'send_refund_needs_review_email', array() ),
            'refund failed'          => array( 'send_refund_failed_email', array() ),
            'mixed currency'         => array( 'send_refund_mixed_currency_review_email', array() ),
            'order cancelled (live)' => array( 'send_order_cancelled_on_live_booking_email', array( 4242 ) ),
        );
    }

    /**
     * @dataProvider direct_admin_alerts
     * @param array<int, int> $extra
     */
    public function test_test_mode_redirects_the_direct_admin_alerts(string $method, array $extra): void
    {
        $this->configure_both();
        $this->enable_test_mode();

        $this->assertTrue(NotificationHelper::$method($this->booking_id, ...$extra));
        $this->assertSame(array( 'qa@example.test' ), $this->recipients());
    }
}
