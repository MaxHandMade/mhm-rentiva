<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Vehicle list-table column UI.
 *
 * The lifecycle meta box and the vendor reliability column were carved out of
 * Lite with the vendor marketplace; their cases live in the Pro suite. The
 * lifecycle *column* below is rendered by the retained VehicleColumns and stays.
 */
class AdminUITest extends \WP_UnitTestCase
{
    // ── Vehicle Columns ──────────────────────────────────────

    public function test_lifecycle_column_added_to_vehicle_list(): void
    {
        $columns = VehicleColumns::columns(array('title' => 'Title', 'date' => 'Date'));
        $this->assertArrayHasKey('mhmrentiva_lifecycle', $columns);
    }

    public function test_lifecycle_column_renders_active_status(): void
    {
        // The "days left" countdown only renders for vendor listings, so the vehicle
        // must be authored by a vendor.
        $vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        get_userdata($vendor_id)->add_role('rentiva_vendor');

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Column Test Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('+45 days')));

        ob_start();
        VehicleColumns::render('mhmrentiva_lifecycle', $vehicle_id);
        $output = ob_get_clean();

        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('days left', $output);
        $this->assertStringContainsString('#28a745', $output);

        wp_delete_post($vehicle_id, true);
        wp_delete_user($vendor_id);
    }

    public function test_lifecycle_column_hides_countdown_for_operator_vehicle(): void
    {
        // Operator-owned (non-vendor) vehicle with a lifecycle/expiry — the countdown
        // must NOT render because operator vehicles do not expire.
        $admin_id   = $this->factory()->user->create(array('role' => 'administrator'));
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $admin_id,
            'post_title'  => 'Operator Column Test Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('+45 days')));

        ob_start();
        VehicleColumns::render('mhmrentiva_lifecycle', $vehicle_id);
        $output = ob_get_clean();

        $this->assertStringContainsString('Active', $output, 'badge still shows Active');
        $this->assertStringNotContainsString('days left', $output, 'operator vehicle must not show a countdown');

        wp_delete_post($vehicle_id, true);
        wp_delete_user($admin_id);
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
        VehicleColumns::render('mhmrentiva_lifecycle', $vehicle_id);
        $output = ob_get_clean();

        $this->assertStringContainsString('Withdrawn', $output);
        $this->assertStringContainsString('#dc3545', $output);

        wp_delete_post($vehicle_id, true);
    }
}
