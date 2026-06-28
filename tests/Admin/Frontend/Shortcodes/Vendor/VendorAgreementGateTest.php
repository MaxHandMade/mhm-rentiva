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

    /** @test */
    public function test_gate_passes_empty_when_not_required(): void
    {
        $this->settings('0', '');
        $this->assertSame(array(), VendorApply::evaluate_terms_gate(array()));
    }

    /** @test */
    public function test_gate_errors_when_required_but_not_accepted(): void
    {
        $this->settings('1', 'Vendor terms');
        $result = VendorApply::evaluate_terms_gate(array());
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('terms_required', $result->get_error_code());
    }

    /** @test */
    public function test_gate_returns_proof_when_accepted(): void
    {
        $this->settings('1', 'Vendor terms');
        $result = VendorApply::evaluate_terms_gate(array('terms_accepted' => '1'));
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['terms_accepted_at']);
        $this->assertSame(hash('sha256', 'Vendor terms'), $result['terms_version']);
    }
}
