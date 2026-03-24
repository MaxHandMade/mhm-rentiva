<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable Value Object representing a Commission Policy snapshot.
 *
 * A policy defines the platform commission rates applicable within a specific
 * temporal window. Instances are resolved by PolicyService::resolve_policy_at()
 * using the booking datetime, ensuring historical accuracy for refunds and audits.
 *
 * version_hash = SHA-256(json_encode([vendor_id, global_rate, effective_from, effective_to]))
 * This produces a cryptographic state fingerprint. If any of the four fields change,
 * the hash changes — guaranteeing audit-proof uniqueness without relying solely on the
 * database row ID which could theoretically be manipulated.
 *
 * @since 4.21.0
 */
final class CommissionPolicy
{
    private int $id;
    private string $label;
    private float $global_rate;
    private ?int $vendor_id;
    private string $effective_from;
    private ?string $effective_to;
    private string $version_hash;

    public function __construct(
        int $id,
        string $label,
        float $global_rate,
        ?int $vendor_id,
        string $effective_from,
        ?string $effective_to,
        string $version_hash
    ) {
        $this->id             = $id;
        $this->label          = $label;
        $this->global_rate    = $global_rate;
        $this->vendor_id      = $vendor_id;
        $this->effective_from = $effective_from;
        $this->effective_to   = $effective_to;
        $this->version_hash   = $version_hash;
    }

    /**
     * Compute the canonical state fingerprint for a set of policy fields.
     * Used during policy creation and when verifying stored hashes.
     *
     * Hash input = JSON-encoded array of [vendor_id, global_rate, effective_from, effective_to].
     * NULL values are preserved in JSON as null, ensuring empty/open-ended policies have
     * distinct fingerprints.
     */
    public static function compute_version_hash(
        ?int $vendor_id,
        float $global_rate,
        string $effective_from,
        ?string $effective_to
    ): string {
        return hash(
            'sha256',
            (string) wp_json_encode(array(
                'vendor_id'      => $vendor_id,
                'global_rate'    => $global_rate,
                'effective_from' => $effective_from,
                'effective_to'   => $effective_to,
            ))
        );
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_label(): string
    {
        return $this->label;
    }

    public function get_global_rate(): float
    {
        return $this->global_rate;
    }

    /**
     * Returns vendor_id for vendor-specific policies, or NULL for platform-wide policies.
     */
    public function get_vendor_id(): ?int
    {
        return $this->vendor_id;
    }

    public function get_effective_from(): string
    {
        return $this->effective_from;
    }

    /**
     * Returns NULL for open-ended policies with no scheduled expiry.
     */
    public function get_effective_to(): ?string
    {
        return $this->effective_to;
    }

    /**
     * Returns the SHA-256 state fingerprint of this policy's fields.
     */
    public function get_version_hash(): string
    {
        return $this->version_hash;
    }
}
