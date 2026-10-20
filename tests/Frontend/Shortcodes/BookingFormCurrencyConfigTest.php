<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * booking-form.js formatMoney() reads every currency part from
 * window.mhmRentivaBookingForm.config. Its prices must match the amounts the
 * template prints through CurrencyHelper::format_price( $x, 0 ), so the
 * separators travel in config next to the symbol and the position.
 */
final class BookingFormCurrencyConfigTest extends WP_UnitTestCase
{
    use WooCommerceOptionSandbox;

    public function tearDown(): void
    {
        // Store settings are restored explicitly: a rollback is not relied on.
        $this->restore_sandboxed_options();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function js_config(): array
    {
        $method = new \ReflectionMethod(BookingForm::class, 'get_localized_data');
        $method->setAccessible(true);

        /** @var array{config: array<string, mixed>} $data */
        $data = $method->invoke(null);

        return $data['config'];
    }

    public function test_config_carries_every_part_format_money_reads(): void
    {
        $config = $this->js_config();

        foreach (array('currency_symbol', 'currency_position', 'locale', 'thousand_separator', 'decimal_separator') as $key) {
            $this->assertArrayHasKey($key, $config, "booking-form.js reads config.$key.");
        }

        $this->assertSame(CurrencyHelper::get_thousand_separator(), $config['thousand_separator']);
        $this->assertSame(CurrencyHelper::get_decimal_separator(), $config['decimal_separator']);
        $this->assertSame(CurrencyHelper::get_currency_position(), $config['currency_position']);
    }

    public function test_separators_follow_woocommerce_settings(): void
    {
        if (! CurrencyHelper::woocommerce_is_active() || ! function_exists('wc_get_price_thousand_separator')) {
            $this->markTestSkipped('WooCommerce owns the separators only when it is active.');
        }

        $this->sandbox_option('woocommerce_price_thousand_sep', '.');
        $this->sandbox_option('woocommerce_price_decimal_sep', ',');

        $config = $this->js_config();

        $this->assertSame('.', $config['thousand_separator']);
        $this->assertSame(',', $config['decimal_separator']);

        $this->sandbox_option('woocommerce_price_thousand_sep', ' ');
        $this->sandbox_option('woocommerce_price_decimal_sep', '.');

        $config = $this->js_config();

        $this->assertSame(' ', $config['thousand_separator']);
        $this->assertSame('.', $config['decimal_separator']);
    }
}
