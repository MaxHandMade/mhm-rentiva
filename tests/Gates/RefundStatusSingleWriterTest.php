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
 * outgoing edges). find_offenders() below widens the alternation to cover all
 * four; the four test_the_gate_catches_* methods are the fixtures proving
 * each newly-covered spelling is actually caught, against a temp directory
 * standing in for src/ so a real one of these is never planted in the
 * codebase itself. Widening a gate without watching it bite is not widening
 * it.
 */
final class RefundStatusSingleWriterTest extends WP_UnitTestCase
{
    /**
     * Scans $root exactly the way this gate (and its CLI twin,
     * bin/check-refund-status-writers.php) always has: every .php file
     * except RefundStatus.php itself, whole-file contents, looking for a
     * direct write of the refund-status key.
     *
     * Two passes. The first is the original alternation, widened: the write
     * function is now update_post_meta()/add_post_meta()/delete_post_meta()
     * (not update_post_meta() alone), and the key spelling is now the bare
     * string literal OR RefundStatus::META_KEY (not the literal alone). The
     * second pass exists because the first cannot see across a `;`: a file
     * that assigns the key's own spelling to a local variable one statement
     * earlier, then passes that variable as the key argument, defeats any
     * single-statement alternation no matter how it is widened. This finds
     * that assignment first, then looks for THAT variable name (and no
     * other) used as a *_post_meta() call's key argument -- narrow on
     * purpose, so a $meta_key variable used for a completely unrelated meta
     * key elsewhere in src/ is not flagged as a false positive.
     *
     * @return array<int, string> "path:line" entries, sorted.
     */
    private static function find_offenders(string $root, string $exclude_filename = 'RefundStatus.php'): array
    {
        $offenders = array();
        $files     = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root ) );

        $call_pattern   = '/(?:update|add|delete)_post_meta\s*\(\s*[^;]{0,200}?(?:_mhmrentiva_refund_status|RefundStatus::META_KEY)/s';
        $assign_pattern = '/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:\'_mhmrentiva_refund_status\'|"_mhmrentiva_refund_status"|RefundStatus::META_KEY)\s*;/';

        foreach ( $files as $file ) {
            if ( 'php' !== $file->getExtension() ) {
                continue;
            }
            // RefundStatus itself is the one legitimate writer.
            if ( $exclude_filename === $file->getFilename() ) {
                continue;
            }

            $contents = file_get_contents( $file->getPathname() );

            if ( false === $contents ) {
                continue;
            }

            $lines = array();

            if ( preg_match_all( $call_pattern, $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
                foreach ( $matches[0] as $match ) {
                    $lines[] = substr_count( $contents, "\n", 0, $match[1] ) + 1;
                }
            }

            if ( preg_match_all( $assign_pattern, $contents, $assign_matches ) ) {
                foreach ( array_unique( $assign_matches[1] ) as $var_name ) {
                    $var_pattern = '/(?:update|add|delete)_post_meta\s*\(\s*[^;]{0,200}?\$' . preg_quote( $var_name, '/' ) . '\b/s';

                    if ( preg_match_all( $var_pattern, $contents, $var_matches, PREG_OFFSET_CAPTURE ) ) {
                        foreach ( $var_matches[0] as $match ) {
                            $lines[] = substr_count( $contents, "\n", 0, $match[1] ) + 1;
                        }
                    }
                }
            }

            foreach ( array_unique( $lines ) as $line ) {
                $offenders[] = $file->getPathname() . ':' . $line;
            }
        }

        sort( $offenders );

        return $offenders;
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
}
