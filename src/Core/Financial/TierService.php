<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves volume-based commission discount tiers from ledger data.
 *
 * TierService sits BELOW vendor override in the resolution hierarchy:
 *   Vehicle Override → Vendor Override → TierService → Policy Global Rate
 *
 * It ONLY activates when neither a vehicle-level nor vendor-level override is present.
 * It NEVER modifies the policy table — it computes an additive discount over the
 * policy's global_rate. This preserves the strict separation:
 *
 *   Policy = base rate contract
 *   Tier   = performance discount logic
 *
 * Revenue calculation is NET (31-day cleared commission_credit + commission_refund):
 *   - commission_credit : vendor income events (positive)
 *   - commission_refund : reversal events (negative values reduce net)
 *   - payout_debit      : EXCLUDED — payouts are not revenue
 *
 * This produces the vendor's true 30-day cleared net revenue for tier eligibility.
 *
 * @since 4.21.0
 */
final class TierService
{
    /**
     * Tier thresholds and their corresponding commission rate discounts (in percentage points).
     * Configured via WordPress options for admin flexibility.
     *
     * Default tiers as a starting point:
     *   0–4999 TRY  cleared 30d net revenue → no discount (use policy global rate)
     *   5000+ TRY                            → 2% reduction
     *   15000+ TRY                           → 4% reduction
     *   30000+ TRY                           → 6% reduction
     */
    private const DEFAULT_TIERS = array(
        array('threshold' => 30000.0, 'discount' => 6.0),
        array('threshold' => 15000.0, 'discount' => 4.0),
        array('threshold' => 5000.0,  'discount' => 2.0),
    );

    /**
     * Resolves the effective rate after applying any applicable tier discount.
     *
     * If the vendor's 30-day net cleared revenue qualifies for a tier, the corresponding
     * discount (in percentage points) is subtracted from the base rate.
     * The resulting rate is floored at 0.0 to prevent negative commissions.
     *
     * @param int   $vendor_id       Vendor user ID.
     * @param float $base_rate       The policy global rate (or fallback default).
     * @param string $as_of_datetime UTC datetime for the revenue calculation window.
     * @return float The effective rate after tier discount, or the original base_rate if no tier applies.
     */
    public static function apply_if_eligible(int $vendor_id, float $base_rate, string $as_of_datetime = ''): float
    {
        if ($as_of_datetime === '') {
            $as_of_datetime = (string) current_time('mysql', true);
        }

        $net_revenue_30d = self::get_net_cleared_revenue_30d($vendor_id, $as_of_datetime);
        $tiers           = self::get_tiers();

        // Tiers are already sorted descending by threshold (highest first).
        foreach ($tiers as $tier) {
            if ($net_revenue_30d >= $tier['threshold']) {
                $discounted = $base_rate - $tier['discount'];
                return max(0.0, $discounted); // Floor at 0 — no negative commission rates.
            }
        }

        return $base_rate; // No tier matched — return original base rate unchanged.
    }

    /**
     * Compute the vendor's 30-day cleared net revenue from the immutable ledger.
     *
     * NET revenue = SUM(amount) WHERE:
     *   vendor_id  = X
     *   status     = 'cleared'
     *   type       IN ('commission_credit', 'commission_refund')
     *   created_at >= (as_of_datetime - 30 days)
     *
     * Rationale:
     *   - commission_credit: positive vendor income entries.
     *   - commission_refund: negative reversal entries (reduce net revenue correctly).
     *   - payout_debit: EXCLUDED — a payout is a withdrawal, not revenue.
     *
     * Only 'cleared' status is used (settled, not pending) to ensure tier eligibility
     * is based on confirmed revenue, not optimistic estimates.
     *
     * Source: ledger only. No booking table, no post_meta, no CPT.
     */
    private static function get_net_cleared_revenue_30d(int $vendor_id, string $as_of_datetime): float
    {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'mhm_rentiva_ledger' );

        $window_start = gmdate('Y-m-d H:i:s', strtotime($as_of_datetime . ' -30 days'));

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount)
				FROM {$table}
				WHERE vendor_id = %d
				AND status = %s
				AND type IN (%s, %s)
				AND created_at >= %s",
                $vendor_id,
                'cleared',
                'commission_credit',
                'commission_refund',
                $window_start
            )
        );

        return (float) $sum;
    }

    /**
     * Load tiered thresholds from WP options, falling back to hardcoded defaults.
     * Admins can override via: update_option('mhm_rentiva_commission_tiers', [...]).
     *
     * @return array<int, array{threshold: float, discount: float}>
     */
    private static function get_tiers(): array
    {
        $option = get_option('mhm_rentiva_commission_tiers', null);

        if (is_array($option) && count($option) > 0) {
            // Sort descending by threshold so highest tier matches first.
            usort($option, static function (array $a, array $b): int {
                return (float) $b['threshold'] <=> (float) $a['threshold'];
            });
            return $option;
        }

        return self::DEFAULT_TIERS; // Already sorted descending.
    }
}
