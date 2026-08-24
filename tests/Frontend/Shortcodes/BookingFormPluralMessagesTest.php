<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use WP_UnitTestCase;

/**
 * The booking form's two duration messages must agree with the number the
 * browser substitutes into them.
 *
 * Slice 5, Minor debt M1 -- the last two members of the class. They were left
 * out of the first pass on the belief that the count lives in the browser, so
 * no server-side _n() could choose the form. Measured 2026-08-24, that belief
 * was wrong: booking-form.js reads
 * window.mhmRentivaBookingForm.config.min_days, which BookingForm::get_js_config()
 * localizes from SettingsCore -- the same setting Util::validate() reads. The
 * count is known at localize time, it is a single value, and _n() works.
 *
 * The invariant this locks is not "the string is translated" but "the FORM
 * matches the NUMBER": the message and the count are two halves of one sentence
 * assembled in two different languages, and nothing but a shared source keeps
 * them from drifting apart. Both now come from min_rental_days() /
 * max_rental_days().
 *
 * Assertions are anchored to literals ("day." vs "days."), not to what _n()
 * returns -- asserting the surface equals the helper would pass even if both
 * were wrong together.
 */
final class BookingFormPluralMessagesTest extends WP_UnitTestCase
{
    /**
     * @param array<string, int> $values
     */
    private function set_rental_day_settings(array $values): void
    {
        $settings = get_option('mhmrentiva_settings', array());
        $settings = is_array($settings) ? $settings : array();

        foreach ($values as $key => $value) {
            $settings[$key] = $value;
        }

        update_option('mhmrentiva_settings', $settings);
    }

    /**
     * @return array{strings: array<string, string>, config: array<string, mixed>}
     */
    private function localized_data(): array
    {
        $method = new \ReflectionMethod(BookingForm::class, 'get_localized_data');
        $method->setAccessible(true);

        /** @var array{strings: array<string, string>, config: array<string, mixed>} $data */
        $data = $method->invoke(null);

        return $data;
    }

    public function test_singular_setting_produces_the_singular_message(): void
    {
        $this->set_rental_day_settings(array(
            'mhmrentiva_vehicle_min_rental_days' => 1,
            'mhmrentiva_vehicle_max_rental_days' => 1,
        ));

        $data = $this->localized_data();

        $this->assertSame(
            1,
            (int) $data['config']['min_days'],
            'The fixture did not move the setting, so the message assertion below would measure nothing.'
        );

        $this->assertStringContainsString(
            '%d day.',
            $data['strings']['min_days_error'],
            'A one-day minimum must not tell the customer "Minimum rental period is 1 days."'
        );
        $this->assertStringNotContainsString(
            '%d days.',
            $data['strings']['min_days_error'],
            'The plural form leaked into a singular count.'
        );
        $this->assertStringContainsString('%d day.', $data['strings']['max_days_error']);
    }

    public function test_plural_setting_produces_the_plural_message(): void
    {
        $this->set_rental_day_settings(array(
            'mhmrentiva_vehicle_min_rental_days' => 3,
            'mhmrentiva_vehicle_max_rental_days' => 30,
        ));

        $data = $this->localized_data();

        $this->assertSame(3, (int) $data['config']['min_days']);
        $this->assertSame(30, (int) $data['config']['max_days']);

        $this->assertStringContainsString('%d days.', $data['strings']['min_days_error']);
        $this->assertStringContainsString('%d days.', $data['strings']['max_days_error']);
    }

    /**
     * The two halves must come from one source. If get_js_config() ever reads
     * the setting again on its own while get_localized_strings() keeps its own
     * read, a change to one is a silent mismatch in the other -- the sentence
     * would say "day" while the browser substitutes 7.
     */
    public function test_the_message_form_and_the_substituted_number_cannot_disagree(): void
    {
        foreach (array(1, 2, 7) as $days) {
            $this->set_rental_day_settings(array(
                'mhmrentiva_vehicle_min_rental_days' => $days,
                'mhmrentiva_vehicle_max_rental_days' => $days,
            ));

            $data     = $this->localized_data();
            $number   = (int) $data['config']['min_days'];
            $message  = $data['strings']['min_days_error'];
            $singular = ( 1 === $number );

            $this->assertSame(
                $days,
                $number,
                'config.min_days drifted from the setting; booking-form.js substitutes this value.'
            );
            $this->assertSame(
                $singular,
                str_contains($message, '%d day.') && ! str_contains($message, '%d days.'),
                sprintf(
                    'For %d day(s) the message form and the number disagree: %s',
                    $number,
                    $message
                )
            );
        }
    }
}
