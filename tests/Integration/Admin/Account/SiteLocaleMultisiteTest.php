<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Account;

use MHMRentiva\Admin\Core\Utilities\SiteLocaleString;
use WP_UnitTestCase;

/**
 * SiteLocaleString::site_locale() has to answer for THIS site, and under a
 * network "this site" is not the network.
 *
 * The helper deliberately reads the stored option rather than get_locale(),
 * because get_locale() applies the `locale` filter and that is where
 * WP_Locale_Switcher installs itself -- a helper built on it would report the
 * switched locale and be a no-op in the one situation it exists for. That
 * decision is sound and is preserved here. What has to match core is the
 * ORDER in which the options are consulted.
 *
 * Core, wp-includes/l10n.php, get_locale() (read from the installed 7.1):
 *
 *     if ( is_multisite() ) {
 *         if ( wp_installing() ) {
 *             $ms_locale = get_site_option( 'WPLANG' );
 *         } else {
 *             $ms_locale = get_option( 'WPLANG' );
 *             if ( false === $ms_locale ) {
 *                 $ms_locale = get_site_option( 'WPLANG' );
 *             }
 *         }
 *         ...
 *
 * Two things that sentence says, and both are asserted below:
 *
 *  1. The SITE option is read first; the network option is only a fallback.
 *  2. The fallback is guarded by `false ===`, which means ABSENT, not empty.
 *     A subsite explicitly set to English stores '' -- core keeps that '' and
 *     resolves en_US, it does not inherit the network's locale. Reading the
 *     network option first, or falling back on `empty()`, silently rewrites
 *     that subsite's URLs into the network language.
 *
 * @group multisite
 *   Opted in to the multisite run (composer test:multisite). Under a single
 *   site the site option IS the only option and there is nothing to order.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\SiteLocaleString
 */
final class SiteLocaleMultisiteTest extends WP_UnitTestCase
{
    private const NETWORK_LOCALE = 'de_DE';
    private const SITE_LOCALE    = 'tr_TR';

    /**
     * Whatever the fixture site arrived with, so this file cannot leave the
     * suite's locale settings different from how it found them -- a suite that
     * mutates shared settings and does not give them back turns a later
     * unrelated failure into a hunt.
     *
     * @var array{site: mixed, network: mixed}
     */
    private array $previous;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previous = array(
            'site'    => get_option('WPLANG'),
            'network' => get_site_option('WPLANG'),
        );

        $this->allow_the_fixture_locales();
    }

    /**
     * 🔴 Without this the fixture cannot store a locale at all, and the file
     * measures nothing.
     *
     * Core sanitises WPLANG against the locales actually installed
     * (wp-includes/formatting.php, sanitize_option()):
     *
     *     case 'WPLANG':
     *         $allowed = get_available_languages();
     *         ...
     *         if ( ! in_array( $value, $allowed, true ) && ! empty( $value ) ) {
     *             $value = get_option( $option );
     *         }
     *
     * The suite has no language packs, so update_option('WPLANG','tr_TR')
     * is silently reverted and returns false -- the write looks like it
     * happened and did not. '' slips through only because `! empty( $value )`
     * exempts it, which is why an empty-locale assertion can pass in a file
     * where every other write was refused.
     *
     * Teaching core that these locales exist keeps the fixture inside core's
     * own contract; forcing the row past sanitize_option() would test a state
     * production can never reach.
     */
    private function allow_the_fixture_locales(): void
    {
        add_filter(
            'get_available_languages',
            static function (array $languages): array {
                $languages[] = self::NETWORK_LOCALE;
                $languages[] = self::SITE_LOCALE;

                return array_unique($languages);
            }
        );
    }

    protected function tearDown(): void
    {
        // Restore BEFORE dropping the filter: if the site arrived carrying a
        // real locale, putting it back is itself an update_option() and would
        // be refused by the same sanitiser the filter exists to satisfy.
        if (false === $this->previous['site']) {
            delete_option('WPLANG');
        } else {
            update_option('WPLANG', $this->previous['site']);
        }

        if (false === $this->previous['network']) {
            delete_site_option('WPLANG');
        } else {
            update_site_option('WPLANG', $this->previous['network']);
        }

        remove_all_filters('get_available_languages');

        parent::tearDown();
    }

    /**
     * The sentinel: without it this whole file would pass vacuously under the
     * single-site run, where is_multisite() is false and site_locale() reads
     * get_option() anyway. A green single-site run would then be read as
     * "multisite ordering is correct", which it never measured.
     */
    public function test_this_file_actually_runs_under_a_network(): void
    {
        if (! is_multisite()) {
            $this->markTestSkipped(
                'Multisite-only; run via: composer test:multisite'
            );
        }

        $this->assertTrue(is_multisite());
    }

    public function test_the_site_option_wins_over_the_network_option(): void
    {
        if (! is_multisite()) {
            $this->markTestSkipped('Multisite-only; run via: composer test:multisite');
        }

        update_site_option('WPLANG', self::NETWORK_LOCALE);
        update_option('WPLANG', self::SITE_LOCALE);

        $this->assertSame(
            self::SITE_LOCALE,
            SiteLocaleString::site_locale(),
            'A subsite with its own locale must resolve to it. Reading the '
            . 'network option first makes every subsite derive its URL slugs '
            . 'from the network language instead of its own.'
        );
    }

    public function test_the_network_option_is_the_fallback_when_the_site_has_none(): void
    {
        if (! is_multisite()) {
            $this->markTestSkipped('Multisite-only; run via: composer test:multisite');
        }

        update_site_option('WPLANG', self::NETWORK_LOCALE);
        delete_option('WPLANG');

        $this->assertSame(
            self::NETWORK_LOCALE,
            SiteLocaleString::site_locale(),
            'Negative control: the network option must still be honoured when '
            . 'the site has none, or the fix traded one wrong answer for another.'
        );
    }

    /**
     * The half that `false ===` buys and `empty()` throws away.
     */
    public function test_an_explicitly_english_subsite_does_not_inherit_the_network_locale(): void
    {
        if (! is_multisite()) {
            $this->markTestSkipped('Multisite-only; run via: composer test:multisite');
        }

        update_site_option('WPLANG', self::NETWORK_LOCALE);
        update_option('WPLANG', '');

        $this->assertSame(
            'en_US',
            SiteLocaleString::site_locale(),
            "A subsite set to English stores '' -- that is a decision, not an "
            . 'absence. Core distinguishes the two with `false ===`; falling '
            . 'back on empty() would hand this site the network locale.'
        );
    }

    /**
     * Nothing above would notice a helper that returned the site option under
     * a network but ignored it on a single site, so pin that end too.
     */
    public function test_a_site_without_either_option_resolves_to_english(): void
    {
        if (! is_multisite()) {
            $this->markTestSkipped('Multisite-only; run via: composer test:multisite');
        }

        delete_option('WPLANG');
        delete_site_option('WPLANG');

        $this->assertSame('en_US', SiteLocaleString::site_locale());
    }
}
