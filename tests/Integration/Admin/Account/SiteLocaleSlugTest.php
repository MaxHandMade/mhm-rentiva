<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Account;

use MHMRentiva\Admin\Core\Utilities\SiteLocaleString;
use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use WP_UnitTestCase;

/**
 * A URL slug is an identifier, not a label, and rewrite rules have no locale
 * dimension: they live in one global option. determine_locale() answers with
 * the USER's locale inside wp-admin (wp-includes/l10n.php:150), so a slug
 * resolved from whatever locale the request happens to carry lets an
 * administrator whose profile language differs rewrite the URLs every visitor
 * sees -- and makes two flush triggers disagree about the endpoint set.
 *
 * 🔴 Two traps this file had to be rewritten around, both found by mutating the
 * source and watching nothing go red:
 *
 * 1. The suite's own locale is en_US, so switching to en_US proved nothing.
 *    Every switch here goes to a locale that differs from the site's.
 * 2. The suite loads no translation catalogue, so _x() returns its source in
 *    every locale and the two answers were identical whatever the code did.
 *    A gettext filter below makes the two locales genuinely disagree.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\SiteLocaleString
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration
 */
final class SiteLocaleSlugTest extends WP_UnitTestCase
{
    private const OTHER_LOCALE = 'de_DE';

    protected function tearDown(): void
    {
        while (is_locale_switched()) {
            restore_previous_locale();
        }

        remove_all_filters('gettext_with_context');
        WooCommerceIntegration::clear_slug_cache();
        parent::tearDown();
    }

    /**
     * Make _x() answer differently per locale, which the suite's empty
     * catalogue otherwise never does.
     */
    private function translate_slugs_per_locale(): void
    {
        add_filter(
            'gettext_with_context',
            static function (string $translation, string $text, string $context): string {
                if ('endpoint slug' !== $context) {
                    return $translation;
                }

                return self::OTHER_LOCALE === determine_locale()
                    ? 'anderer-slug'
                    : $translation;
            },
            10,
            3
        );
    }

    public function test_it_resolves_in_the_site_locale_while_another_is_switched_in(): void
    {
        $site = SiteLocaleString::site_locale();
        $this->assertNotSame(self::OTHER_LOCALE, $site, 'Precondition: the two locales must differ.');

        switch_to_locale(self::OTHER_LOCALE);
        $this->assertSame(self::OTHER_LOCALE, determine_locale(), 'Precondition: the switch took effect.');

        $seen = SiteLocaleString::resolve(static fn (): string => determine_locale());

        $this->assertSame($site, $seen, 'The resolver must answer in the site locale, not the asker\'s.');
    }

    public function test_it_restores_the_previous_locale_afterwards(): void
    {
        switch_to_locale(self::OTHER_LOCALE);

        SiteLocaleString::resolve(static fn (): string => 'x');

        $this->assertSame(self::OTHER_LOCALE, determine_locale(), 'The switch must be put back.');
    }

    public function test_it_restores_the_previous_locale_even_when_the_resolver_throws(): void
    {
        switch_to_locale(self::OTHER_LOCALE);

        try {
            SiteLocaleString::resolve(static function (): string {
                throw new \RuntimeException('boom');
            });
            $this->fail('The exception should have propagated.');
        } catch (\RuntimeException $e) {
            unset($e);
        }

        $this->assertSame(self::OTHER_LOCALE, determine_locale(), 'A throwing resolver must not leave the locale switched.');
    }

    /**
     * site_locale() must read past the `locale` filter, because that filter is
     * where WP_Locale_Switcher installs itself -- a helper built on get_locale()
     * is a no-op inside a switch, which is the only place it matters.
     */
    public function test_the_site_locale_does_not_follow_a_switch(): void
    {
        $before = SiteLocaleString::site_locale();

        switch_to_locale(self::OTHER_LOCALE);

        $this->assertSame(self::OTHER_LOCALE, get_locale(), 'Precondition: get_locale() does follow the switch.');
        $this->assertSame($before, SiteLocaleString::site_locale(), 'site_locale() must not.');
    }

    /**
     * The property has to hold where it actually matters: the slug that reaches
     * the rewrite rules. 'view_booking' is used because it has no shortcode
     * page to resolve from, so resolution reaches the translation step.
     */
    public function test_the_endpoint_slug_does_not_follow_the_switched_locale(): void
    {
        $this->translate_slugs_per_locale();

        WooCommerceIntegration::clear_slug_cache();
        $site_answer = WooCommerceIntegration::get_endpoint_slug('view_booking');

        WooCommerceIntegration::clear_slug_cache();
        switch_to_locale(self::OTHER_LOCALE);
        $switched_answer = WooCommerceIntegration::get_endpoint_slug('view_booking');

        $this->assertNotSame(
            'anderer-slug',
            $switched_answer,
            'The slug followed the asker; that rewrites the URLs everyone else sees.'
        );
        $this->assertSame($site_answer, $switched_answer);
    }

    /**
     * The cache still has to work -- the locale pinning must not turn every
     * call into a switch_to_locale() round trip.
     *
     * 📌 The other half of the guard, "do not cache a slug resolved before
     * init", is two lines in get_endpoint_slug() and is NOT exercised here:
     * did_action('init') is already true in a booted suite and faking it would
     * need a seam that exists only for the test. Recorded rather than papered
     * over with a test that cannot fail.
     */
    public function test_the_cache_still_serves_the_second_call(): void
    {
        WooCommerceIntegration::clear_slug_cache();

        $first = WooCommerceIntegration::get_endpoint_slug('bookings');

        $this->assertTrue(
            WooCommerceIntegration::slug_cache_is_warm('bookings'),
            'A slug resolved after init should be cached for the rest of the request.'
        );
        $this->assertSame($first, WooCommerceIntegration::get_endpoint_slug('bookings'));
    }
}
