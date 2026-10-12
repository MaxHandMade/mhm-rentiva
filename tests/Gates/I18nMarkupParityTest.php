<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * A translation may move markup around a sentence. It may not add, drop or
 * change it.
 *
 * A dropped `<strong>` loses emphasis; a dropped `</a>` swallows the rest of
 * the page into a link; a changed URL sends a Turkish reader somewhere the
 * English one is not sent; a dropped `&nbsp;` re-wraps a price. None of these
 * is visible to msgfmt -c (it checks printf conversions), to
 * check-i18n-placeholders (it checks {{tokens}}), or to build-i18n.py (both
 * sides agree on the wrong msgstr).
 *
 * The scanner lives in bin/check-i18n-markup.php and nowhere else; this test
 * requires that file and drives it, so the CI twin and the suite cannot drift.
 */
final class I18nMarkupParityTest extends WP_UnitTestCase
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
     * @return array<int, array{msgid: string, context: string, form: string, missing: array<int, string>, extra: array<int, string>}>
     */
    private function scan(string $path, int &$scanned = 0): array
    {
        require_once dirname(__DIR__, 2) . '/bin/check-i18n-markup.php';

        return mhmrentiva_find_markup_mismatches($path, $scanned);
    }

    private function write_catalog(string $body): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mhmpo') . '.po';
        file_put_contents($file, $body);
        $this->temp_files[] = $file;

        return $file;
    }

    public function test_every_committed_catalog_keeps_its_markup(): void
    {
        $catalogs = glob(dirname(__DIR__, 2) . '/languages/*.po') ?: array();

        $this->assertNotEmpty($catalogs, 'No catalog was scanned, so a clean verdict would be about the glob.');

        foreach ($catalogs as $catalog) {
            $scanned = 0;
            $found   = $this->scan($catalog, $scanned);

            // The tr_TR catalog carried 9 markup-bearing translated strings
            // (e-mail bodies with <br>, a <strong>/<a> notice, four URLs) when
            // this bound was written.
            $this->assertGreaterThan(
                5,
                $scanned,
                basename($catalog) . ': the scan reached too few entries for its verdict to mean anything.'
            );

            $this->assertSame(
                array(),
                array_map(
                    static fn (array $f): string => sprintf(
                        '%s [%s] [eksik: %s] [fazla: %s]',
                        $f['msgid'],
                        $f['form'],
                        implode(',', $f['missing']) ?: '-',
                        implode(',', $f['extra']) ?: '-'
                    ),
                    $found
                ),
                basename($catalog) . ': a translation changed markup, entities or URLs.'
            );
        }
    }

    public function test_the_gate_catches_a_dropped_strong(): void
    {
        $file = $this->write_catalog(
            "msgid \"<strong>Note:</strong> Deposit is non-refundable.\"\n"
            . "msgstr \"Not: Kapora iade edilmez.\"\n"
        );

        $found = $this->scan($file);

        $this->assertCount(1, $found);
        $this->assertSame(array('</strong>', '<strong>'), $found[0]['missing']);
        $this->assertSame(array(), $found[0]['extra']);
    }

    public function test_the_gate_counts_markup_not_just_its_presence(): void
    {
        // Two <br> in, one out: a set comparison calls this clean.
        $file = $this->write_catalog(
            "msgid \"Line one<br>Line two<br>Line three\"\n"
            . "msgstr \"Birinci satir<br>Ikinci satir Ucuncu satir\"\n"
        );

        $found = $this->scan($file);

        $this->assertCount(1, $found);
        $this->assertSame(array('<br>'), $found[0]['missing']);
    }

    public function test_the_gate_catches_a_changed_url_and_entity(): void
    {
        $file = $this->write_catalog(
            "msgid \"See <a href=\\\"https://wpalemi.com/docs/\\\">docs</a>&nbsp;now.\"\n"
            . "msgstr \"<a href=\\\"https://wpalemi.com/tr/docs/\\\">Belgeler</a> simdi.\"\n"
        );

        $found = $this->scan($file);

        $this->assertCount(1, $found);
        $this->assertSame(array('&nbsp;', 'https://wpalemi.com/docs/'), $found[0]['missing']);
        $this->assertSame(array('https://wpalemi.com/tr/docs/'), $found[0]['extra']);
    }

    public function test_reordered_markup_is_clean(): void
    {
        $file = $this->write_catalog(
            "msgid \"Click <em>Save</em> to <strong>apply</strong>.\"\n"
            . "msgstr \"<strong>Uygulamak</strong> icin <em>Kaydet</em>'e tiklayin.\"\n"
        );

        $scanned = 0;
        $found   = $this->scan($file, $scanned);

        $this->assertSame(1, $scanned);
        $this->assertSame(array(), $found);
    }

    public function test_plural_forms_are_each_compared_with_their_source(): void
    {
        $file = $this->write_catalog(
            "msgid \"<b>%d</b> vehicle\"\n"
            . "msgid_plural \"<b>%d</b> vehicles\"\n"
            . "msgstr[0] \"<b>%d</b> arac\"\n"
            . "msgstr[1] \"%d arac\"\n"
        );

        $found = $this->scan($file);

        $this->assertCount(1, $found);
        $this->assertSame('msgstr[1]', $found[0]['form']);
        $this->assertSame(array('</b>', '<b>'), $found[0]['missing']);
    }

    public function test_untranslated_entries_are_not_this_gates_finding(): void
    {
        $file = $this->write_catalog(
            "msgid \"<strong>Bold</strong>\"\n"
            . "msgstr \"\"\n"
        );

        $scanned = 0;
        $this->assertSame(array(), $this->scan($file, $scanned));
        $this->assertSame(0, $scanned);
    }
}
