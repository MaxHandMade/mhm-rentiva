<?php
declare(strict_types=1);

namespace MHMRentiva\CLI;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Automation\MaturedPayoutJob;
use WP_CLI;
use WP_CLI_Command;



/**
 * CLI Command for manually executing matured time-locked payouts.
 *
 * @since 4.23.0
 */
final class MaturedPayoutCommand extends \WP_CLI_Command {

    /**
     * Executes all matured time-locked payouts.
     *
     * ## EXAMPLES
     *
     *     wp mhm payout:execute-matured
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        WP_CLI::log('Starting manual execution of matured payouts...');

        try {
            // Re-use logic from Job but log to CLI
            MaturedPayoutJob::run();
            WP_CLI::success('Matured payout execution completed.');
        } catch (\Exception $e) {
            WP_CLI::error('Matured payout execution failed: ' . $e->getMessage(), true);
        }
    }
}
