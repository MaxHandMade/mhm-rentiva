<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * Mode is the one Licensing class Lite ships. Everything else in the namespace
 * — LicenseManager, FeatureTokenVerifier, Restrictions, LiteOverflow — is
 * carved out, and Pro re-adds it.
 *
 * Two things must hold in a Lite-only tree, and this test pins both:
 *
 *  1. Every gate answers false, and answers it WITHOUT fataling. Mode names
 *     LicenseManager by same-namespace short name, so the inline class_exists()
 *     on a literal FQN is the only thing standing between Lite and a fatal
 *     "Class not found". Short-circuit evaluation is the contract under test.
 *
 *  2. The limit/upsell API is really gone, not merely unused. Those methods
 *     were the crippleware surface (artificial caps on a Lite user's own data);
 *     the carve removes them outright rather than making them return PHP_INT_MAX.
 *     bin/check-crippleware.php enforces this from the call-site direction; this
 *     enforces it from the API direction, so re-adding a method cannot pass
 *     unnoticed just because nothing calls it yet.
 *
 * The Pro-side behaviour (a valid RSA feature token granting a feature) cannot
 * be exercised here — LicenseManager does not exist in this tree — and belongs
 * to the Pro suite.
 *
 * @covers \MHMRentiva\Admin\Licensing\Mode
 */
final class ModeThinRouterTest extends WP_UnitTestCase
{
    public function test_license_manager_is_absent_from_the_lite_tree(): void
    {
        // Guards the premise of every assertion below: if Pro ever leaked into
        // the Lite tree, the false-by-default expectations would be testing
        // nothing and this test would silently become a tautology.
        $this->assertFalse(
            class_exists('\MHMRentiva\Admin\Licensing\LicenseManager'),
            'LicenseManager must not exist in a Lite build.'
        );
    }

    public function test_is_pro_is_false_without_license_manager(): void
    {
        $this->assertFalse(Mode::isPro());
    }

    public function test_is_lite_is_true_without_license_manager(): void
    {
        $this->assertTrue(Mode::isLite());
    }

    public function test_is_pro_snake_case_alias_agrees_with_is_pro(): void
    {
        $this->assertFalse(Mode::is_pro());
        $this->assertSame(Mode::isPro(), Mode::is_pro());
    }

    /**
     * @dataProvider featureGateProvider
     */
    public function test_feature_gate_returns_false_without_fataling(string $method): void
    {
        $this->assertFalse(
            Mode::$method(),
            sprintf('Mode::%s() must be false when Pro is absent.', $method)
        );
    }

    /** @return array<string, array{string}> */
    public static function featureGateProvider(): array
    {
        return [
            'vendor payout'      => ['canUseVendorPayout'],
            'vendor marketplace' => ['canUseVendorMarketplace'],
            'messages'           => ['canUseMessages'],
            'advanced reports'   => ['canUseAdvancedReports'],
            'export'             => ['canUseExport'],
        ];
    }

    /**
     * @dataProvider removedLimitMethodProvider
     */
    public function test_limit_and_upsell_methods_no_longer_exist(string $method): void
    {
        $this->assertFalse(
            method_exists(Mode::class, $method),
            sprintf('Mode::%s() is crippleware and must stay removed.', $method)
        );
    }

    /** @return array<string, array{string}> */
    public static function removedLimitMethodProvider(): array
    {
        return [
            'vehicle cap'          => ['maxVehicles'],
            'gateway restriction'  => ['allowedGateways'],
            'upsell comparison'    => ['get_comparison_table_data'],
            'insecure gate'        => ['featureGranted'],
            'deprecated gate'      => ['featureEnabled'],
            'Pro feature upsell'   => ['get_pro_features_list'],
        ];
    }
}
