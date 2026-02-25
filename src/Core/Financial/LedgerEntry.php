<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable Value Object representing a single transaction in the ledger.
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
        ?string $created_at = null
    ) {
        $this->transaction_uuid  = $transaction_uuid;
        $this->vendor_id         = $vendor_id;
        $this->booking_id        = $booking_id;
        $this->order_id          = $order_id;
        $this->type              = $type;
        $this->amount            = $amount;
        $this->gross_amount      = $gross_amount;
        $this->commission_amount = $commission_amount;
        $this->commission_rate   = $commission_rate;
        $this->currency          = $currency;
        $this->context           = $context;
        $this->status            = $status;
        $this->created_at        = $created_at;
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
}
