<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * The untranslated-entry gate: bin/check-po-untranslated.php.
 *
 * bin/check-i18n-placeholders.php skips untranslated entries and says why: "an
 * empty msgstr is a different finding with its own gate". That gate did not
 * exist -- 30 Lite composer scripts and 14 Pro gates were read for it (plan 2B
 * v3, measurement 8). This is it.
 *
 * It is fixture-tested in BOTH directions, and the fixtures carry the three
 * shapes an earlier draft of this gate got wrong without anyone running it:
 * a multi-line msgid (read as the header and skipped), a plural entry missing
 * msgstr[1] (not seen), and an entry with no msgstr key at all. Plus fuzzy.
 *
 * The clean fixture is the other half: 119 entries in Pro's tr_TR catalog open
 * with `msgstr ""` and continue on the next lines. A gate that reports those is
 * wrong, and clean.po failing is how that would show.
 *
 * Like I18nPlaceholderParityTest, this requires the scanner and drives it, so
 * the CI step and the suite cannot disagree about what counts as untranslated.
 */
final class PoUntranslatedGateTest extends WP_UnitTestCase
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

    // ── The scanner ─────────────────────────────────────────

    public function test_the_clean_fixture_has_no_finding(): void
    {
        $scanned  = 0;
        $findings = $this->scan($this->fixture('clean.po'), $scanned);

        $this->assertSame(array(), $findings, 'clean.po is fully translated; a finding here means the gate is wrong: ' . wp_json_encode($findings));
        $this->assertSame(6, $scanned, 'Six live entries: the header and the obsolete #~ entry are not counted. Five would mean the U+2705 entry was split and never read.');
    }

    public function test_every_live_entry_of_the_real_catalogs_is_read(): void
    {
        // The count that exposed the \R defect: the gate scanned 3252 of Lite's
        // 3253 live entries and 1766 of Pro's 1768, and reported both catalogs
        // clean. The reference count here is deliberately a different method --
        // `msgid ` line starts (after optional indentation, which WP-CLI
        // accepts), minus the header -- so a parser bug cannot agree with itself.
        $catalogs = glob(dirname(__DIR__, 2) . '/languages/*.po') ?: array();
        $pro      = dirname(__DIR__, 3) . '/mhm-rentiva-pro/languages';
        if (is_dir($pro)) {
            $catalogs = array_merge($catalogs, glob($pro . '/*.po') ?: array());
        }

        $this->assertNotEmpty($catalogs);

        foreach ($catalogs as $catalog) {
            $reference = preg_match_all('/^[ \t]*msgid /m', (string) file_get_contents($catalog)) - 1;
            $scanned   = 0;
            $this->scan($catalog, $scanned);

            $this->assertSame($reference, $scanned, basename($catalog) . ': the gate did not read every live entry.');
        }
    }

    public function test_the_dirty_fixture_reports_all_six_shapes_by_name(): void
    {
        $findings = $this->scan($this->fixture('dirty.po'));

        $by_shape = array();
        foreach ($findings as $finding) {
            $by_shape[ strtok($finding['msgid'], ' ') ] = $finding['reason'];
        }
        ksort($by_shape);

        $this->assertSame(
            array(
                'SHAPE-EMPTY-MSGSTR'        => 'empty',
                'SHAPE-FUZZY'               => 'fuzzy',
                'SHAPE-MISSING-PLURAL-FORM' => 'missing_plural_form',
                'SHAPE-MULTILINE-MSGID'     => 'empty',
                'SHAPE-NEL-BYTE'            => 'empty',
                'SHAPE-NO-MSGSTR-KEY'       => 'missing_msgstr',
            ),
            $by_shape,
            'Each of the six shapes must be reported exactly once, and the translated control entry not at all.'
        );
        $this->assertCount(6, $findings);
    }

    public function test_a_plural_catalog_with_three_forms_needs_all_three(): void
    {
        // nplurals is read from the catalog's own header, not assumed to be 2.
        $path = $this->write_catalog(
            "msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : 1);\\n\"\n\n"
            . "msgid \"%d item\"\nmsgid_plural \"%d items\"\nmsgstr[0] \"a\"\nmsgstr[1] \"b\"\n"
        );

        $findings = $this->scan($path);

        $this->assertCount(1, $findings);
        $this->assertSame('missing_plural_form', $findings[0]['reason']);
    }

    // ── Reading a catalog the way WP-CLI reads it (Codex, Lite #48) ──

    /**
     * WP-CLI compiles the catalogs WordPress loads, and its gettext Po extractor
     * trim()s every line: a line holding only spaces or tabs ends an entry, and
     * a directive may be indented. The gate split blocks on EMPTY lines only
     * and anchored every directive at column 0, so it read a different catalog
     * from the one that ships -- and in both cases the wrong read was "clean".
     */
    public function test_a_whitespace_only_separator_ends_the_header_like_an_empty_line(): void
    {
        $path = $this->write_catalog(
            "msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=2; plural=n != 1;\\n\"\n \t\n"
            . "msgid \"UNTRANSLATED-AFTER-A-TAB-LINE\"\nmsgstr \"\"\n"
        );

        $scanned  = 0;
        $findings = $this->scan($path, $scanned);

        $this->assertSame(1, $scanned, 'The entry was merged into the header block and inherited its msgstr.');
        $this->assertCount(1, $findings);
        $this->assertSame('empty', $findings[0]['reason']);
    }

    public function test_an_indented_entry_is_read(): void
    {
        $path = $this->write_catalog(
            "msgid \"\"\nmsgstr \"\"\n\n  msgid \"INDENTED-UNTRANSLATED\"\n\tmsgstr \"\"\n"
        );

        $scanned  = 0;
        $findings = $this->scan($path, $scanned);

        $this->assertSame(1, $scanned, 'An indented directive was ignored and the catalog read as empty and clean.');
        $this->assertCount(1, $findings);
        $this->assertSame(1, $this->cli(array( $path ))['code'], 'The CLI printed [OK] for a catalog it had not read.');
    }

    public function test_a_bare_hash_line_ends_an_entry_as_wp_cli_treats_it(): void
    {
        // WP-CLI's extractor turns a line that is exactly "#" into an empty
        // line. Without the same rule the untranslated entry below would be
        // merged into the header and inherit its non-empty msgstr.
        $path = $this->write_catalog(
            "msgid \"\"\nmsgstr \"\"\n\"Language: tr_TR\\n\"\n#\nmsgid \"AFTER-A-BARE-HASH\"\nmsgstr \"\"\n"
        );

        $findings = $this->scan($path);

        $this->assertCount(1, $findings);
        $this->assertSame('AFTER-A-BARE-HASH', $findings[0]['msgid']);
    }

    public function test_the_last_entry_of_a_file_without_a_final_newline_is_read(): void
    {
        // Every fixture ends with a newline, and a mutation removing the reader's
        // end-of-input separator changed nothing -- so the case it exists for
        // was untested. An editor that strips the final newline must not make
        // the last entry disappear from the scan.
        $path = $this->write_catalog("msgid \"\"\nmsgstr \"\"\n\nmsgid \"LAST-WITHOUT-NEWLINE\"\nmsgstr \"\"");

        $scanned  = 0;
        $findings = $this->scan($path, $scanned);

        $this->assertSame(1, $scanned);
        $this->assertCount(1, $findings);
    }

    public function test_an_indented_multi_line_value_is_joined(): void
    {
        $path = $this->write_catalog(
            "msgid \"\"\nmsgstr \"\"\n\nmsgid \"Split\"\nmsgstr \"\"\n   \"\"\n"
        );

        $findings = $this->scan($path);

        $this->assertCount(1, $findings, 'Two empty pieces are still an empty translation.');
    }

    public function test_an_obsolete_entry_is_never_a_finding(): void
    {
        $path = $this->write_catalog("msgid \"\"\nmsgstr \"\"\n\n#~ msgid \"Gone\"\n#~ msgstr \"\"\n");

        $this->assertSame(array(), $this->scan($path), 'WP-CLI skips disabled (#~) entries when it compiles; so does this gate.');
    }

    // ── The command line ────────────────────────────────────

    public function test_the_cli_exits_zero_on_the_clean_fixture(): void
    {
        $this->assertSame(0, $this->cli(array( $this->fixture('clean.po') ))['code']);
    }

    public function test_the_cli_exits_one_on_the_dirty_fixture_and_names_the_entries(): void
    {
        $run = $this->cli(array( $this->fixture('dirty.po') ));

        $this->assertSame(1, $run['code']);

        foreach (array( 'SHAPE-EMPTY-MSGSTR', 'SHAPE-MULTILINE-MSGID', 'SHAPE-MISSING-PLURAL-FORM', 'SHAPE-NO-MSGSTR-KEY', 'SHAPE-FUZZY', 'SHAPE-NEL-BYTE' ) as $name) {
            $this->assertStringContainsString($name, $run['output']);
        }
    }

    public function test_the_cli_exits_two_for_a_catalog_that_does_not_exist(): void
    {
        // Not a silent pass: a gate that finds nothing to read says so.
        $this->assertSame(2, $this->cli(array( sys_get_temp_dir() . '/no-such-catalog-' . wp_generate_password(8, false) . '.po' ))['code']);
    }

    public function test_the_cli_exits_two_when_a_plugin_dir_has_no_catalog(): void
    {
        $empty = sys_get_temp_dir() . '/mhm-po-gate-' . wp_generate_password(8, false);
        mkdir($empty . '/languages', 0777, true);

        try {
            $this->assertSame(2, $this->cli(array( '--plugin-dir', $empty ))['code']);
        } finally {
            rmdir($empty . '/languages');
            rmdir($empty);
        }
    }

    public function test_the_cli_reads_another_plugins_catalogs_with_plugin_dir(): void
    {
        $pro = dirname(__DIR__, 3) . '/mhm-rentiva-pro';

        if (! is_dir($pro . '/languages')) {
            $this->markTestSkipped('Pro is not checked out beside Lite.');
        }

        $run = $this->cli(array( '--plugin-dir', $pro ));

        $this->assertStringContainsString('mhm-rentiva-pro-tr_TR.po', $run['output'], 'The Pro catalog was not the one read: ' . $run['output']);
    }

    // ── Helpers ─────────────────────────────────────────────

    /**
     * @return array<int, array{msgid: string, context: string, reason: string}>
     */
    private function scan(string $path, int &$scanned = 0): array
    {
        require_once dirname(__DIR__, 2) . '/bin/check-po-untranslated.php';

        return mhmrentiva_find_untranslated_entries($path, $scanned);
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/po/' . $name;
    }

    private function write_catalog(string $body): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mhmpo') . '.po';
        file_put_contents($file, $body);
        $this->temp_files[] = $file;

        return $file;
    }

    /**
     * @param array<int, string> $args
     * @return array{code: int, output: string}
     */
    private function cli(array $args): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/check-po-untranslated.php');
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $output = array();
        $code   = 0;
        exec($command . ' 2>&1', $output, $code);

        return array( 'code' => $code, 'output' => implode("\n", $output) );
    }
}
