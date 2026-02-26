<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Risk;

/**
 * Pure, deterministic rules-based risk scoring engine.
 *
 * @since 4.21.0
 */
class DeterministicRiskEngine implements RiskScoringEngineInterface
{
    private float $high_amount_threshold;
    private int $new_vendor_days;
    private int $high_refund_ratio_pct;

    /**
     * @param float $high_amount_threshold Above this amount triggers a risk flag. Default 5000.0.
     * @param int   $new_vendor_days       Vendors younger than this trigger a risk flag. Default 90.
     * @param int   $high_refund_ratio_pct Refund ratio above this triggers a risk flag. Default 10.
     */
    public function __construct(
        float $high_amount_threshold = 5000.0,
        int $new_vendor_days = 90,
        int $high_refund_ratio_pct = 10
    ) {
        $this->high_amount_threshold = $high_amount_threshold;
        $this->new_vendor_days       = $new_vendor_days;
        $this->high_refund_ratio_pct = $high_refund_ratio_pct;
    }

    public function score(PayoutContext $context): RiskResult
    {
        $score   = 0;
        $reasons = [];

        // 1. Amount Threshold Rule
        if ($context->payout_amount > $this->high_amount_threshold) {
            $score += 30;
            $reasons[] = sprintf('Payout amount exceeds threshold (%.2f > %.2f)', $context->payout_amount, $this->high_amount_threshold);
        }

        // 2. Vendor Age Rule
        if ($context->vendor_age_days < $this->new_vendor_days) {
            $score += 20;
            $reasons[] = sprintf('Vendor age is below trusted threshold (%d < %d days)', $context->vendor_age_days, $this->new_vendor_days);
        }

        // 3. Historical Refund Ratio Rule (Strict Integer Math)
        $total_payouts = $context->total_completed_payouts + $context->total_refunded_payouts;
        if ($total_payouts > 0) {
            $refund_ratio = intdiv($context->total_refunded_payouts * 100, $total_payouts);
            if ($refund_ratio > $this->high_refund_ratio_pct) {
                $score += 40;
                $reasons[] = sprintf('Historical refund ratio is abnormally high (%d%% > %d%%)', $refund_ratio, $this->high_refund_ratio_pct);
            }
        } elseif ($context->total_completed_payouts === 0 && $context->total_refunded_payouts === 0) {
            // No history yet
            $score += 10;
            $reasons[] = 'No historical payout data available';
        }

        // 4. Determine Level
        $level = RiskLevel::LOW;
        if ($score >= 70) {
            $level = RiskLevel::HIGH;
        } elseif ($score >= 30) {
            $level = RiskLevel::MEDIUM;
        }

        return new RiskResult(min(100, $score), $level, $reasons);
    }
}
