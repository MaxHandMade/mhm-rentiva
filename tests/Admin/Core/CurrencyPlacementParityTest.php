<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Currency placement must be inherited from WooCommerce, everywhere.
 *
 * The reported defect: with `woocommerce_currency_pos` = `left`, the Vehicles
 * list "Price/Day" column still rendered `3.500 $` (right). The canonical
 * helper was already correct — the surfaces around it hand-rolled their own
 * placement from the plugin's own `mhmrentiva_currency_position` option, which
 * is normally UNSET, so they silently fell back to `right_space` no matter what
 * WooCommerce said.
 *
 * These tests are driven by the WooCommerce option, never by a hardcoded
 * expectation, and they include a negative control: when the option moves, the
 * canonical helper and every fixed surface must move together.
 *
 * @covers \MHMRentiva\Admin\Core\CurrencyHelper
 * @covers \MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper
 */
final class CurrencyPlacementParityTest extends WP_UnitTestCase
{
    /** @var array<string, mixed> */
    private array $savedSettings = array();

    public function setUp(): void
    {
        parent::setUp();
        VehicleColumns::register();
        $this->savedSettings = (array) get_option('mhmrentiva_settings', array());
        // The defect's precondition: the plugin's own override is UNSET, so the
        // only thing that may decide placement is WooCommerce.
        unset($this->savedSettings['mhmrentiva_currency_position']);
        update_option('mhmrentiva_settings', $this->savedSettings);
        update_option('woocommerce_currency', 'USD');
    }

    public function tearDown(): void
    {
        delete_option('woocommerce_currency_pos');
        delete_option('woocommerce_currency');
        parent::tearDown();
    }

    /**
     * Every surface that renders a price for a human, keyed by a label used in
     * failure messages. Each callable takes an amount and returns the rendered
     * string, so one option flip can be asserted across all of them at once.
     *
     * @return array<string, callable(float):string>
     */
    private function humanPriceSurfaces(): array
    {
        return array(
            'CurrencyHelper::format_price' => static fn (float $a): string => CurrencyHelper::format_price($a, 0),
            'VehicleColumns price column'  => function (float $a): string {
                return $this->renderPriceColumn($a);
            },
            'VehicleFeatureHelper::format_price' => static function (float $a): string {
                $method = new \ReflectionMethod(
                    \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::class,
                    'format_price'
                );
                $method->setAccessible(true);

                return (string) $method->invoke(null, $a);
            },
        );
    }

    private function renderPriceColumn(float $price): string
    {
        $vehicle_id = self::factory()->post->create(
            array(
                'post_type'   => 'mhmrentiva_vehicle',
                'post_status' => 'publish',
            )
        );
        update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', $price);

        ob_start();
        VehicleColumns::render('mhmrentiva_price_per_day', $vehicle_id);

        return trim((string) ob_get_clean());
    }

    /**
     * Normalize the non-breaking space `wc_price()` emits between symbol and
     * amount so placement — not whitespace flavour — is what is asserted.
     */
    private function normalize(string $value): string
    {
        return trim(str_replace("\u{00A0}", ' ', $value));
    }

    /**
     * The reported defect, stated as the product rule: the Vehicles list price
     * cell follows `woocommerce_currency_pos`. Driven by the option — flipping
     * it must flip the cell.
     *
     * @dataProvider providePositions
     */
    public function test_the_vehicles_price_column_follows_the_woocommerce_position(
        string $wc_position,
        bool $symbol_leads
    ): void {
        update_option('woocommerce_currency_pos', $wc_position);

        $rendered = $this->normalize($this->renderPriceColumn(3500.0));

        // Separators come from WooCommerce too, so compare against the canonical
        // number rather than a locale-dependent literal.
        $this->assertStringContainsString(
            CurrencyHelper::format_amount(3500.0, 0),
            $rendered,
            'The amount itself must still render, with WooCommerce separators.'
        );

        if ($symbol_leads) {
            $this->assertStringStartsWith(
                '$',
                $rendered,
                sprintf('With woocommerce_currency_pos=%s the symbol must lead; got "%s".', $wc_position, $rendered)
            );
        } else {
            $this->assertStringEndsWith(
                '$',
                $rendered,
                sprintf('With woocommerce_currency_pos=%s the symbol must trail; got "%s".', $wc_position, $rendered)
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function providePositions(): array
    {
        return array(
            'left'        => array('left', true),
            'left_space'  => array('left_space', true),
            'right'       => array('right', false),
            'right_space' => array('right_space', false),
        );
    }

    /**
     * Negative control. With WooCommerce's setting changed, the canonical
     * helper and every fixed surface must still agree with each other — no
     * surface may drift back to a placement of its own.
     *
     * @dataProvider providePositions
     */
    public function test_no_surface_drifts_from_the_canonical_helper(string $wc_position): void
    {
        update_option('woocommerce_currency_pos', $wc_position);

        $canonical = $this->normalize(CurrencyHelper::format_price(3500.0, 0));

        foreach ($this->humanPriceSurfaces() as $label => $render) {
            $this->assertSame(
                $canonical,
                $this->normalize($render(3500.0)),
                sprintf(
                    '%s drifted from the canonical helper at woocommerce_currency_pos=%s.',
                    $label,
                    $wc_position
                )
            );
        }
    }

    /**
     * The precedence rule, asserted directly: the plugin's own option is only
     * consulted when WooCommerce has no opinion. An UNSET plugin option must
     * never produce a placement that contradicts WooCommerce.
     */
    public function test_woocommerce_wins_over_the_plugin_option(): void
    {
        $settings                                  = (array) get_option('mhmrentiva_settings', array());
        $settings['mhmrentiva_currency_position'] = 'right_space';
        update_option('mhmrentiva_settings', $settings);
        update_option('woocommerce_currency_pos', 'left');

        $this->assertSame(
            'left',
            CurrencyHelper::get_currency_position(),
            'WooCommerce is authoritative when it has a position; the plugin option is a fallback only.'
        );
    }

    /**
     * The fallback rule when WooCommerce has no opinion: the plugin's own
     * option decides, and only then.
     */
    public function test_the_plugin_option_decides_when_woocommerce_is_silent(): void
    {
        delete_option('woocommerce_currency_pos');
        $settings                                  = (array) get_option('mhmrentiva_settings', array());
        $settings['mhmrentiva_currency_position'] = 'right';
        update_option('mhmrentiva_settings', $settings);

        $this->assertSame('right', CurrencyHelper::get_currency_position());
    }
}
