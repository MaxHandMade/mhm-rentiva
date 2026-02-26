<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Risk;

/**
 * Immutable DTO providing context for payout risk evaluation.
 *
 * @since 4.21.0
 */
class PayoutContext
{
    /** @var int */
    public int $payout_id;

    /** @var int */
    public int $vendor_id;

    /** @var float */
    public float $payout_amount;

    /** @var int */
    public int $vendor_age_days;

    /** @var int */
    public int $total_completed_payouts;

    /** @var int */
    public int $total_refunded_payouts;

    public function __construct(
        int $payout_id,
        int $vendor_id,
        float $payout_amount,
        int $vendor_age_days,
        int $total_completed_payouts,
        int $total_refunded_payouts
    ) {
        $this->payout_id               = $payout_id;
        $this->vendor_id               = $vendor_id;
        $this->payout_amount           = $payout_amount;
        $this->vendor_age_days         = $vendor_age_days;
        $this->total_completed_payouts = $total_completed_payouts;
        $this->total_refunded_payouts  = $total_refunded_payouts;
    }
}
