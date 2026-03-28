<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\PenaltyCalculator;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleManager;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Core\MetaKeys;

class PenaltyCalculatorTest extends \WP_UnitTestCase
{
    private int $vendor_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($this->vendor_id);
        $user->add_role('rentiva_vendor');
    }

    // ── Penalty Rate Tiers ───────────────────────────────────

    public function test_first_withdrawal_is_free(): void
    {
        $this->assertSame(0.0, PenaltyCalculator::get_penalty_rate(0));
    }

    public function test_second_withdrawal_is_10_percent(): void
    {
        $this->assertSame(0.10, PenaltyCalculator::get_penalty_rate(1));
    }

    public function test_third_withdrawal_is_25_percent(): void
    {
        $this->assertSame(0.25, PenaltyCalculator::get_penalty_rate(2));
    }

    public function test_fourth_plus_withdrawal_is_25_percent(): void
    {
        $this->assertSame(0.25, PenaltyCalculator::get_penalty_rate(5));
    }

    // ── Rolling Withdrawal Count ─────────────────────────────

    public function test_count_zero_for_new_vendor(): void
    {
        $this->assertSame(0, PenaltyCalculator::get_rolling_withdrawal_count($this->vendor_id));
    }

    public function test_count_includes_recent_withdrawal(): void
    {
        $vehicle_id = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 month')));

        $this->assertSame(1, PenaltyCalculator::get_rolling_withdrawal_count($this->vendor_id));

        wp_delete_post($vehicle_id, true);
    }

    public function test_count_excludes_old_withdrawal(): void
    {
        $vehicle_id = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-13 months')));

        $this->assertSame(0, PenaltyCalculator::get_rolling_withdrawal_count($this->vendor_id));

        wp_delete_post($vehicle_id, true);
    }

    public function test_count_multiple_withdrawals(): void
    {
        $v1 = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 month')));
        $v2 = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-3 months')));
        $v3 = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-14 months'))); // Out of window.

        $this->assertSame(2, PenaltyCalculator::get_rolling_withdrawal_count($this->vendor_id));

        wp_delete_post($v1, true);
        wp_delete_post($v2, true);
        wp_delete_post($v3, true);
    }

    // ── Penalty Calculation ──────────────────────────────────

    public function test_first_withdrawal_penalty_is_zero(): void
    {
        $vehicle_id = $this->create_active_vehicle();

        $penalty = PenaltyCalculator::calculate_withdrawal_penalty($vehicle_id, $this->vendor_id);
        $this->assertSame(0.0, $penalty);

        wp_delete_post($vehicle_id, true);
    }

    public function test_no_revenue_means_zero_penalty(): void
    {
        // Create one prior withdrawal so penalty rate would be > 0.
        $prior = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 month')));
        $vehicle_id = $this->create_active_vehicle();

        $penalty = PenaltyCalculator::calculate_withdrawal_penalty($vehicle_id, $this->vendor_id);
        $this->assertSame(0.0, $penalty); // No ledger entries → 0 revenue → 0 penalty.

        wp_delete_post($prior, true);
        wp_delete_post($vehicle_id, true);
    }

    // ── Helper Methods ───────────────────────────────────────

    private function create_active_vehicle(): int
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Penalty Test Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);

        return $vehicle_id;
    }

    private function create_withdrawn_vehicle(string $withdrawn_at): int
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'draft',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Withdrawn Vehicle',
        ));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::WITHDRAWN);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_WITHDRAWN_AT, $withdrawn_at);

        return $vehicle_id;
    }

    protected function tearDown(): void
    {
        wp_delete_user($this->vendor_id);
        parent::tearDown();
    }
}
