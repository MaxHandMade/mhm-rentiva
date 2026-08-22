<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * F2 (Task 12 slice 5, fix round 1, ruled up from Minor). This defect class
 * has now bitten twice: Task 11's close_manual_refund() shipped it as
 * CRITICAL, and the plan dictated the identical shape again for Task 12's
 * review_dismiss(). The shape both times: a terminating wp_send_json_*()
 * call sitting INSIDE a try{}finally{} whose finally releases a RefundLock.
 * wp_send_json_*() calls wp_die(), a hard exit in production that PHP does
 * NOT run a finally block across -- so that shape leaks the lock for
 * RefundLock::TTL_SECONDS on every real request.
 *
 * The runtime PHPUnit suite is structurally blind to this class of bug:
 * WP_Ajax_UnitTestCase makes wp_die() THROW (WPAjaxDieContinueException, an
 * \Exception) rather than hard-exit the process, and PHP's finally DOES run
 * while an exception unwinds the stack -- so a lock-release regression test
 * (e.g. ManualRefundCloseTest::test_the_lock_is_released_after_a_successful_close(),
 * RefundReviewActionsTest::test_the_lock_is_released_after_a_successful_dismiss())
 * passes identically whether the shape is correct or broken. Those tests say
 * so themselves. Only a source-shape scan can see the difference, which is
 * this ecosystem's own house rule for a defect class seen twice: enumerate
 * it and gate it, do not rely on a human noticing a third time.
 *
 * Scope, stated because a scanner that cannot say where it looked is worse
 * than none (bin/check-refund-status-writers.php's own house style): scans
 * src/ for every `try { ... } finally { ... }` whose finally body calls
 * RefundLock::release(...), and flags any such block whose try body calls
 * any wp_send_json_*(). Brace-matched, not a full PHP parse -- deliberately,
 * following the same "bounded scan over the real file" approach as
 * ManualRefundButtonHandlerPairingTest rather than inventing a parser. It
 * walks past zero or more `catch (...) { ... }` blocks between the try and
 * the finally (also brace-matched), so a caught exception in the middle does
 * not confuse it, even though none of today's four RefundLock::release()
 * call sites (DepositManagementAjax.php x2, CancellationHandler.php,
 * Refunds/Service.php, AutoCancel.php) actually has one.
 *
 * What this does NOT do: understand control flow. A wp_send_json_*() call
 * reachable only through a nested closure, or one guarded by a condition
 * that happens to always be false today, would still be flagged -- and
 * correctly so for this gate's purpose: the risk is "this call is textually
 * inside the try that shares a finally with a lock release", not "this call
 * always executes". A false positive here costs one line moved outside a
 * try block; a false negative costs a production lock leak nobody notices
 * for RefundLock::TTL_SECONDS at a time.
 */
final class RefundLockFinallyDoesNotSendJsonTest extends WP_UnitTestCase
{
    private const SRC_ROOT = __DIR__ . '/../../src';

    /**
     * @return array<int, array{try_open: int, try_body: string, finally_body: string}>
     */
    private function find_try_finally_blocks( string $contents ): array {
        $blocks = array();

        if ( ! preg_match_all( '/\btry\s*\{/', $contents, $try_matches, PREG_OFFSET_CAPTURE ) ) {
            return $blocks;
        }

        foreach ( $try_matches[0] as $try_match ) {
            [$match, $offset] = $try_match;
            $try_open         = $offset + strlen( $match ) - 1;
            $try_close = $this->matching_brace( $contents, $try_open );

            if ( null === $try_close ) {
                continue;
            }

            $try_body = substr( $contents, $try_open + 1, $try_close - $try_open - 1 );
            $cursor   = $try_close + 1;

            // Walk past zero or more `catch (...) { ... }` blocks. None of
            // this gate's real sites have one today (see class docblock),
            // but a scanner that cannot handle the general try/catch/finally
            // shape would be lying about what it checked.
            while ( preg_match( '/\G\s*catch\s*\([^)]*\)\s*\{/', $contents, $catch_match, 0, $cursor ) ) {
                $catch_open  = $cursor + strlen( $catch_match[0] ) - 1;
                $catch_close = $this->matching_brace( $contents, $catch_open );

                if ( null === $catch_close ) {
                    break;
                }

                $cursor = $catch_close + 1;
            }

            if ( preg_match( '/\G\s*finally\s*\{/', $contents, $finally_match, 0, $cursor ) ) {
                $finally_open  = $cursor + strlen( $finally_match[0] ) - 1;
                $finally_close = $this->matching_brace( $contents, $finally_open );

                if ( null !== $finally_close ) {
                    $blocks[] = array(
                        'try_open'     => $offset,
                        'try_body'     => $try_body,
                        'finally_body' => substr( $contents, $finally_open + 1, $finally_close - $finally_open - 1 ),
                    );
                }
            }
        }

        return $blocks;
    }

    private function matching_brace( string $contents, int $open_pos ): ?int {
        $depth = 1;
        $len   = strlen( $contents );

        for ( $pos = $open_pos + 1; $pos < $len; $pos++ ) {
            if ( '{' === $contents[ $pos ] ) {
                ++$depth;
            } elseif ( '}' === $contents[ $pos ] ) {
                --$depth;

                if ( 0 === $depth ) {
                    return $pos;
                }
            }
        }

        return null;
    }

    public function test_no_try_finally_that_releases_a_refund_lock_sends_json_from_inside_the_try(): void {
        $root = realpath( self::SRC_ROOT );
        $this->assertNotFalse( $root, 'Scan root must resolve.' );

        $offenders = array();
        $files     = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS ) );

        foreach ( $files as $file ) {
            if ( 'php' !== $file->getExtension() ) {
                continue;
            }

            $contents = file_get_contents( $file->getPathname() );

            if ( false === $contents ) {
                continue;
            }

            foreach ( $this->find_try_finally_blocks( $contents ) as $block ) {
                if ( false === strpos( $block['finally_body'], 'RefundLock::release(' ) ) {
                    continue;
                }

                if ( false !== strpos( $block['try_body'], 'wp_send_json_' ) ) {
                    $line        = substr_count( $contents, "\n", 0, $block['try_open'] ) + 1;
                    $offenders[] = $file->getPathname() . ':' . $line;
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            "wp_send_json_*() calls wp_die(), a hard exit in production a finally block does not run\n"
                . "across. Every terminating response for an endpoint that releases a RefundLock in a\n"
                . "finally must sit OUTSIDE that try/finally, or the lock leaks for RefundLock::TTL_SECONDS\n"
                . "on every real request -- invisible to this suite's own lock-release tests. Offenders:\n"
                . implode( "\n", $offenders )
        );
    }

    /**
     * The read-proving control this ecosystem's scanners are held to
     * (ManualRefundButtonHandlerPairingTest's own precedent): a scanner that
     * silently found nothing would make the assertion above pass whether or
     * not it actually read anything. This proves the SAME mechanism
     * recovers the two known try/finally-releases-a-lock sites this gate
     * exists to police -- close_manual_refund() and review_dismiss(), both
     * in DepositManagementAjax.php, both already fixed -- rather than
     * trusting the empty-array result above to mean the scan ran at all.
     */
    public function test_the_scan_recovers_the_two_known_refund_lock_finally_sites(): void {
        $path = realpath( self::SRC_ROOT . '/Admin/Booking/Actions/DepositManagementAjax.php' );
        $this->assertNotFalse( $path );

        $contents = file_get_contents( $path );
        $this->assertNotFalse( $contents );

        $blocks = $this->find_try_finally_blocks( $contents );

        $with_lock_release = array_values( array_filter(
            $blocks,
            static fn ( array $block ): bool => false !== strpos( $block['finally_body'], 'RefundLock::release(' )
        ) );

        $this->assertCount(
            2,
            $with_lock_release,
            'Sanity check on the scan itself: DepositManagementAjax.php must have exactly two '
                . 'try/finally blocks that release a RefundLock (close_manual_refund(), review_dismiss()), '
                . 'or nothing above proves the scanner reads this file at all.'
        );

        foreach ( $with_lock_release as $block ) {
            $this->assertStringNotContainsString(
                'wp_send_json_',
                $block['try_body'],
                'Both known sites must already be clean.'
            );
        }
    }
}
