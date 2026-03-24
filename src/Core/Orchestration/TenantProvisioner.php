<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Orchestration;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Automates Tenant Provisioning for MHM Rentiva SaaS.
 *
 * Implements Chief Engineer's "DB First + Failure State" pattern.
 *
 * @since 4.23.0
 */
final class TenantProvisioner {


    /**
     * Provisions a new tenant with atomic registry entry and site creation.
     *
     * Hiyerarşi:
     * 1. DB Row (PENDING) + Keys -> Commit
     * 2. Site Creation (Multisite)
     * 3. Update Status (ACTIVE or PROVISIONING_FAILED)
     *
     * @param int    $tenant_id Unique Financial ID.
     * @param string $domain    Domain for the new site.
     * @param string $path      Path for the new site.
     * @param array  $quotas    Initial quotas [payouts, ledger].
     * @return bool Success status.
     */
    public static function provision(int $tenant_id, string $domain, string $path, array $quotas = []): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_tenants';
        $now   = current_time('mysql', 1);

        $payout_limit = $quotas['payouts'] ?? 100;
        $ledger_limit = $quotas['ledger_entries'] ?? 1000;
        $risk_limit   = $quotas['risk_events'] ?? 50;

        try {
            // STEP 1: Authoritative DB Registry (PENDING)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->query('START TRANSACTION');

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $inserted = $wpdb->insert(
                $table,
                [
                    'tenant_id'                  => $tenant_id,
                    'status'                     => 'PENDING',
                    'quota_payouts_limit'        => $payout_limit,
                    'quota_ledger_entries_limit' => $ledger_limit,
                    'quota_risk_events_limit'    => $risk_limit,
                    'created_at'                 => $now,
                ],
                [ '%d', '%s', '%d', '%d', '%d', '%s' ]
            );

            if (! $inserted) {
                throw new \RuntimeException('Failed to insert tenant registry record.');
            }

            // Generate Initial Keys (Cryptographic Seed)
            if (class_exists('\\MHMRentiva\\Core\\Financial\\Audit\\Crypto\\KeyPairManager')) {
                \MHMRentiva\Core\Financial\Audit\Crypto\KeyPairManager::rotate_key('retired', null, $tenant_id);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->query('COMMIT');

            // STEP 2: WordPress Site Creation (Not Transactional)
            $site_id = self::create_multisite_blog($domain, $path);
            $site_id = apply_filters('mhm_rentiva_provisioning_site_id', $site_id, $tenant_id);

            if ($site_id === null) {
                // STEP 3: Handle Failure State
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($table, [ 'status' => 'PROVISIONING_FAILED' ], [ 'tenant_id' => $tenant_id ]);
                return false;
            }

            // STEP 4: Activation
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table,
                [
                    'status'  => 'ACTIVE',
                    'site_id' => $site_id,
                ],
                [ 'tenant_id' => $tenant_id ]
            );

            return true;
        } catch (\Throwable $e) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->query('ROLLBACK');
            if (class_exists('\\MHMRentiva\\Admin\\PostTypes\\Logs\\AdvancedLogger')) {
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
                    'Tenant provisioning failed: ' . $e->getMessage(),
                    [ 'tenant_id' => $tenant_id ],
                    'saas_provisioning'
                );
            }
            return false;
        }
    }

    /**
     * Wrapper for Multisite blog creation.
     */
    private static function create_multisite_blog(string $domain, string $path): ?int
    {
        if (! is_multisite()) {
            return 0; // Fallback for single site dev environments
        }

        $result = wp_insert_site([
            'domain'  => $domain,
            'path'    => $path,
            'title'   => 'Rentiva Tenant Site',
            'user_id' => get_current_user_id() ?: 1,
        ]);

        return is_wp_error($result) ? null : (int) $result;
    }
}
