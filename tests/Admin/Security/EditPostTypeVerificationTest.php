<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Security;

use WP_UnitTestCase;

/**
 * M-1 class lock: a handler that asks current_user_can( 'edit_post', $id ) must also
 * establish WHAT $id is.
 *
 * edit_post answers "may this user edit that post". It never answers "is that post one
 * of ours" -- map_meta_cap grants it for any post the caller owns, so a handler writing
 * vehicle or booking meta onto whatever id arrives acts on an object it never identified.
 *
 * This lock was first scoped to three hand-picked files, because that is where the
 * recorded inventory said the class lived. An independent audit then found two more
 * members outside those three -- invisible to a lock scoped by a list. The scope is now
 * the whole of src/, and membership is decided by measurement.
 *
 * THREE EXEMPTIONS, each derived from the source rather than hand-listed:
 *
 *   1. Registered on a type-specific save_post hook -- the hook does the filtering.
 *      Recognised for the concatenated form Pro uses as well as a literal hook name.
 *   2. Delegates to BookingActionGuard::authorize(), which checks the post type before
 *      it checks the capability.
 *   3. Registered as a save_handler in a metabox field config: AbstractMetaBox::save_meta
 *      verifies the type before dispatching to it.
 *
 * An exemption that stops being true disappears on its own: move a handler off its typed
 * hook and it is a member again. That is deliberate. The twelfth member of this class
 * came into being exactly that way, and a hand-written exemption list would have
 * re-created the blind spot it was meant to close.
 *
 * WHAT THIS TEST STILL CANNOT SEE, printed because a lock that under-reports while
 * passing is worse than no lock:
 *
 *   - Comments are stripped through token_get_all(), but string literals are not, so a
 *     type name inside an unrelated string could satisfy the shape match.
 *   - It matches a type check by SHAPE, never by meaning: a check against the wrong type,
 *     or against a different id, passes.
 *   - It does not know whether the check runs before the write, only that it is present.
 *   - Its unit is a function delimited by function keywords in source order, so a nested
 *     closure counts as part of its enclosing function.
 *   - It reads src/ only. templates/, bin/ and the Pro repo are outside it. Pro has to
 *     carry its own copy of this lock, and as of 2026-08-27 it does not.
 *   - Unreachable code is judged the same as live code. Two dead handlers were fixed
 *     rather than exempted, because dead is a property of today's wiring.
 */
final class EditPostTypeVerificationTest extends WP_UnitTestCase
{
    private function cap_call_present(string $text): bool
    {
        return strpos($text, "current_user_can('edit_post'") !== false
            || strpos($text, "current_user_can( 'edit_post'") !== false;
    }

    /**
     * Removes comments while preserving line count, so a capability named in a docblock
     * is not mistaken for a call. Two files in this repo have exactly that shape.
     */
    private function without_comments(string $source): string
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }

                $out .= $token[1];
                continue;
            }

            $out .= $token;
        }

        return $out;
    }

    /**
     * @return string[]
     */
    private function source_files(): array
    {
        $root  = dirname(__DIR__, 3) . '/src';
        $found = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $found[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Matching is done line by line with strpos() rather than a regex: the patterns this
     * needs carry both quote characters and backslashes, and escaping them was costing
     * more than it bought.
     */
    private function is_exempt(string $source, string $chunk, string $name): bool
    {
        $needle = "'" . $name . "'";

        foreach (explode("\n", $source) as $line) {
            if (strpos($line, $needle) === false) {
                continue;
            }

            if (strpos($line, 'add_action') !== false && strpos($line, 'save_post_') !== false) {
                return true;
            }

            if (strpos($line, 'save_handler') !== false) {
                return true;
            }
        }

        return strpos($chunk, 'BookingActionGuard::authorize') !== false;
    }

    public function test_no_edit_post_handler_in_src_writes_without_establishing_the_post_type(): void
    {
        $offenders = array();
        $checked   = 0;

        foreach ($this->source_files() as $path) {
            $source = $this->without_comments((string) file_get_contents($path));

            if (! $this->cap_call_present($source)) {
                continue;
            }

            $relative = substr($path, strpos($path, '/src/') + 1);

            foreach (preg_split('/(?=\bfunction\s+\w+\s*\()/', $source) as $chunk) {
                if (! $this->cap_call_present($chunk)) {
                    continue;
                }

                ++$checked;

                if (strpos($chunk, 'get_post_type(') !== false
                    || strpos($chunk, '->post_type') !== false) {
                    continue;
                }

                preg_match('/function\s+(\w+)/', $chunk, $m);
                $name = isset($m[1]) ? $m[1] : '(unnamed)';

                if ($this->is_exempt($source, $chunk, $name)) {
                    continue;
                }

                $offenders[] = $relative . '::' . $name;
            }
        }

        $this->assertGreaterThan(
            20,
            $checked,
            'Only ' . $checked . ' edit_post call sites were found in src/. The sweep covered '
            . '30; a collapse means the matcher stopped matching, not that the code got safer.'
        );

        $this->assertSame(
            array(),
            $offenders,
            'These handlers ask edit_post without establishing the post type: '
            . implode(', ', $offenders)
        );
    }
}
