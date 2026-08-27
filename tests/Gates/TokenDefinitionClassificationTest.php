<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * Every `--mhm-*` token must land in exactly one class, and prose could not say which.
 *
 * Three independent audit rounds (two Fable lenses + Codex, 2026-08-27) failed the
 * same thing three different ways: the spec described the gate's predicates in
 * sentences, and sentences have no execution semantics. The blockers were not
 * disagreements about what SHOULD happen -- they were places where two honest
 * implementers would read the same paragraph and build different gates:
 *
 *   - `--mhm-columns` is written by templates/shortcodes/featured-vehicles.php:39
 *     and consumed in featured-vehicles.css. Under the spec's written rule it was
 *     not component-private (consumed in another file), not unused, not an orphan
 *     (it IS declared) -- so by elimination it became "shared", which the gate
 *     fails on. The gate could never go green while the spec also said to leave
 *     that token alone.
 *   - After the blueprint targets are renamed to `--mhm-bp-*`, all nine have no
 *     consumer anywhere in either tree, because their consumers live in
 *     blueprint-authored CSS that is not in the repository. Read literally they
 *     are all "unused", which moves the locked baseline from 3 to 12.
 *   - A PHP docblock that merely mentions `--mhm-primary:` (TokenMapper.php:29)
 *     looks exactly like a producer to a regex, and a template that legitimately
 *     reads `var(--mhm-primary)` looks like one too under a broader regex. One
 *     reading makes a required mutation test pass when it should fail; the other
 *     fails an innocent file.
 *
 * So the classification stops being prose. This file is the specification: each
 * case below is a fixture tree whose expected class is asserted. The scanner in
 * bin/check-token-definitions.php is the only implementation, and this test
 * drives that file directly so the CI twin and the suite cannot drift apart.
 *
 * The classes are positive and disjoint -- every token lands in exactly one:
 *
 *   canonical           declared in one of the canonical stylesheets
 *   component-private   declared in exactly one non-canonical CSS file, consumed
 *                       at least once, and every consumer is that same file
 *   unused              declared in exactly one non-canonical CSS file, consumed
 *                       nowhere
 *   runtime-parameter   written by a PHP producer, consumed from CSS
 *   blueprint-namespace `--mhm-bp-*`, written only by the blueprint token map
 *   orphan              consumed somewhere, declared in no universe
 *   shared              declared outside the canonical files AND either declared
 *                       in more than one file or consumed outside its own file
 *
 * Only `shared` fails. That is the whole gate.
 */
final class TokenDefinitionClassificationTest extends WP_UnitTestCase
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
     * Builds a fixture tree. Keys are paths relative to the tree root.
     *
     * @param array<string, string> $files
     */
    private function tree(array $files): string
    {
        $root = sys_get_temp_dir() . '/mhmtok' . uniqid('', true);
        mkdir($root, 0777, true);
        $this->temp_dirs[] = $root;

        foreach ($files as $rel => $body) {
            $path = $root . '/' . $rel;
            $dir  = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($path, $body);
        }

        return $root;
    }

    /**
     * @return array<string, string> token => class
     */
    private function classify(string $root): array
    {
        require_once dirname(__DIR__, 2) . '/bin/check-token-definitions.php';

        $report = mhmrentiva_classify_tokens(
            array($root),
            array($root . '/assets/css/core/css-variables.css')
        );

        return array_map(static fn (array $row): string => $row['class'], $report['tokens']);
    }

    public function test_a_token_declared_in_the_canonical_stylesheet_is_canonical(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'assets/css/frontend/card.css'      => ".rv-card { color: var(--mhm-primary); }\n",
        ));

        $this->assertSame('canonical', $this->classify($root)['--mhm-primary']);
    }

    /**
     * The intermediate state the migration actually passes through, and the one
     * that quietly disarmed the gate when it was first run for real.
     *
     * Moving a token INTO the canonical stylesheet does not delete the copies
     * that were already declaring it elsewhere -- that is a later step. A
     * classifier that answers "canonical" as soon as it sees a canonical
     * declaration stops seeing those copies, so the very PR whose job is to
     * delete them would run with no gate pressure at all. Measured 2026-08-27:
     * after the three shared tokens moved, `shared` dropped from 3 to 0 while 19
     * component stylesheets still declared every one of them.
     */
    public function test_a_token_declared_both_canonically_and_in_a_component_file_is_still_shared(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-text: #1d2327; }\n",
            'assets/css/frontend/a.css'         => "[class*=\"rv-\"] { --mhm-text: #1d2327; }\n",
        ));

        $this->assertSame(
            'shared',
            $this->classify($root)['--mhm-text'],
            'a canonical home does not excuse the copies still declaring the same token'
        );
    }

    public function test_a_token_used_only_inside_its_own_file_is_component_private(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'assets/css/frontend/routes.css'    => ".rv-r { --mhm-pr-bg: #fff; background: var(--mhm-pr-bg); }\n",
        ));

        $this->assertSame('component-private', $this->classify($root)['--mhm-pr-bg']);
    }

    public function test_a_token_declared_once_and_never_read_is_unused_not_private(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'assets/css/core/card.css'          => ".mhm-card { --mhm-card-shadow: 0 1px 2px #000; }\n",
        ));

        $this->assertSame('unused', $this->classify($root)['--mhm-card-shadow']);
    }

    public function test_a_token_read_from_another_file_is_shared(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'assets/css/frontend/a.css'         => ".rv-a { --mhm-gap: 8px; }\n",
            'assets/css/frontend/b.css'         => ".rv-b { margin: var(--mhm-gap); }\n",
        ));

        $this->assertSame('shared', $this->classify($root)['--mhm-gap']);
    }

    public function test_a_token_declared_in_two_files_is_shared(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'assets/css/frontend/a.css'         => "[class*=\"rv-\"] { --mhm-text: #1d2327; }\n",
            'assets/css/frontend/b.css'         => "[class*=\"rv-\"] { --mhm-text: #1d2327; }\n",
        ));

        $this->assertSame('shared', $this->classify($root)['--mhm-text']);
    }

    public function test_a_token_read_but_never_declared_is_an_orphan(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'assets/css/frontend/a.css'         => ".rv-a { transition: var(--mhm-ease-out); }\n",
        ));

        $this->assertSame('orphan', $this->classify($root)['--mhm-ease-out']);
    }

    /**
     * The first of the two blockers that closed the prose era.
     *
     * featured-vehicles.php writes --mhm-columns onto the element; the stylesheet
     * reads it. Declared in one file, consumed in another -- which is the literal
     * shape of "shared", and shared is the only failing class. Left as prose, the
     * gate could never go green on a tree the spec says to leave alone.
     */
    public function test_a_token_written_by_php_and_read_from_css_is_a_runtime_parameter(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'templates/grid.php'                => "<div style=\"--mhm-columns: <?php echo 3; ?>\">\n",
            'assets/css/frontend/grid.css'      => ".rv-grid { grid-template-columns: repeat(var(--mhm-columns), 1fr); }\n",
        ));

        $this->assertSame('runtime-parameter', $this->classify($root)['--mhm-columns']);
    }

    /**
     * The second blocker. After the blueprint targets are renamed, their consumers
     * are blueprint-authored stylesheets that this repository never sees. Counting
     * them as "unused" would move the locked baseline from 3 to 12 and the gate
     * would report a defect that is not one.
     */
    public function test_a_blueprint_namespace_token_without_consumers_is_not_unused(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'src/ContractRules.php'             => "<?php\nconst TOKEN_MAPPING = array('colors.primary' => '--mhm-bp-primary');\n",
        ));

        $this->assertSame('blueprint-namespace', $this->classify($root)['--mhm-bp-primary']);
    }

    public function test_the_same_token_declared_twice_unconditionally_in_the_canonical_file_is_a_violation(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root {\n\t--mhm-text-primary: #1f2937;\n\t--mhm-text-primary: #1f2937;\n}\n",
        ));

        require_once dirname(__DIR__, 2) . '/bin/check-token-definitions.php';
        $report = mhmrentiva_classify_tokens(
            array($root),
            array($root . '/assets/css/core/css-variables.css')
        );

        $this->assertContains('--mhm-text-primary', $report['duplicate_in_canonical']);
    }

    /**
     * The discriminator is file position, not conditionality: a dark-mode variant
     * inside the canonical file is the mechanism working, not a duplicate.
     */
    public function test_a_media_variant_inside_the_canonical_file_is_not_a_duplicate(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' =>
                ":root {\n\t--mhm-text-primary: #1f2937;\n}\n"
                . "@media (prefers-color-scheme: dark) {\n\t:root {\n\t\t--mhm-text-primary: #f0f0f1;\n\t}\n}\n",
        ));

        require_once dirname(__DIR__, 2) . '/bin/check-token-definitions.php';
        $report = mhmrentiva_classify_tokens(
            array($root),
            array($root . '/assets/css/core/css-variables.css')
        );

        $this->assertNotContains('--mhm-text-primary', $report['duplicate_in_canonical']);
    }

    /**
     * The green control the mutation set was missing: a template that READS a token
     * is not a producer. Without this case, a scanner broad enough to catch a
     * mutated TOKEN_MAPPING target also fails every innocent template.
     */
    public function test_a_php_file_that_only_reads_a_token_is_not_a_producer(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'templates/button.php'              => "<div style=\"color: var(--mhm-primary)\">\n",
        ));

        $this->assertSame('canonical', $this->classify($root)['--mhm-primary']);
    }

    /**
     * Set A -- the admin React palette in src-react/ -- is a self-contained token
     * system that satisfies its own reads with its own declarations. It shares two
     * names with set C (`--mhm-text`, `--mhm-card-bg`, measured 2026-08-27), and
     * pulling it into the classification universe turns both into false "shared"
     * violations that this slice is not allowed to fix. Its overlap is reported
     * elsewhere; it must not be classified here.
     */
    public function test_the_admin_react_palette_is_outside_the_classification_universe(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'assets/css/core/card.css'          => ".mhm-card { --mhm-card-bg: #fff; background: var(--mhm-card-bg); }\n",
            'src-react/shared/admin.css'        => ":root { --mhm-card-bg: #ffffff; --mhm-admin-only: #eee; }\n",
        ));

        $classes = $this->classify($root);

        $this->assertSame(
            'component-private',
            $classes['--mhm-card-bg'],
            'set A re-declaring the name must not make the CSS-side token shared'
        );
        $this->assertArrayNotHasKey('--mhm-admin-only', $classes, 'set A tokens must not be classified at all');
    }

    /**
     * TokenMapper.php:29 carries `--mhm-primary:#000;` inside a docblock as an
     * example of the string it builds. A regex that cannot tell code from comment
     * calls that file a producer, which is how the earlier probe in this project
     * mistook a docblock for an emitter.
     */
    public function test_a_token_named_only_in_a_php_docblock_is_not_a_producer(): void
    {
        $root = $this->tree(array(
            'assets/css/core/css-variables.css' => ":root { --mhm-primary: #2271b1; }\n",
            'src/Mapper.php'                    => "<?php\n/**\n * Returns a string such as \"--mhm-ghost: #000;\".\n */\nclass Mapper {}\n",
        ));

        $this->assertArrayNotHasKey('--mhm-ghost', $this->classify($root));
    }
}
