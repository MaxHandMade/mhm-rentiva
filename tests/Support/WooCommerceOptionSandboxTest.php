<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use WP_UnitTestCase;

/**
 * The sandbox exists because tearDown() was calling delete_option() on
 * WooCommerce settings. In an isolated test that is harmless -- the
 * transaction rolls everything back anyway. In THIS suite it is not: Locker
 * issues START TRANSACTION mid-test, MySQL has no nested transactions, and the
 * writes survive. delete_option() then leaves the site without the row, so the
 * store silently falls back to WooCommerce's default, and the next measurement
 * reads a number nobody configured.
 *
 * Deleting is not restoring. That is the whole point of this class.
 */
final class WooCommerceOptionSandboxTest extends WP_UnitTestCase
{
    use WooCommerceOptionSandbox;

    public function tearDown(): void
    {
        $this->restore_sandboxed_options();
        parent::tearDown();
    }

    public function test_an_existing_option_is_put_back_at_its_original_value(): void
    {
        update_option('mhmrentiva_sandbox_probe', 'configured');

        $this->sandbox_option('mhmrentiva_sandbox_probe', 'temporary');
        $this->assertSame('temporary', get_option('mhmrentiva_sandbox_probe'));

        $this->restore_sandboxed_options();

        $this->assertSame(
            'configured',
            get_option('mhmrentiva_sandbox_probe'),
            'Restoring must return the configured value, not delete the row.'
        );
    }

    public function test_an_absent_option_is_absent_again_afterwards(): void
    {
        delete_option('mhmrentiva_sandbox_probe_absent');

        $this->sandbox_option('mhmrentiva_sandbox_probe_absent', 'temporary');
        $this->restore_sandboxed_options();

        $sentinel = new \stdClass();

        $this->assertSame(
            $sentinel,
            get_option('mhmrentiva_sandbox_probe_absent', $sentinel),
            'An option the site never had must not exist after the test either.'
        );
    }

    public function test_the_first_saved_value_wins_when_a_test_sets_the_same_option_twice(): void
    {
        update_option('mhmrentiva_sandbox_probe', 'configured');

        $this->sandbox_option('mhmrentiva_sandbox_probe', 'first');
        $this->sandbox_option('mhmrentiva_sandbox_probe', 'second');
        $this->restore_sandboxed_options();

        $this->assertSame(
            'configured',
            get_option('mhmrentiva_sandbox_probe'),
            'Re-sandboxing must not record "first" as the value to restore.'
        );
    }

    public function test_a_falsy_configured_value_is_restored_rather_than_deleted(): void
    {
        // '0' is a legitimate setting -- woocommerce_price_num_decimals = 0 is
        // a JPY store. A restore that tests truthiness would delete it.
        update_option('mhmrentiva_sandbox_probe', '0');

        $this->sandbox_option('mhmrentiva_sandbox_probe', '3');
        $this->restore_sandboxed_options();

        $this->assertSame('0', get_option('mhmrentiva_sandbox_probe'));
    }
}
