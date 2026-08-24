<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use WP_UnitTestCase;

/**
 * The store owns the currency, and it owns it everywhere.
 *
 * Slice 5, Minor debt M3. CurrencyHelper::get_currency_code() is the canonical
 * answer to "what currency is this store in": WooCommerce when active, the
 * plugin setting otherwise, defaulting to 'USD'. Eleven call sites across four
 * files answered the same question with a hardcoded 'TRY' instead -- a leftover
 * from the plugin's Turkish origin. That is not cosmetic: measured on the dev
 * site (2026-08-24) the store reads USD while a refund mail for a booking with
 * no stored currency reads TRY, so the same figure carries two different
 * currencies on the same install.
 *
 * Two locks, because either alone is weak:
 *
 * 1. A behavioural assert anchored to a LITERAL ('EUR'), not to
 *    get_currency_code()'s return. "Surface equals canonical" proves only that
 *    they agree; if the sweep bound every surface to a helper that was itself
 *    wrong, all of them would agree and all of them would be wrong.
 * 2. An inventory assert over src/, because a sweep that fixes N sites and
 *    writes N tests proves nothing about the members it never enumerated.
 */
final class CurrencyFallbackIsTheStoreTest extends WP_UnitTestCase
{
    /**
     * Files that legitimately spell 'TRY': the currency catalogue itself.
     *
     * Whole paths, never substrings -- a fragment like 'CurrencyHelper' would
     * also excuse a future CurrencyHelperFactory nobody audited.
     *
     * @var array<int, string>
     */
    private const CATALOGUE_FILES = array(
        'src/Admin/Core/CurrencyHelper.php',
        'src/Admin/Settings/Groups/GeneralSettings.php',
    );

    /**
     * Forces the store onto a currency that is neither 'TRY' nor any default,
     * through both branches of get_currency_code() so the test does not depend
     * on whether WooCommerce happens to be loaded in this suite run.
     */
    private function force_store_currency(string $code): void
    {
        add_filter('woocommerce_currency', static fn (): string => $code);

        $settings                        = get_option('mhmrentiva_settings', array());
        $settings                        = is_array($settings) ? $settings : array();
        $settings['mhmrentiva_currency'] = $code;
        update_option('mhmrentiva_settings', $settings);
    }

    /**
     * The anchored half of the lock, on a LIVE surface.
     *
     * It used to run against Templates::price_html(), which was measured on
     * 2026-08-24 to have zero callers in either tree -- so it proved the fix and
     * nothing a user can reach, and the method was deleted. This runs against
     * the e-mail context builder instead: 35 of the dev site's 39 bookings carry
     * no _mhmrentiva_payment_currency, so this fallback is the branch real
     * refund mail actually takes.
     */
    public function test_a_booking_with_no_stored_currency_falls_back_to_the_store(): void
    {
        $this->force_store_currency('EUR');

        // Loud precondition. If the environment refuses to be moved onto EUR,
        // this must fail HERE, saying so -- not further down as a confusing
        // assertion about the context while the store was never EUR at all.
        $this->assertSame(
            'EUR',
            CurrencyHelper::get_currency_code(),
            'The fixture could not move the store currency, so the assertion below would measure nothing.'
        );

        $booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        $this->assertSame(
            '',
            (string) get_post_meta((int) $booking_id, '_mhmrentiva_payment_currency', true),
            'The fixture must have NO stored currency, or it would never reach the fallback under test.'
        );

        $ctx = EmailTemplates::build_context('refund_customer', (int) $booking_id);

        $this->assertSame(
            'EUR',
            $ctx['booking']['payment']['currency'] ?? null,
            'A booking that never stored a currency must be described in the currency this shop sells in.'
        );
        $this->assertNotSame(
            'TRY',
            $ctx['booking']['payment']['currency'] ?? null,
            'This is the anchored assertion: it names the value the surface produced BEFORE the sweep, so'
                . ' binding every surface to a helper that is itself wrong cannot make it pass.'
        );
    }

    /**
     * @return array<int, string> Relative "path:line" entries, sorted.
     */
    private function find_hardcoded_try(string $root, int &$scanned): array
    {
        $offenders = array();
        $scanned   = 0;
        $base      = str_replace('\\', '/', dirname(__DIR__, 3)) . '/';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getPathname());
            if (0 === strpos($relative, $base)) {
                $relative = substr($relative, strlen($base));
            }

            if (in_array($relative, self::CATALOGUE_FILES, true)) {
                continue;
            }

            ++$scanned;

            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                continue;
            }

            // Asked of the format's own owner. A line-wise strpos() cannot tell
            // a string literal from a comment ABOUT one, so it flags the very
            // notes explaining why a site no longer hardcodes the currency --
            // a gate that fails on its own documentation trains people to
            // delete the documentation. token_get_all() sees only real
            // literals.
            foreach (token_get_all($contents) as $token) {
                if (! is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
                    continue;
                }

                if ("'TRY'" === $token[1] || '"TRY"' === $token[1]) {
                    $offenders[] = $relative . ':' . $token[2];
                }
            }
        }

        sort($offenders);

        return $offenders;
    }

    public function test_no_source_file_outside_the_currency_catalogue_hardcodes_try(): void
    {
        $scanned = 0;
        $offend  = $this->find_hardcoded_try(dirname(__DIR__, 3) . '/src', $scanned);

        // A scan that reached almost nothing reports "clean" and is telling the
        // truth about the wrong set. src/ carried well over 400 PHP files when
        // this bound was written; 250 stays clear of that while still ruling
        // out a probe that walked into an empty directory.
        $this->assertGreaterThan(
            250,
            $scanned,
            'The inventory scanned almost no files, so its "clean" verdict is a statement about its own reach.'
        );

        $this->assertSame(
            array(),
            $offend,
            "The store's currency has one canonical source, CurrencyHelper::get_currency_code(). These lines"
                . " answer the same question with a hardcoded 'TRY':\n  " . implode("\n  ", $offend)
        );
    }
}
