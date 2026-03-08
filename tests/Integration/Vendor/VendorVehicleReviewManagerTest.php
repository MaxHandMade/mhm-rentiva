<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Vendor\VendorVehicleReviewManager;

class VendorVehicleReviewManagerTest extends \WP_UnitTestCase
{
    private int $vehicle_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vehicle_id = $this->factory()->post->create(array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
        ));
        update_post_meta($this->vehicle_id, '_vehicle_review_status', 'pending_review');
    }

    public function test_approve_publishes_vehicle(): void
    {
        VendorVehicleReviewManager::approve($this->vehicle_id);
        $this->assertSame('publish', get_post($this->vehicle_id)->post_status);
    }

    public function test_approve_sets_review_status_approved(): void
    {
        VendorVehicleReviewManager::approve($this->vehicle_id);
        $this->assertSame('approved', get_post_meta($this->vehicle_id, '_vehicle_review_status', true));
    }

    public function test_approve_fires_hook(): void
    {
        VendorVehicleReviewManager::approve($this->vehicle_id);
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vehicle_approved'));
    }

    public function test_reject_sets_status_and_note(): void
    {
        VendorVehicleReviewManager::reject($this->vehicle_id, 'Fotoğraflar yetersiz.');
        $this->assertSame('rejected', get_post_meta($this->vehicle_id, '_vehicle_review_status', true));
        $this->assertSame('Fotoğraflar yetersiz.', get_post_meta($this->vehicle_id, '_vehicle_rejection_note', true));
    }

    public function test_reject_fires_hook(): void
    {
        VendorVehicleReviewManager::reject($this->vehicle_id, 'reason');
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vehicle_rejected'));
    }

    public function test_is_critical_field_true_for_price(): void
    {
        $this->assertTrue(VendorVehicleReviewManager::is_critical_field('price_per_day'));
    }

    public function test_is_critical_field_false_for_description(): void
    {
        $this->assertFalse(VendorVehicleReviewManager::is_critical_field('description'));
    }

    public function test_handle_vendor_edit_triggers_rereview_for_critical_field(): void
    {
        wp_update_post(array('ID' => $this->vehicle_id, 'post_status' => 'publish'));
        update_post_meta($this->vehicle_id, '_vehicle_review_status', 'approved');

        VendorVehicleReviewManager::handle_vendor_edit($this->vehicle_id, array('price_per_day' => 500));

        $this->assertSame('pending_review', get_post_meta($this->vehicle_id, '_vehicle_review_status', true));
        $this->assertGreaterThan(0, did_action('mhm_rentiva_vehicle_needs_rereview'));
    }

    public function test_handle_vendor_edit_no_rereview_for_minor_field(): void
    {
        wp_update_post(array('ID' => $this->vehicle_id, 'post_status' => 'publish'));
        update_post_meta($this->vehicle_id, '_vehicle_review_status', 'approved');

        VendorVehicleReviewManager::handle_vendor_edit($this->vehicle_id, array('description' => 'Updated text'));

        $this->assertSame('approved', get_post_meta($this->vehicle_id, '_vehicle_review_status', true));
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->vehicle_id, true);
        parent::tearDown();
    }
}
