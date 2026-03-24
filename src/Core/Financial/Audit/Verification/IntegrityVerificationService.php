<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Audit\Verification;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Audit\Export\HashChainBuilder;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Core\Financial\Exceptions\GovernanceException;



/**
 * Service for Internal Integrity Verification of the Financial Ledger.
 * 
 * Rebuilds the HashChain from Genesis to the latest Row and compares it 
 * against previously stored checkpoints to detect tampering.
 */
class IntegrityVerificationService
{

    public const OPTION_STATUS = 'mhm_rentiva_system_integrity_status';
    public const OPTION_LAST_CHECK = 'mhm_rentiva_last_integrity_check';
    public const STATUS_SECURE = 'SECURE';
    public const STATUS_COMPROMISED = 'COMPROMISED';

    /**
     * Executes a full integrity audit of the ledger.
     * 
     * @return array{ status: string, rows_verified: int, tip_hash: string }
     * @throws GovernanceException
     */
    public function verify_ledger_integrity(): array
    {
        global $wpdb;

        // 1. Strict READ ONLY session enforcement
        $wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED;');
        $wpdb->query('START TRANSACTION READ ONLY;');

        try {
            $l_table = $wpdb->prefix . 'mhm_rentiva_ledger';
            $a_table = $wpdb->prefix . 'mhm_rentiva_payout_audit';

            // Rebuild query exactly like the Export Service for canonical parity.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT 
                        l.id,
                        l.transaction_uuid as tx_uuid,
                        IFNULL(a.payout_id, 0) as payout_id,
                        l.vendor_id,
                        l.amount,
                        IFNULL(a.action, l.type) as action,
                        l.created_at,
                        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json, '$.risk_score')), 0) as risk_score,
                        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json, '$.workflow_state')), 'cleared') as approval_stage,
                        IFNULL(a.actor_user_id, 0) as actor_id
                    FROM %i l
                    LEFT JOIN %i a ON l.transaction_uuid = a.payout_id
                    ORDER BY l.created_at ASC, l.id ASC
                    ",
                    $l_table,
                    $a_table
                ),
                ARRAY_A
            );
            $count = count($results);

            $chain = new HashChainBuilder();
            foreach ($results as $row) {
                $chain->advance((array) $row);
            }

            $tip_hash = $chain->get_tip_hash();

            // 2. Perform Verification Logic
            // In this implementation, "verification" means the chain itself is mathematically consistent
            // (advance() would throw if data was missing or malformed).
            // We also compare against the last check if it exists and no new rows were added.

            $last_check = get_option(self::OPTION_LAST_CHECK, []);
            $integrity_status = self::STATUS_SECURE;

            // If we have a previous check, and row count is same but hash differs -> TAMPERING
            if (!empty($last_check) && $last_check['row_count'] === $count && $last_check['tip_hash'] !== $tip_hash) {
                $integrity_status = self::STATUS_COMPROMISED;
            }

            // 3. Update Persistence
            $this->update_system_status($integrity_status, $count, $tip_hash);

            $wpdb->query('COMMIT;');

            return [
                'status'        => $integrity_status,
                'rows_verified' => $count,
                'tip_hash'      => $tip_hash
            ];
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK;');
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
            throw new GovernanceException('Integrity verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Updates the system status. Once COMPROMISED, it stays COMPROMISED (sticky)
     * until a manual reset or forensic resolution.
     */
    private function update_system_status(string $status, int $row_count, string $tip_hash): void
    {
        $current_status = get_option(self::OPTION_STATUS, self::STATUS_SECURE);

        // Sticky Rule: If current is COMPROMISED, do not auto-revert to SECURE
        if ($current_status === self::STATUS_COMPROMISED && $status === self::STATUS_SECURE) {
            // Log that we checked but the sticky flag remains
            AdvancedLogger::info('Integrity check passed, but system remains in sticky COMPROMISED state.', [], AdvancedLogger::CATEGORY_SECURITY);
            $status = self::STATUS_COMPROMISED;
        }

        if ($status === self::STATUS_COMPROMISED) {
            AdvancedLogger::critical(
                'CRITICAL_FORENSIC_ALERT: Ledger integrity mismatch detected!',
                [
                    'expected_hash_at_count' => $row_count,
                    'calculated_hash'        => $tip_hash,
                    'action_required'        => 'Forensic Audit'
                ],
                AdvancedLogger::CATEGORY_SECURITY
            );
        }

        update_option(self::OPTION_STATUS, $status);
        update_option(self::OPTION_LAST_CHECK, [
            'timestamp' => current_time('mysql', true),
            'row_count' => $row_count,
            'tip_hash'  => $tip_hash
        ]);
    }

    /**
     * Manually reset the integrity status to SECURE after forensic review.
     */
    public static function manual_reset_status(): void
    {
        update_option(self::OPTION_STATUS, self::STATUS_SECURE);
        AdvancedLogger::warning('System integrity status manually reset to SECURE.', [], AdvancedLogger::CATEGORY_SECURITY);
    }

    /**
     * Get the current system integrity status.
     * 
     * @return string
     */
    public static function get_system_status(): string
    {
        return (string) get_option(self::OPTION_STATUS, self::STATUS_SECURE);
    }
}
