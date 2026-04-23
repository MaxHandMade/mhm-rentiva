<?php
declare(strict_types=1);

namespace MHMRentiva\CLI;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Audit\Export\AuditExportService;
use WP_CLI;
use WP_CLI_Command;

/**
 * CLI Command for generating Immutable External Audit Exports.
 *
 * @since 4.22.0
 */
class ExportAuditCommand extends WP_CLI_Command {

    /**
     * Generates a Cryptographically Verifiable Audit Export.
     *
     * ## OPTIONS
     *
     * [--from=<from-date>]
     * : Start date in YYYY-MM-DD format. Required.
     *
     * [--to=<to-date>]
     * : End date in YYYY-MM-DD format. Required.
     *
     * [--format=<format>]
     * : Export format. Currently only 'csv' is supported.
     * ---
     * default: csv
     * options:
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mhm audit:export --from=2026-01-01 --to=2026-01-31 --format=csv
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $from   = $assoc_args['from'] ?? '';
        $to     = $assoc_args['to'] ?? '';
        $format = $assoc_args['format'] ?? 'csv';

        if (empty($from) || empty($to)) {
            // Must return an exit code on failure per Chief Engineer constraint
            WP_CLI::error('Both --from and --to parameters are strictly required (Format: YYYY-MM-DD).', true);
        }

        if ($format !== 'csv') {
            WP_CLI::error('Only CSV format is supported for canonical deterministic exports.', true);
        }

        // Validate date string formats
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            WP_CLI::error('Dates must be in strictly YYYY-MM-DD format.', true);
        }

        WP_CLI::log(sprintf('Generating Audit Export from %s to %s...', $from, $to));

        try {
            $export_data = AuditExportService::generate_export($from, $to);

            WP_CLI::success('Audit Export Generated Successfully.');
            WP_CLI::log('Payload CSV: ' . $export_data['csv_path']);
            WP_CLI::log('Signature:   ' . $export_data['sig_path']);
            WP_CLI::log('Metadata:    ' . $export_data['meta_path']);
            WP_CLI::log('Hex Hash:    ' . $export_data['file_hash']);
        } catch (\Exception $e) {
            // Must return non-zero exit code on failure
            WP_CLI::error('Audit Export Failed: ' . $e->getMessage(), true);
        }
    }
}
