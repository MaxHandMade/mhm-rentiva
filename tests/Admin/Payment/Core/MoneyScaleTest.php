<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
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
    use WooCommerceOptionSandbox;

    public function tearDown(): void
    {
        $this->restore_sandboxed_options();
        parent::tearDown();
    }

    public function test_to_minor_rounds_instead_of_truncating(): void
    {
        $this->sandbox_option('woocommerce_price_num_decimals', '2');

        $this->assertSame(
            1999,
            Money::toMinor(19.99),
            'A truncating conversion loses a kurus on the most ordinary price in the catalogue.'
        );
    }

    public function test_to_minor_honours_a_three_decimal_store(): void
    {
        $this->sandbox_option('woocommerce_price_num_decimals', '3');

        $this->assertSame(19990, Money::toMinor('19.990'));
    }

    public function test_to_minor_honours_a_zero_decimal_store(): void
    {
        $this->sandbox_option('woocommerce_price_num_decimals', '0');

        $this->assertSame(1999, Money::toMinor('1999'));
    }

    /**
     * Phase-close review, item 2: toMinor()'s string branch used to be a bare
     * (float) cast, which CurrencyHelper::to_amount()'s own docblock names as
     * unsafe on money -- PHP reads a locale-formatted "1.500,00" as 1.5,
     * silently. No production call site sends toMinor() a value shaped like
     * this today (all seven feed machine format), but the signature is
     * float|string and public, so nothing stops a future caller from doing
     * so. This pins the actual fix: routing the string branch through
     * to_amount() reads "1.500,00" as one thousand five hundred, not 1.5 --
     * without it, this assertion would see 150 instead of 150000, a 1000x
     * error the old cast produced with no warning of any kind.
     */
    public function test_to_minor_reads_a_locale_formatted_string_through_to_amount(): void
    {
        $this->sandbox_option('woocommerce_price_num_decimals', '2');

        $this->assertSame(
            150000,
            Money::toMinor('1.500,00'),
            'A bare (float) cast reads this as 1.5 and returns 150 -- a silent 1000x error.'
        );

        // The route this exercises is CurrencyHelper::to_amount() itself, not a
        // second parser -- confirms the two agree rather than pinning a
        // coincidence.
        $this->assertSame(1500.0, CurrencyHelper::to_amount('1.500,00'));
    }

    public function test_to_major_round_trips_through_to_minor(): void
    {
        $this->sandbox_option('woocommerce_price_num_decimals', '2');

        $this->assertSame('1999.99', Money::toMajor(Money::toMinor('1999.99')));
    }

    public function test_to_major_round_trips_in_a_three_decimal_store(): void
    {
        $this->sandbox_option('woocommerce_price_num_decimals', '3');

        $this->assertSame('1999.990', Money::toMajor(Money::toMinor('1999.99')));
    }

    public function test_decimals_defaults_to_two(): void
    {
        // Sandbox before forcing absence, not after: sandbox_option() must
        // capture whatever the store really had BEFORE this test deletes the
        // row, so tearDown() restores that instead of leaving it gone.
        $this->sandbox_option('woocommerce_price_num_decimals', '2');
        delete_option('woocommerce_price_num_decimals');

        $this->assertSame(2, Money::decimals());
    }

    /**
     * The delegation lock, and it has to be this one.
     *
     * Asserting Money::decimals() === CurrencyHelper::get_price_decimals() at a
     * positive decimal count proves nothing: WooCommerce is always active in
     * this suite and wc_get_price_decimals() absints upstream, so both of
     * CurrencyHelper's distinguishing behaviours are no-ops there and the old
     * PaymentState formula would pass too.
     *
     * This filter is the one state where the two answers differ. It is public
     * API -- a site may declare that its own currency options are authoritative
     * while WooCommerce is still loaded -- so a Money that read
     * wc_get_price_decimals() directly would ignore that declaration and scale
     * money by the wrong power of ten.
     */
    public function test_decimals_honours_the_house_rule_when_woocommerce_is_not_authoritative(): void
    {
        $this->sandbox_option('woocommerce_price_num_decimals', '3');
        add_filter('mhmrentiva_woocommerce_is_active', '__return_false');

        try {
            $this->assertSame(
                2,
                Money::decimals(),
                'Reading wc_get_price_decimals() directly returns 3 here and ignores the house rule.'
            );
        } finally {
            remove_filter('mhmrentiva_woocommerce_is_active', '__return_false');
        }
    }

    // NOT TESTED HERE, ON PURPOSE: the WooCommerce-absent branch. WooCommerce is
    // always loaded in this suite -- WooCommerceTestEnvironmentTest fails the
    // whole run otherwise -- so a test claiming to measure its absence would be
    // measuring the option default and saying it measured something else. The
    // guard is a source-level protection against a fatal on a WC-less install,
    // verified by reading it, not by this suite.
}
