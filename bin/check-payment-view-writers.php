<?php
/**
 * Payment-view writer inventory (spec PR 3a, plan r4 Task 6).
 *
 * The booking's payment view is three meta keys: _mhmrentiva_payment_status,
 * _mhmrentiva_remaining_amount, _mhmrentiva_payment_type. Every function that
 * writes one of them must run inside a PaymentView scope, or a change goes
 * unrecorded. This gate lists those functions ("File::function", not line
 * numbers -- they drift) and compares the list with
 * bin/payment-view-writers-baseline.txt. A new writer fails the gate: put its
 * entry point in a PaymentView scope, prove it in PaymentHistoryScopesTest
 * (or a sibling), then add the writer to the baseline by name.
 *
 * Three lists:
 *   writer   -- (update|add|delete)_(post_meta|metadata) or the
 *               write_booking_meta_alone / _or_roll_back helpers, with one of
 *               the keys (literal or MetaKeys::BOOKING_*) in the call;
 *   create   -- a `meta_input` array entry for a key (booking creation,
 *               no scope needed: there is no "before");
 *   generic  -- a post meta write whose key is a variable of any name
 *               (WooCommerceBridge::write_booking_meta's `$key`,
 *               QueueManager's `$meta_key`), found by shape. Not every one
 *               writes a payment key; each is listed so a reviewer decides.
 *
 * Comments are blanked through token_get_all() before matching (same length,
 * newlines kept), so a key named in a comment is not a writer. A closure is
 * counted to the named function that defines it.
 *
 * Usage: php bin/check-payment-view-writers.php [--write]
 * Exit:  0 = inventory matches the baseline, 1 = it does not.
 */

declare(strict_types=1);

/**
 * Source with comments blanked to spaces (newlines kept).
 */
function mhmrentiva_pvw_strip_comments(string $source): string
{
    $out = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0]) {
                $out .= preg_replace('/[^\n]/', ' ', $token[1]);
                continue;
            }
            $out .= $token[1];
        } else {
            $out .= $token;
        }
    }
    return $out;
}

/**
 * Byte offsets of named function declarations, ascending.
 *
 * @return list<array{0: int, 1: string}>
 */
function mhmrentiva_pvw_functions(string $source): array
{
    $functions = array();
    $offset    = 0;
    $tokens    = token_get_all($source);
    $count     = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[ $i ];
        $text  = is_array($token) ? $token[1] : $token;
        if (is_array($token) && T_FUNCTION === $token[0]) {
            $at = $offset;
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[ $j ]) && T_WHITESPACE === $tokens[ $j ][0]) {
                    continue;
                }
                if (is_array($tokens[ $j ]) && T_STRING === $tokens[ $j ][0]) {
                    $functions[] = array( $at, $tokens[ $j ][1] );
                }
                break; // `function (` is a closure: no name, not listed.
            }
        }
        $offset += strlen($text);
    }
    return $functions;
}

/**
 * @param list<array{0: int, 1: string}> $functions
 */
function mhmrentiva_pvw_function_at(array $functions, int $offset): string
{
    $name = '(file scope)';
    foreach ($functions as $f) {
        if ($f[0] > $offset) {
            break;
        }
        $name = $f[1];
    }
    return $name;
}

/**
 * @return array{writer: list<string>, create: list<string>, generic: list<string>}
 */
function mhmrentiva_find_payment_view_writers(string $root): array
{
    $keys    = "(?:'_mhmrentiva_(?:payment_status|remaining_amount|payment_type)'|\"_mhmrentiva_(?:payment_status|remaining_amount|payment_type)\"|MetaKeys::BOOKING_(?:PAYMENT_STATUS|REMAINING_AMOUNT|PAYMENT_TYPE))";
    $writer  = '/(?:(?:update|add|delete)_(?:post_meta|metadata)|write_booking_meta_alone|write_booking_meta_or_roll_back)\s*\(\s*[^;]{0,300}?' . $keys . '/s';
    $create  = "/'_mhmrentiva_(?:payment_status|remaining_amount|payment_type)'\s*=>/";
    $generic = '/(?:add|update)_post_meta\s*\(\s*\$\w+\s*,\s*\$\w+\s*[,)]/';

    $found    = array(
        'writer'  => array(),
        'create'  => array(),
        'generic' => array(),
    );
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ('php' !== $file->getExtension()) {
            continue;
        }
        $raw = file_get_contents($file->getPathname());
        if (false === $raw) {
            continue;
        }
        $source    = mhmrentiva_pvw_strip_comments($raw);
        $functions = mhmrentiva_pvw_functions($raw);
        $class     = $file->getBasename('.php');
        foreach (array( 'writer' => $writer, 'create' => $create, 'generic' => $generic ) as $list => $pattern) {
            if (preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $found[ $list ][] = $class . '::' . mhmrentiva_pvw_function_at($functions, (int) $hit[1]);
                }
            }
        }
    }
    foreach ($found as $list => $entries) {
        $entries = array_values(array_unique($entries));
        sort($entries);
        $found[ $list ] = $entries;
    }
    return $found;
}

/**
 * @param array{writer: list<string>, create: list<string>, generic: list<string>} $found
 */
function mhmrentiva_pvw_render(array $found): string
{
    $out = '';
    foreach ($found as $list => $entries) {
        foreach ($entries as $entry) {
            $out .= $list . ' ' . $entry . "\n";
        }
    }
    return $out;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $root     = dirname(__DIR__) . '/src';
    $baseline = __DIR__ . '/payment-view-writers-baseline.txt';
    $now      = mhmrentiva_pvw_render(mhmrentiva_find_payment_view_writers($root));

    if (in_array('--write', $argv, true)) {
        file_put_contents($baseline, $now);
        echo "[WROTE] {$baseline}\n";
        exit(0);
    }

    $want = is_file($baseline) ? str_replace("\r\n", "\n", (string) file_get_contents($baseline)) : '';
    if ($want === $now) {
        echo '[OK] payment-view writers match the baseline (' . substr_count($now, "\n") . " entries).\n";
        exit(0);
    }

    echo "[FAIL] payment-view writers differ from bin/payment-view-writers-baseline.txt.\n";
    $a = array_filter(explode("\n", $want));
    $b = array_filter(explode("\n", $now));
    foreach (array_diff($b, $a) as $line) {
        $hint = 0 === strpos($line, 'generic ')
            ? 'new variable-key writer: if it can write a payment key, scope its entry point; then add it'
            : 'new: put its entry point in a PaymentView scope, test it, then add it';
        echo "  + {$line}   ({$hint})\n";
    }
    foreach (array_diff($a, $b) as $line) {
        echo "  - {$line}   (gone: remove it from the baseline)\n";
    }
    exit(1);
}
