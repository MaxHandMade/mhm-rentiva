<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Audit\Crypto;

use MHMRentiva\Core\Tenancy\TenantResolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for managing Cryptographic Key Lifecycle.
 *
 * Enforces strict "Only One Active Key Per Tenant" constraint at the
 * application level, complementing the database UNIQUE index.
 *
 * ALL operations are scoped to a specific tenant_id to ensure
 * strict cryptographic isolation between tenants.
 *
 * @since 4.22.0
 * @updated 4.23.0 Added tenant isolation.
 */
class KeyRegistryRepository
{
    private string $table_name;
    private int $tenant_id;

    /**
     * @param int|null $tenant_id The tenant to scope all operations to.
     *                             Defaults to the currently resolved tenant.
     */
    public function __construct(?int $tenant_id = null)
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mhm_rentiva_key_registry';
        $this->tenant_id  = $tenant_id ?? TenantResolver::resolve()->get_id();
    }

    /**
     * Get the currently active key for this tenant.
     *
     * @return array|null
     */
    public function get_active_key(): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE tenant_id = %d AND active_key = 1 AND status = 'active' LIMIT 1",
                $this->tenant_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get a key by its UUID, scoped to this tenant.
     *
     * @param string $uuid
     * @return array|null
     */
    public function get_key_by_uuid(string $uuid): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE tenant_id = %d AND key_uuid = %s LIMIT 1",
                $this->tenant_id,
                $uuid
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Atomically rotate keys: create a new active key and retire/revoke the old one.
     * 
     * @param array $new_key_data
     * @param string|null $old_key_uuid
     * @param string $old_key_new_status 'retired' or 'revoked'
     * @param string|null $revocation_reason
     * @return bool
     * @throws \Exception
     */
    public function rotate_keys(
        array $new_key_data,
        ?string $old_key_uuid = null,
        string $old_key_new_status = 'retired',
        ?string $revocation_reason = null
    ): bool {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            // 1. Deactivate existing active key FOR THIS TENANT ONLY (defensive)
            $wpdb->update(
                $this->table_name,
                ['active_key' => null],
                ['active_key' => 1, 'tenant_id' => $this->tenant_id]
            );

            // 2. Update status of the specific old key if provided
            if ($old_key_uuid) {
                $update_data = ['status' => $old_key_new_status];
                if ($revocation_reason) {
                    $update_data['revocation_reason'] = $revocation_reason;
                }

                $wpdb->update(
                    $this->table_name,
                    $update_data,
                    ['key_uuid' => $old_key_uuid, 'tenant_id' => $this->tenant_id]
                );
            }

            // 3. Insert new active key, scoped to this tenant.
            $new_key_data['active_key'] = 1;
            $new_key_data['status']     = 'active';
            $new_key_data['tenant_id']  = $this->tenant_id;
            $new_key_data['created_at'] = gmdate('Y-m-d H:i:s'); // UTC strict.

            $inserted = $wpdb->insert($this->table_name, $new_key_data);

            if (!$inserted) {
                throw new \Exception('Failed to insert new key into registry: ' . $wpdb->last_error);
            }

            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Revoke a specific key, scoped to this tenant.
     *
     * @param string $uuid
     * @param string $reason
     * @return bool
     */
    public function revoke_key(string $uuid, string $reason): bool
    {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->table_name,
            [
                'status'            => 'revoked',
                'active_key'        => null,
                'revocation_reason' => $reason
            ],
            ['key_uuid' => $uuid, 'tenant_id' => $this->tenant_id]
        );
    }

    /**
     * Get count of revoked keys for this tenant.
     *
     * @return int
     */
    public function get_revoked_key_count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE tenant_id = %d AND status = 'revoked'",
                $this->tenant_id
            )
        );
    }
}
