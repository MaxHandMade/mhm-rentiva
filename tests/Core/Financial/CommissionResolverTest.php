<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Core\Financial\CommissionPolicy;
use MHMRentiva\Core\Financial\CommissionResolver;
use MHMRentiva\Core\Financial\CommissionResult;
use MHMRentiva\Core\Financial\PolicyRepository;

/**
 * Unit tests for CommissionResolver hierarchy resolution.
 *
 * Scenarios covered:
 *  1. Vehicle-level override wins (SOURCE_VEHICLE)
 *  2. Vendor-level override wins when no vehicle override (SOURCE_VENDOR)
 *  3. Tier discount applied when no override exists (SOURCE_TIER)
 *  4. Policy global rate used when no override and no tier eligibility (SOURCE_GLOBAL)
 *
 * @since 4.21.0
 */
class CommissionResolverTest extends \WP_UnitTestCase
{
    private CommissionPolicy $policy;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_commission_policy");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger");

        // Create a platform-wide policy at 15% base rate.
        $this->policy = new CommissionPolicy(
            1,
            'Platform Default',
            15.0,
            null, // platform-wide
            '2000-01-01 00:00:00',
            null, // open-ended
            CommissionPolicy::compute_version_hash(null, 15.0, '2000-01-01 00:00:00', null)
        );
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_commission_policy");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger");

        parent::tearDown();
    }

	// -------------------------------------------------------------------------
	// Scenario 1: Vehicle-level override
	// -------------------------------------------------------------------------

    /**
     * @test
     * When a vehicle has a CPT-level commission rate meta, it should supersede
     * vendor override, tier, and policy global rate entirely.
     */
    public function test_vehicle_override_wins_over_all_lower_layers(): void
    {
        $vendor_id  = 1;
        $vehicle_id = 100;
        $amount     = 1000.0;

        $this->stub_policy_service($vendor_id, $this->policy);
        $this->stub_get_post_meta($vehicle_id, '_mhm_vendor_commission_rate', '10.0');
        $this->stub_get_user_meta($vendor_id, '_mhm_vendor_commission_rate', '12.0');

        $result = CommissionResolver::calculate($amount, $vendor_id, '', $vehicle_id);

        $this->assertInstanceOf(CommissionResult::class, $result);
        $this->assertSame(CommissionResult::SOURCE_VEHICLE, $result->get_applied_source());
        $this->assertSame(10.0, $result->get_commission_rate_snapshot());
        $this->assertSame(100.0, $result->get_commission_amount()); // 1000 * 10% = 100
        $this->assertSame(900.0, $result->get_vendor_net_amount());
        $this->assertNotNull($result->get_policy_id());
    }

	// -------------------------------------------------------------------------
	// Scenario 2: Vendor-level override (no vehicle override)
	// -------------------------------------------------------------------------

    /**
     * @test
     * When no vehicle override exists, the vendor-level override must win
     * over tier and global rate.
     */
    public function test_vendor_override_wins_when_no_vehicle_override(): void
    {
        $vendor_id  = 2;
        $vehicle_id = 0; // No vehicle context.
        $amount     = 2000.0;

        $this->stub_policy_service($vendor_id, $this->policy);
        $this->stub_get_post_meta(0, '_mhm_vendor_commission_rate', null); // No vehicle meta.
        $this->stub_get_user_meta($vendor_id, '_mhm_vendor_commission_rate', '8.0');

        $result = CommissionResolver::calculate($amount, $vendor_id, '', $vehicle_id);

        $this->assertSame(CommissionResult::SOURCE_VENDOR, $result->get_applied_source());
        $this->assertSame(8.0, $result->get_commission_rate_snapshot());
        $this->assertSame(160.0, $result->get_commission_amount()); // 2000 * 8% = 160
        $this->assertSame(1840.0, $result->get_vendor_net_amount());
    }

	// -------------------------------------------------------------------------
	// Scenario 3: Tier discount applied (no vehicle or vendor override)
	// -------------------------------------------------------------------------

    /**
     * @test
     * When no vehicle or vendor override exists, and the vendor's 30-day revenue
     * qualifies for a tier discount, the effective rate must be policy_base - discount.
     *
     * Policy base: 15%
     * Tier (>=5000 TRY 30d revenue): -2% discount → effective rate: 13%
     */
    public function test_tier_discount_applied_when_no_override_and_revenue_qualifies(): void
    {
        $vendor_id  = 3;
        $vehicle_id = 0;
        $amount     = 500.0;

        $this->stub_policy_service($vendor_id, $this->policy);
        $this->stub_get_post_meta(0, '_mhm_vendor_commission_rate', null);
        $this->stub_get_user_meta($vendor_id, '_mhm_vendor_commission_rate', null);

        // Simulate 8000 TRY revenue → qualifies for 2% discount.
        $this->stub_tier_service($vendor_id, 15.0, 13.0);

        $result = CommissionResolver::calculate($amount, $vendor_id, '', $vehicle_id);

        $this->assertSame(CommissionResult::SOURCE_TIER, $result->get_applied_source());
        $this->assertSame(13.0, $result->get_commission_rate_snapshot());
        $this->assertSame(65.0, $result->get_commission_amount()); // 500 * 13% = 65
        $this->assertSame(435.0, $result->get_vendor_net_amount());
    }

	// -------------------------------------------------------------------------
	// Scenario 4: Policy global rate (no override, no tier eligibility)
	// -------------------------------------------------------------------------

    /**
     * @test
     * When no override and vendor revenue does not qualify for any tier,
     * the policy global rate must be used and applied_source = 'global'.
     */
    public function test_global_policy_rate_used_as_final_fallback(): void
    {
        $vendor_id  = 4;
        $vehicle_id = 0;
        $amount     = 200.0;

        $this->stub_policy_service($vendor_id, $this->policy);
        $this->stub_get_post_meta(0, '_mhm_vendor_commission_rate', null);
        $this->stub_get_user_meta($vendor_id, '_mhm_vendor_commission_rate', null);

        // Tier returns same as base (no discount applies — revenue < 5000 threshold).
        $this->stub_tier_service($vendor_id, 15.0, 15.0);

        $result = CommissionResolver::calculate($amount, $vendor_id, '', $vehicle_id);

        $this->assertSame(CommissionResult::SOURCE_GLOBAL, $result->get_applied_source());
        $this->assertSame(15.0, $result->get_commission_rate_snapshot());
        $this->assertSame(30.0, $result->get_commission_amount()); // 200 * 15% = 30
        $this->assertSame(170.0, $result->get_vendor_net_amount());
        $this->assertNotNull($result->get_policy_id());
    }

    // -------------------------------------------------------------------------
    // Helper Integrations (replaces WP_Mock)
    // -------------------------------------------------------------------------

    private function stub_policy_service(int $vendor_id, CommissionPolicy $policy): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_commission_policy';
        $wpdb->insert(
            $table,
            array(
                'label'          => $policy->get_label(),
                'global_rate'    => $policy->get_global_rate(),
                'vendor_id'      => null, // platform-wide
                'effective_from' => '2000-01-01 00:00:00',
                'effective_to'   => null,
                'version_hash'   => substr($policy->get_version_hash(), 0, 64),
                'created_by'     => 1,
                'created_at'     => current_time('mysql', true),
            ),
            array('%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s') // null values will be ignored/passed properly by WPDB
        );
    }

    private function stub_get_post_meta(int $vehicle_id, string $key, ?string $value): void
    {
        if ($vehicle_id > 0) {
            if ($value !== null) {
                update_post_meta($vehicle_id, $key, $value);
            } else {
                delete_post_meta($vehicle_id, $key);
            }
        }
    }

    private function stub_get_user_meta(int $vendor_id, string $key, ?string $value): void
    {
        if ($vendor_id > 0) {
            if ($value !== null) {
                update_user_meta($vendor_id, $key, $value);
            } else {
                delete_user_meta($vendor_id, $key);
            }
        }
    }

    private function stub_tier_service(int $vendor_id, float $base_rate, float $result_rate): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';
        $discount = $base_rate - $result_rate;
        $revenue = 0.0;

        if ($discount >= 6.0) {
            $revenue = 35000.0;
        } elseif ($discount >= 4.0) {
            $revenue = 20000.0;
        } elseif ($discount >= 2.0) {
            $revenue = 8000.0;
        }

        if ($revenue > 0) {
            $uuid = substr(md5('tier_test_' . $vendor_id . '_' . microtime(true)), 0, 36);
            $wpdb->insert(
                $table,
                array(
                    'transaction_uuid' => $uuid,
                    'vendor_id'        => $vendor_id,
                    'booking_id'       => 1001,
                    'type'             => 'commission_credit',
                    'amount'           => $revenue,
                    'currency'         => 'TRY',
                    'context'          => 'vendor',
                    'status'           => 'cleared',
                    'created_at'       => gmdate('Y-m-d H:i:s', time() - 86400),
                ),
                array('%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s')
            );
        }
    }
}
