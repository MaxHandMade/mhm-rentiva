<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pure Domain Layer resolving vendor commission rates through an explicit 4-level hierarchy.
 *
 * Resolution Order (highest specificity wins):
 * ┌─────────────────────────────────────────────────────────────────┐
 * │ 1. Vehicle-Level Override  (meta: _mhm_vendor_commission_rate   │
 * │                             on the vehicle CPT post)            │
 * │    → Source: 'vehicle'                                          │
 * ├─────────────────────────────────────────────────────────────────┤
 * │ 2. Vendor-Level Override   (meta: _mhm_vendor_commission_rate   │
 * │                             on the vendor WP user)              │
 * │    → Source: 'vendor'                                           │
 * ├─────────────────────────────────────────────────────────────────┤
 * │ 3. TierService Discount    (30d cleared NET revenue from        │
 * │                             ledger applied over Policy rate)    │
 * │    → Source: 'tier'                                             │
 * ├─────────────────────────────────────────────────────────────────┤
 * │ 4. Policy Global Rate      (from active CommissionPolicy)       │
 * │    → Source: 'global'                                           │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * KEY DESIGN DECISIONS:
 * - Vendor override always wins over Tier. Contracts are inviolable.
 * - Tier discounts are additive reductions applied over the Policy global rate,
 *   never over a vendor or vehicle override — they represent platform incentives,
 *   not contract modifications.
 * - PolicyService provides the base rate + policy metadata (id + version_hash)
 *   for ledger audit purposes on every single calculation.
 * - If PolicyService throws (no active policy), the RuntimeException propagates
 *   to the caller. No silent fallback to a hardcoded default.
 *
 * @since 4.20.0
 * @since 4.21.0 Refactored with explicit 4-level hierarchy, PolicyService integration,
 *               TierService integration, and full CommissionResult metadata.
 */
final class CommissionResolver {

    /**
     * Legacy default — used only as an emergency guard if PolicyService is unavailable
     * in a non-production test context. NOT a silent production fallback.
     */
    public const DEFAULT_COMMISSION_RATE = 15.0;

    /**
     * Calculate commission for a payment, resolving rates through the full hierarchy.
     *
     * @param float   $payment_amount Gross payment captured by WooCommerce.
     * @param int     $vendor_id      WordPress user ID of the vendor.
     * @param string  $booking_date   UTC datetime of the booking event (for policy resolution).
     *                                Defaults to current UTC time if empty.
     * @param int     $vehicle_id     CPT post ID of the vehicle (for vehicle-level override).
     *                                0 = no vehicle context (transfer, etc.)
     *
     * @throws \InvalidArgumentException If $payment_amount is negative.
     * @throws \RuntimeException         If PolicyService finds no active policy.
     */
    public static function calculate(
        float $payment_amount,
        int $vendor_id,
        string $booking_date = '',
        int $vehicle_id = 0
    ): CommissionResult {
        if ($payment_amount < 0.0) {
            throw new \InvalidArgumentException(
                'Gross payment amount must be non-negative.'
            );
        }

        // Normalize booking date: default to now (UTC) if not supplied.
        $booking_date = ( $booking_date !== '' ) ? $booking_date : (string) current_time('mysql', true);

        // --- Resolve active policy (provides base rate + audit metadata) ---
        $policy           = PolicyService::resolve_policy_at($vendor_id, $booking_date);
        $policy_id        = $policy->get_id();
        $policy_version   = $policy->get_version_hash();
        $policy_base_rate = $policy->get_global_rate();

        // --- HIERARCHY RESOLUTION ---
        $rate   = null;
        $source = CommissionResult::SOURCE_GLOBAL;

        // Layer 1: Vehicle-level override.
        if ($vehicle_id > 0) {
            $vehicle_rate = get_post_meta($vehicle_id, '_mhm_vendor_commission_rate', true);
            if (is_numeric($vehicle_rate) && (float) $vehicle_rate >= 0.0) {
                $rate   = (float) $vehicle_rate;
                $source = CommissionResult::SOURCE_VEHICLE;
            }
        }

        // Layer 2: Vendor-level override (only if vehicle override did not match).
        if ($rate === null) {
            $vendor_rate = get_user_meta($vendor_id, '_mhm_vendor_commission_rate', true);
            if (is_numeric($vendor_rate) && (float) $vendor_rate >= 0.0) {
                $rate   = (float) $vendor_rate;
                $source = CommissionResult::SOURCE_VENDOR;
            }
        }

        // Layer 3 + 4: No direct override — apply TierService over the Policy global rate.
        if ($rate === null) {
            $tier_rate = TierService::apply_if_eligible($vendor_id, $policy_base_rate, $booking_date);

            // Distinguish whether tier reduced the rate or not.
            if ($tier_rate < $policy_base_rate) {
                $source = CommissionResult::SOURCE_TIER;
            } else {
                $source = CommissionResult::SOURCE_GLOBAL;
            }

            $rate = $tier_rate;
        }

        // --- MATH (banker-safe rounding) ---
        $commission_amount = round(( $payment_amount * $rate ) / 100.0, 2, PHP_ROUND_HALF_UP);
        $vendor_net_amount = round($payment_amount - $commission_amount, 2, PHP_ROUND_HALF_UP);

        return new CommissionResult(
            $payment_amount,
            $commission_amount,
            $vendor_net_amount,
            $rate,
            $source,
            $policy_id,
            $policy_version
        );
    }
}
