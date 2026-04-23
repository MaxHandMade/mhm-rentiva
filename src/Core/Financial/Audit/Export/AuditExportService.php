<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Audit\Export;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Audit\Crypto\ExportSignatureService;
use MHMRentiva\Core\Financial\Audit\Crypto\KeyPairManager;
use MHMRentiva\Core\Financial\Exceptions\GovernanceException;



/**
 * Service to generate Cryptographically Verifiable Audit Exports.
 *
 * Enforces Read-Only queries to prevent data mutation during export operations.
 * Strictly formatted via HashChainBuilder mathematically bound structures.
 *
 * @since 4.22.0
 */
class AuditExportService {

    /**
     * Executes the immutable CSV generation.
     *
     * @param string $date_from 'YYYY-MM-DD'
     * @param string $date_to   'YYYY-MM-DD'
     * @return array{ csv_path: string, sig_path: string, meta_path: string, file_hash: string }
     * @throws GovernanceException
     */
    public static function generate_export(string $date_from, string $date_to): array
    {
        global $wpdb;

        // Force a read-only perspective just for logical encapsulation, though we only execute SELECTs.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Export session must set a live read-only isolation boundary.
        $wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED;');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Export session must open a live read-only transaction.
        $wpdb->query('START TRANSACTION READ ONLY;');

        try {
            // Setup Export Storage Workspace
            $temp_dir = wp_upload_dir()['basedir'] . '/mhm_audit_exports';
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            // File Setup
            $export_uuid = uniqid('export_', true);
            $csv_file    = $temp_dir . '/' . $export_uuid . '.csv';

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Deterministic export writer uses native file streams by design.
            $fh = fopen($csv_file, 'wb');
            if (!$fh) {
                throw new GovernanceException('Cannot open export file for writing.');
            }

            // Setup Header Row
            fputcsv($fh, [
                'id',
                'tx_uuid',
                'payout_id',
                'vendor_id',
                'amount',
                'action',
                'created_at',
                'risk_score',
                'approval_stage',
                'actor_id',
                'PREVIOUS_HASH',     // Cryptographic Bind Pre-Row
                'CURRENT_HASH',       // Cryptographic Verification Tip
            ]);

            $l_table = $wpdb->prefix . 'mhm_rentiva_ledger';
            $a_table = $wpdb->prefix . 'mhm_rentiva_payout_audit';

            // THE CANONICAL DETERMINISTIC QUERY
            // Enforces order explicitly
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Audit export must stream a live deterministic snapshot.
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
                    LEFT JOIN %i a ON l.transaction_uuid = a.payout_id /* Approximate map for system tx vs payout tx */
                    WHERE DATE(l.created_at) >= %s 
                      AND DATE(l.created_at) <= %s
                    ORDER BY l.created_at ASC, l.id ASC
                    ",
                    $l_table,
                    $a_table,
                    (string) $date_from,
                    (string) $date_to
                ),
                ARRAY_A
            );

            $chain = new HashChainBuilder();

            foreach ($results as $row) {
                $hash_data = $chain->advance( (array) $row);

                // Build Final Export Row
                $export_row = array_merge($hash_data['csv_row'], [
                    $hash_data['previous_hash'],
                    $hash_data['current_hash'],
                ]);

                fputcsv($fh, $export_row);
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Deterministic export writer uses native file streams by design.
            fclose($fh);

            // Commit implicit Read Only context
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Export session must close the live read-only transaction.
            $wpdb->query('COMMIT;');

            // Cryptographic File Sealing
            $sig_data  = ExportSignatureService::sign_file($csv_file);
            $meta_data = self::generate_metadata($sig_data['key_id']);

            // Drop sibling files
            $sig_file  = $temp_dir . '/' . $export_uuid . '.sig';
            $meta_file = $temp_dir . '/' . $export_uuid . '.meta.json';

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Deterministic export writer persists generated signature bytes directly.
            file_put_contents($sig_file, $sig_data['signature']);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Deterministic export writer persists generated metadata bytes directly.
            file_put_contents($meta_file, wp_json_encode($meta_data, JSON_PRETTY_PRINT));

            return [
                'csv_path'  => $csv_file,
                'sig_path'  => $sig_file,
                'meta_path' => $meta_file,
                'file_hash' => $sig_data['file_hash'],
            ];
        } catch (\Exception $e) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Export session must roll back the live read-only transaction on failure.
            $wpdb->query('ROLLBACK;');
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
            throw new GovernanceException('Export creation failed: ' . $e->getMessage());
        }
    }

    /**
     * @param string $key_id
     * @return array
     */
    private static function generate_metadata(string $key_id): array
    {
        $public_key = KeyPairManager::get_public_key($key_id);

        return [
            'export_version' => 'v1',
            'algorithm'      => 'ed25519',
            'key_id'         => $key_id,
            'public_key'     => $public_key,
            'schema'         => [
                'id',
                'tx_uuid',
                'payout_id',
                'vendor_id',
                'amount',
                'action',
                'created_at',
                'risk_score',
                'approval_stage',
                'actor_id',
                'PREVIOUS_HASH',
                'CURRENT_HASH',
            ],
            'generation_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }
}
