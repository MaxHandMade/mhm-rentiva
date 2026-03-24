<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Risk;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for the Risk Scoring Engine.
 *
 * Must remain pure and deterministic. No DB writes are permitted within implementations.
 *
 * @since 4.21.0
 */
interface RiskScoringEngineInterface
{
    /**
     * Calculates the risk score and level based on the provided payout context.
     *
     * @param PayoutContext $context Immutable context containing all required facts.
     * @return RiskResult The evaluation outcome containing score, level, and reasons.
     */
    public function score(PayoutContext $context): RiskResult;
}
