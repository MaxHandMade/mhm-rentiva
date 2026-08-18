<?php
/**
 * Find `wp_send_json_*` refusals the request can walk straight past.
 *
 * `wp_send_json_*` ends the request through `wp_die()`, so a call written
 * without a following `return` still works in production -- the terminator
 * carries the guarantee. The moment such a call moves behind a wrapper, into a
 * pure method, or onto a code path where `wp_die()` is filtered, the refusal
 * stops refusing and execution continues past it. The booking submit path had
 * five of these, one of them the locked overlap check that stands between a
 * visitor and a double booking.
 *
 * Where this tool starts: every PHP file handed to it on the command line.
 * `wp_send_json_*` is a literal, so a token scan cannot miss a call the way a
 * hook-walker misses code no registration reaches. What it CANNOT see: a call
 * made through a variable function name or a wrapper of our own.
 *
 * Reports, and does not gate: a suppressed finding here is a finding lost.
 *
 * Usage: php bin/audit-json-fallthrough.php <file>...
 */

declare(strict_types=1);

/**
 * @return list<array{line: int, next: int, code: string}>
 */
function scan_file(string $path): array
{
    $src    = (string) file_get_contents($path);
    $tokens = token_get_all($src);

    // Drop whitespace and comments; keep line numbers.
    $flat = [];
    foreach ($tokens as $t) {
        if (is_array($t)) {
            if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $flat[] = ['text' => $t[1], 'line' => $t[2], 'id' => $t[0]];
            continue;
        }
        $flat[] = ['text' => $t, 'line' => 0, 'id' => null];
    }

    // Carry the last known line forward onto the bare-character tokens.
    $line = 0;
    foreach ($flat as $i => $tok) {
        if ($tok['line'] > 0) {
            $line = $tok['line'];
        }
        $flat[$i]['line'] = $line;
    }

    $findings = [];
    $count    = count($flat);

    for ($i = 0; $i < $count; $i++) {
        if ($flat[$i]['id'] !== T_STRING
            || ! preg_match('/^wp_send_json(_success|_error)?$/', $flat[$i]['text'])) {
            continue;
        }
        // A definition or a method call of the same name is not the function.
        if ($i > 0 && in_array($flat[$i - 1]['id'], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
            continue;
        }

        // Walk to the statement's semicolon.
        $depth = 0;
        $j     = $i;
        for (; $j < $count; $j++) {
            $c = $flat[$j]['text'];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ';' && $depth === 0) {
                break;
            }
        }
        if ($j >= $count) {
            continue;
        }

        // From here, close every block the call sits inside. The call is safe
        // when each closing brace is followed by another closing brace (or by
        // `else`/`elseif`/`catch`/`finally`, whose own branch does not run
        // after this one) until the function itself ends. Any statement token
        // reached at an outer depth is code the refusal falls through into.
        $brace = 0;
        for ($k = $j + 1; $k < $count; $k++) {
            $c  = $flat[$k]['text'];
            $id = $flat[$k]['id'];

            if ($c === '{') {
                $brace++;
                continue;
            }
            if ($c === '}') {
                if ($brace > 0) {
                    $brace--;
                    continue;
                }
                // Left the block the call was in. Peek at what follows.
                $n = $k + 1;
                if ($n >= $count) {
                    break;
                }
                $nid  = $flat[$n]['id'];
                $ntxt = $flat[$n]['text'];
                if ($ntxt === '}') {
                    // Another block closes; keep unwinding.
                    $k = $n - 1;
                    continue;
                }
                if (in_array($nid, [T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_FUNCTION, T_ABSTRACT, T_FINAL, T_CONST, T_VAR, T_CLASS, T_ATTRIBUTE], true)) {
                    // The function itself ended; a member declaration follows.
                    break;
                }
                if (in_array($nid, [T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY], true)) {
                    // Sibling branch: skip its whole body and keep unwinding.
                    $d = 0;
                    for ($m = $n; $m < $count; $m++) {
                        if ($flat[$m]['text'] === '{') {
                            $d++;
                        } elseif ($flat[$m]['text'] === '}') {
                            $d--;
                            if ($d === 0) {
                                break;
                            }
                        }
                    }
                    $k = $m - 1;
                    continue;
                }
                // Real code after the block: the request walks into it.
                $findings[] = [
                    'line' => $flat[$i]['line'],
                    'next' => $flat[$n]['line'],
                    'code' => $ntxt,
                ];
                break;
            }
            if ($brace === 0) {
                // Code in the SAME block, right after the call.
                if (in_array($id, [T_RETURN, T_EXIT, T_THROW], true)) {
                    break;
                }
                $findings[] = [
                    'line' => $flat[$i]['line'],
                    'next' => $flat[$k]['line'],
                    'code' => $c,
                ];
                break;
            }
        }
    }

    return $findings;
}

$files = array_slice($argv, 1);
$total = 0;
foreach ($files as $file) {
    foreach (scan_file($file) as $f) {
        printf("%s:%d falls through to line %d (%s)\n", $file, $f['line'], $f['next'], $f['code']);
        $total++;
    }
}
printf("\nFILES SCANNED: %d\nFALL-THROUGH REFUSALS: %d\n", count($files), $total);
