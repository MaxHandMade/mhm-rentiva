<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable snapshot evaluating Commission deduction mathematics explicitly separating net from gross.
 */
final class CommissionResult
{
    private float $gross_amount;
    private float $commission_amount;
    private float $vendor_net_amount;
    private float $commission_rate_snapshot;

    public function __construct(
        float $gross_amount,
        float $commission_amount,
        float $vendor_net_amount,
        float $commission_rate_snapshot
    ) {
        $this->gross_amount             = $gross_amount;
        $this->commission_amount        = $commission_amount;
        $this->vendor_net_amount        = $vendor_net_amount;
        $this->commission_rate_snapshot = $commission_rate_snapshot;
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
}
