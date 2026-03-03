<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Billing;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingFeatureFlagRepositoryTest extends \WP_UnitTestCase
{
    private string $table = '';

    public function setUp(): void
    {
        parent::setUp();

        $this->require_class('\MHMRentiva\\Core\\Database\\Migrations\\UsageBillingFeatureFlagMigration');
        $this->require_class('\MHMRentiva\\Core\\Billing\\Usage\\UsageBillingFeatureFlagRepository');

        \MHMRentiva\Core\Database\Migrations\UsageBillingFeatureFlagMigration::create_table();

        global $wpdb;
        $this->table = $wpdb->prefix . 'mhm_rentiva_usage_billing_feature_flags';
        $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    public function test_default_flag_is_off_when_row_missing(): void
    {
        $repository = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureFlagRepository();

        $this->assertFalse($repository->is_enabled(7001));
    }

    public function test_set_enabled_persists_tinyint_one(): void
    {
        $repository = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureFlagRepository();

        $repository->set_enabled(7001, true, '2026-03-03T10:00:00Z');
        $this->assertTrue($repository->is_enabled(7001));

        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT is_enabled FROM {$this->table} WHERE tenant_id = %d",
            7001
        ));
        $this->assertSame('1', (string) $value);
    }

    public function test_set_disabled_persists_tinyint_zero(): void
    {
        $repository = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureFlagRepository();

        $repository->set_enabled(7001, true, '2026-03-03T10:00:00Z');
        $repository->set_enabled(7001, false, '2026-03-03T10:01:00Z');

        $this->assertFalse($repository->is_enabled(7001));

        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT is_enabled FROM {$this->table} WHERE tenant_id = %d",
            7001
        ));
        $this->assertSame('0', (string) $value);
    }

    public function test_cross_tenant_reads_are_isolated(): void
    {
        $repository = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureFlagRepository();

        $repository->set_enabled(8001, true, '2026-03-03T11:00:00Z');
        $repository->set_enabled(8002, false, '2026-03-03T11:00:00Z');

        $this->assertTrue($repository->is_enabled(8001));
        $this->assertFalse($repository->is_enabled(8002));
    }

    public function test_invalid_tenant_or_timestamp_is_rejected(): void
    {
        $repository = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureFlagRepository();

        $this->expectException(\InvalidArgumentException::class);
        $repository->set_enabled(0, true, '2026-03-03T10:00:00Z');
    }

    public function test_invalid_timestamp_is_rejected(): void
    {
        $repository = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureFlagRepository();

        $this->expectException(\InvalidArgumentException::class);
        $repository->set_enabled(9001, true, 'invalid-ts');
    }

    private function require_class(string $fqcn): void
    {
        if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn)) {
            $this->fail('Missing class contract for RED phase: ' . $fqcn);
        }
    }
}
