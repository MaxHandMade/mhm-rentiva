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
        // Pinned rather than inferred: WooCommerce IS loaded here now
        // (2026-08-18), but these tests are about what the plugin does when it
        // treats WooCommerce as authoritative, and that must not become a
        // question of how the environment happens to be configured. The one test
        // that means the opposite drops this filter itself.
        add_filter('mhmrentiva_woocommerce_is_active', '__return_true');
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
            'VehicleFeatureHelper::format_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::class, 'format_price', $a),
            // Booking admin surfaces. These carried their own copy of the
            // placement switch, each reading the plugin option directly.
            'DepositManagementAjax::format_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::class, 'format_price', $a),
            'BookingDepositMetaBox::format_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox::class, 'format_price', $a),
            'BookingMeta::format_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Booking\Meta\BookingMeta::class, 'format_price', $a),
            'BookingColumns::format_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Booking\ListTable\BookingColumns::class, 'format_price', $a),
            'AddonBooking::format_addon_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Booking\Addons\AddonBooking::class, 'format_addon_price', $a),
            'ManualBookingMetaBox::format_addon_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox::class, 'format_addon_price', $a),
            'VehicleColumns::format_currency' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns::class, 'format_currency', $a),
            'AddonManager::format_addon_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Addons\AddonManager::class, 'format_addon_price', $a),
            // Email bodies and previews. Both used to hardcode a left placement —
            // the preview additionally read a top-level option the plugin never
            // writes, so it was pinned to `right_space`.
            'Emails\Core\Templates::format_price' => static fn (float $a): string
                => self::callPrivate(\MHMRentiva\Admin\Emails\Core\Templates::class, 'format_price', $a),
        );
    }

    /**
     * Surfaces that differ only in decimal precision are compared through the
     * canonical helper at the SAME precision, so a 2-decimal surface is never
     * failed for not looking like a 0-decimal one.
     *
     * @return array<string, int>
     */
    private function surfaceDecimals(): array
    {
        return array(
            'CurrencyHelper::format_price'            => 0,
            'VehicleColumns price column'             => 0,
            'VehicleFeatureHelper::format_price'      => 0,
            'DepositManagementAjax::format_price'     => 2,
            'BookingDepositMetaBox::format_price'     => 2,
            'BookingMeta::format_price'               => 2,
            'BookingColumns::format_price'            => 2,
            'AddonBooking::format_addon_price'        => 2,
            'ManualBookingMetaBox::format_addon_price' => 2,
            'VehicleColumns::format_currency'         => 2,
            'AddonManager::format_addon_price'        => 2,
            'Emails\Core\Templates::format_price'     => 2,
        );
    }

    /**
     * A record may carry a currency of its own (a refund, a payment log row).
     * That overrides the SYMBOL only — placement still comes from the one house
     * rule, so a stored currency code can never imply a different layout.
     *
     * @dataProvider providePositions
     */
    public function test_a_per_record_currency_overrides_the_symbol_but_not_the_placement(
        string $wc_position,
        bool $symbol_leads
    ): void {
        update_option('woocommerce_currency_pos', $wc_position);

        $rendered = $this->normalize(CurrencyHelper::format_price(3500.0, 2, 'EUR'));

        $this->assertStringContainsString(
            "\u{20AC}",
            $rendered,
            sprintf('The per-record currency must decide the symbol; got "%s".', $rendered)
        );

        if ($symbol_leads) {
            $this->assertStringStartsWith("\u{20AC}", $rendered);
        } else {
            $this->assertStringEndsWith("\u{20AC}", $rendered);
        }
    }

    /**
     * Legacy and alias currency values live in the data (`TL`, `LIRA`, a bare
     * `₺` — which is exactly why `normalize_currency_code()` exists). Handing one
     * of those straight to WooCommerce makes it answer with an EMPTY symbol, so
     * the payment-log surfaces that pass a per-record currency printed a bare
     * number. The code must be normalised before either branch sees it.
     *
     * @dataProvider provideLegacyCurrencyCodes
     */
    public function test_a_legacy_currency_code_still_renders_a_symbol(string $stored): void
    {
        update_option('woocommerce_currency_pos', 'left');

        $rendered = $this->normalize(CurrencyHelper::format_price(1250.0, 2, $stored));

        $this->assertStringContainsString(
            "\u{20BA}",
            $rendered,
            sprintf('A row storing "%s" must still show a currency; got "%s".', $stored, $rendered)
        );
        $this->assertStringContainsString(
            CurrencyHelper::format_amount(1250.0, 2),
            $rendered,
            'The amount itself must survive the normalisation.'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideLegacyCurrencyCodes(): array
    {
        return array(
            'TL'          => array('TL'),
            'LIRA'        => array('LIRA'),
            'bare symbol' => array("\u{20BA}"),
            'lowercase'   => array('tl'),
            'padded'      => array(' TRY '),
        );
    }

    /**
     * The Customers screen hands its clients (the PHP panel, the React table and
     * side panel) a pre-formatted `total_spent`. Those clients used to prepend
     * the symbol themselves, which hardcoded it to the left; the payload now
     * carries the canonical placement so nothing has to prepend anything.
     *
     * @dataProvider providePositions
     */
    public function test_the_customers_payload_carries_the_canonical_placement(string $wc_position): void
    {
        update_option('woocommerce_currency_pos', $wc_position);

        $customer_id = self::factory()->user->create(
            array(
                'role'       => 'subscriber',
                'user_email' => 'currency-parity@example.test',
            )
        );

        $detail = \MHMRentiva\Admin\Customers\CustomersOptimizer::get_customer_details_optimized($customer_id);

        if (null === $detail || ! isset($detail['total_spent'])) {
            $this->markTestSkipped('CustomersOptimizer returned no detail row for the fixture user.');
        }

        $this->assertSame(
            $this->normalize(CurrencyHelper::format_price(0.0, 2)),
            $this->normalize((string) $detail['total_spent']),
            'total_spent must already carry the canonical symbol and placement.'
        );
    }

    /**
     * The Customers payloads cache PRE-FORMATTED money for 15 minutes. With no
     * currency or placement in the key, flipping the WooCommerce setting showed
     * yesterday's symbol for the rest of the TTL.
     */
    public function test_flipping_the_position_does_not_serve_stale_formatted_money(): void
    {
        $customer_id = self::factory()->user->create(
            array(
                'role'       => 'subscriber',
                'user_email' => 'currency-cache@example.test',
            )
        );

        update_option('woocommerce_currency_pos', 'left');
        $first = \MHMRentiva\Admin\Customers\CustomersOptimizer::get_customer_details_optimized($customer_id);

        if (null === $first || ! isset($first['total_spent'])) {
            $this->markTestSkipped('CustomersOptimizer returned no detail row for the fixture user.');
        }

        // Warm the cache, then move the setting under it.
        update_option('woocommerce_currency_pos', 'right');
        $second = \MHMRentiva\Admin\Customers\CustomersOptimizer::get_customer_details_optimized($customer_id);

        $this->assertSame(
            $this->normalize(CurrencyHelper::format_price(0.0, 2)),
            $this->normalize((string) $second['total_spent']),
            'A cached payload must not outlive the currency setting it was formatted under.'
        );
        $this->assertNotSame(
            $this->normalize((string) $first['total_spent']),
            $this->normalize((string) $second['total_spent']),
            'The placement really did change, so the two renderings must differ.'
        );
    }

    private static function callPrivate(string $class, string $method, float $amount): string
    {
        $reflection = new \ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return (string) $reflection->invoke(null, $amount);
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

        $decimals = $this->surfaceDecimals();

        foreach ($this->humanPriceSurfaces() as $label => $render) {
            $canonical = $this->normalize(CurrencyHelper::format_price(3500.0, $decimals[ $label ]));
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

    /**
     * The state an ex-WooCommerce site is actually in, which the test above can
     * never reach because it starts by DELETING the option.
     *
     * `woocommerce_currency_pos` is autoloaded and survives WooCommerce's
     * deactivation and uninstall. The helper used to read that option directly,
     * so on such a site the settings screen offered the position dropdown, saved
     * a choice — and the plugin ignored it forever, because a dead plugin's
     * leftover option kept winning.
     */
    public function test_the_plugin_option_wins_when_woocommerce_is_gone_but_its_option_lingers(): void
    {
        remove_filter('mhmrentiva_woocommerce_is_active', '__return_true');
        add_filter('mhmrentiva_woocommerce_is_active', '__return_false');

        // The leftover, exactly as an uninstalled WooCommerce leaves it.
        update_option('woocommerce_currency_pos', 'left');

        $settings                                 = (array) get_option('mhmrentiva_settings', array());
        $settings['mhmrentiva_currency_position'] = 'right_space';
        update_option('mhmrentiva_settings', $settings);

        $this->assertSame(
            'right_space',
            CurrencyHelper::get_currency_position(),
            'A stale woocommerce_currency_pos must not outrank the plugin setting once WooCommerce is gone.'
        );

        // And the rendered surface must move with it, not just the accessor.
        $this->assertStringEndsWith(
            '$',
            $this->normalize(CurrencyHelper::format_price(3500.0, 0)),
            'The rendered price must follow the plugin setting too.'
        );
    }

    /**
     * The settings screen must ask the same question the helper answers. It used
     * to ask `class_exists('WooCommerce')`, so on an ex-WooCommerce site it
     * offered a dropdown whose value `get_currency_position()` then ignored.
     */
    public function test_the_settings_screen_offers_the_dropdown_exactly_when_the_plugin_option_applies(): void
    {
        remove_filter('mhmrentiva_woocommerce_is_active', '__return_true');
        add_filter('mhmrentiva_woocommerce_is_active', '__return_false');
        update_option('woocommerce_currency_pos', 'left');

        ob_start();
        \MHMRentiva\Admin\Settings\Groups\GeneralSettings::render_currency_position_field();
        $offered = (string) ob_get_clean();

        $this->assertStringContainsString(
            'mhmrentiva_settings[mhmrentiva_currency_position]',
            $offered,
            'With WooCommerce gone the screen must offer the plugin setting.'
        );

        remove_filter('mhmrentiva_woocommerce_is_active', '__return_false');
        add_filter('mhmrentiva_woocommerce_is_active', '__return_true');

        ob_start();
        \MHMRentiva\Admin\Settings\Groups\GeneralSettings::render_currency_position_field();
        $withheld = (string) ob_get_clean();

        $this->assertStringNotContainsString(
            '<select',
            $withheld,
            'With WooCommerce authoritative the screen must not offer a setting it would ignore.'
        );
    }
}
