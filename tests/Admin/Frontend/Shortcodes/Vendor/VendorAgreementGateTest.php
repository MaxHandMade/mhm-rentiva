<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Shortcodes\Vendor;

use MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorApply;
use WP_UnitTestCase;

/**
 * The agreement gate is required only when the admin toggle is on AND the
 * agreement text is non-empty.
 */
class VendorAgreementGateTest extends WP_UnitTestCase
{
    private function settings(string $enabled, string $text): void
    {
        update_option('mhm_rentiva_settings', array(
            'vendor_agreement_enabled' => $enabled,
            'vendor_agreement_text'    => $text,
        ));
    }

    public function tearDown(): void
    {
        delete_option('mhm_rentiva_settings');
        parent::tearDown();
    }

    /** @test */
    public function test_not_required_when_toggle_off(): void
    {
        $this->settings('0', 'Some terms');
        $this->assertFalse(VendorApply::is_agreement_required());
    }

    /** @test */
    public function test_not_required_when_text_empty(): void
    {
        $this->settings('1', '');
        $this->assertFalse(VendorApply::is_agreement_required());
    }

    /** @test */
    public function test_required_when_on_and_text_present(): void
    {
        $this->settings('1', "These are the vendor terms.\nLine two.");
        $this->assertTrue(VendorApply::is_agreement_required());
    }
}
