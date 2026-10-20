<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler;
use MHMRentiva\Admin\Emails\Core\Mailer;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * An e-mail is written in the SITE's language, whoever triggers it.
 *
 * Inside wp-admin (admin-ajax included) determine_locale() answers with the
 * operator's PROFILE language, so on a Turkish site an operator whose profile
 * says English saw every preview in English -- subject heading, title, body --
 * and every e-mail the operator's own action triggered (a status change, a
 * cancel, a refund) went to the customer in English as well. Measured on the
 * dev site 2026-10-20 with the subagent profile set to en_US.
 *
 * This suite's site locale is en_US and it ships no core tr_TR pack, so the
 * fixture makes the site Turkish the way core would see it: WPLANG = tr_TR,
 * tr_TR listed as available (both to sanitize_option() and to the locale
 * switcher, which snapshots the list at boot). The plugin's own bundled
 * catalogue (languages/mhm-rentiva-tr_TR.mo) is the only Turkish source, so a
 * switch that does not reload it renders English and the test stays red.
 *
 * @covers \MHMRentiva\Admin\Emails\Core\Mailer
 * @covers \MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler
 */
final class EmailSiteLocaleTest extends WP_Ajax_UnitTestCase
{
    /** @var mixed */
    private $previous_wplang;

    /** @var array<int, string> */
    private array $previous_available;

    /** @var array<int, array<string, mixed>> */
    private array $mails = array();

    public function setUp(): void
    {
        parent::setUp();

        add_filter(
            'get_available_languages',
            static function (array $languages): array {
                $languages[] = 'tr_TR';
                return array_values(array_unique($languages));
            }
        );

        $switcher                 = $GLOBALS['wp_locale_switcher'];
        $prop                     = new \ReflectionProperty($switcher, 'available_languages');
        $prop->setAccessible(true);
        $this->previous_available = (array) $prop->getValue($switcher);
        $prop->setValue($switcher, array_values(array_unique(array_merge($this->previous_available, array( 'tr_TR' )))));

        $this->previous_wplang = get_option('WPLANG');
        update_option('WPLANG', 'tr_TR');
        $this->assertSame('tr_TR', get_option('WPLANG'), 'Precondition: the site is Turkish.');
        $this->assertSame('en_US', determine_locale(), 'Precondition: the asker is English.');

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

        if (! has_action('wp_ajax_mhmrentiva_preview_email_ajax')) {
            EmailAjaxHandler::register();
        }
    }

    public function tearDown(): void
    {
        while (is_locale_switched()) {
            restore_previous_locale();
        }

        if (false === $this->previous_wplang) {
            delete_option('WPLANG');
        } else {
            update_option('WPLANG', $this->previous_wplang);
        }

        $switcher = $GLOBALS['wp_locale_switcher'];
        $prop     = new \ReflectionProperty($switcher, 'available_languages');
        $prop->setAccessible(true);
        $prop->setValue($switcher, $this->previous_available);

        $_POST = array();
        parent::tearDown();
    }

    /** @return array{subject: string, html: string} */
    private function preview(string $key): array
    {
        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

        $_POST = array(
            'action'       => 'mhmrentiva_preview_email_ajax',
            'nonce'        => wp_create_nonce('mhmrentiva_email_preview_action'),
            'template_key' => $key,
            'booking_id'   => '',
            'new_status'   => '',
        );

        try {
            $this->_handleAjax('mhmrentiva_preview_email_ajax');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success'] ?? false, 'Preview failed: ' . $this->_last_response);

        return $response['data'];
    }

    public function test_a_preview_asked_for_in_english_renders_in_the_site_language(): void
    {
        $data = $this->preview('booking_created_customer');

        $this->assertStringContainsString('Rezervasyon #9999 onaylandı', $data['subject']);
        $this->assertStringContainsString('<h1>Rezervasyon #9999 onaylandı', $data['html'], 'The e-mail heading is the subject.');
        $this->assertStringContainsString('Rezervasyon detayları', $data['html']);
        $this->assertStringNotContainsString('Reservation Details', $data['html']);
    }

    /**
     * Every template the preview offers, not just one: the English heading
     * came from the request locale, which every template shared.
     */
    public function test_no_preview_template_renders_its_english_subject(): void
    {
        $english = array(
            'booking_created_customer'        => 'Confirmed',
            'booking_created_admin'           => 'New Booking Request',
            'booking_status_changed_customer' => 'Status Updated',
            'booking_status_changed_admin'    => 'Status Updated',
            'booking_reminder_customer'       => 'Starts Soon',
            'remaining_payment_link_customer' => 'Complete Your Payment',
            'welcome_customer'                => 'Welcome to',
            'booking_cancelled'               => 'Cancelled',
            'auto_cancel'                     => 'Cancelled',
            'refund_customer'                 => 'Refund Processed',
            'refund_admin'                    => 'Refund Alert',
        );

        foreach ($english as $key => $needle) {
            $this->_last_response = '';
            $data                 = $this->preview($key);
            $this->assertStringNotContainsString($needle, $data['subject'], "{$key} previewed in English.");
            $this->assertStringNotContainsString('All rights reserved', $data['html'], "{$key} layout footer previewed in English.");
        }
    }

    public function test_a_mail_an_english_operator_triggers_reaches_the_customer_in_the_site_language(): void
    {
        $id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        update_post_meta($id, '_mhmrentiva_customer_email', 'renter@example.test');
        update_post_meta($id, '_mhmrentiva_customer_first_name', 'Ren');

        $this->assertTrue(Mailer::sendBookingEmail('booking_cancelled', $id, 'customer'));

        $this->assertCount(1, $this->mails);
        $this->assertStringContainsString('iptal edildi', (string) $this->mails[0]['subject']);
        $this->assertStringContainsString('Sayın', (string) $this->mails[0]['message']);
        $this->assertSame('en_US', determine_locale(), 'The operator\'s own locale is put back afterwards.');
    }
}
