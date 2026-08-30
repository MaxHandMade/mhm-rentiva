<?php
/**
 * Definition gate for the `--mhm-*` design tokens.
 *
 * A shared token must have exactly one home. Measured 2026-08-27 across both
 * trees: 36 stylesheets carried 268 declarations of 151 tokens, and
 * `--mhm-primary` alone was declared 33 times behind five different
 * expressions and three different fallback colours -- so the same name painted
 * a different blue depending on which stylesheet's selector happened to match.
 *
 * Three audit rounds failed the prose that tried to describe this gate. The
 * failures were never about intent; they were about predicates two honest
 * implementers would read differently. So the predicates live here, and
 * tests/Gates/TokenDefinitionClassificationTest.php drives this file directly
 * -- the CI twin and the suite cannot drift into disagreeing about what counts.
 *
 * Every token lands in exactly one class:
 *
 *   canonical           declared in a canonical stylesheet
 *   component-private   declared in exactly one non-canonical CSS file, read at
 *                       least once, and every reader is that same file
 *   unused              declared in exactly one non-canonical CSS file, read
 *                       nowhere
 *   runtime-parameter   written by a PHP producer, read from CSS
 *   blueprint-namespace `--mhm-bp-*`, named only by the blueprint token map;
 *                       its readers are blueprint-authored stylesheets that
 *                       this repository never sees, so absence of a reader here
 *                       is not a defect
 *   orphan              read somewhere, declared in no universe
 *   shared              declared outside the canonical files AND either
 *                       declared in more than one file or read outside its own
 *
 * Only `shared` fails. `unused` and `orphan` are reported BY NAME, not against
 * a manifest: an accepted-list would turn a deferred decision into a permanent
 * baseline, and this project has already been bitten by a bucket that read as
 * a baseline when it was really a "could not decide" list.
 *
 * The gate also prints `[SET-A-OVERLAP]`: names that the admin React palette
 * (set A, skipped by the walker below) and the classified universe BOTH
 * declare. Set A being out of scope is a decision; drifting unseen is not.
 *
 * PHP is read through token_get_all() rather than a regex, because a docblock
 * that shows `--mhm-primary:#000;` as an example (TokenMapper.php:29) is
 * indistinguishable from an emitter to any pattern that cannot tell code from
 * comment -- a trap this project has already fallen into once.
 *
 * Usage:
 *   php bin/check-token-definitions.php [--report-only] [path ...]
 */

declare(strict_types=1);

/**
 * One spelling for every path the gates compare.
 *
 * The walker keys its maps by the paths it walked; the dependency gate looks
 * those maps up with a path it rebuilt from a URL constant. Spelled differently
 * -- one relative, one absolute -- every lookup misses and the gate reports a
 * clean tree. Measured 2026-08-27: the dependency gate printed "failures 0" on
 * a tree where the same scanner, given an absolute root, printed 28.
 */
function mhmrentiva_real(string $path): string
{
    $real = realpath($path);

    return strtr(false === $real ? $path : $real, '\\', '/');
}

/**
 * Strips CSS comments while preserving byte offsets' ordering.
 */
function mhmrentiva_strip_css_comments(string $css): string
{
    return (string) preg_replace('!/\*.*?\*/!s', ' ', $css);
}

/**
 * Splits a stylesheet into declarations, recording whether each sits inside an
 * at-rule block. The discriminator for a duplicate is file position, not
 * conditionality: a dark-mode variant in the canonical file is the mechanism
 * working, so it must not read as a second declaration of the same token.
 *
 * @return array<int, array{token: string, conditional: bool}>
 */
function mhmrentiva_css_declarations(string $css): array
{
    $css     = mhmrentiva_strip_css_comments($css);
    $found   = array();
    $stack   = array();
    $chunk   = '';
    $length  = strlen($css);

    for ($i = 0; $i < $length; $i++) {
        $char = $css[$i];

        if ('{' === $char) {
            $stack[] = ('@' === substr(ltrim($chunk), 0, 1));
            $chunk   = '';
            continue;
        }

        if ('}' === $char) {
            array_pop($stack);
            $chunk = '';
            continue;
        }

        if (';' === $char) {
            $chunk = '';
            continue;
        }

        $chunk .= $char;

        if (':' === $char && preg_match('/(--mhm-[a-z0-9-]+)\s*:$/i', $chunk, $m)) {
            $found[] = array(
                'token'       => strtolower($m[1]),
                'conditional' => in_array(true, $stack, true),
            );
        }
    }

    return $found;
}

/**
 * @return list<string>
 */
function mhmrentiva_css_consumptions(string $css): array
{
    preg_match_all('/var\(\s*(--mhm-[a-z0-9-]+)/i', mhmrentiva_strip_css_comments($css), $m);

    return array_map('strtolower', $m[1]);
}

/**
 * Reads PHP through the language's own lexer, so a comment can never be
 * mistaken for a producer and a string that merely reads a token can never be
 * mistaken for one either.
 *
 * @return array{produces: list<string>, consumes: list<string>, blueprint: list<string>}
 */
function mhmrentiva_php_token_usage(string $code): array
{
    $produces  = array();
    $consumes  = array();
    $blueprint = array();

    $carriers = array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML);

    foreach (token_get_all($code) as $token) {
        if (! is_array($token) || ! in_array($token[0], $carriers, true)) {
            continue;
        }

        $text = $token[1];

        if (preg_match_all('/var\(\s*(--mhm-[a-z0-9-]+)/i', $text, $m)) {
            foreach ($m[1] as $name) {
                $consumes[] = strtolower($name);
            }
        }

        // A producer assigns: `--mhm-x: <value>`. A reader never does.
        $without_reads = (string) preg_replace('/var\(\s*--mhm-[a-z0-9-]+/i', ' ', $text);
        if (preg_match_all('/(--mhm-[a-z0-9-]+)\s*:/i', $without_reads, $m)) {
            foreach ($m[1] as $name) {
                $produces[] = strtolower($name);
            }
        }

        // A blueprint mapping target is a bare quoted name, never an assignment.
        //
        // Two namespaces, deliberately. `--mhmui-bp-*` is the LIVE set, owned by
        // mhm/ui-core's TokenMapper since v0.6.0 and the only one this plugin
        // still writes into page content. `--mhm-bp-*` is what
        // src/Layout/Config/ContractRules.php still names; that class has had no
        // reader since the engine moved and is kept on purpose rather than
        // deleted, so its names stay recognised instead of surfacing as orphans.
        if (preg_match('/^[\'"](--mhm(?:ui)?-bp-[a-z0-9-]+)[\'"]$/i', trim($text), $m)) {
            $blueprint[] = strtolower($m[1]);
        }
    }

    return array(
        'produces'  => $produces,
        'consumes'  => $consumes,
        'blueprint' => $blueprint,
    );
}

/**
 * @return list<string>
 */
function mhmrentiva_walk(string $root, string $extension): array
{
    if (! is_dir($root)) {
        return array();
    }

    // `src-react` is set A: a self-contained palette that satisfies its own reads
    // with its own declarations. It shares two names with set C, so scanning it
    // here would invent "shared" violations this slice may not fix. `build` is
    // compiled from it, so including either would also double-count.
    $skip  = array('node_modules', 'vendor', 'build', '.git', '.superpowers', 'src-react');
    $files = array();

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function ($current) use ($skip): bool {
                return ! ($current->isDir() && in_array($current->getFilename(), $skip, true));
            }
        )
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === $extension) {
            $files[] = mhmrentiva_real($file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * Walks the installed package's Layout source for blueprint token names.
 *
 * Kept separate from mhmrentiva_walk() for the same reason set A is: that
 * walker skips `vendor` on purpose, and lifting the skip would drag the whole
 * dependency tree into classification. This one reads a single directory,
 * vendor/mhm/ui-core/src/Layout, because that is where the blueprint token map
 * moved in ui-core v0.6.0.
 *
 * Without it the gate reports blueprint names it can no longer see. The nine
 * `--mhm-bp-*` entries left in ContractRules are dead constants, so a count
 * taken from them alone reads exactly the same whether the live map exists or
 * not -- which is to say it measures nothing.
 *
 * @return list<string>
 */
function mhmrentiva_walk_package_layout(string $root): array
{
    $base = $root . '/vendor/mhm/ui-core/src/Layout';

    if (! is_dir($base)) {
        return array();
    }

    $files = array();

    foreach (new DirectoryIterator($base) as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = mhmrentiva_real($file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * Walks set A only: the admin React palette the classifier deliberately skips.
 *
 * Kept separate from mhmrentiva_walk() on purpose. That walker's skip list is
 * load-bearing -- widening it would pull set A into classification and turn the
 * shared names into false `shared` violations, which is the exact outcome the
 * carve-out exists to avoid. This walker reads the same files for a different
 * question and never feeds the classifier.
 *
 * @return list<string>
 */
function mhmrentiva_walk_set_a(string $root): array
{
    $base = $root . '/src-react';

    if (! is_dir($base)) {
        return array();
    }

    $skip  = array('node_modules', 'vendor', 'build', '.git');
    $files = array();

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            static function ($current) use ($skip): bool {
                return ! ($current->isDir() && in_array($current->getFilename(), $skip, true));
            }
        )
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'css') {
            $files[] = mhmrentiva_real($file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * Names that set A and the classified universe BOTH declare.
 *
 * This is the compensating control for the carve-out. Set A being out of scope
 * is a decision; set A drifting unseen is not. One spelling with two sources is
 * a latent collision -- whichever selector matches last wins, and today the two
 * sources disagree in kind: the product's canonical `--mhm-text` is theme-aware
 * (`var( --wp--preset--color--foreground, ... )`) while set A's is a hard
 * value. They paint the same pixel only until a theme defines the preset.
 *
 * Report-only by contract: this function never decides a violation. Failing on
 * it would fail the tree for a decision the slice deliberately deferred.
 *
 * @param list<string> $roots          Same roots the classifier walked.
 * @param list<string> $declared_names Tokens the classifier found a DECLARATION
 *                                     for; orphans (read, never declared) are
 *                                     not an overlap with anything.
 *
 * @return list<string> Sorted, unique.
 */
function mhmrentiva_set_a_overlap(array $roots, array $declared_names): array
{
    $set_a = array();

    foreach ($roots as $root) {
        foreach (mhmrentiva_walk_set_a(mhmrentiva_real($root)) as $file) {
            $css = (string) file_get_contents($file);

            foreach (mhmrentiva_css_declarations($css) as $decl) {
                $set_a[$decl['token']] = true;
            }
        }
    }

    $overlap = array_values(array_intersect(array_keys($set_a), $declared_names));

    sort($overlap);

    return $overlap;
}

/**
 * @param list<string> $roots
 * @param list<string> $canonical_files
 *
 * @return array{
 *     tokens: array<string, array{class: string, declared_in: list<string>, consumed_in: list<string>}>,
 *     duplicate_in_canonical: list<string>,
 *     scanned: array{css: int, php: int, declarations: int}
 * }
 */
function mhmrentiva_classify_tokens(array $roots, array $canonical_files): array
{
    $canonical = array_map('mhmrentiva_real', $canonical_files);

    $declared_in  = array();
    $consumed_in  = array();
    $php_produced = array();
    $blueprint    = array();
    $duplicates   = array();
    $counts       = array('css' => 0, 'php' => 0, 'declarations' => 0);

    foreach ($roots as $root) {
        $root = mhmrentiva_real(rtrim($root, '/'));

        foreach (mhmrentiva_walk($root, 'css') as $file) {
            $counts['css']++;
            $body = (string) file_get_contents($file);

            $unconditional = array();
            foreach (mhmrentiva_css_declarations($body) as $decl) {
                $counts['declarations']++;
                $declared_in[$decl['token']][$file] = true;

                if ($decl['conditional']) {
                    continue;
                }
                if (isset($unconditional[$decl['token']]) && in_array($file, $canonical, true)) {
                    $duplicates[$decl['token']] = true;
                }
                $unconditional[$decl['token']] = true;
            }

            foreach (mhmrentiva_css_consumptions($body) as $token) {
                $consumed_in[$token][$file] = true;
            }
        }

        foreach (mhmrentiva_walk($root, 'php') as $file) {
            if (preg_match('#/(tests|bin)/#', $file)) {
                continue;
            }
            $counts['php']++;
            $usage = mhmrentiva_php_token_usage((string) file_get_contents($file));

            foreach ($usage['produces'] as $token) {
                $php_produced[$token][$file] = true;
            }
            foreach ($usage['consumes'] as $token) {
                $consumed_in[$token][$file] = true;
            }
            foreach ($usage['blueprint'] as $token) {
                $blueprint[$token] = true;
            }
        }

        // The live blueprint map lives in the installed package, which the
        // walker above skips along with the rest of vendor/.
        foreach (mhmrentiva_walk_package_layout($root) as $file) {
            $usage = mhmrentiva_php_token_usage((string) file_get_contents($file));

            foreach ($usage['blueprint'] as $token) {
                $blueprint[$token] = true;
            }
        }
    }

    $names = array_unique(array_merge(
        array_keys($declared_in),
        array_keys($consumed_in),
        array_keys($php_produced),
        array_keys($blueprint)
    ));
    sort($names);

    $tokens = array();
    foreach ($names as $token) {
        $declared = array_keys($declared_in[$token] ?? array());
        $consumed = array_keys($consumed_in[$token] ?? array());

        $tokens[$token] = array(
            'class'       => mhmrentiva_token_class(
                $token,
                $declared,
                $consumed,
                $canonical,
                isset($php_produced[$token]),
                isset($blueprint[$token])
            ),
            'declared_in' => $declared,
            'consumed_in' => $consumed,
        );
    }

    return array(
        'tokens'                 => $tokens,
        'duplicate_in_canonical' => array_keys($duplicates),
        'scanned'                => $counts,
    );
}

/**
 * @param list<string> $declared
 * @param list<string> $consumed
 * @param list<string> $canonical
 */
function mhmrentiva_token_class(
    string $token,
    array $declared,
    array $consumed,
    array $canonical,
    bool $php_produced,
    bool $blueprint
): string {
    if (array_intersect($declared, $canonical)) {
        // A canonical home does not excuse copies that still declare the same
        // token. The migration passes through exactly that state -- the token is
        // moved in before the copies come out -- and answering "canonical" there
        // would disarm the gate for the very step whose job is to delete them.
        return array_diff($declared, $canonical) ? 'shared' : 'canonical';
    }
    if ($blueprint) {
        return 'blueprint-namespace';
    }
    if ($php_produced) {
        return 'runtime-parameter';
    }
    if (array() === $declared) {
        return 'orphan';
    }
    if (count($declared) > 1) {
        return 'shared';
    }
    if (array() === $consumed) {
        return 'unused';
    }

    return array_diff($consumed, $declared) ? 'shared' : 'component-private';
}

// ---------------------------------------------------------------------------
// CLI. The functions above are the contract; this only reports on them.
// ---------------------------------------------------------------------------

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $args        = array_slice($argv, 1);
    $report_only = in_array('--report-only', $args, true);
    $paths       = array_values(array_filter($args, static fn (string $a): bool => '--' !== substr($a, 0, 2)));

    if (array() === $paths) {
        $paths = array(dirname(__DIR__));
    }

    $canonical = array(
        dirname(__DIR__) . '/assets/css/core/css-variables.css',
        dirname(__DIR__) . '/assets/css/core/golden-ratio-contract.css',
    );

    $report = mhmrentiva_classify_tokens($paths, $canonical);

    $buckets = array();
    foreach ($report['tokens'] as $token => $row) {
        $buckets[$row['class']][] = $token;
    }

    $fail = 0;

    foreach ($report['tokens'] as $token => $row) {
        if ('shared' === $row['class']) {
            $fail++;
            printf("[FAIL] %s declared outside canonical: %s\n", $token, implode(', ', $row['declared_in']));
        }
    }

    foreach ($report['duplicate_in_canonical'] as $token) {
        $fail++;
        printf("[FAIL] %s declared twice unconditionally in a canonical file\n", $token);
    }

    printf(
        "scanned: %d css, %d php, %d declarations\n",
        $report['scanned']['css'],
        $report['scanned']['php'],
        $report['scanned']['declarations']
    );
    foreach (array('canonical', 'component-private', 'unused', 'runtime-parameter', 'blueprint-namespace', 'orphan', 'shared') as $class) {
        printf("  %-20s %d\n", $class, count($buckets[$class] ?? array()));
    }

    // A count cannot be acted on. The spec asks for the sets themselves, and a
    // reader who has to re-derive them from a number will not derive them.
    foreach (array('unused', 'orphan') as $class) {
        $names = $buckets[$class] ?? array();

        if (array() === $names) {
            continue;
        }

        sort($names);
        printf("[%s] %s
", strtoupper($class), implode(', ', $names));
    }

    // Compensating control for the set A carve-out. Report-only by contract:
    // failing here would fail the tree for a decision this slice deferred.
    $declared = array();

    foreach ($report['tokens'] as $token => $row) {
        if (array() !== $row['declared_in']) {
            $declared[] = $token;
        }
    }

    $overlap = mhmrentiva_set_a_overlap($paths, $declared);

    if (array() !== $overlap) {
        printf("[SET-A-OVERLAP] %s
", implode(', ', $overlap));
    }

    if ($fail > 0 && ! $report_only) {
        printf("\n%d violation(s).\n", $fail);
        exit(1);
    }

    if ($fail > 0) {
        printf("\n%d violation(s) -- report-only, not blocking.\n", $fail);
    }

    exit(0);
}
