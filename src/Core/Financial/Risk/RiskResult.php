<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Risk;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Immutable DTO carrying the output of a risk scoring evaluation.
 *
 * @since 4.21.0
 */
class RiskResult {

    /**
     * The numeric risk score, typically 0 to 100.
     *
     * @var int
     */
    public int $score;

    /**
     * The calculated risk level categorization.
     *
     * @var RiskLevel
     */
    public RiskLevel $level;

    /**
     * Array of string triggers/reasons for forensic tracking.
     *
     * @var string[]
     */
    public array $reasons;

    /**
     * Constructor.
     *
     * @param int       $score
     * @param RiskLevel $level
     * @param string[]  $reasons
     */
    public function __construct(int $score, RiskLevel $level, array $reasons)
    {
        $this->score   = $score;
        $this->level   = $level;
        $this->reasons = $reasons;
    }
}
