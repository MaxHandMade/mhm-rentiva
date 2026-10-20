<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use WP_UnitTestCase;

/**
 * E-mails render in the site locale under switch_to_locale(); the plugin then
 * reloads its bundled catalogue on `change_locale`. The hook names the locale
 * switched to. determine_locale() is not that on every supported WordPress: in
 * an admin request it can answer with the operator's profile language, and the
 * reload then picked the wrong file.
 *
 * The suite has no core tr_TR pack, so tr_TR is made available to the switcher
 * (which snapshots the list at boot) for the duration of the test.
 *
 * @covers \MHMRentiva\Plugin::load_textdomain
 */
final class CatalogueFollowsLocaleSwitchTest extends WP_UnitTestCase
{
    /** @var array<int, string> */
    private array $previous_available;

    public function setUp(): void
    {
        parent::setUp();

        $switcher                 = $GLOBALS['wp_locale_switcher'];
        $prop                     = new \ReflectionProperty($switcher, 'available_languages');
        $prop->setAccessible(true);
        $this->previous_available = (array) $prop->getValue($switcher);
        $prop->setValue($switcher, array_values(array_unique(array_merge($this->previous_available, array( 'tr_TR' )))));
    }

    public function tearDown(): void
    {
        while (is_locale_switched()) {
            restore_previous_locale();
        }

        $switcher = $GLOBALS['wp_locale_switcher'];
        $prop     = new \ReflectionProperty($switcher, 'available_languages');
        $prop->setAccessible(true);
        $prop->setValue($switcher, $this->previous_available);

        parent::tearDown();
    }

    public function test_the_reload_uses_the_locale_the_hook_names(): void
    {
        $pin = static fn (): string => 'en_US';
        add_filter('determine_locale', $pin, PHP_INT_MAX);

        try {
            $this->assertTrue(switch_to_locale('tr_TR'));
            $label = __('Booking Details', 'mhm-rentiva');
            // The reload must not pull the translation controller back to the
            // locale determine_locale() names: core and every other plugin
            // would then render English for the rest of the switch.
            $controller = \WP_Translation_Controller::get_instance()->get_locale();
            restore_previous_locale();
        } finally {
            remove_filter('determine_locale', $pin, PHP_INT_MAX);
        }

        $this->assertSame('Rezervasyon detayları', $label);
        $this->assertSame('tr_TR', $controller, 'The translation controller left the switched locale.');
        $this->assertSame('Booking Details', __('Booking Details', 'mhm-rentiva'), 'Restoring the locale must bring English back.');
    }
}
