<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Financial\Audit\Crypto\KeyRegistryRepository;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Cross-Tenant Cryptographic Key Isolation Tests
 *
 * Verifies that Tenant A's keys cannot be retrieved by Tenant B's repository,
 * and key operations (rotate, revoke) cannot impact another tenant's keychain.
 *
 * @group multi-tenant
 * @group cryptographic-isolation
 * @since 4.23.0
 */
class CrossTenantKeyTest extends WP_UnitTestCase
{
    private const TENANT_A_ID = 1;
    private const TENANT_B_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::reset();
    }

    protected function tearDown(): void
    {
        TenantResolver::reset();
        parent::tearDown();
    }

    /**
     * @test
     * Tenant B's KeyRegistryRepository must NOT return Tenant A's active key.
     */
    public function test_active_key_is_isolated_per_tenant(): void
    {
        // Simulate Tenant A having an active key in the registry.
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_key_registry';

        // Insert a synthetic active key for Tenant A only.
        $wpdb->insert($table, [
            'tenant_id'              => self::TENANT_A_ID,
            'key_uuid'               => 'test-key-tenant-a-001',
            'status'                 => 'active',
            'active_key'             => 1,
            'fingerprint'            => str_repeat('a', 64),
            'key_algorithm'          => 'Ed25519',
            'public_key'             => base64_encode('tenant-a-public-key'),
            'private_key_encrypted'  => base64_encode('tenant-a-encrypted-private'),
            'created_at'             => gmdate('Y-m-d H:i:s'),
        ]);

        // Tenant B's repository should find no active key.
        $repo_b     = new KeyRegistryRepository(self::TENANT_B_ID);
        $active_key = $repo_b->get_active_key();
        $this->assertNull($active_key, 'Tenant B MUST NOT retrieve Tenant A\'s active cryptographic key. SECURITY BREACH!');

        // Tenant A's repository SHOULD find its key.
        $repo_a     = new KeyRegistryRepository(self::TENANT_A_ID);
        $active_key = $repo_a->get_active_key();
        $this->assertNotNull($active_key, 'Tenant A should be able to retrieve its own active key.');
        $this->assertEquals('test-key-tenant-a-001', $active_key['key_uuid']);
    }

    /**
     * @test
     * Revoking Tenant A's key must NOT affect Tenant B's keys.
     */
    public function test_key_revocation_is_scoped_to_tenant(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_key_registry';

        // Insert active keys for both tenants.
        $wpdb->insert($table, [
            'tenant_id'              => self::TENANT_A_ID,
            'key_uuid'               => 'test-revoke-key-a',
            'status'                 => 'active',
            'active_key'             => 1,
            'fingerprint'            => str_repeat('a', 64),
            'key_algorithm'          => 'Ed25519',
            'public_key'             => base64_encode('tenant-a-pub'),
            'private_key_encrypted'  => base64_encode('tenant-a-priv'),
            'created_at'             => gmdate('Y-m-d H:i:s'),
        ]);

        $wpdb->insert($table, [
            'tenant_id'              => self::TENANT_B_ID,
            'key_uuid'               => 'test-revoke-key-b',
            'status'                 => 'active',
            'active_key'             => 1,
            'fingerprint'            => str_repeat('b', 64),
            'key_algorithm'          => 'Ed25519',
            'public_key'             => base64_encode('tenant-b-pub'),
            'private_key_encrypted'  => base64_encode('tenant-b-priv'),
            'created_at'             => gmdate('Y-m-d H:i:s'),
        ]);

        // Revoke Tenant A's key.
        $repo_a = new KeyRegistryRepository(self::TENANT_A_ID);
        $repo_a->revoke_key('test-revoke-key-a', 'Cross-tenant key isolation test.');

        // Tenant B's key must remain active.
        $repo_b     = new KeyRegistryRepository(self::TENANT_B_ID);
        $active_key = $repo_b->get_active_key();
        $this->assertNotNull($active_key, 'Tenant B\'s key MUST remain active after Tenant A revokes its own key.');
        $this->assertEquals('test-revoke-key-b', $active_key['key_uuid']);
        $this->assertEquals('active', $active_key['status']);
    }
}
