<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\RefundStatus;
use WP_UnitTestCase;

/**
 * Every surface this plugin announces must be one spec v3 section 3 names.
 *
 * transition() already drops an out-of-contract value and logs it, so a bad
 * surface cannot reach a listener -- but that guard only speaks at runtime, on
 * a site, after the fact. This test speaks at commit time.
 *
 * It exists because the values that were there before I-2 were not typos: they
 * were LAYER names -- 'refunds_service', 'cancellation_handler',
 * 'sync_orphan_wc_orders' -- each one honestly describing which class was
 * executing. That is the natural thing to write when you are inside the class,
 * and it is exactly what the contract does not want: the five surfaces say
 * where the ACTION came from, so that an integrator can tell an operator's
 * refund from a customer's cancellation from a nightly sweep. Nothing but a
 * check like this stops the next caller from writing its own class name again.
 *
 * 🔴 Declared blind spot: this reads literals. A call site that passes a
 * variable is invisible here, and four of them do -- they carry the surface
 * threaded down from their entry point, which is the pattern I-2 introduced
 * precisely so the value is decided where it is known. Those paths are covered
 * by the behaviour tests in RefundStatusTransitionTest and by the callers'
 * own tests, not by this one.
 */
final class RefundStatusContextInventoryTest extends WP_UnitTestCase
{
    public function test_no_call_site_announces_a_surface_the_spec_does_not_name(): void
    {
        $offenders = array();

        foreach ($this->php_sources() as $file) {
            $body = (string) file_get_contents($file);

            if (! str_contains($body, 'RefundStatus::transition(')) {
                continue;
            }

            preg_match_all("/'surface'\s*=>\s*'([a-z_]+)'/", $body, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                if (in_array($match[1], RefundStatus::SURFACES, true)) {
                    continue;
                }
                $offenders[] = basename($file) . ": '" . $match[1] . "'";
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            "A refund_status_changed context named a surface spec v3 section 3 does not define.\n"
                . 'Allowed: ' . implode(', ', RefundStatus::SURFACES) . "\n"
                . "Found: " . implode(' | ', $offenders)
        );
    }

    /**
     * The allowed set is read from the class, never restated here: two lists
     * of the same thing drift, and the one in the test would drift silently.
     */
    public function test_the_allowed_surfaces_are_the_five_the_spec_names(): void
    {
        $this->assertSame(
            array( 'admin_deposit', 'customer_account', 'auto_cancel', 'manual_close', 'review_action' ),
            RefundStatus::SURFACES,
            'Spec v3 section 3 names exactly these five. Changing the code set means changing the spec first.'
        );
    }

    /**
     * @return list<string>
     */
    private function php_sources(): array
    {
        $root  = dirname(__DIR__, 4) . '/src';
        $files = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotEmpty($files, 'The probe found no PHP sources -- it was aimed at the wrong directory.');

        return $files;
    }
}
