<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Security;

use WP_UnitTestCase;

/**
 * M-1 class lock: a handler that asks current_user_can( 'edit_post', $id ) must also
 * establish WHAT $id is.
 *
 * edit_post answers "may this user edit that post". It never answers "is that post one
 * of ours" -- map_meta_cap grants it for any post the caller owns, so a handler that
 * writes vehicle or booking meta onto whatever id arrives is acting on an object it
 * never identified. Twelve members had that shape (2026-08-26); the twelfth,
 * BookingMeta::save_meta(), is hooked to the untyped save_post and therefore ran on
 * every post type on the site.
 *
 * WHAT THIS TEST CANNOT SEE, stated because a lock that under-reports while passing is
 * worse than no lock:
 *
 *   - It reads three files, named below. A handler of the same shape in another file is
 *     invisible to it. The three are where the class was found; widening the scope means
 *     widening this list deliberately, not by accident.
 *   - It matches a type check by SHAPE ( get_post_type( ... ) or ->post_type ), not by
 *     meaning. A check comparing against the wrong post type would satisfy it.
 *   - It does not know whether the check runs BEFORE the write, only that it is present
 *     in the same function.
 *   - Its unit is a function as delimited by `function` keywords in source order, so a
 *     nested closure counts as part of its enclosing function.
 */
final class EditPostTypeVerificationTest extends WP_UnitTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public function guarded_files(): array
    {
        return array(
            'BlockedDatesMetaBox' => array( 'src/Admin/Vehicle/Meta/BlockedDatesMetaBox.php' ),
            'VehicleGallery'      => array( 'src/Admin/Vehicle/Meta/VehicleGallery.php' ),
            'BookingMeta'         => array( 'src/Admin/Booking/Meta/BookingMeta.php' ),
        );
    }

    /**
     * @dataProvider guarded_files
     */
    public function test_every_edit_post_handler_also_verifies_the_post_type(string $relative): void
    {
        $path = dirname(__DIR__, 3) . '/' . $relative;
        $this->assertFileExists($path, 'The lock cannot measure a file it cannot find.');

        $source = (string) file_get_contents($path);
        $chunks = preg_split('/(?=\bfunction\s+\w+\s*\()/', $source);
        $this->assertIsArray($chunks);

        $offenders = array();
        $checked   = 0;

        foreach ($chunks as $chunk) {
            if (strpos($chunk, "current_user_can( 'edit_post'") === false
                && strpos($chunk, "current_user_can('edit_post'") === false) {
                continue;
            }

            ++$checked;

            $has_type_check = strpos($chunk, 'get_post_type(') !== false
                || strpos($chunk, '->post_type') !== false;

            if ($has_type_check) {
                continue;
            }

            preg_match('/function\s+(\w+)/', $chunk, $m);
            $name = $m[1] ?? '(unnamed)';

            // One exemption, and it is MEASURED rather than listed: a handler registered
            // on a type-specific save_post_{type} hook is already filtered by the hook
            // itself, so an explicit check would be dead code. The exemption is derived
            // from this file's own add_action() calls, which means it disappears the
            // moment someone moves that handler to the untyped save_post -- which is
            // exactly how the twelfth member of this class came to exist.
            $typed_hook = "/add_action\(\s*'save_post_\w+'\s*,\s*array\(\s*self::class\s*,\s*'"
                . preg_quote($name, '/') . "'/";

            if (preg_match($typed_hook, $source) === 1) {
                continue;
            }

            $offenders[] = $name;
        }

        $this->assertGreaterThan(
            0,
            $checked,
            "No edit_post handler found in {$relative}. Either the file moved or the tool "
            . 'stopped matching -- both mean this lock measured nothing.'
        );

        $this->assertSame(
            array(),
            $offenders,
            "These handlers in {$relative} ask edit_post without establishing the post type: "
            . implode(', ', $offenders)
        );
    }

    /**
     * The inventory itself, so a changing scope is visible. If a handler is added or
     * removed, this number moves and the diff shows which member changed.
     */
    public function test_the_class_still_has_the_membership_it_was_swept_for(): void
    {
        $expected = array(
            'src/Admin/Vehicle/Meta/BlockedDatesMetaBox.php' => 4,
            'src/Admin/Vehicle/Meta/VehicleGallery.php'      => 4,
            'src/Admin/Booking/Meta/BookingMeta.php'         => 6,
        );

        foreach ($expected as $relative => $count) {
            $source = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $relative);
            $actual = preg_match_all("/current_user_can\(\s*'edit_post'/", $source);

            $this->assertSame(
                $count,
                $actual,
                "{$relative} now has {$actual} edit_post call sites, the sweep covered {$count}. "
                . 'A new one is a new member of the M-1 class and needs its own type check.'
            );
        }
    }
}
