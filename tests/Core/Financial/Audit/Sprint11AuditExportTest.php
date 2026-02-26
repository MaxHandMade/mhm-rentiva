<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial\Audit;

use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\Audit\Crypto\ExportSignatureService;
use MHMRentiva\Core\Financial\Audit\Crypto\KeyPairManager;
use MHMRentiva\Core\Financial\Audit\Export\AuditExportService;
use MHMRentiva\Core\Financial\Audit\Export\HashChainBuilder;
use WP_UnitTestCase;

/**
 * Validates the Cryptographic Tamper-Evident Audit Export Layer.
 *
 * @since 4.22.0
 */
class Sprint11AuditExportTest extends WP_UnitTestCase
{
    private string $date_from = '2026-01-01';
    private string $date_to   = '2026-12-31';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LedgerMigration::create_table();
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_payout_audit_table();
    }

    public function setUp(): void
    {
        parent::setUp();

        // Ensure strictly cleared environments for Hash/Time determinism
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_payout_audit");
        delete_option('mhm_rentiva_audit_key_store'); // Reset KeyPair store
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_payout_audit");
        delete_option('mhm_rentiva_audit_key_store');

        // Clean up any test export files
        $temp_dir = wp_upload_dir()['basedir'] . '/mhm_audit_exports';
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    private function seed_deterministic_ledger_data(): void
    {
        global $wpdb;
        $ledger = $wpdb->prefix . 'mhm_rentiva_ledger';
        $audit  = $wpdb->prefix . 'mhm_rentiva_payout_audit';

        // Row 1 - Direct Ledger
        $wpdb->insert($ledger, [
            'transaction_uuid' => 'tx_123',
            'vendor_id'        => 10,
            'type'             => 'commission_credit',
            'amount'           => 150.50,
            'currency'         => 'TRY',
            'context'          => 'booking_1',
            'status'           => 'cleared',
            'created_at'       => '2026-02-01 10:00:00', // MySQL Time
        ]);

        // Row 2 - Payout with Governance
        $wpdb->insert($ledger, [
            'transaction_uuid' => 'payout_1',
            'vendor_id'        => 10,
            'type'             => 'payout',
            'amount'           => -150.50,
            'currency'         => 'TRY',
            'context'          => 'withdrawal',
            'status'           => 'cleared',
            'created_at'       => '2026-02-02 12:00:00',
        ]);

        $wpdb->insert($audit, [
            'payout_id'       => 1, // Matches 'payout_1' indirectly via schema
            'action'          => 'execute_payout',
            'actor_user_id'   => 5,
            'tx_uuid'         => 'tx_mock_audit_1',
            'metadata_json'   => wp_json_encode(['risk_score' => 10, 'workflow_state' => 'executed']),
            'created_at'      => '2026-02-02 12:00:05',
        ]);

        // We will fake the JOIN ON l.transaction_uuid = a.payout_id for the test by using 'payout_1' -> '1' logic
        // Actually, the AuditExportService joints ON l.transaction_uuid = a.payout_id (string to int cast issue typically, but fine for test)
        // Let's ensure the TX UUID is '1' so the JOIN matches exactly.
        $wpdb->update($ledger, ['transaction_uuid' => '1'], ['transaction_uuid' => 'payout_1']);
    }

    public function test_audit_export_determinism(): void
    {
        $this->seed_deterministic_ledger_data();

        // RUN 1
        $export1 = AuditExportService::generate_export($this->date_from, $this->date_to);
        $hash1   = $export1['file_hash'];

        // RUN 2
        $export2 = AuditExportService::generate_export($this->date_from, $this->date_to);
        $hash2   = $export2['file_hash'];

        $this->assertEquals($hash1, $hash2, 'Export hashes MUST be byte-for-byte identical across multiple deterministic runs.');
    }

    public function test_signature_verification_integrity(): void
    {
        $this->seed_deterministic_ledger_data();

        $export = AuditExportService::generate_export($this->date_from, $this->date_to);

        $json_data = json_decode(file_get_contents($export['meta_path']), true);
        $this->assertNotNull($json_data);
        $this->assertArrayHasKey('public_key', $json_data);

        $signature = file_get_contents($export['sig_path']);

        $is_valid = ExportSignatureService::verify_external(
            $export['csv_path'],
            $signature,
            $json_data['public_key']
        );

        $this->assertTrue($is_valid, 'External auditor must be able to verify the signature utilizing solely the Base64 Public Key.');
    }

    public function test_tamper_detection_breaks_signature(): void
    {
        $this->seed_deterministic_ledger_data();

        $export = AuditExportService::generate_export($this->date_from, $this->date_to);

        $json_data  = json_decode(file_get_contents($export['meta_path']), true);
        $signature  = file_get_contents($export['sig_path']);

        // Maliciously tamper with the CSV (e.g. altering amount)
        $csv_content = file_get_contents($export['csv_path']);
        $tampered_content = str_replace('150.50', '950.50', $csv_content);
        file_put_contents($export['csv_path'], $tampered_content);

        $is_valid = ExportSignatureService::verify_external(
            $export['csv_path'],
            $signature,
            $json_data['public_key']
        );

        $this->assertFalse($is_valid, 'Tampering with ANY byte of the CSV must strictly fail the Cryptographic signature.');
    }

    public function test_hash_chain_integrity(): void
    {
        $chain = new HashChainBuilder();

        $row1 = [
            'id' => 1,
            'tx_uuid' => 'A1',
            'payout_id' => 0,
            'vendor_id' => 10,
            'amount' => 100.00,
            'action' => 'credit',
            'created_at' => '2026-02-01 10:00:00',
            'risk_score' => 0,
            'approval_stage' => 'cleared',
            'actor_id' => 0
        ];

        $step1 = $chain->advance($row1);
        $this->assertEquals('GENESIS', $step1['previous_hash']);

        $row2 = [
            'id' => 2,
            'tx_uuid' => 'A2',
            'payout_id' => 0,
            'vendor_id' => 10,
            'amount' => -100.00,
            'action' => 'credit',
            'created_at' => '2026-02-01 11:00:00',
            'risk_score' => 0,
            'approval_stage' => 'cleared',
            'actor_id' => 0
        ];

        $step2 = $chain->advance($row2);
        $this->assertEquals($step1['current_hash'], $step2['previous_hash'], 'Row 2 MUST mathematically chain to the Hash of Row 1.');

        $final_tip = $chain->get_tip_hash();
        $this->assertEquals($step2['current_hash'], $final_tip);

        // If row1 was tampered with, the current_hash changes, disconnecting the entire chain
        $bad_chain = new HashChainBuilder();
        $row1_tampered = $row1;
        $row1_tampered['amount'] = 999.00; // Attack
        $bad_step1 = $bad_chain->advance($row1_tampered);

        $this->assertNotEquals($step1['current_hash'], $bad_step1['current_hash'], 'Tampering with data MUST produce a radically different row hash.');
    }

    public function test_key_rotation_preserves_historical_verification(): void
    {
        $this->seed_deterministic_ledger_data();

        // 1. Generate Generation 1 Keypair (Implicit via first export)
        $export1 = AuditExportService::generate_export($this->date_from, $this->date_to);
        $meta1   = json_decode(file_get_contents($export1['meta_path']), true);
        $sig1    = file_get_contents($export1['sig_path']);

        $this->assertTrue(ExportSignatureService::verify_external($export1['csv_path'], $sig1, $meta1['public_key']));

        // 2. Rotate to Generation 2 Keypair
        $new_key = KeyPairManager::rotate_key();

        // The second export will use Key 2
        $export2 = AuditExportService::generate_export($this->date_from, $this->date_to);
        $meta2   = json_decode(file_get_contents($export2['meta_path']), true);
        $sig2    = file_get_contents($export2['sig_path']);

        $this->assertNotEquals($meta1['key_id'], $meta2['key_id'], 'Keys must rotate successfully.');

        // 3. Prove isolation
        // Export 1 MUST fail if checked against Key 2
        $this->assertFalse(ExportSignatureService::verify_external($export1['csv_path'], $sig1, $meta2['public_key']));

        // Export 1 MUST continually succeed against Key 1 retrieved from history
        $historical_pubkey = KeyPairManager::get_public_key($meta1['key_id']);
        $this->assertEquals($meta1['public_key'], $historical_pubkey);
        $this->assertTrue(ExportSignatureService::verify_external($export1['csv_path'], $sig1, $historical_pubkey));
    }
}
