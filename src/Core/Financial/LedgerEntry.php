<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable Value Object representing a single transaction in the ledger.
 *
 * @since 4.20.0
 * @since 4.21.0 Added policy_id and policy_version_hash for Commission Policy Versioning.
 */
final class LedgerEntry
{
    private string $transaction_uuid;
    private int $vendor_id;
    private ?int $booking_id;
    private ?int $order_id;
    private string $type;
    private float $amount;
    private ?float $gross_amount;
    private ?float $commission_amount;
    private ?float $commission_rate;
    private string $currency;
    private string $context;
    private string $status;
    private ?string $created_at;

    /**
     * Policy ID foreign key referencing mhm_rentiva_commission_policy.id.
     * NULL for entries created before Policy Versioning was introduced.
     */
    private ?int $policy_id;

    /**
     * SHA-256 fingerprint of the policy state at write-time.
     * Computed as: hash('sha256', json_encode([vendor_id, global_rate, effective_from, effective_to]))
     * Provides cryptographic audit proof independent of row mutations in the policy table.
     * NULL for entries created before Policy Versioning was introduced.
     */
    private ?string $policy_version_hash;

    public function __construct(
        string $transaction_uuid,
        int $vendor_id,
        ?int $booking_id,
        ?int $order_id,
        string $type,
        float $amount,
        ?float $gross_amount,
        ?float $commission_amount,
        ?float $commission_rate,
        string $currency,
        string $context,
        string $status,
        ?string $created_at = null,
        ?int $policy_id = null,
        ?string $policy_version_hash = null
    ) {
        $this->transaction_uuid    = $transaction_uuid;
        $this->vendor_id           = $vendor_id;
        $this->booking_id          = $booking_id;
        $this->order_id            = $order_id;
        $this->type                = $type;
        $this->amount              = $amount;
        $this->gross_amount        = $gross_amount;
        $this->commission_amount   = $commission_amount;
        $this->commission_rate     = $commission_rate;
        $this->currency            = $currency;
        $this->context             = $context;
        $this->status              = $status;
        $this->created_at          = $created_at;
        $this->policy_id           = $policy_id;
        $this->policy_version_hash = $policy_version_hash;
    }

    public function get_transaction_uuid(): string
    {
        return $this->transaction_uuid;
    }

    public function get_vendor_id(): int
    {
        return $this->vendor_id;
    }

    public function get_booking_id(): ?int
    {
        return $this->booking_id;
    }

    public function get_order_id(): ?int
    {
        return $this->order_id;
    }

    public function get_type(): string
    {
        return $this->type;
    }

    public function get_amount(): float
    {
        return $this->amount;
    }

    public function get_gross_amount(): ?float
    {
        return $this->gross_amount;
    }

    public function get_commission_amount(): ?float
    {
        return $this->commission_amount;
    }

    public function get_commission_rate(): ?float
    {
        return $this->commission_rate;
    }

    public function get_currency(): string
    {
        return $this->currency;
    }

    public function get_context(): string
    {
        return $this->context;
    }

    public function get_status(): string
    {
        return $this->status;
    }

    public function get_created_at(): ?string
    {
        return $this->created_at;
    }

    /**
     * Returns the FK to the commission policy active at write-time.
     * NULL for entries predating Policy Versioning (Sprint 3).
     */
    public function get_policy_id(): ?int
    {
        return $this->policy_id;
    }

    /**
     * Returns the SHA-256 state fingerprint of the active policy at write-time.
     * NULL for entries predating Policy Versioning (Sprint 3).
     */
    public function get_policy_version_hash(): ?string
    {
        return $this->policy_version_hash;
    }
}
