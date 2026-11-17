<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Groups\BookingSettings;
use WP_UnitTestCase;

/**
 * The rental agreement settings: off by default, and an empty text resolves
 * to the built-in template, so an enabled agreement always has text.
 */
final class RentalAgreementSettingsTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        delete_option('mhmrentiva_settings');
        parent::tearDown();
    }

    public function test_off_by_default_with_the_template_as_text(): void
    {
        delete_option('mhmrentiva_settings');

        $agreement = BookingSettings::rental_agreement();

        $this->assertFalse($agreement['enabled']);
        $this->assertSame(BookingSettings::rental_agreement_template(), $agreement['text']);
    }

    public function test_a_stored_empty_text_resolves_to_the_template(): void
    {
        update_option('mhmrentiva_settings', array(
            'mhmrentiva_rental_agreement_enabled' => '1',
            'mhmrentiva_rental_agreement_text'    => '',
        ));

        $agreement = BookingSettings::rental_agreement();

        $this->assertTrue($agreement['enabled']);
        $this->assertSame(BookingSettings::rental_agreement_template(), $agreement['text']);
    }

    public function test_a_stored_text_is_used_as_written(): void
    {
        update_option('mhmrentiva_settings', array(
            'mhmrentiva_rental_agreement_enabled' => '1',
            'mhmrentiva_rental_agreement_text'    => "## Our terms\n- Be kind",
        ));

        $this->assertSame("## Our terms\n- Be kind", BookingSettings::rental_agreement()['text']);
    }

    public function test_the_template_covers_the_rental_and_names_no_party(): void
    {
        $template = BookingSettings::rental_agreement_template();

        foreach (array('Driver', 'Fuel', 'Damage', 'Fines', 'Cancellation', 'Personal data') as $topic) {
            $this->assertStringContainsString($topic, $template, $topic);
        }
        $this->assertStringNotContainsString('[', $template, 'No placeholders to fill in.');
        $this->assertStringNotContainsString('MHM', $template);
        $this->assertSame(7, substr_count(\MHMRentiva\Helpers\AgreementText::to_html($template), '<h4>'));
    }

    public function test_the_defaults_carry_the_two_keys_resolved(): void
    {
        $defaults = BookingSettings::get_default_settings();

        $this->assertSame('0', $defaults['mhmrentiva_rental_agreement_enabled']);
        $this->assertIsString($defaults['mhmrentiva_rental_agreement_text']);
        $this->assertSame(BookingSettings::rental_agreement_template(), $defaults['mhmrentiva_rental_agreement_text']);
    }

    public function test_the_booking_tab_save_keeps_the_switch_and_the_line_breaks(): void
    {
        $out = SettingsSanitizer::sanitize(array(
            'current_active_tab'                  => 'booking',
            'mhmrentiva_rental_agreement_enabled' => '1',
            'mhmrentiva_rental_agreement_text'    => "## Terms\n- one <b>x</b>\n- two",
        ));

        $this->assertSame('1', $out['mhmrentiva_rental_agreement_enabled']);
        $this->assertSame("## Terms\n- one x\n- two", $out['mhmrentiva_rental_agreement_text']);

        $off = SettingsSanitizer::sanitize(array('current_active_tab' => 'booking'));
        $this->assertSame('0', $off['mhmrentiva_rental_agreement_enabled']);
    }

    public function test_the_booking_tab_renders_both_fields(): void
    {
        set_current_screen('dashboard');
        BookingSettings::register();

        ob_start();
        BookingSettings::render_settings_section();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('mhmrentiva_rental_agreement_enabled', $html);
        $this->assertStringContainsString('mhmrentiva_rental_agreement_text', $html);
        $this->assertSame(SettingsCore::get('mhmrentiva_rental_agreement_text', ''), BookingSettings::rental_agreement_template());
    }
}
