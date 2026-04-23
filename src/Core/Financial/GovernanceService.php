<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Core\Financial\Events\DomainEventDispatcher;
use MHMRentiva\Core\Financial\Events\PayoutApprovedEvent;



/**
 * Governance layer wrapping AtomicPayoutService.
 *
 * Enforces role-based capabilities, logs an immutable audit trail,
 * and handles payout freeze states. Does NOT contain financial mechanics.
 *
 * @since 4.21.0
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
final class GovernanceService {

    /**
     * Reusable action constants
     */
    public const ACTION_SUBMITTED         = 'submit_payout';
    public const ACTION_REVIEWED          = 'review_payout';
    public const ACTION_FINALIZED         = 'finalize_payout';
    public const ACTION_REJECTED          = 'reject_payout';
    public const ACTION_EXECUTED          = 'execute_payout';
    public const ACTION_FLAGGED           = 'flag_payout';
    public const ACTION_OVERRIDE_USED     = 'override_maker_checker';
    public const ACTION_DENY              = 'deny_payout_approval';
    public const ACTION_TIMELOCK_CREATED  = 'timelock_created';
    public const ACTION_TIMELOCK_MATURED  = 'timelock_matured';
    public const ACTION_TIMELOCK_EXECUTED = 'timelock_executed';
    public const ACTION_TIMELOCK_BYPASS   = 'timelock_bypass';

    /**
     * Entrypoint. Orchestrates risk evaluation, multi-actor approval workflow, and delegates to AtomicPayoutService.
     *
     * @param int $payout_id
     * @return true|\WP_Error
     */
    public static function process_approval(int $payout_id)
    {
        global $wpdb;
        $actor_id = get_current_user_id();

        $post = get_post($payout_id);
        if (! $post instanceof \WP_Post) {
            return new \WP_Error('invalid_payout', __('Invalid payout.', 'mhm-rentiva'));
        }

        $vendor_id = (int) $post->post_author;

        // 1. Enforce Freeze States
        $freeze_result = self::enforce_freeze_state($vendor_id);
        if (is_wp_error($freeze_result)) {
            self::log_approval_event($payout_id, $actor_id, self::ACTION_DENY, '', $freeze_result->get_error_message());
            return $freeze_result;
        }

        // 2. Determine Current State & Context
        $current_state = get_post_meta($payout_id, '_mhm_workflow_state', true);
        if (empty($current_state)) {
            $current_state = ApprovalStateMachine::STATE_PENDING;
        }

        $payout_amount   = (float) get_post_meta($payout_id, '_mhm_payout_amount', true);
        $vendor_user     = get_user_by('id', $vendor_id);
        $vendor_age_days = 0;
        if ($vendor_user) {
            $registered      = strtotime($vendor_user->user_registered);
            $vendor_age_days = (int) floor(( time() - $registered ) / DAY_IN_SECONDS);
        }

        $total_completed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE vendor_id = %d AND type = 'commission_credit'", $vendor_id));
        $total_refunded  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE vendor_id = %d AND type = 'commission_refund'", $vendor_id));

        $context = new Risk\PayoutContext($payout_id, $vendor_id, $payout_amount, $vendor_age_days, $total_completed, $total_refunded);

        // 3. Score Risk
        $risk_engine = new Risk\DeterministicRiskEngine();
        $risk_result = $risk_engine->score($context);

        // 4. Resolve Candidate State
        try {
            $candidate_state = ApprovalStateMachine::resolve_candidate_state($current_state, $risk_result);
        } catch (Exceptions\GovernanceException $e) {
            // High Risk auto-freeze
            self::log_approval_event($payout_id, $actor_id, self::ACTION_FLAGGED, '', $e->getMessage() . ' | Score: ' . $risk_result->score);

            // SaaS Metering: Capture Risk Event (Flagged)
            $tenant_id = (int) \MHMRentiva\Core\Tenancy\TenantResolver::resolve()->get_id();
            \MHMRentiva\Core\Orchestration\MeteredUsageTracker::increment($tenant_id, 'risk_events');

            return new \WP_Error('governance_frozen_risk', $e->getMessage());
        }

        // 5. Authorize Candidate Action
        $auth_result = self::authorize_state_transition($candidate_state, $actor_id);
        if (is_wp_error($auth_result)) {
            self::log_approval_event($payout_id, $actor_id, self::ACTION_DENY, '', $auth_result->get_error_message());
            return $auth_result;
        }

        // 6. Maker-Checker Validate
        $maker_id = (int) get_post_meta($payout_id, '_mhm_payout_maker_id', true);
        // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom payout governance capability registered by the plugin.
        $has_override = user_can($actor_id, 'mhm_rentiva_override_maker_checker');

        try {
            ApprovalStateMachine::validate_transition($current_state, $candidate_state, $maker_id, $actor_id, $has_override);
        } catch (Exceptions\GovernanceException $e) {
            self::log_approval_event($payout_id, $actor_id, self::ACTION_DENY, '', $e->getMessage());
            return new \WP_Error('governance_maker_checker', $e->getMessage());
        }

        // Identify override usage
        if ($maker_id === $actor_id && $has_override) {
            self::log_approval_event($payout_id, $actor_id, self::ACTION_OVERRIDE_USED, '', "Maker-Checker bypass used for state: {$candidate_state}");

            // SaaS Metering: Capture Risk Event (Override)
            $tenant_id = (int) \MHMRentiva\Core\Tenancy\TenantResolver::resolve()->get_id();
            \MHMRentiva\Core\Orchestration\MeteredUsageTracker::increment($tenant_id, 'risk_events');
        }

        // 7. Transition Workflow State
        try {
            ApprovalStateMachine::atomic_update_state($wpdb, $payout_id, $current_state, $candidate_state);
        } catch (Exceptions\GovernanceException $e) {
            self::log_approval_event($payout_id, $actor_id, 'concurrency_failure', '', $e->getMessage());
            return new \WP_Error('governance_concurrency', $e->getMessage());
        }

        // Log Workflow Transition (Deferred to listener if final state, otherwise immediate)
        $action_map   = [
            ApprovalStateMachine::STATE_UNDER_REVIEW     => self::ACTION_REVIEWED,
            ApprovalStateMachine::STATE_APPROVED_STAGE_1 => self::ACTION_REVIEWED,
            ApprovalStateMachine::STATE_APPROVED_STAGE_2 => self::ACTION_FINALIZED,
        ];
        $action_event = $action_map[ $candidate_state ] ?? 'state_transition';

        // 8. Execute Atomic Core if workflow resolves to final approval or time-lock transition
        if ($candidate_state === ApprovalStateMachine::STATE_APPROVED_STAGE_2 || $candidate_state === ApprovalStateMachine::STATE_TIME_LOCKED) {
            $dispatcher = new DomainEventDispatcher();
            $dispatcher->listen('payout_approved', static function (PayoutApprovedEvent $event) use ($actor_id, $candidate_state, $action_event, $risk_result) {
                // Forensic Log 1: The state transition itself (Now inside the transaction!)
                self::log_approval_event(
                    $event->get_payout_id(),
                    $actor_id,
                    $action_event,
                    $event->get_tx_uuid(),
                    "State advanced to {$candidate_state}",
                    $risk_result->score,
                    $candidate_state
                );

                // Forensic Log 2: The atomic submission
                self::log_approval_event(
                    $event->get_payout_id(),
                    $actor_id,
                    self::ACTION_SUBMITTED,
                    $event->get_tx_uuid(),
                    'Atomic payout reserved and locked.',
                    0,
                    $candidate_state
                );
            });

            // This now RESERVES the balance (payout_pending_debit)
            $atomic_result = AtomicPayoutService::approve($payout_id, $dispatcher);

            if (is_wp_error($atomic_result)) {
                self::log_approval_event($payout_id, $actor_id, 'atomic_failure', '', $atomic_result->get_error_message());
                return $atomic_result;
            }

            // Calculate Cooling Period (Tenant-Aware: respects compliance_profile overrides)
            $tenant        = \MHMRentiva\Core\Tenancy\TenantResolver::resolve();
            $release_after = Risk\CoolingPolicyManager::calculate_release_time($risk_result->level->value, $tenant);

            update_post_meta($payout_id, '_mhm_release_after', $release_after);
            update_post_meta($payout_id, '_mhm_lock_status', 'LOCKED');
            update_post_meta($payout_id, '_mhm_cooling_policy_version', Risk\CoolingPolicyManager::POLICY_VERSION);

            // Immediate transition to TIME_LOCKED
            try {
                ApprovalStateMachine::atomic_update_state($wpdb, $payout_id, $candidate_state, ApprovalStateMachine::STATE_TIME_LOCKED);
                self::log_approval_event($payout_id, $actor_id, self::ACTION_TIMELOCK_CREATED, '', "Payout locked until {$release_after}", $risk_result->score, ApprovalStateMachine::STATE_TIME_LOCKED);
            } catch (Exceptions\GovernanceException $e) {
                AdvancedLogger::critical('Atomic Payout Reserved but Time-Lock State Update Failed', [
					'payout_id' => $payout_id,
					'error'     => $e->getMessage(),
				], 'payout_governance');
            }
        }

        return true;
    }

    /**
     * Check if a user has explicitly the review or finalize capability.
     *
     * @param string $candidate_state
     * @param int $user_id
     * @return true|\WP_Error
     */
    public static function authorize_state_transition(string $candidate_state, int $user_id)
    {
        if ($candidate_state === ApprovalStateMachine::STATE_APPROVED_STAGE_2) {
            // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom payout governance capability registered by the plugin.
            if (! user_can($user_id, 'mhm_rentiva_finalize_payout')) {
                return new \WP_Error('governance_denied', __('You do not have the capability to finalize payouts.', 'mhm-rentiva'));
            }
        // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom payout governance capability registered by the plugin.
        } elseif (! user_can($user_id, 'mhm_rentiva_review_payout')) {
                return new \WP_Error('governance_denied', __('You do not have the capability to review payouts.', 'mhm-rentiva'));
        }

        return true;
    }

    /**
     * Enforce freeze blocks.
     *
     * @param int $vendor_id
     * @return true|\WP_Error
     */
    public static function enforce_freeze_state(int $vendor_id = 0)
    {
        // Global Freeze Check
        $global_freeze = get_option('mhm_rentiva_global_payout_freeze', 'no');
        if ($global_freeze === 'yes') {
            return new \WP_Error('governance_frozen_global', __('Financial payouts are currently globally frozen by Governance.', 'mhm-rentiva'));
        }

        // Specific Vendor Freeze
        if ($vendor_id > 0) {
            $vendor_freeze = get_user_meta($vendor_id, '_mhm_vendor_payout_freeze', true);
            if ($vendor_freeze === 'yes') {
                return new \WP_Error('governance_frozen_vendor', __('Payouts for this vendor have been frozen.', 'mhm-rentiva'));
            }
        }

        return true;
    }

    /**
     * Immutable append-only logging of payout interactions.
     *
     * @param int    $payout_id
     * @param int    $actor_id
     * @param string $action
     * @param string $tx_uuid
     * @param string $context
     * @param int    $risk_score
     * @param string $workflow_state
     */
    public static function log_approval_event(int $payout_id, int $actor_id, string $action, string $tx_uuid = '', string $context = '', int $risk_score = 0, string $workflow_state = ''): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mhm_rentiva_payout_audit';

        // Graceful skip if table not migrated yet
        $has_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($has_table !== $table) {
            AdvancedLogger::warning('Governance Audit table missing. Run migration.', array(), 'payout_governance');
            return;
        }

        // Generate IP hash (privacy + tracking)
        $raw_ip = 'CLI';
        if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
            $raw_ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        $ip_hash = hash('sha256', $raw_ip . wp_salt());

        $metadata = wp_json_encode(array(
            'context'        => $context,
            'risk_score'     => $risk_score,
            'workflow_state' => $workflow_state,
        ));

        $tenant_id = (int) \MHMRentiva\Core\Tenancy\TenantResolver::resolve()->get_id();

        // Use INSERT IGNORE to stay idempotent during retries/concurrent race conditions.
        $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO %i (tenant_id, payout_id, actor_user_id, action, tx_uuid, ip_hash, metadata_json, created_at)
             VALUES (%d, %d, %d, %s, %s, %s, %s, %s)',
            $table,
            $tenant_id,
            $payout_id,
            $actor_id,
            $action,
            $tx_uuid,
            $ip_hash,
            $metadata,
            current_time('mysql')
        ));
    }

    /**
     * Bypass the cooling period and execute immediately.
     *
     * @param int $payout_id
     * @param string $reason MANDATORY reason for bypass
     * @return true|\WP_Error
     */
    public static function bypass_time_lock(int $payout_id, string $reason)
    {
        $actor_id = get_current_user_id();
        // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom payout governance capability registered by the plugin.
        if (! current_user_can('mhm_rentiva_bypass_time_lock')) {
            return new \WP_Error('governance_denied', __('You do not have the capability to bypass time-locks.', 'mhm-rentiva'));
        }

        if (empty(trim($reason))) {
            return new \WP_Error('invalid_reason', __('A bypass reason is strictly required for audit auditability.', 'mhm-rentiva'));
        }

        $post = get_post($payout_id);
        if (! $post instanceof \WP_Post || get_post_meta($payout_id, '_mhm_workflow_state', true) !== ApprovalStateMachine::STATE_TIME_LOCKED) {
            return new \WP_Error('invalid_state', __('Only time-locked payouts can be bypassed.', 'mhm-rentiva'));
        }

        // 1. Re-score risk for the bypass attempt
        $payout_amount = (float) get_post_meta($payout_id, '_mhm_payout_amount', true);
        $vendor_id     = (int) $post->post_author;

        // Simplified context for bypass re-scoring (real system would fetch full context)
        $context     = new Risk\PayoutContext($payout_id, $vendor_id, $payout_amount, 0, 0, 0);
        $risk_engine = new Risk\DeterministicRiskEngine();
        $risk_result = $risk_engine->score($context);

        // 2. Execute Immediate
        $result = AtomicPayoutService::finalize_time_locked_payout($payout_id);
        if (is_wp_error($result)) {
            return $result;
        }

        update_post_meta($payout_id, '_mhm_lock_status', 'BYPASSED');
        update_post_meta($payout_id, '_mhm_bypass_reason', $reason);

        self::log_approval_event(
            $payout_id,
            $actor_id,
            self::ACTION_TIMELOCK_BYPASS,
            '',
            "Manual Bypass: {$reason} | Re-scored Risk: {$risk_result->score}",
            $risk_result->score,
            ApprovalStateMachine::STATE_EXECUTED
        );

        return true;
    }
}
