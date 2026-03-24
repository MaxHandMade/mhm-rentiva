<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Exceptions\GovernanceException;

/**
 * Handles workflow state transitions for Payout Governance.
 *
 * Enforces explicitly defined transitions and Maker-Checker segregation.
 *
 * @since 4.21.0
 */
class ApprovalStateMachine
{
    public const STATE_PENDING        = 'pending';
    public const STATE_UNDER_REVIEW   = 'under_review';
    public const STATE_APPROVED_STAGE_1 = 'approved_stage_1';
    public const STATE_APPROVED_STAGE_2 = 'approved_stage_2';
    public const STATE_REJECTED       = 'rejected';
    public const STATE_TIME_LOCKED    = 'time_locked';
    public const STATE_EXECUTED       = 'executed';

    /**
     * Strict hardcoded transition matrix. Dynamics are forbidden.
     * format: [from_state => [allowed_to_states]]
     */
    private const TRANSITIONS = [
        self::STATE_PENDING => [
            self::STATE_UNDER_REVIEW,
            self::STATE_APPROVED_STAGE_2, // Fast-track for low risk + single approval
            self::STATE_REJECTED
        ],
        self::STATE_UNDER_REVIEW => [
            self::STATE_APPROVED_STAGE_1,
            self::STATE_REJECTED
        ],
        self::STATE_APPROVED_STAGE_1 => [
            self::STATE_APPROVED_STAGE_2,
            self::STATE_REJECTED
        ],
        self::STATE_APPROVED_STAGE_2 => [
            self::STATE_TIME_LOCKED, // Moved to time-lock before execution
            self::STATE_REJECTED
        ],
        self::STATE_TIME_LOCKED => [
            self::STATE_EXECUTED,
            self::STATE_REJECTED
        ]
    ];

    /**
     * Resolves the next forward state based on current state and risk evaluation.
     *
     * @throws GovernanceException If the risk is HIGH (auto-freeze) or state is invalid.
     */
    public static function resolve_candidate_state(string $current_state, \MHMRentiva\Core\Financial\Risk\RiskResult $risk): string
    {
        if ($risk->level === \MHMRentiva\Core\Financial\Risk\RiskLevel::HIGH && $current_state === self::STATE_PENDING) {
            throw new GovernanceException('Payout is auto-frozen due to HIGH risk scoring.');
        }

        if ($current_state === self::STATE_PENDING) {
            if ($risk->level === \MHMRentiva\Core\Financial\Risk\RiskLevel::LOW) {
                return self::STATE_APPROVED_STAGE_2; // Fast-track single approval
            }
            // MEDIUM risk requires dual approval workflow
            return self::STATE_UNDER_REVIEW;
        }

        if ($current_state === self::STATE_UNDER_REVIEW) {
            return self::STATE_APPROVED_STAGE_1;
        }

        if ($current_state === self::STATE_APPROVED_STAGE_1) {
            return self::STATE_APPROVED_STAGE_2;
        }

        if ($current_state === self::STATE_APPROVED_STAGE_2) {
            return self::STATE_TIME_LOCKED;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
        throw new GovernanceException("Cannot resolve forward candidate state from: {$current_state}");
    }

    /**
     * Validates if a transition is legal for the given actors.
     *
     * @param string $current_state       The current workflow state.
     * @param string $candidate_state     The desired workflow state.
     * @param int    $maker_id            User ID of the person who created/triggered the payout.
     * @param int    $actor_id            User ID attempting the transition.
     * @param bool   $has_override_cap    Whether the actor holds the mhm_rentiva_override_maker_checker capability.
     *
     * @throws GovernanceException If the transition is illegal or Maker-Checker rule is violated.
     */
    public static function validate_transition(
        string $current_state,
        string $candidate_state,
        int $maker_id,
        int $actor_id,
        bool $has_override_cap
    ): void {
        if (!isset(self::TRANSITIONS[$current_state])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
            throw new GovernanceException("Invalid current workflow state: {$current_state}");
        }

        if (!in_array($candidate_state, self::TRANSITIONS[$current_state], true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
            throw new GovernanceException("Illegal state transition from {$current_state} to {$candidate_state}");
        }

        // Maker-Checker Enforcement
        // A user cannot advance their own payout. They can, however, reject things if they have cap? 
        // Typically Maker != Checker applies to advancing approval.
        if ($maker_id === $actor_id && $candidate_state !== self::STATE_REJECTED) {
            if (!$has_override_cap) {
                throw new GovernanceException('Maker cannot be the Checker. Self-approval is explicitly prohibited.');
            }
        }
    }

    /**
     * Atomically updates the workflow state meta using a rows_affected guard to prevent race conditions.
     *
     * @param \wpdb  $wpdb
     * @param int    $payout_id
     * @param string $old_state
     * @param string $new_state
     *
     * @throws GovernanceException If a concurrency collision occurs.
     */
    public static function atomic_update_state(\wpdb $wpdb, int $payout_id, string $old_state, string $new_state): void
    {
        // Special case: `pending` is the implicit default state. The meta key may not literally exist.
        if ($old_state === self::STATE_PENDING) {
            $existing_state = get_post_meta($payout_id, '_mhm_workflow_state', true);
            if (empty($existing_state) || $existing_state === self::STATE_PENDING) {
                $inserted = update_post_meta($payout_id, '_mhm_workflow_state', $new_state, $old_state ?: '');
                if (!$inserted) {
                    throw new GovernanceException('Concurrency Error: Workflow state was modified by another process.');
                }
                return;
            } else {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
                throw new GovernanceException("State mismatch. Expected {$old_state} but found {$existing_state}");
            }
        }

        // Standard strict atomic update for existing explicit states.
        $rows_affected = $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET meta_value = %s WHERE post_id = %d AND meta_key = %s AND meta_value = %s',
                $wpdb->postmeta,
                (string) $new_state,
                (int) $payout_id,
                '_mhm_workflow_state',
                (string) $old_state
            )
        );

        if ($rows_affected !== 1) {
            throw new GovernanceException('Concurrency Error: Workflow state transitioned off-cycle or was modified parallelly.');
        }
    }
}
