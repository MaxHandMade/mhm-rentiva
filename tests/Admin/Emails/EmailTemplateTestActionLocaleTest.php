<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Settings\EmailTemplateTestAction;
use WP_UnitTestCase;

/**
 * The template test send (Settings -> Email Templates -> Preview -> "Send Test
 * Email" via admin-post) built its booking context -- every value read for the
 * mail -- in the operator's profile language, and only the render happened in
 * the site's. The whole composition belongs to the site locale, like the AJAX
 * test send and the preview.
 *
 * Measured by recording the locale in effect whenever the context reads the
 * booking (get_post_metadata), on a Turkish site with an English operator.
 *
 * @covers \MHMRentiva\Admin\Emails\Settings\EmailTemplateTestAction::handle
 */
final class EmailTemplateTestActionLocaleTest extends WP_UnitTestCase
{
    /** @var mixed */
    private $previous_wplang;

    /** @var array<int, string> */
    private array $previous_available;

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

        add_filter('pre_wp_mail', '__return_true');
        // handle() ends in wp_safe_redirect() + exit; stop it before exit.
        add_filter(
            'wp_redirect',
            static function (): void {
                throw new \RuntimeException('redirected');
            }
        );
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

    public function test_the_context_is_built_in_the_site_locale(): void
    {
        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
        $booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        update_post_meta($booking, '_mhmrentiva_customer_email', 'renter@example.test');

        $seen = array();
        add_filter(
            'get_post_metadata',
            static function ($value, $object_id) use ($booking, &$seen) {
                if ((int) $object_id === $booking) {
                    $seen[] = determine_locale();
                }
                return $value;
            },
            10,
            2
        );

        $_POST = array(
            '_wpnonce'     => wp_create_nonce('mhmrentiva_send_template_test'),
            'template_key' => 'booking_status_changed_customer',
            'to'           => 'operator@example.test',
            'booking_id'   => (string) $booking,
            'new_status'   => 'confirmed',
        );

        try {
            EmailTemplateTestAction::handle();
            $this->fail('handle() should have redirected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('redirected', $e->getMessage());
        }

        $this->assertNotEmpty($seen, 'Precondition: the context read the booking.');
        $this->assertSame(array( 'tr_TR' ), array_values(array_unique($seen)), 'The booking context was read in the operator\'s locale.');
        $this->assertSame('en_US', determine_locale());
    }
}
