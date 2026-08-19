<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Payment\Core\Money;
use WP_UnitTestCase;

/**
 * M-02: money conversion used a hard-coded 100 in seventeen places.
 *
 * Two failure modes are pinned here. The first is truncation: (int) (19.99 *
 * 100) is 1998 in PHP because the float is really 1998.9999..., so every
 * conversion has to round. The second is the store's own precision --
 * woocommerce_price_num_decimals defaults to 2 but is a setting, and a
 * 0-decimal (JPY) or 3-decimal (KWD) store makes a fixed 100 the wrong
 * multiplier.
 */
final class MoneyScaleTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        delete_option('woocommerce_price_num_decimals');
        parent::tearDown();
    }

    public function test_to_minor_rounds_instead_of_truncating(): void
    {
        update_option('woocommerce_price_num_decimals', '2');

        $this->assertSame(
            1999,
            Money::toMinor(19.99),
            'A truncating conversion loses a kurus on the most ordinary price in the catalogue.'
        );
    }

    public function test_to_minor_honours_a_three_decimal_store(): void
    {
        update_option('woocommerce_price_num_decimals', '3');

        $this->assertSame(19990, Money::toMinor('19.990'));
    }

    public function test_to_minor_honours_a_zero_decimal_store(): void
    {
        update_option('woocommerce_price_num_decimals', '0');

        $this->assertSame(1999, Money::toMinor('1999'));
    }

    public function test_to_major_round_trips_through_to_minor(): void
    {
        update_option('woocommerce_price_num_decimals', '2');

        $this->assertSame('1999.99', Money::toMajor(Money::toMinor('1999.99')));
    }

    public function test_to_major_round_trips_in_a_three_decimal_store(): void
    {
        update_option('woocommerce_price_num_decimals', '3');

        $this->assertSame('1999.990', Money::toMajor(Money::toMinor('1999.99')));
    }

    public function test_decimals_defaults_to_two(): void
    {
        delete_option('woocommerce_price_num_decimals');

        $this->assertSame(2, Money::decimals());
    }

    /**
     * Not a tautology: it fails the moment Money grows its own copy of the
     * precision rule. CurrencyHelper clamps with max(0, ...) and additionally
     * checks woocommerce_is_active(); a reimplementation on Money would drift
     * from both, and the plugin would answer "how many decimals" two ways.
     */
    public function test_decimals_delegates_to_the_house_currency_accessor(): void
    {
        update_option('woocommerce_price_num_decimals', '3');

        $this->assertSame(CurrencyHelper::get_price_decimals(), Money::decimals());
    }

    // NOT TESTED HERE, ON PURPOSE: the WooCommerce-absent branch. WooCommerce is
    // always loaded in this suite -- WooCommerceTestEnvironmentTest fails the
    // whole run otherwise -- so a test claiming to measure its absence would be
    // measuring the option default and saying it measured something else. The
    // guard is a source-level protection against a fatal on a WC-less install,
    // verified by reading it, not by this suite.
}
