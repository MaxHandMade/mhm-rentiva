<?php
/**
 * Single-writer gate for _mhmrentiva_refund_status.
 *
 * RefundStatus::transition() is the one legitimate writer of this key: it
 * refuses to write without the booking lock held, and validates every move
 * against a fixed matrix. A bare update_post_meta() bypasses both guards, so
 * any such call outside RefundStatus.php itself is a back door into a machine
 * this gate exists to keep shut.
 *
 * This is the CLI twin of tests/Gates/RefundStatusSingleWriterTest.php, so a
 * developer without a PHPUnit environment (or a CI job without one) still has
 * this invariant enforced. "Twin" is now literal: that test requires this file
 * and calls mhmrentiva_find_refund_status_writers() below. It used to keep its
 * own copy of the patterns, which meant its fixtures proved the copy, not the
 * gate -- widening one and not the other leaves both green and only one right.
 *
 * 🔴 Scans src/ ONLY, and says so in its own name and its own output. A tool
 * that cannot say where it looked is worse than no tool -- tests/ and bin/
 * are out of scope on purpose.
 *
 * The pattern reads whole file contents with the /s modifier, not line by
 * line: Refunds\Service.php's update_post_meta() calls span multiple lines
 * (the key and the value are several arguments apart), so a per-line pattern
 * would miss exactly the writers this gate exists to catch.
 *
 * Whole-branch review, F4: measured against five spellings of "write this key
 * outside RefundStatus::transition()", the pattern used to catch only one --
 * the bare string literal passed straight to update_post_meta(). It missed:
 * RefundStatus::META_KEY (already the live idiom at three sites in src/, and
 * so the likeliest spelling a future back door would use), a local variable
 * the file itself assigned the key's own spelling to one statement earlier,
 * add_post_meta(), and delete_post_meta() (a terminality bypass: deleting the
 * key resets get_post_meta()'s (string) cast to '', whose matrix row has
 * outgoing edges). The call pattern below now covers those write functions and
 * both key spellings; the second pass (over $assign_pattern) covers the
 * variable case, which no single-statement alternation can see across a `;` --
 * it finds the assignment first, then looks for THAT variable name (and no
 * other) used as a write call's key argument, so an unrelated $meta_key
 * variable elsewhere in src/ is not flagged.
 *
 * Slice 5 Minor debt: the alternation named only the *_post_meta() family.
 * update_post_meta() is a thin wrapper over update_metadata( 'post', ... ),
 * which writes the identical row -- so a back door spelled update_metadata()
 * bypassed the single writer as completely, and this gate reported "clean"
 * while doing it. src/ had no live member of that class when it was measured;
 * a blind spot with no current occupant is still where the next writer lands.
 * tests/Gates/RefundStatusSingleWriterTest.php carries a fixture per
 * newly-covered spelling, proving each is actually caught.
 *
 * Exit codes: 0 = clean, 1 = a direct writer found (or the scan root is missing).
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

/**
 * Scan $root for direct writers of the refund-status key.
 *
 * Extracted from this file's own top-level body so that
 * tests/Gates/RefundStatusSingleWriterTest.php can drive THIS implementation
 * instead of carrying a second copy of the patterns. It carried one until
 * now, and a copy is a gate that can drift: widening the alternation here
 * while the test kept scanning with the old one would have left the test
 * reporting a pass for a spelling this gate no longer had to catch (and vice
 * versa). One implementation, one pattern, one place to widen.
 *
 * @param string $root             Directory to scan.
 * @param string $base             Prefix trimmed from reported paths ('' keeps them absolute).
 * @param string $exclude_filename The one legitimate writer, skipped by filename.
 *
 * @return array<int, string> "path:line" entries, sorted.
 */
function mhmrentiva_find_refund_status_writers(
    string $root,
    string $base = '',
    string $exclude_filename = 'RefundStatus.php'
): array {
    $call_pattern   = '/(?:update|add|delete)_(?:post_meta|metadata)\s*\(\s*[^;]{0,200}?(?:_mhmrentiva_refund_status|RefundStatus::META_KEY)/s';
    $assign_pattern = '/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:\'_mhmrentiva_refund_status\'|"_mhmrentiva_refund_status"|RefundStatus::META_KEY)\s*;/';

    $offenders = [];
    $iterator  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        // RefundStatus itself is the one legitimate writer.
        if ($file->getFilename() === $exclude_filename) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        $relative = str_replace('\\', '/', $file->getPathname());
        if ($base !== '' && strpos($relative, $base) === 0) {
            $relative = substr($relative, strlen($base));
        }

        $lines = [];

        if (preg_match_all($call_pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $lines[] = substr_count($contents, "\n", 0, $match[1]) + 1;
            }
        }

        // Second pass: a variable the file itself assigned the key's own
        // spelling to one statement earlier. The call pattern above cannot see
        // across the `;` that ends that assignment no matter how its
        // alternation is widened, so this traces the assignment first and then
        // looks for that exact variable name used as a write function's key
        // argument -- narrow on purpose, so an unrelated $meta_key variable
        // elsewhere in src/ is not flagged.
        if (preg_match_all($assign_pattern, $contents, $assign_matches)) {
            foreach (array_unique($assign_matches[1]) as $var_name) {
                $var_pattern = '/(?:update|add|delete)_(?:post_meta|metadata)\s*\(\s*[^;]{0,200}?\$' . preg_quote($var_name, '/') . '\b/s';

                if (preg_match_all($var_pattern, $contents, $var_matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($var_matches[0] as $match) {
                        $lines[] = substr_count($contents, "\n", 0, $match[1]) + 1;
                    }
                }
            }
        }

        foreach (array_unique($lines) as $line) {
            $offenders[] = $relative . ':' . $line;
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * CLI entry point.
 *
 * Guarded so the PHPUnit gate can require this file for the function above
 * without the scan running -- and calling exit() -- at include time. The guard
 * compares the resolved entry script against this file rather than testing
 * PHP_SAPI alone, because PHPUnit itself runs under the cli SAPI.
 */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $root = dirname(__DIR__) . '/src';

    if (! is_dir($root)) {
        fwrite(STDERR, "[ERROR] Scan root not found: {$root}\n");
        exit(1);
    }

    $offenders = mhmrentiva_find_refund_status_writers(
        $root,
        str_replace('\\', '/', dirname(__DIR__)) . '/'
    );

    if ($offenders !== []) {
        printf("src/ altında %d doğrudan yazıcı bulundu:\n\n", count($offenders));
        echo '  ' . implode("\n  ", $offenders) . "\n\n";
        echo "Write through RefundStatus::transition() instead -- it is the one\n";
        echo "writer allowed to touch _mhmrentiva_refund_status, because it is the\n";
        echo "only place that checks the booking lock and the state matrix first.\n";
        exit(1);
    }

    echo "[OK] src/ altında doğrudan yazıcı bulunamadı (RefundStatus::transition() dışında).\n";
    exit(0);
}
