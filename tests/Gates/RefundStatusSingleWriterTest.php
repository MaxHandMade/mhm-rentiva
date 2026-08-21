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
 */
final class RefundStatusSingleWriterTest extends WP_UnitTestCase
{
    public function test_no_source_file_writes_the_refund_status_key_directly(): void
    {
        $root   = dirname( __DIR__, 2 ) . '/src';
        $offend = array();
        $files  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root ) );

        foreach ( $files as $file ) {
            if ( 'php' !== $file->getExtension() ) {
                continue;
            }
            // RefundStatus itself is the one legitimate writer.
            if ( 'RefundStatus.php' === $file->getFilename() ) {
                continue;
            }

            $contents = file_get_contents( $file->getPathname() );

            if ( false === $contents ) {
                continue;
            }

            if ( preg_match_all( '/update_post_meta\s*\(\s*[^;]{0,200}?_mhmrentiva_refund_status/s', $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
                foreach ( $matches[0] as $match ) {
                    $line     = substr_count( $contents, "\n", 0, $match[1] ) + 1;
                    $offend[] = $file->getPathname() . ':' . $line;
                }
            }
        }

        $this->assertSame(
            array(),
            $offend,
            "Write through RefundStatus::transition() instead:\n" . implode( "\n", $offend )
        );
    }
}
