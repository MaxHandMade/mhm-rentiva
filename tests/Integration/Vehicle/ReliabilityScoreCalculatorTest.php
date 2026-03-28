<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Core\MetaKeys;

class ReliabilityScoreCalculatorTest extends \WP_UnitTestCase
{
    private int $vendor_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($this->vendor_id);
        $user->add_role('rentiva_vendor');
    }

    // ── Base Score ───────────────────────────────────────────

    public function test_new_vendor_has_perfect_score(): void
    {
        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        $this->assertSame(100, $score);
    }

    public function test_get_returns_100_when_not_calculated(): void
    {
        $this->assertSame(100, ReliabilityScoreCalculator::get($this->vendor_id));
    }

    // ── Withdrawal Demerits ──────────────────────────────────

    public function test_withdrawal_reduces_score(): void
    {
        $vehicle_id = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 month')));

        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        $this->assertSame(100 - ReliabilityScoreCalculator::WITHDRAWAL_PENALTY, $score);

        wp_delete_post($vehicle_id, true);
    }

    public function test_two_withdrawals_reduces_score_by_20(): void
    {
        $v1 = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 month')));
        $v2 = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-3 months')));

        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        $this->assertSame(100 - (2 * ReliabilityScoreCalculator::WITHDRAWAL_PENALTY), $score);

        wp_delete_post($v1, true);
        wp_delete_post($v2, true);
    }

    // ── Pause Demerits ───────────────────────────────────────

    public function test_pause_reduces_score(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Pause Test',
        ));

        $current_month = gmdate('Y-m');
        update_post_meta($vehicle_id, '_mhm_vehicle_pause_count_month', $current_month . ':1');

        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        $this->assertSame(100 - ReliabilityScoreCalculator::PAUSE_PENALTY, $score);

        wp_delete_post($vehicle_id, true);
    }

    // ── Completion Bonus ─────────────────────────────────────

    public function test_completed_bookings_add_bonus(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Bonus Test Vehicle',
        ));

        $this->create_booking($vehicle_id, 'completed');
        $this->create_booking($vehicle_id, 'completed');

        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        // 100 + (2 * 5) = 110 → clamped to 100.
        $this->assertSame(100, $score);

        wp_delete_post($vehicle_id, true);
    }

    public function test_completion_bonus_capped_at_max(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Bonus Test Vehicle',
        ));

        // Create enough completed bookings to max out bonus.
        for ($i = 0; $i < 10; $i++) {
            $this->create_booking($vehicle_id, 'completed');
        }

        // Also add a withdrawal so base < 100.
        $withdrawn = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-1 month')));

        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        // 100 - 10 (withdrawal) + 20 (max bonus) = 110 → clamped to 100.
        $this->assertSame(100, $score);

        wp_delete_post($vehicle_id, true);
        wp_delete_post($withdrawn, true);
    }

    // ── Combined Scenario ────────────────────────────────────

    public function test_combined_demerits_and_bonuses(): void
    {
        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Combined Test',
        ));

        // 1 withdrawal (-10), 1 pause (-2), 1 completed booking (+5)
        $withdrawn = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime('-2 months')));

        $current_month = gmdate('Y-m');
        update_post_meta($vehicle_id, '_mhm_vehicle_pause_count_month', $current_month . ':1');

        $this->create_booking($vehicle_id, 'completed');

        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        // 100 - 10 - 2 + 5 = 93
        $this->assertSame(93, $score);

        wp_delete_post($vehicle_id, true);
        wp_delete_post($withdrawn, true);
    }

    // ── Score Clamped to 0 ───────────────────────────────────

    public function test_score_never_below_zero(): void
    {
        // Create many withdrawals.
        $vehicles = array();
        for ($i = 0; $i < 15; $i++) {
            $vehicles[] = $this->create_withdrawn_vehicle(gmdate('Y-m-d H:i:s', strtotime("-{$i} months")));
        }

        $score = ReliabilityScoreCalculator::calculate($this->vendor_id);
        $this->assertGreaterThanOrEqual(0, $score);

        foreach ($vehicles as $v) {
            wp_delete_post($v, true);
        }
    }

    // ── Persistence ──────────────────────────────────────────

    public function test_update_persists_score(): void
    {
        $score = ReliabilityScoreCalculator::update($this->vendor_id);

        $this->assertSame(100, $score);
        $this->assertSame(100, ReliabilityScoreCalculator::get($this->vendor_id));
        $this->assertNotEmpty(get_user_meta($this->vendor_id, MetaKeys::VENDOR_RELIABILITY_UPDATED_AT, true));
    }

    // ── Labels and Colors ────────────────────────────────────

    public function test_labels(): void
    {
        $this->assertSame('Excellent', ReliabilityScoreCalculator::get_label(95));
        $this->assertSame('Good', ReliabilityScoreCalculator::get_label(75));
        $this->assertSame('Fair', ReliabilityScoreCalculator::get_label(55));
        $this->assertSame('Poor', ReliabilityScoreCalculator::get_label(30));
    }

    public function test_colors(): void
    {
        $this->assertSame('#28a745', ReliabilityScoreCalculator::get_color(95));
        $this->assertSame('#17a2b8', ReliabilityScoreCalculator::get_color(75));
        $this->assertSame('#ffc107', ReliabilityScoreCalculator::get_color(55));
        $this->assertSame('#dc3545', ReliabilityScoreCalculator::get_color(30));
    }

    // ── Helpers ──────────────────────────────────────────────

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

    private function create_booking(int $vehicle_id, string $status): int
    {
        $booking_id = wp_insert_post(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title'  => 'Test Booking',
        ));

        update_post_meta($booking_id, '_mhm_vehicle_id', $vehicle_id);
        update_post_meta($booking_id, '_mhm_status', $status);

        return $booking_id;
    }

    protected function tearDown(): void
    {
        wp_delete_user($this->vendor_id);
        parent::tearDown();
    }
}
