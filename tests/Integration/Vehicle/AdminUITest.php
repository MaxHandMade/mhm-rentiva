<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use MHMRentiva\Admin\Vehicle\Meta\LifecycleMetaBox;
use MHMRentiva\Admin\Vehicle\VendorReliabilityColumn;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator;
use MHMRentiva\Admin\Core\MetaKeys;

class AdminUITest extends \WP_UnitTestCase
{
    // ── Vehicle Columns ──────────────────────────────────────

    public function test_lifecycle_column_added_to_vehicle_list(): void
    {
        $columns = VehicleColumns::columns(array('title' => 'Title', 'date' => 'Date'));
        $this->assertArrayHasKey('mhm_lifecycle', $columns);
    }

    public function test_lifecycle_column_renders_active_status(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Column Test Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('+45 days')));

        ob_start();
        VehicleColumns::render('mhm_lifecycle', $vehicle_id);
        $output = ob_get_clean();

        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('days left', $output);
        $this->assertStringContainsString('#28a745', $output);

        wp_delete_post($vehicle_id, true);
    }

    public function test_lifecycle_column_renders_withdrawn_status(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'draft',
            'post_title'  => 'Withdrawn Column Test',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::WITHDRAWN);

        ob_start();
        VehicleColumns::render('mhm_lifecycle', $vehicle_id);
        $output = ob_get_clean();

        $this->assertStringContainsString('Withdrawn', $output);
        $this->assertStringContainsString('#dc3545', $output);

        wp_delete_post($vehicle_id, true);
    }

    // ── Lifecycle Meta Box ───────────────────────────────────

    public function test_lifecycle_meta_box_renders_without_errors(): void
    {
        $vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($vendor_id);
        $user->add_role('rentiva_vendor');

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Meta Box Test',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, gmdate('Y-m-d H:i:s', strtotime('-30 days')));
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('+60 days')));

        $post = get_post($vehicle_id);
        $this->assertNotNull($post);

        ob_start();
        LifecycleMetaBox::render($post);
        $output = ob_get_clean();

        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('Started:', $output);
        $this->assertStringContainsString('Expires:', $output);
        $this->assertStringContainsString('Vendor Score:', $output);

        wp_delete_post($vehicle_id, true);
    }

    // ── Vendor Reliability Column ────────────────────────────

    public function test_reliability_column_added_to_users_list(): void
    {
        $columns = VendorReliabilityColumn::add_column(array('username' => 'Username'));
        $this->assertArrayHasKey('mhm_reliability', $columns);
    }

    public function test_reliability_column_shows_dash_for_non_vendor(): void
    {
        $user_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $output = VendorReliabilityColumn::render_column('', 'mhm_reliability', $user_id);
        $this->assertSame('—', $output);
        wp_delete_user($user_id);
    }

    public function test_reliability_column_shows_score_for_vendor(): void
    {
        $vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($vendor_id);
        $user->add_role('rentiva_vendor');

        ReliabilityScoreCalculator::update($vendor_id);

        $output = VendorReliabilityColumn::render_column('', 'mhm_reliability', $vendor_id);
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('Excellent', $output);

        wp_delete_user($vendor_id);
    }

    public function test_reliability_column_is_sortable(): void
    {
        $columns = VendorReliabilityColumn::sortable_column(array());
        $this->assertArrayHasKey('mhm_reliability', $columns);
    }

    public function test_reliability_column_ignores_other_columns(): void
    {
        $output = VendorReliabilityColumn::render_column('original', 'other_column', 1);
        $this->assertSame('original', $output);
    }
}
