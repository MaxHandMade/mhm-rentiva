<?php

declare(strict_types=1);

namespace MHMRentiva\CLI;

if (!defined('ABSPATH')) {
    exit;
}


use MHMRentiva\Core\Financial\Audit\Verification\IntegrityVerificationService;
use WP_CLI;
use WP_CLI_Command;

/**
 * CLI Command for manual integrity verification of the Financial Ledger.
 * 
 * @since 4.22.0
 */
class IntegrityCheckCommand extends WP_CLI_Command
{

    /**
     * Triggers a full integrity audit of the ledger.
     * 
     * ## EXAMPLES
     * 
     *     wp mhm audit:verify
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        WP_CLI::log('Starting full ledger integrity verification...');

        try {
            $service = new IntegrityVerificationService();
            $result = $service->verify_ledger_integrity();

            if ($result['status'] === IntegrityVerificationService::STATUS_SECURE) {
                WP_CLI::success(sprintf('Ledger Integrity Verified: %s. Rows: %d', $result['status'], $result['rows_verified']));
                WP_CLI::log('Tip Hash: ' . $result['tip_hash']);
            } else {
                WP_CLI::error(sprintf('CRITICAL: Ledger Integrity %s! Tampering detected.', $result['status']), true);
            }
        } catch (\Exception $e) {
            WP_CLI::error('Integrity Verification Failed: ' . $e->getMessage(), true);
        }
    }
}
