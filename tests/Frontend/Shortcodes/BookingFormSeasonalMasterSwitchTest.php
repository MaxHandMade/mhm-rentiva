<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_Ajax_UnitTestCase;

/**
 * T8 Görev 10c-B (K5-F5) -- the seasonal-multiplier master switch.
 *
 * BookingForm.php:~1112 has ALWAYS gated the whole seasonal-multiplier
 * branch behind `SettingsCore::get('mhmrentiva_vehicle_seasonal_pricing',
 * '0') === '1'`, but no admin UI anywhere ever wrote that flat option key.
 * Görev 9 (F19) moved the multiplier VALUE fields onto the live Vehicle tab
 * and explicitly left this switch untouched (see
 * VehicleTabSeasonalPricingFieldsTest.php's sibling docblock and
 * task-9-report.md §2, "Also out of scope, noted only"): a config screen
 * where the multiplier fields save and read back correctly, yet STILL never
 * affect a real quote, because the one flag guarding whether they are ever
 * consulted has no writer anywhere. This is the coherence test that gap
 * never had:
 *
 *  (a) configuring a multiplier WITHOUT touching the switch must be a no-op
 *      -- today's behaviour, and the zero-default-change guarantee this
 *      task promises for every existing install;
 *  (b) saving the switch on must make BookingForm's own price calculation
 *      react to the already-configured multiplier.
 *
 * Drives the real AJAX endpoint (`mhmrentiva_calculate_price`) rather than a
 * unit around the read alone: ajax_calculate_price() has no smaller pure
 * function isolating the per-day price loop. Uses the WP_Ajax_UnitTestCase +
 * _handleAjax() pattern already established in this codebase (see
 * CompareAjaxTest.php).
 *
 * Every OTHER multiplier this calculation can apply is neutralized
 * (base price x1.0, weekend x1.0, tax off both for the no-WooCommerce path
 * AND -- WooCommerce IS active in this test environment -- via
 * woocommerce_calc_taxes) so the seasonal switch is the only variable
 * between the two cases.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 */
final class BookingFormSeasonalMasterSwitchTest extends WP_Ajax_UnitTestCase
{
    use WooCommerceOptionSandbox;

    /** @var string */
    protected $_last_response;

    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        BookingForm::register();

        $this->vehicle_id = self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_vehicle',
            'post_status' => 'publish',
        ));
        update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
        // No `_mhmrentiva_deposit` meta -> ajax_calculate_price() forces
        // payment_type='full' internally; deposit/remaining are not asserted
        // on here and do not feed vehicle_total/total_price either way.

        $this->sandbox_option('woocommerce_calc_taxes', 'no');
    }

    public function tearDown(): void
    {
        delete_option('mhmrentiva_settings');
        $this->restore_sandboxed_options();
        parent::tearDown();
    }

    /**
     * Saves the whole 'vehicle' tab in one shot (matching the real form's
     * single-POST shape, same as VehicleTabSeasonalPricingFieldsTest.php),
     * pinning base/weekend multipliers and tax rate so only the seasonal
     * switch differs between the two test cases below.
     */
    private function save_pricing_settings(bool $switch_on): void
    {
        $input = array(
            'current_active_tab'                    => 'vehicle',
            'mhmrentiva_vehicle_base_price'         => '1.0',
            'mhmrentiva_vehicle_weekend_multiplier' => '1.0',
            'mhmrentiva_vehicle_tax_rate'            => '0',
            'vehicle_pricing'                        => array(
                'seasonal_multipliers' => array(
                    'summer' => array( 'multiplier' => '2.75' ),
                ),
            ),
        );
        if ($switch_on) {
            $input['mhmrentiva_vehicle_seasonal_pricing'] = '1';
        }

        update_option('mhmrentiva_settings', SettingsSanitizer::sanitize($input));
    }

    /**
     * @return array<string,mixed> The AJAX response's 'data' payload.
     */
    private function calculate_price(): array
    {
        // Full reassignment (not incremental) so no stray key from another
        // test/file can leak into this call.
        $_POST = array(
            'action'       => 'mhmrentiva_calculate_price',
            'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
            'vehicle_id'   => $this->vehicle_id,
            // Wholly inside summer (June-August), Monday->Thursday: 3 rental
            // days, no weekend multiplier interference even though it is
            // also pinned to 1.0 above.
            'pickup_date'  => '2026-07-13',
            'dropoff_date' => '2026-07-16',
            'pickup_time'  => '10:00',
            'dropoff_time' => '10:00',
            'addons'       => array(),
            'payment_type' => 'full',
        );

        $ob_level_before = ob_get_level();
        try {
            $this->_handleAjax('mhmrentiva_calculate_price');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected path for WP_Ajax_UnitTestCase.
        }
        // The same pre-existing double-die quirk documented below also pops
        // one output-buffer level that `_handleAjax()` itself never opened
        // (see the die-handler chain in testcase-ajax.php), which otherwise
        // trips PHPUnit's own "did not (only) close its own output buffers"
        // risky-test check. Restore parity rather than let that leak.
        while (ob_get_level() > $ob_level_before) {
            ob_end_clean();
        }
        while (ob_get_level() < $ob_level_before) {
            ob_start();
        }

        // Pre-existing, test-harness-only quirk (confirmed empirically,
        // unrelated to K5-F5/K5-BUG, NOT fixed here -- see task report):
        // ajax_calculate_price()'s own `catch (\Exception $e)` is broad
        // enough to also catch WPAjaxDieContinueException/WPAjaxDieStopException
        // (both extend the base \Exception) -- these two types only exist
        // under WP_Ajax_UnitTestCase's die()-interception; wp_die() truly
        // terminates the request in production, so this branch never fires
        // there. The FIRST wp_send_json_success() call's internal wp_die()
        // throws one, which that broad catch swallows and reacts to by
        // calling wp_send_json_error() a SECOND time, appending a second,
        // generic-error JSON blob onto $_last_response. Keep only the
        // FIRST complete JSON object -- the real response.
        $raw             = (string) $this->_last_response;
        $second_blob_at  = strpos($raw, '{"success"', 1);
        $json_text       = false !== $second_blob_at ? substr($raw, 0, $second_blob_at) : $raw;

        $response = json_decode($json_text, true);
        $this->assertIsArray($response, 'AJAX response must decode to an array: ' . $raw);
        $this->assertTrue($response['success'] ?? false, 'AJAX call must succeed: ' . $raw);

        return $response['data'];
    }

    /**
     * Case (a) -- today's behaviour, must still hold once the switch exists:
     * a configured summer multiplier with the switch left at its default
     * ('0', unset) must NOT touch the price.
     */
    public function test_configured_multiplier_is_ignored_while_switch_stays_default_off(): void
    {
        $this->save_pricing_settings(false);

        $this->assertSame(
            '0',
            SettingsCore::get('mhmrentiva_vehicle_seasonal_pricing', '0'),
            'Sanity: switch must still read as its "0" default -- this case never sets it.'
        );

        $data = $this->calculate_price();

        // 3 rental days * 1000 base price/day, base multiplier 1.0, no
        // weekend, no tax, seasonal multiplier NOT applied (2.75 configured
        // but switch off) = 3000.
        $this->assertEqualsWithDelta(3000.0, (float) $data['total_price'], 0.01, 'total_price');
        $this->assertEqualsWithDelta(3000.0, (float) $data['vehicle_total'], 0.01, 'vehicle_total');
    }

    /**
     * Case (b) -- the fix: saving the switch on makes the already-configured
     * multiplier reach the price. RED until Part 1's field + sanitizer arm
     * exist (pre-fix, the submitted '1' is silently dropped by the
     * sanitizer -- no arm recognises the key -- so the switch stays '0' and
     * this collapses to the same 3000.0 as case (a)).
     */
    public function test_configured_multiplier_is_applied_once_switch_is_saved_on(): void
    {
        $this->save_pricing_settings(true);

        $this->assertSame(
            '1',
            SettingsCore::get('mhmrentiva_vehicle_seasonal_pricing', '0'),
            'Sanity: the save above must actually have persisted the switch on.'
        );

        $data = $this->calculate_price();

        // Same 3 days * 1000 base, now scaled by the 2.75 summer multiplier.
        $this->assertEqualsWithDelta(8250.0, (float) $data['total_price'], 0.01, 'total_price');
        $this->assertEqualsWithDelta(8250.0, (float) $data['vehicle_total'], 0.01, 'vehicle_total');
    }
}
