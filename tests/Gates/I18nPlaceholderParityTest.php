<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * A translation may change the words. It may not change the placeholders.
 *
 * Found 2026-08-24 while tracing why one refund e-mail showed two different
 * numbers for the same booking:
 *
 *   msgid  "Refund Processed for Booking #{{booking.order_id}}"
 *   msgstr "Rezervasyon #{{booking.id}} icin Geri Odeme Isleme Alindi"
 *
 * Templates.php:586 maps the customer-facing token to booking.order_id on
 * purpose -- the WooCommerce order number is the one a customer recognises.
 * booking.id is the internal post id. So the Turkish subject printed 9517 while
 * the Turkish body printed 9516, in the same message. Seven subjects carried the
 * identical substitution: confirmation, new-request, status-changed, reminder,
 * cancellation, refund-customer and refund-admin. Every transactional e-mail
 * this plugin sends in Turkish was inconsistent with itself.
 *
 * Nothing already in the pipeline can see this class, which is why it survived:
 *   - msgfmt -c validates printf conversions (%s, %d), not {{dot.path}}/{token};
 *     it reported the catalog clean with all seven present.
 *   - build-i18n.py --verify-only compares committed catalogs against the
 *     committed .po -- both sides agree on the wrong placeholder.
 *   - No test renders a translated subject.
 * The first observer is the customer.
 *
 * The scanner lives in bin/check-i18n-placeholders.php and nowhere else; this
 * test requires that file and drives it, so the CI twin and the suite cannot
 * drift into disagreeing about what counts as a mismatch.
 */
final class I18nPlaceholderParityTest extends WP_UnitTestCase
{
    /** @var list<string> */
    private array $temp_files = array();

    protected function tearDown(): void
    {
        foreach ($this->temp_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->temp_files = array();
        parent::tearDown();
    }

    /**
     * @return array<int, array{msgid: string, missing: array<int, string>, extra: array<int, string>}>
     */
    private function scan(string $path, int &$scanned = 0): array
    {
        require_once dirname(__DIR__, 2) . '/bin/check-i18n-placeholders.php';

        return mhmrentiva_find_placeholder_mismatches($path, $scanned);
    }

    private function write_catalog(string $body): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mhmpo') . '.po';
        file_put_contents($file, $body);
        $this->temp_files[] = $file;

        return $file;
    }

    public function test_every_committed_catalog_keeps_its_placeholders(): void
    {
        $catalogs = glob(dirname(__DIR__, 2) . '/languages/*.po') ?: array();

        $this->assertNotEmpty($catalogs, 'No catalog was scanned, so a clean verdict would be about the glob.');

        foreach ($catalogs as $catalog) {
            $scanned = 0;
            $found   = $this->scan($catalog, $scanned);

            // A scan that compared almost nothing reports "clean" truthfully and
            // uselessly. The tr_TR catalog carried 29 placeholder-bearing
            // translated strings when this bound was written.
            $this->assertGreaterThan(
                20,
                $scanned,
                basename($catalog) . ': the scan reached too few entries for its verdict to mean anything.'
            );

            $this->assertSame(
                array(),
                array_map(
                    static fn (array $f): string => sprintf(
                        '%s [eksik: %s] [fazla: %s]',
                        $f['msgid'],
                        implode(',', $f['missing']) ?: '-',
                        implode(',', $f['extra']) ?: '-'
                    ),
                    $found
                ),
                basename($catalog) . ': a translation changed a placeholder, which changes behaviour, not wording.'
            );
        }
    }

    public function test_the_gate_catches_a_substituted_placeholder(): void
    {
        $file = $this->write_catalog(
            "msgid \"Booking #{{booking.order_id}} Confirmed\"\n"
            . "msgstr \"Rezervasyon #{{booking.id}} Onaylandi\"\n"
        );

        $found = $this->scan($file);

        $this->assertCount(1, $found, 'The exact shape that shipped -- one token swapped for another -- must be caught.');
        $this->assertSame(array('{{booking.order_id}}'), $found[0]['missing']);
        $this->assertSame(array('{{booking.id}}'), $found[0]['extra']);
    }

    public function test_the_gate_catches_a_dropped_placeholder(): void
    {
        $file = $this->write_catalog(
            "msgid \"Booking #{{booking.order_id}} Confirmed\"\n"
            . "msgstr \"Rezervasyonunuz onaylandi\"\n"
        );

        $found = $this->scan($file);

        $this->assertCount(1, $found, 'A dropped placeholder means the value never reaches the reader at all.');
        $this->assertSame(array('{{booking.order_id}}'), $found[0]['missing']);
        $this->assertSame(array(), $found[0]['extra']);
    }

    public function test_the_gate_catches_the_single_brace_family_too(): void
    {
        $file = $this->write_catalog(
            "msgid \"Wait {minutes} minutes\"\n"
            . "msgstr \"{dakika} dakika bekleyin\"\n"
        );

        $found = $this->scan($file);

        $this->assertCount(1, $found, 'replace_placeholders() resolves {token} in its second pass, so that family counts too.');
    }

    /**
     * Negative control. Without it, a scanner that flagged everything would pass
     * all three positives above and still be worthless.
     */
    public function test_a_faithful_translation_is_not_flagged(): void
    {
        $file = $this->write_catalog(
            "msgid \"Booking #{{booking.order_id}} Confirmed - {{site.name}}\"\n"
            . "msgstr \"{{site.name}} - #{{booking.order_id}} numarali rezervasyon onaylandi\"\n"
            . "\n"
            . "msgid \"No placeholders here\"\n"
            . "msgstr \"Burada yer tutucu yok\"\n"
        );

        $scanned = 0;
        $found   = $this->scan($file, $scanned);

        $this->assertSame(array(), $found, 'Reordering placeholders is legitimate translation; only the SET must match.');
        $this->assertSame(1, $scanned, 'The entry carrying no placeholder must not be counted as compared.');
    }
}
