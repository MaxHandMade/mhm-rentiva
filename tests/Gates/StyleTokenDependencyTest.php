<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * A stylesheet that reads a token must be guaranteed the file that defines it.
 *
 * Measured 2026-08-27: the paid plugin registers the free plugin's
 * user-dashboard.css with an empty dependency array (VendorLedger.php:36, and
 * the same shape at VendorBookings.php:44). Today that is survivable only
 * because every one of those stylesheets carries its own copied block of
 * `--mhm-*` declarations. The slice that deletes those copies removes the only
 * thing keeping those pages coloured -- so the copies cannot go until the
 * dependency is real. Of the paid plugin's 32 registrations, exactly 2 name the
 * canonical handle, and both of those are admin.
 *
 * The definition gate (bin/check-token-definitions.php) knows WHICH files read
 * tokens. This gate knows whether a registration reaches the canonical handle.
 * Neither is sufficient alone: with only the first, the gate goes green while a
 * page renders uncoloured; with only the second, the copies quietly grow back.
 *
 * Three predicates defeated three rounds of prose, so they are fixtures here:
 *
 *   "reaches"    -- transitively, through other registered handles, not just
 *                   as a direct dependency.
 *   "resolved"   -- a call whose handle, source or dependencies are built from
 *                   variables cannot be decided by reading the source. It is
 *                   reported per record, never counted into a threshold that a
 *                   silent regression could hide inside.
 *   "which file" -- a registration may point at the OTHER plugin's tree through
 *                   that plugin's URL constant, and the file it names is the one
 *                   whose token reads matter.
 *
 * The scanner lives in bin/check-style-token-deps.php and nowhere else; this
 * test drives that file so the CI twin and the suite cannot disagree.
 */
final class StyleTokenDependencyTest extends WP_UnitTestCase
{
    /** @var list<string> */
    private array $temp_dirs = array();

    protected function tearDown(): void
    {
        foreach ($this->temp_dirs as $dir) {
            $this->remove_tree($dir);
        }
        $this->temp_dirs = array();
        parent::tearDown();
    }

    private function remove_tree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: array() as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->remove_tree($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @param array<string, string> $files
     */
    private function tree(array $files): string
    {
        $root = sys_get_temp_dir() . '/mhmdep' . uniqid('', true);
        mkdir($root, 0777, true);
        $this->temp_dirs[] = $root;

        foreach ($files as $rel => $body) {
            $path = $root . '/' . $rel;
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $body);
        }

        return $root;
    }

    /**
     * @param array<string, string> $constants constant name => tree root
     *
     * @return array{
     *     records: list<array{handle: string, file: string, site: string, reaches: bool, resolved: bool}>,
     *     failures: list<array{handle: string, file: string, site: string}>,
     *     unresolved: list<array{site: string, reason: string}>
     * }
     */
    private function report(array $roots, array $constants): array
    {
        require_once dirname(__DIR__, 2) . '/bin/check-style-token-deps.php';

        return mhmrentiva_style_dependency_report($roots, $constants, 'mhm-rentiva-css-variables');
    }

    private function css_that_reads_a_token(): string
    {
        return ".rv-a { color: var(--mhm-primary); }\n";
    }

    public function test_a_registration_that_names_the_canonical_handle_passes(): void
    {
        $root = $this->tree(array(
            'assets/css/a.css' => $this->css_that_reads_a_token(),
            'src/Enqueue.php'  => "<?php\nwp_enqueue_style('mhm-a', LITE_URL . 'assets/css/a.css', array('mhm-rentiva-css-variables'), '1');\n",
        ));

        $this->assertSame(array(), $this->report(array($root), array('LITE_URL' => $root))['failures']);
    }

    public function test_a_registration_reaches_the_canonical_handle_through_another_style(): void
    {
        $root = $this->tree(array(
            'assets/css/a.css' => $this->css_that_reads_a_token(),
            'src/Enqueue.php'  => "<?php\n"
                . "wp_register_style('mhm-base', LITE_URL . 'assets/css/base.css', array('mhm-rentiva-css-variables'), '1');\n"
                . "wp_enqueue_style('mhm-a', LITE_URL . 'assets/css/a.css', array('mhm-base'), '1');\n",
            'assets/css/base.css' => "/* no tokens */\n",
        ));

        $this->assertSame(array(), $this->report(array($root), array('LITE_URL' => $root))['failures']);
    }

    public function test_a_token_reading_stylesheet_registered_with_no_dependencies_fails(): void
    {
        $root = $this->tree(array(
            'assets/css/a.css' => $this->css_that_reads_a_token(),
            'src/Enqueue.php'  => "<?php\nwp_enqueue_style('mhm-a', LITE_URL . 'assets/css/a.css', array(), '1');\n",
        ));

        $failures = $this->report(array($root), array('LITE_URL' => $root))['failures'];

        $this->assertCount(1, $failures);
        $this->assertSame('mhm-a', $failures[0]['handle']);
    }

    public function test_a_stylesheet_that_reads_no_token_may_have_no_dependencies(): void
    {
        $root = $this->tree(array(
            'assets/css/plain.css' => ".rv-plain { color: #333; }\n",
            'src/Enqueue.php'      => "<?php\nwp_enqueue_style('mhm-plain', LITE_URL . 'assets/css/plain.css', array(), '1');\n",
        ));

        $this->assertSame(array(), $this->report(array($root), array('LITE_URL' => $root))['failures']);
    }

    /**
     * BlockRegistry.php:432 and AbstractShortcode.php:289 build handles at
     * runtime. A gate that quietly passes what it cannot read is worse than no
     * gate: it converts an unknown into a green. Each one is named.
     */
    public function test_a_registration_built_from_variables_is_reported_unresolved_not_passed(): void
    {
        $root = $this->tree(array(
            'assets/css/a.css' => $this->css_that_reads_a_token(),
            'src/Enqueue.php'  => "<?php\n\$handle = 'mhm-' . \$name;\nwp_enqueue_style(\$handle, LITE_URL . 'assets/css/a.css', array(), '1');\n",
        ));

        $report = $this->report(array($root), array('LITE_URL' => $root));

        $this->assertCount(1, $report['unresolved']);
        $this->assertStringContainsString('Enqueue.php:3', $report['unresolved'][0]['site']);
    }

    public function test_a_registration_pointing_at_the_other_tree_resolves_to_that_file(): void
    {
        $lite = $this->tree(array(
            'assets/css/shared.css' => $this->css_that_reads_a_token(),
        ));
        $pro  = $this->tree(array(
            'src/Ledger.php' => "<?php\nwp_enqueue_style('mhm-shared', LITE_URL . 'assets/css/shared.css', array(), '1');\n",
        ));

        $report = $this->report(array($lite, $pro), array('LITE_URL' => $lite, 'PRO_URL' => $pro));

        $this->assertCount(1, $report['failures'], 'the paid tree registering the free tree\'s token-reading file must be seen');
        $this->assertStringContainsString('shared.css', $report['failures'][0]['file']);
    }

    /**
     * The free plugin registers its core stylesheets from a static registry array
     * and enqueues them in a foreach, so the handle and the dependencies are both
     * variables at the call site (AssetManager.php:205). A gate that reads only
     * call sites cannot see the one place where the canonical dependency is
     * actually declared -- measured 2026-08-27, that blindness produced 4 false
     * failures, including AssetManager.php:926, which depends on
     * mhm-rentiva-core-css and does reach the canonical handle through it.
     */
    public function test_dependencies_declared_in_a_registry_array_are_read(): void
    {
        $root = $this->tree(array(
            'assets/css/core.css' => "/* no tokens */\n",
            'assets/css/a.css'    => $this->css_that_reads_a_token(),
            'src/AssetManager.php' => "<?php\nclass AssetManager {\n"
                . "\tprivate static array \$core_css = array(\n"
                . "\t\t'mhm-core' => array(\n"
                . "\t\t\t'url'  => 'assets/css/core.css',\n"
                . "\t\t\t'deps' => array('mhm-rentiva-css-variables'),\n"
                . "\t\t),\n"
                . "\t);\n"
                . "\tpublic static function go() {\n"
                . "\t\tforeach (self::\$core_css as \$handle => \$asset) {\n"
                . "\t\t\twp_register_style(\$handle, self::url(\$asset['url']), \$asset['deps'], '1');\n"
                . "\t\t}\n"
                . "\t}\n}\n",
            'src/Later.php' => "<?php\nwp_enqueue_style('mhm-a', LITE_URL . 'assets/css/a.css', array('mhm-core'), '1');\n",
        ));

        $report = $this->report(array($root), array('LITE_URL' => $root));

        $this->assertSame(
            array(),
            $report['failures'],
            'mhm-a depends on mhm-core, whose canonical dependency is declared in the registry array'
        );
    }

    /**
     * The scanner keys its "which files read tokens" map by the paths it walked,
     * and looks that map up with the path it resolved from a registration's URL
     * constant. If the two are spelled differently -- one walked from a relative
     * root, the other rebuilt as an absolute path -- every lookup misses and the
     * gate reports a clean tree.
     *
     * Measured 2026-08-27: invoking it as `php bin/check-style-token-deps.php .`
     * printed "failures 0" on a tree where the same scanner, given an absolute
     * path, printed 28. A gate that answers zero because it cannot match its own
     * bookkeeping is worse than no gate.
     */
    public function test_a_non_canonical_spelling_of_the_root_finds_the_same_failures(): void
    {
        $root = $this->tree(array(
            'assets/css/a.css' => $this->css_that_reads_a_token(),
            'src/Enqueue.php'  => "<?php\nwp_enqueue_style('mhm-a', LITE_URL . 'assets/css/a.css', array(), '1');\n",
        ));

        // Exactly the CLI's shape: the scanned root is spelled one way, while the
        // constant map that rebuilds a registration's path is spelled another.
        $awkward = $root . '/../' . basename($root);

        $this->assertCount(
            1,
            $this->report(array($awkward), array('LITE_URL' => $root))['failures'],
            'the walked path and the resolved path must be compared in one spelling'
        );
    }

    public function test_every_failure_names_the_call_site_that_produced_it(): void
    {
        $root = $this->tree(array(
            'assets/css/a.css' => $this->css_that_reads_a_token(),
            'src/Enqueue.php'  => "<?php\n\n\nwp_enqueue_style('mhm-a', LITE_URL . 'assets/css/a.css', array(), '1');\n",
        ));

        $failures = $this->report(array($root), array('LITE_URL' => $root))['failures'];

        $this->assertStringContainsString('Enqueue.php:4', $failures[0]['site']);
    }
}
