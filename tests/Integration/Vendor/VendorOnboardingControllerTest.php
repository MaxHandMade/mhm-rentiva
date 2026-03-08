<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Vendor\VendorOnboardingController;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use MHMRentiva\Admin\Vendor\PostType\VendorApplication;

class VendorOnboardingControllerTest extends \WP_UnitTestCase
{
    private int $user_id;
    private int $application_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user_id = $this->factory()->user->create();

        $this->application_id = wp_insert_post(array(
            'post_type'   => VendorApplication::POST_TYPE,
            'post_author' => $this->user_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Application',
        ));

        // Seed required meta for approval
        update_post_meta($this->application_id, '_vendor_city', 'Istanbul');
        update_post_meta($this->application_id, '_vendor_phone', '+90 555 000 0001');
        update_post_meta($this->application_id, '_vendor_iban', 'ENCRYPTED_IBAN_PLACEHOLDER');
        update_post_meta($this->application_id, '_vendor_service_areas', array('Istanbul', 'Ankara'));
        update_post_meta($this->application_id, '_vendor_profile_bio', 'Test bio');
    }

    public function test_approve_assigns_vendor_role(): void
    {
        VendorOnboardingController::approve($this->application_id);
        $user = get_userdata($this->user_id);
        $this->assertContains('rentiva_vendor', $user->roles);
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vendor_approved'));
    }

    public function test_approve_syncs_user_meta(): void
    {
        VendorOnboardingController::approve($this->application_id);
        $this->assertSame('Istanbul', get_user_meta($this->user_id, '_rentiva_vendor_city', true));
        $areas = get_user_meta($this->user_id, '_rentiva_vendor_service_areas', true);
        $this->assertContains('Ankara', $areas);
        $this->assertNotEmpty(get_user_meta($this->user_id, '_rentiva_vendor_iban', true));
    }

    public function test_approve_updates_post_status(): void
    {
        VendorOnboardingController::approve($this->application_id);
        $post = get_post($this->application_id);
        $this->assertSame(VendorApplicationManager::STATUS_APPROVED, $post->post_status);
    }

    public function test_reject_stores_sanitized_rejection_note(): void
    {
        // Input with HTML that should be stripped by sanitize_textarea_field
        VendorOnboardingController::reject($this->application_id, '<script>alert(1)</script>Belgeler eksik.');
        $note = get_post_meta($this->application_id, '_vendor_rejection_note', true);
        // sanitize_textarea_field strips tags
        $this->assertStringNotContainsString('<script>', $note);
        $this->assertStringContainsString('Belgeler eksik.', $note);
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vendor_rejected'));
    }

    public function test_reject_does_not_assign_vendor_role(): void
    {
        VendorOnboardingController::reject($this->application_id, 'reason');
        $user = get_userdata($this->user_id);
        $this->assertNotContains('rentiva_vendor', $user->roles);
    }

    public function test_suspend_removes_vendor_role(): void
    {
        // First approve to give vendor role
        VendorOnboardingController::approve($this->application_id);
        $this->assertContains('rentiva_vendor', get_userdata($this->user_id)->roles);

        // Then suspend
        VendorOnboardingController::suspend($this->user_id);
        $user = get_userdata($this->user_id);
        $this->assertNotContains('rentiva_vendor', $user->roles);
        $this->assertSame('suspended', get_user_meta($this->user_id, '_rentiva_vendor_status', true));
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vendor_suspended'));
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->application_id, true);
        wp_delete_user($this->user_id);
        parent::tearDown();
    }
}
