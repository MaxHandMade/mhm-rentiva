<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Service layer for temporal commission policy resolution.
 *
 * PolicyService is the single entry point for resolving which commission policy
 * was active at any given moment. It guarantees that:
 *
 * 1. The result is always a concrete CommissionPolicy — never a silent fallback to
 *    a hardcoded default without administrator awareness.
 * 2. Overlapping policies resolve deterministically (most recently created wins).
 * 3. A missing platform-wide policy throws a RuntimeException to surface admin
 *    misconfiguration rather than silently applying a wrong rate.
 *
 * Usage:
 *   $policy = PolicyService::resolve_policy_at($vendor_id, current_time('mysql', true));
 *   $rate   = $policy->get_global_rate();
 *
 * @since 4.21.0
 */
final class PolicyService
{
    /**
     * Resolve the commission policy active for a vendor at the given UTC datetime.
     *
     * @param int    $vendor_id The vendor user ID.
     * @param string $datetime  UTC datetime string 'Y-m-d H:i:s'. Defaults to now if empty.
     *
     * @throws \RuntimeException If no active policy (vendor-specific or platform-wide) is
     *                           found for the given datetime. This indicates an admin configuration
     *                           gap and must never be silently swallowed.
     */
    public static function resolve_policy_at(int $vendor_id, string $datetime = ''): CommissionPolicy
    {
        // Normalize: if booking_date is not provided, default to now (UTC).
        if ($datetime === '') {
            $datetime = (string) current_time('mysql', true);
        }

        $policy = PolicyRepository::find_active_at($vendor_id, $datetime);

        if ($policy === null) {
            throw new \RuntimeException(sprintf(
                /* translators: 1: vendor ID, 2: datetime string */
                esc_html__(
                    'No active commission policy found for vendor #%1$d at %2$s. Please configure a platform-wide policy in the admin.',
                    'mhm-rentiva'
                ),
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
                $vendor_id,
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
                $datetime
            ));
        }

        return $policy;
    }
}
