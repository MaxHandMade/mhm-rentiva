<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Task A9c seam inversion guards.
 *
 * DatabaseMigrator::run_migrations() used to gate the payout_audit table /
 * governance capabilities on Mode::canUseVendorPayout() (a licence check),
 * and create_transfer_tables() used to check class_exists() for Pro's
 * Transfer\Engine\LocationProvider directly. Both are now class_exists()
 * checks against dedicated "Migrations" marker classes
 * (GovernanceService / TransferMigration) that only exist when Pro ships
 * them -- this tree has neither, so both must stay inert, exactly like the
 * Ledger/CommissionPolicy/VendorReports cluster LiteSchemaSeamTest already
 * covers.
 *
 * @package MHMRentiva\Tests\Integration\Migration
 */
final class DatabaseMigratorSeamTest extends WP_UnitTestCase
{
    public function test_premise_pro_migration_marker_classes_are_absent_from_lite(): void
    {
        $this->assertFalse(class_exists('MHMRentiva\Core\Financial\GovernanceService'));
        $this->assertFalse(class_exists('MHMRentiva\Core\Database\Migrations\TransferMigration'));
    }

    public function test_create_table_transfer_locations_returns_false_without_pro(): void
    {
        $this->assertFalse(
            DatabaseMigrator::create_table('transfer_locations'),
            'Lite must not create the Transfer locations table without Pro\'s TransferMigration.'
        );
    }

    public function test_create_table_transfer_routes_returns_false_without_pro(): void
    {
        $this->assertFalse(
            DatabaseMigrator::create_table('transfer_routes'),
            'Lite must not create the Transfer routes table without Pro\'s TransferMigration.'
        );
    }

    /**
     * Asserts over the SQL run_migrations() actually issues rather than table
     * state, matching LiteSchemaSeamTest::test_lite_never_touches_pro_tables().
     * A `SHOW TABLES` / `DROP TABLE` state check is not reliable here: WP core's
     * WP_UnitTestCase::start_transaction() rewrites every `DROP TABLE`/
     * `CREATE TABLE` this test issues into `DROP/CREATE TEMPORARY TABLE`, which
     * cannot remove or be fooled by a genuinely persistent table already sitting
     * in the shared test database -- so a prior real table would make a
     * state-based assertion fail regardless of what this run's migrator did.
     * Capturing the SQL sidesteps that: it proves the *migrator* issued nothing
     * against payout_audit this run, independent of whatever pre-existing state
     * the test database happens to carry.
     */
    public function test_run_migrations_does_not_create_payout_audit_table_without_pro(): void
    {
        $queries = $this->capture_migration_queries();

        $this->assertSame(
            array(),
            $this->grep($queries, 'payout_audit'),
            'Lite must not issue schema queries against payout_audit without Pro\'s GovernanceService.'
        );
    }

    /**
     * Force the version-gated migrator to run, capturing every query it issues.
     * Mirrors LiteSchemaSeamTest::capture_migration_queries().
     *
     * @return array<int, string>
     */
    private function capture_migration_queries(): array
    {
        $queries = array();

        $collector = static function (string $query) use (&$queries): string {
            $queries[] = $query;
            return $query;
        };

        $previous_version = get_option('mhm_rentiva_db_version');
        update_option('mhm_rentiva_db_version', '1.0.0');

        add_filter('query', $collector, 9999);
        DatabaseMigrator::run_migrations();
        remove_filter('query', $collector, 9999);

        if (false === $previous_version) {
            delete_option('mhm_rentiva_db_version');
        } else {
            update_option('mhm_rentiva_db_version', $previous_version);
        }

        return $queries;
    }

    /**
     * @param array<int, string> $queries
     * @return array<int, string>
     */
    private function grep(array $queries, string $needle): array
    {
        return array_values(
            array_filter(
                $queries,
                static function (string $query) use ($needle): bool {
                    return str_contains(strtolower($query), strtolower($needle));
                }
            )
        );
    }
}
