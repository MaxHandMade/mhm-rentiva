<?php
declare(strict_types=1);

namespace MHMRentiva\CLI;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Audit\Crypto\KeyPairManager;
use MHMRentiva\Core\Financial\Audit\Crypto\KeyRegistryRepository;
use WP_CLI;
use WP_CLI_Command;

/**
 * CLI Command for revoking Cryptographic Keys.
 * 
 * @since 4.22.0
 */
class KeyRevokeCommand extends WP_CLI_Command
{

    /**
     * Revokes a specific key by UUID.
     * 
     * ## OPTIONS
     * 
     * <key_uuid>
     * : The UUID of the key to revoke.
     * 
     * --reason=<reason>
     * : The reason for revocation.
     * 
     * ## EXAMPLES
     * 
     *     wp mhm key:revoke key_abc123 --reason="Key Compromised"
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $uuid   = $args[0] ?? '';
        $reason = $assoc_args['reason'] ?? '';

        if (empty($uuid) || empty($reason)) {
            WP_CLI::error('Both UUID and --reason are strictly required for revocation.', true);
        }

        try {
            $repository = new KeyRegistryRepository();
            $key = $repository->get_key_by_uuid($uuid);

            if (!$key) {
                WP_CLI::error(sprintf('Key with UUID %s not found in registry.', $uuid), true);
            }

            if ($key['status'] === 'revoked') {
                WP_CLI::warning(sprintf('Key %s is already revoked.', $uuid));
                return;
            }

            $is_active = (bool) $key['active_key'];

            if ($is_active) {
                WP_CLI::log('Revoking ACTIVE key. A new replacement key will be automatically generated.');
                KeyPairManager::revoke_active_key($reason);
            } else {
                $repository->revoke_key($uuid, $reason);
            }

            WP_CLI::success(sprintf('Key %s has been successfully revoked. Reason: %s', $uuid, $reason));
        } catch (\Exception $e) {
            WP_CLI::error('Key Revocation Failed: ' . $e->getMessage(), true);
        }
    }
}
