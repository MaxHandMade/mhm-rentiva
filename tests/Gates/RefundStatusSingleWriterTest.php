<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * The status machine is only a machine if nothing writes around it. Measured
 * before this task: five call sites wrote the key directly.
 *
 * This gate scans src/ ONLY -- it says so in its own name and in every line
 * it reports. A tool that cannot say where it looked is worse than no tool;
 * tests/ and bin/ are out of scope on purpose (bin/check-refund-status-writers.php
 * is this gate's CLI twin and scans the same root for the same reason).
 *
 * The regex reads whole file contents with the /s modifier, not line by
 * line: Service.php's update_post_meta() calls span multiple lines (the key
 * and the value are several arguments apart), so a per-line pattern misses
 * exactly the writers this gate exists to catch.
 *
 * Whole-branch review, F4: measured against five spellings of "write this key
 * outside RefundStatus::transition()", the pattern above only caught one --
 * the bare string literal passed straight to update_post_meta(). It missed:
 * RefundStatus::META_KEY (already the live idiom at three sites in src/, and
 * so the likeliest spelling a future back door would use), a local variable
 * the file itself assigned the key's own spelling to one statement earlier,
 * add_post_meta(), and delete_post_meta() (a terminality bypass: deleting the
 * key resets get_post_meta()'s (string) cast to '', whose matrix row has
 * outgoing edges). Slice 5 added a fifth: update_metadata(), the lower-level
 * family update_post_meta() itself wraps, writing the identical row. The
 * test_the_gate_catches_* methods are the fixtures proving each covered
 * spelling is actually caught, against a temp directory standing in for src/
 * so a real one of these is never planted in the codebase itself. Widening a
 * gate without watching it bite is not widening it.
 *
 * The alternation itself lives in bin/check-refund-status-writers.php and
 * nowhere else. find_offenders() below used to hold a second copy, which meant
 * these fixtures measured the copy: widening one side and not the other leaves
 * both green and only one of them right.
 */
final class RefundStatusSingleWriterTest extends WP_UnitTestCase
{
    /**
     * Delegates to the gate itself.
     *
     * This helper used to carry its own copy of the two patterns, which made
     * every fixture below a test of the copy rather than of the gate CI
     * actually runs. Widening one and not the other would have left both
     * green and only one of them right -- a gate proved by a duplicate of
     * itself is not proved. There is now a single implementation,
     * mhmrentiva_find_refund_status_writers() in
     * bin/check-refund-status-writers.php, and these fixtures drive it.
     *
     * That file guards its CLI block against the resolved entry script, so
     * requiring it here loads the function without running -- and exiting --
     * the src/ scan.
     *
     * @return array<int, string> "path:line" entries, sorted.
     */
    private static function find_offenders(string $root, string $exclude_filename = 'RefundStatus.php'): array
    {
        require_once dirname( __DIR__, 2 ) . '/bin/check-refund-status-writers.php';

        return mhmrentiva_find_refund_status_writers( $root, '', $exclude_filename );
    }


    /**
     * @return string The temp directory the fixture file was written into.
     */
    private static function write_fixture( string $contents ): string
    {
        $dir = sys_get_temp_dir() . '/mhmrentiva-refund-gate-fixture-' . uniqid( '', true );
        mkdir( $dir );
        file_put_contents( $dir . '/Fixture.php', $contents );

        return $dir;
    }

    private static function remove_fixture( string $dir ): void
    {
        foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
            unlink( $file );
        }
        rmdir( $dir );
    }

    public function test_no_source_file_writes_the_refund_status_key_directly(): void
    {
        $root = dirname( __DIR__, 2 ) . '/src';

        $offend = self::find_offenders( $root );

        $this->assertSame(
            array(),
            $offend,
            "Write through RefundStatus::transition() instead:\n" . implode( "\n", $offend )
        );
    }

    public function test_the_gate_catches_the_meta_key_constant_spelling(): void
    {
        $dir = self::write_fixture(
            "<?php\n" .
            "update_post_meta( \$booking_id, RefundStatus::META_KEY, 'pending' );\n"
        );

        try {
            $this->assertNotEmpty(
                self::find_offenders( $dir ),
                'RefundStatus::META_KEY is already the live idiom at three sites in src/ -- the likeliest'
                    . ' spelling a future back door would use -- and the old string-literal-only alternation missed it entirely.'
            );
        } finally {
            self::remove_fixture( $dir );
        }
    }

    public function test_the_gate_catches_a_variable_holding_the_meta_key(): void
    {
        $dir = self::write_fixture(
            "<?php\n" .
            "\$meta_key = RefundStatus::META_KEY;\n" .
            "update_post_meta( \$booking_id, \$meta_key, 'pending' );\n"
        );

        try {
            $this->assertNotEmpty(
                self::find_offenders( $dir ),
                'A single-statement alternation, however widened, cannot see the assignment on the line'
                    . ' before -- this is the second pass, tracing the variable back to its own assignment.'
            );
        } finally {
            self::remove_fixture( $dir );
        }
    }

    public function test_the_gate_catches_add_post_meta(): void
    {
        $dir = self::write_fixture(
            "<?php\n" .
            "add_post_meta( \$booking_id, '_mhmrentiva_refund_status', 'pending' );\n"
        );

        try {
            $this->assertNotEmpty(
                self::find_offenders( $dir ),
                'add_post_meta() is as much a direct writer of this key as update_post_meta() -- the old'
                    . ' pattern named only the latter.'
            );
        } finally {
            self::remove_fixture( $dir );
        }
    }

    public function test_the_gate_catches_delete_post_meta(): void
    {
        $dir = self::write_fixture(
            "<?php\n" .
            "delete_post_meta( \$booking_id, '_mhmrentiva_refund_status' );\n"
        );

        try {
            $this->assertNotEmpty(
                self::find_offenders( $dir ),
                'delete_post_meta() is a terminality bypass: deleting the key resets RefundStatus::get()\'s'
                    . ' (string) cast to \'\', whose matrix row has outgoing edges -- a back door out of a'
                    . ' terminal status, not just into one.'
            );
        } finally {
            self::remove_fixture( $dir );
        }
    }

    /**
     * Negative control for the META_KEY widening: a plain READ of the key
     * through get_post_meta() (the live idiom at
     * DepositManagementAjax.php:668) must never be flagged -- only
     * update_post_meta()/add_post_meta()/delete_post_meta() are writers.
     */
    public function test_the_gate_does_not_flag_a_read_of_the_meta_key_constant(): void
    {
        $dir = self::write_fixture(
            "<?php\n" .
            "\$status = (string) get_post_meta( \$booking_id, RefundStatus::META_KEY, true );\n"
        );

        try {
            $this->assertSame(
                array(),
                self::find_offenders( $dir ),
                'get_post_meta() is a read, not a write -- widening the alternation to catch META_KEY must'
                    . ' not turn every READ of it into a false positive.'
            );
        } finally {
            self::remove_fixture( $dir );
        }
    }

    /**
     * update_post_meta() is a thin wrapper over update_metadata( 'post', ... ):
     * the lower-level family writes the identical row in the identical table,
     * so a back door spelled update_metadata() bypasses RefundStatus::transition()
     * exactly as completely as update_post_meta() would -- and the alternation
     * above, which names only the *_post_meta() spellings, cannot see it.
     *
     * Measured before this test: src/ has no live member of this class (its one
     * textual match is a comment), so this fixture is the gate's only proof that
     * the widening bites. A blind spot with no current occupant is still a blind
     * spot; it is where the next writer lands.
     */
    public function test_the_gate_catches_update_metadata(): void
    {
        $dir = self::write_fixture(
            "<?php\n" .
            "update_metadata( 'post', \$booking_id, RefundStatus::META_KEY, 'pending' );\n"
        );

        try {
            $this->assertNotEmpty(
                self::find_offenders( $dir ),
                'update_metadata() writes the same row update_post_meta() writes; a pattern naming only'
                    . ' the *_post_meta() family reports "clean" while the key is written around the'
                    . ' single writer.'
            );
        } finally {
            self::remove_fixture( $dir );
        }
    }
}
