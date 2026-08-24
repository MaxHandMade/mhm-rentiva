<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use WP_UnitTestCase;

/**
 * Spec §4.5. RefundCalculator had eight public static methods; seven had zero
 * callers and zero tests, and all of them read _mhmrentiva_payment_amount, a
 * key with zero writers. Its one live method was where H-03 and H-04 fired.
 *
 * This test is a retirement lock, not a design statement: the class is easy to
 * bring back by autoload the moment someone types the name, and a resurrected
 * copy would quietly reintroduce a second answer to "how much is refundable".
 */
final class RefundCalculatorRetiredTest extends WP_UnitTestCase
{
    public function test_the_class_is_gone(): void
    {
        $this->assertFalse(
            class_exists('MHMRentiva\\Admin\\Payment\\Refunds\\RefundCalculator'),
            'PaymentState is the single answer to how much is refundable.'
        );
    }

    public function test_no_source_file_names_it(): void
    {
        $root  = dirname(__DIR__, 4);
        $hits  = array();
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // A comment may name it as history; a use/new/:: call may not.
            if (preg_match('/(new\s+RefundCalculator|RefundCalculator::|use\s+[\\\\\w]*RefundCalculator)/', $source)) {
                $hits[] = $file->getPathname();
            }
        }

        $this->assertSame(array(), $hits, 'Live references to the retired calculator: ' . implode(', ', $hits));
    }
}
