<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Vendor\AdminVendorApplicationsPage;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use MHMRentiva\Admin\Vendor\PostType\VendorApplication;

/**
 * Integration tests for AdminVendorApplicationsPage approve/reject actions.
 */
class AdminVendorApplicationsPageTest extends \WP_UnitTestCase
{
    private int $user_id;
    private int $application_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user_id = $this->factory()->user->create();

        $this->application_id = (int) wp_insert_post(array(
            'post_type'   => VendorApplication::POST_TYPE,
            'post_author' => $this->user_id,
            'post_status' => VendorApplicationManager::STATUS_PENDING,
            'post_title'  => 'Test Vendor Application',
        ));

        // Seed required meta so approval can sync to user meta.
        update_post_meta($this->application_id, '_vendor_city', 'Istanbul');
        update_post_meta($this->application_id, '_vendor_phone', '+90 555 000 0001');
        update_post_meta($this->application_id, '_vendor_iban', '');
        update_post_meta($this->application_id, '_vendor_service_areas', array('Istanbul'));
        update_post_meta($this->application_id, '_vendor_profile_bio', 'Test bio');
    }

    public function test_process_approve_assigns_vendor_role(): void
    {
        $result = AdminVendorApplicationsPage::process_approve($this->application_id);

        $this->assertTrue($result, 'process_approve() should return true on success');

        $user = get_userdata($this->user_id);
        $this->assertContains(
            'rentiva_vendor',
            $user->roles,
            'User should have the rentiva_vendor role after approval'
        );
    }

    public function test_process_approve_updates_post_status_to_approved(): void
    {
        AdminVendorApplicationsPage::process_approve($this->application_id);

        $post = get_post($this->application_id);
        $this->assertSame(
            VendorApplicationManager::STATUS_APPROVED,
            $post->post_status,
            'Application post_status should be publish after approval'
        );
    }

    public function test_process_reject_stores_rejection_note(): void
    {
        $reason = 'Documents are incomplete.';
        $result = AdminVendorApplicationsPage::process_reject($this->application_id, $reason);

        $this->assertTrue($result, 'process_reject() should return true on success');

        $stored = get_post_meta($this->application_id, '_vendor_rejection_note', true);
        $this->assertSame(
            $reason,
            $stored,
            'Rejection note should be stored in _vendor_rejection_note post meta'
        );
    }

    public function test_process_reject_does_not_assign_vendor_role(): void
    {
        AdminVendorApplicationsPage::process_reject($this->application_id, 'Not eligible.');

        $user = get_userdata($this->user_id);
        $this->assertNotContains(
            'rentiva_vendor',
            $user->roles,
            'Rejected applicant should not receive the rentiva_vendor role'
        );
    }

    public function test_process_approve_returns_wp_error_for_invalid_id(): void
    {
        $result = AdminVendorApplicationsPage::process_approve(999999);
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function test_process_reject_returns_wp_error_for_invalid_id(): void
    {
        $result = AdminVendorApplicationsPage::process_reject(999999, 'reason');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->application_id, true);
        wp_delete_user($this->user_id);
        parent::tearDown();
    }
}
