<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Lite must create no Pro schema.
 *
 * The financial/ledger-audit tables (`ledger`, `commission_policy` and the
 * `key_registry` holding the ledger-signing keys) belong to Pro: Lite ships no
 * class that reads or writes any of them. Creating them anyway left dead tables
 * in every Lite install, and MultiTenantMigration then ALTERed three tables that
 * do not exist, failing once per table per upgrade.
 *
 * Asserts over the SQL the migrator actually issues rather than over table state,
 * so the test cannot be fooled by tables another test (or the bootstrap) left
 * behind.
 *
 * @package MHMRentiva\Tests\Integration\Migration
 */
final class LiteSchemaSeamTest extends WP_UnitTestCase
{

    /**
     * Guards the premise: these are the classes whose absence makes the schema dead.
     */
    public function test_financial_schema_owners_are_absent_from_lite(): void
    {
        $this->assertFalse(class_exists('MHMRentiva\Core\Database\Migrations\LedgerMigration'));
        $this->assertFalse(class_exists('MHMRentiva\Core\Database\Migrations\CommissionPolicyMigration'));
        $this->assertFalse(class_exists('MHMRentiva\Core\Financial\Ledger'));
    }

    /**
     * MultiTenantMigration itself still ships in Lite -- it is the *invocation*
     * that is gated, so this pins the reason the gate is needed at all.
     */
    public function test_multi_tenant_migration_still_ships_but_is_not_invoked(): void
    {
        $this->assertTrue(
            class_exists('MHMRentiva\Core\Database\Migrations\MultiTenantMigration'),
            'Premise: the class is present, so only the call-site gate can stop it.'
        );

        $queries = $this->capture_migration_queries();

        $this->assertSame(array(), $this->grep($queries, 'tenant_id'), 'Lite ran the multi-tenant ALTERs.');
    }

    /**
     * Asserts Lite's migrator does not touch the Pro tables AT ALL, rather than
     * merely that it issues no CREATE TABLE for them.
     *
     * Filtering for "create table" looked equivalent but was vacuous wherever the
     * table already existed: dbDelta probes with DESCRIBE / SHOW INDEX first and
     * only emits CREATE TABLE when the table is missing. On a site upgraded from a
     * Pro-era database -- exactly the case that matters -- the table is present, so
     * the ungated code issues no CREATE TABLE and a CREATE-only assertion passes
     * while the dead-schema work still runs. Verified against the dev database,
     * where wp_mhmrentiva_key_registry exists and the ungated migrator emitted
     * only DESCRIBE/SHOW INDEX/INFORMATION_SCHEMA probes.
     *
     * @dataProvider pro_table_provider
     */
    public function test_lite_never_touches_pro_tables(string $table): void
    {
        $queries = $this->capture_migration_queries();

        $this->assertSame(
            array(),
            $this->grep($queries, $table),
            sprintf('Lite issued schema queries against the Pro table "%s".', $table)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function pro_table_provider(): array
    {
        return array(
            'key registry'      => array( 'mhmrentiva_key_registry' ),
            'ledger'            => array( 'mhmrentiva_ledger' ),
            'commission policy' => array( 'mhmrentiva_commission_policy' ),
        );
    }

    /**
     * The gate must be surgical: Lite's own schema must still be created, which
     * also proves run_migrations() really ran and the assertions above are not
     * passing simply because nothing happened.
     */
    public function test_lite_still_creates_its_own_schema(): void
    {
        $queries = $this->capture_migration_queries();

        $this->assertNotSame(array(), $queries, 'Premise: the migrator issued no SQL at all.');
        $this->assertNotSame(
            array(),
            $this->grep($queries, 'notification_queue'),
            'Lite must still create its own notification_queue table.'
        );
    }

    /**
     * Force the version-gated migrator to run, capturing every query it issues.
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
