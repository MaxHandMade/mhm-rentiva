<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * An unlicensed site must not be able to PERSIST Pro settings tabs (F9).
 *
 * The render layer shows a placeholder instead of the Transfer / Vendor-Marketplace
 * form, but a forged or replayed POST could still reach SettingsSanitizer::sanitize().
 * The gate there fails closed: for a Pro tab whose licence is absent it returns the
 * untouched current values (a no-op save). This tree has no LicenseManager, so
 * Mode::isPro()/canUseVendorMarketplace() are false — exactly the unlicensed state.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize
 */
final class SettingsSanitizerProTabGateTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        delete_option('mhm_rentiva_settings');
        parent::tearDown();
    }

    public function test_premise_this_tree_is_unlicensed(): void
    {
        $this->assertFalse(Mode::isPro(), 'Premise failed: the Lite test tree reports a Pro licence.');
        $this->assertFalse(Mode::canUseVendorMarketplace(), 'Premise failed: vendor marketplace is licensed here.');
    }

    /**
     * Mutation proof: drop the `$pro_tab_gates` short-circuit and this fails — the
     * submitted 77 would be sanitized and persisted instead of the stored 33.
     */
    public function test_unlicensed_transfer_save_does_not_persist(): void
    {
        update_option('mhm_rentiva_settings', array( 'mhm_transfer_deposit_rate' => 33 ));

        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'        => 'transfer',
            'mhm_transfer_deposit_rate' => '77',
        ));

        $this->assertSame(33, $result['mhm_transfer_deposit_rate'], 'Unlicensed transfer save must be a no-op.');
    }

    public function test_unlicensed_vendor_marketplace_save_does_not_persist(): void
    {
        update_option('mhm_rentiva_settings', array( 'vendor_listing_duration_days' => 90 ));

        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'           => 'vendor-marketplace',
            'vendor_listing_duration_days' => '365',
        ));

        $this->assertSame(90, $result['vendor_listing_duration_days'], 'Unlicensed vendor-marketplace save must be a no-op.');
    }

    /**
     * Positive control: the gate must not turn the whole sanitizer into a no-op. A
     * core (non-Pro) tab must still persist its input.
     */
    public function test_core_tab_save_still_persists(): void
    {
        $result = SettingsSanitizer::sanitize(array(
            'current_active_tab'            => 'system',
            'mhm_rentiva_max_login_attempts' => '10',
        ));

        $this->assertSame(10, $result['mhm_rentiva_max_login_attempts'], 'A core settings tab must still save.');
    }
}
