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
        VehicleColumns::render('mhm_lifecycle', $vehicle_id);
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
        VehicleColumns::render('mhm_lifecycle', $vehicle_id);
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

    /**
     * Regression for Note 1 (v4.33.1): when an admin opens a vehicle edit
     * screen, the Lifecycle meta box has to identify which vendor owns the
     * vehicle. Before v4.33.1 the box rendered the reliability score but
     * never the vendor's name, so admins had no immediate context for the
     * score they were looking at.
     */
    public function test_lifecycle_meta_box_renders_vendor_display_name(): void
    {
        $vendor_id = $this->factory()->user->create(array(
            'role'         => 'subscriber',
            'display_name' => 'Acme Filo Kiralama',
            'user_login'   => 'acme_filo',
        ));
        $user = get_userdata($vendor_id);
        $user->add_role('rentiva_vendor');

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Vendor Name Test Vehicle',
        ));

        $post = get_post($vehicle_id);
        $this->assertNotNull($post);

        ob_start();
        LifecycleMetaBox::render($post);
        $output = ob_get_clean();

        $this->assertStringContainsString('Vendor:', $output, 'Meta box must label the vendor row');
        $this->assertStringContainsString('Acme Filo Kiralama', $output, 'Vendor display_name must be visible to admins');

        wp_delete_post($vehicle_id, true);
    }

    /**
     * Fallback: when display_name is empty, the meta box still has to
     * identify the vendor. Falls back to user_login so admins never see a
     * blank vendor row.
     */
    public function test_lifecycle_meta_box_falls_back_to_user_login_when_display_name_is_empty(): void
    {
        $vendor_id = $this->factory()->user->create(array(
            'role'         => 'subscriber',
            'user_login'   => 'fallback_vendor_login',
            'display_name' => '',
        ));
        // Force display_name empty (factory may copy from user_login).
        wp_update_user(array('ID' => $vendor_id, 'display_name' => ''));
        $user = get_userdata($vendor_id);
        $user->add_role('rentiva_vendor');

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Fallback Vendor Test',
        ));

        $post = get_post($vehicle_id);
        ob_start();
        LifecycleMetaBox::render($post);
        $output = ob_get_clean();

        $this->assertStringContainsString('Vendor:', $output);
        $this->assertStringContainsString('fallback_vendor_login', $output);

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
