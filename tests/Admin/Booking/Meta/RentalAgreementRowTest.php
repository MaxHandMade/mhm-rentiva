<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox;
use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use MHMRentiva\Helpers\AgreementText;
use WP_UnitTestCase;

/**
 * Where the recorded rental agreement acceptance shows: the booking's
 * Customer Account box, the personal data export, and the meta inventory
 * that keeps the two keys from being reported as unknown.
 */
final class RentalAgreementRowTest extends WP_UnitTestCase
{
    private const VERSION = '3f9a1c2e0000000000000000000000000000000000000000000000000000abcd';

    /**
     * @param array<string, string> $meta
     */
    private function booking(array $meta): int
    {
        return self::factory()->post->create(array(
            'post_type'  => 'mhmrentiva_booking',
            'meta_input' => $meta,
        ));
    }

    private function box(int $booking_id): string
    {
        ob_start();
        BookingPortalMetaBox::render(get_post($booking_id));

        return (string) ob_get_clean();
    }

    public function test_an_accepted_booking_shows_when_and_which_text(): void
    {
        update_option('timezone_string', 'Europe/Istanbul');
        update_option('date_format', 'd.m.Y');
        update_option('time_format', 'H:i');
        $id = $this->booking(array(
            '_mhmrentiva_booking_type'      => 'booking_form',
            '_mhmrentiva_terms_accepted_at' => '2026-11-18 11:32:00',
            '_mhmrentiva_terms_version'     => self::VERSION,
        ));

        $html = $this->box($id);

        $this->assertStringContainsString('Rental agreement', $html);
        $this->assertStringContainsString('Accepted', $html);
        $this->assertStringContainsString('18.11.2026 14:32 · version 3f9a1c2e', $html, 'Site time, short version.');
    }

    public function test_an_admin_booking_says_nothing_was_accepted(): void
    {
        $id = $this->booking(array( '_mhmrentiva_booking_type' => 'manual' ));

        $this->assertStringContainsString('No acceptance (admin booking)', $this->box($id));
    }

    public function test_a_form_booking_made_while_the_agreement_was_off_shows_no_line(): void
    {
        $id = $this->booking(array( '_mhmrentiva_booking_type' => 'booking_form' ));

        $this->assertStringNotContainsString('data-mhm-agreement', $this->box($id));
    }

    public function test_a_malformed_record_is_not_shown_as_accepted(): void
    {
        $this->assertSame('', AgreementText::acceptance_line('yesterday', self::VERSION));
        $this->assertSame('', AgreementText::acceptance_line('2026-11-18 11:32:00', 'not-a-hash'));
    }

    public function test_the_personal_data_export_carries_the_acceptance(): void
    {
        $id = $this->booking(array(
            '_mhmrentiva_customer_email'    => 'jane@example.com',
            '_mhmrentiva_terms_accepted_at' => '2026-11-18 11:32:00',
            '_mhmrentiva_terms_version'     => self::VERSION,
        ));

        $method = new \ReflectionMethod(\MHMRentiva\Admin\Privacy\BookingExporter::class, 'fields');
        $method->setAccessible(true);
        $names = array_column($method->invoke(null, $id), 'value', 'name');

        $this->assertArrayHasKey('Rental agreement accepted', $names);
        $this->assertStringContainsString('version 3f9a1c2e', $names['Rental agreement accepted']);
    }

    public function test_the_two_keys_are_known_meta(): void
    {
        $known = DatabaseCleaner::valid_meta_keys();

        $this->assertContains('_mhmrentiva_terms_accepted_at', $known);
        $this->assertContains('_mhmrentiva_terms_version', $known);
    }
}
