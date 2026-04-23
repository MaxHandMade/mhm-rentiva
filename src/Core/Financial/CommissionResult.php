<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable snapshot of a commission calculation result.
 *
 * @since 4.20.0
 * @since 4.21.0 Extended with policy_id, policy_version_hash, and applied_source
 *               for full Policy Versioning and Analytics support.
 */
final class CommissionResult {

    /**
     * Constants declaring the layer of the hierarchy that resolved the final rate.
     * Used by Analytics to audit and aggregate breakdown metrics per tier.
     */
    public const SOURCE_VEHICLE = 'vehicle';
    public const SOURCE_VENDOR  = 'vendor';
    public const SOURCE_TIER    = 'tier';
    public const SOURCE_GLOBAL  = 'global';

    private float $gross_amount;
    private float $commission_amount;
    private float $vendor_net_amount;
    private float $commission_rate_snapshot;

    /**
     * FK to mhm_rentiva_commission_policy.id active at calculation time.
     * NULL only when no Policy Versioning system is available.
     */
    private ?int $policy_id;

    /**
     * SHA-256 state fingerprint of the active policy at calculation time.
     */
    private ?string $policy_version_hash;

    /**
     * Which layer of the hierarchy resolved the final rate.
     * One of: 'vehicle' | 'vendor' | 'tier' | 'global'
     */
    private string $applied_source;

    public function __construct(
        float $gross_amount,
        float $commission_amount,
        float $vendor_net_amount,
        float $commission_rate_snapshot,
        string $applied_source = self::SOURCE_GLOBAL,
        ?int $policy_id = null,
        ?string $policy_version_hash = null
    ) {
        $this->gross_amount             = $gross_amount;
        $this->commission_amount        = $commission_amount;
        $this->vendor_net_amount        = $vendor_net_amount;
        $this->commission_rate_snapshot = $commission_rate_snapshot;
        $this->applied_source           = $applied_source;
        $this->policy_id                = $policy_id;
        $this->policy_version_hash      = $policy_version_hash;
    }

    public function get_gross_amount(): float
    {
        return $this->gross_amount;
    }

    public function get_commission_amount(): float
    {
        return $this->commission_amount;
    }

    public function get_vendor_net_amount(): float
    {
        return $this->vendor_net_amount;
    }

    public function get_commission_rate_snapshot(): float
    {
        return $this->commission_rate_snapshot;
    }

    /**
     * Returns which hierarchy layer resolved the final rate.
     * Possible values: 'vehicle' | 'vendor' | 'tier' | 'global'
     */
    public function get_applied_source(): string
    {
        return $this->applied_source;
    }

    /**
     * Returns FK to commission policy active at calculation time.
     */
    public function get_policy_id(): ?int
    {
        return $this->policy_id;
    }

    /**
     * Returns SHA-256 state fingerprint of the policy at calculation time.
     */
    public function get_policy_version_hash(): ?string
    {
        return $this->policy_version_hash;
    }
}
