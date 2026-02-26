<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Risk;

use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Tenancy\TenantResolver;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Manages risk-based cooling periods for Time-Locked Execution.
 *
 * Implements a deterministic mapping between RiskLevel and cooling duration.
 * Supports per-tenant policy overrides via the `mhm_rentiva_filter_cooling_policy_map` hook.
 * Uses strict UTC (gmdate) for all calculations to ensure cross-environment consistency.
 *
 * @since 4.23.0
 * @updated 4.23.0 Added multi-tenant policy support.
 */
final class CoolingPolicyManager
{
    /**
     * Current policy version for forensic tracing.
     */
    public const POLICY_VERSION = 'v1';

    /**
     * Default global policy: RiskLevel => Minutes (Integer, UTC strict).
     */
    private const DEFAULT_POLICY_MAP = [
        'low'    => 360,  // 6 hours
        'medium' => 1440, // 24 hours
        'high'   => 2880, // 48 hours
    ];

    /**
     * Calculates the release timestamp based on risk level and optional tenant context.
     *
     * Allows per-tenant cooling period overrides via the
     * `mhm_rentiva_filter_cooling_policy_map` WordPress filter.
     *
     * @param string             $risk_level One of RiskLevel constants ('low', 'medium', 'high').
     * @param TenantContext|null $tenant     The tenant context. Defaults to current resolved tenant.
     * @return string UTC formatted timestamp (Y-m-d H:i:s).
     */
    public static function calculate_release_time(string $risk_level, ?TenantContext $tenant = null): string
    {
        $minutes = self::get_cooling_minutes($risk_level, $tenant);

        // Strict UTC arithmetic: time() is always epoch UTC, gmdate formats it.
        $release_timestamp = time() + ($minutes * 60);

        return gmdate('Y-m-d H:i:s', $release_timestamp);
    }

    /**
     * Gets the number of cooling minutes for a given risk level.
     * Applies per-tenant policy overrides if a TenantContext is provided.
     *
     * @since 4.23.0
     * @param string             $risk_level
     * @param TenantContext|null $tenant
     * @return int
     */
    public static function get_cooling_minutes(string $risk_level, ?TenantContext $tenant = null): int
    {
        $tenant = $tenant ?? TenantResolver::resolve();

        /**
         * Filters the cooling policy map for a specific tenant.
         * Enables jurisdiction-specific overrides (e.g., EU regulations may require longer periods).
         *
         * @since 4.23.0
         * @param array<string, int> $policy_map        The default policy map ['low' => 360, ...].
         * @param string             $compliance_profile The tenant's compliance profile (e.g., 'eu', 'tr').
         * @param int                $tenant_id          The tenant's ID.
         */
        $policy_map = (array) apply_filters(
            'mhm_rentiva_filter_cooling_policy_map',
            self::DEFAULT_POLICY_MAP,
            $tenant->get_compliance_profile(),
            $tenant->get_id()
        );

        // V1.8 HARDENING: Validate hook return values
        // Rules: Must be integer, positive, and max 10080 minutes (7 days)
        $minutes = $policy_map[$risk_level] ?? $policy_map['medium'] ?? self::DEFAULT_POLICY_MAP['medium'];

        if (! is_int($minutes) || $minutes < 0 || $minutes > 10080) {
            // Log violation but keep engine running with default to prevent lockout
            if (class_exists('\\MHMRentiva\\Admin\\PostTypes\\Logs\\AdvancedLogger')) {
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
                    'Invalid cooling policy override detected. Falling back to default.',
                    [
                        'tenant_id' => $tenant->get_id(),
                        'provided_value' => $minutes,
                        'risk_level' => $risk_level
                    ],
                    'payout_governance'
                );
            }
            return self::DEFAULT_POLICY_MAP[$risk_level] ?? self::DEFAULT_POLICY_MAP['medium'];
        }

        return (int) $minutes;
    }
}
