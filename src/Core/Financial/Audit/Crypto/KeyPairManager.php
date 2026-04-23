<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Audit\Crypto;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Exceptions\GovernanceException;



/**
 * Manages Asymmetric Key Generation, Rotation, and Secure Storage for Audit Signatures.
 *
 * Implements strict Ed25519 (libsodium) cryptography for non-repudiation.
 * Stores private keys encrypted at rest using symmetric (secretbox) ciphers
 * seeded by WP Authentication secrets.
 *
 * @since 4.22.0
 */
class KeyPairManager {

    /**
     * Retrieves the currently active signing KeyPair (Private + Public).
     * If one does not exist or is malformed, generates a new one.
     *
     * @return array{ key_id: string, public_key: string, secret_key: string, algo: string }
     * @throws GovernanceException
     */
    public static function get_active_keypair(?int $tenant_id = null): array
    {
        $repository = new KeyRegistryRepository($tenant_id);
        $active_key = $repository->get_active_key();

        if ($active_key) {
            // Decrypt the secret key
            $secret_key = self::decrypt_private_key($active_key['private_key_encrypted']);

            return [
                'key_id'     => $active_key['key_uuid'],
                'public_key' => $active_key['public_key'],
                'secret_key' => $secret_key,
                'algo'       => $active_key['key_algorithm'],
            ];
        }

        // Generate Genesis Key
        return self::rotate_key();
    }

    /**
     * Generates a new cryptographic KeyPair, stores it securely in the registry, and sets it as active.
     *
     * @param string $old_key_new_status 'retired' or 'revoked'
     * @param string|null $revocation_reason
     * @param int|null $tenant_id Optional tenant override for provisioning.
     * @return array{ key_id: string, public_key: string, secret_key: string, algo: string }
     * @throws GovernanceException
     */
    public static function rotate_key(string $old_key_new_status = 'retired', ?string $revocation_reason = null, ?int $tenant_id = null): array
    {
        if (!extension_loaded('sodium')) {
            throw new GovernanceException('Libsodium extension is strictly required for Audit Cryptography.');
        }

        $repository = new KeyRegistryRepository($tenant_id);
        $active_key = $repository->get_active_key();
        $old_uuid   = $active_key ? $active_key['key_uuid'] : null;

        $keypair    = sodium_crypto_sign_keypair();
        $secret_key = sodium_crypto_sign_secretkey($keypair);
        $public_key = sodium_crypto_sign_publickey($keypair);

        $key_uuid    = 'key_' . substr(hash('sha256', $public_key), 0, 16);
        $fingerprint = hash('sha256', base64_encode($public_key));

        $encrypted_secret = self::encrypt_private_key($secret_key);

        $new_key_data = [
            'key_uuid'              => $key_uuid,
            'key_algorithm'         => 'ed25519',
            'fingerprint'           => $fingerprint,
            'public_key'            => base64_encode($public_key),
            'private_key_encrypted' => $encrypted_secret,
            'expires_at'            => gmdate('Y-m-d H:i:s', time() + ( 365 * 24 * 60 * 60 )), // 1 year default
        ];

        try {
            $repository->rotate_keys($new_key_data, $old_uuid, $old_key_new_status, $revocation_reason);
        } catch (\Exception $e) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
            throw new GovernanceException('Failed to rotate Audit KeyPair: ' . $e->getMessage());
        }

        return [
            'key_id'     => $key_uuid,
            'public_key' => base64_encode($public_key),
            'secret_key' => $secret_key,
            'algo'       => 'ed25519',
        ];
    }

    /**
     * Revokes the current active key and immediately generates a replacement.
     *
     * @param string $reason
     * @return array The new active keypair
     * @throws GovernanceException
     */
    public static function revoke_active_key(string $reason, ?int $tenant_id = null): array
    {
        return self::rotate_key('revoked', $reason, $tenant_id);
    }

    /**
     * Retrieves a historical public key by its UUID for verification purposes.
     *
     * @param string $key_uuid
     * @return string|null Base64 encoded public key
     */
    public static function get_public_key(string $key_uuid, ?int $tenant_id = null): ?string
    {
        $repository = new KeyRegistryRepository($tenant_id);
        $key        = $repository->get_key_by_uuid($key_uuid);

        return $key ? $key['public_key'] : null;
    }

    /**
     * Derives a strict 32-byte symmetric encryption key using WP configuration salts.
     *
     * @return string
     * @throws GovernanceException
     */
    private static function derive_encryption_key(): string
    {
        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_SALT')) {
            throw new GovernanceException('WordPress security constants (AUTH_KEY, SECURE_AUTH_SALT) must be defined to encrypt audit keys safely.');
        }

        // Derive a consistent 32-byte key for libsodium secretbox
        $raw_material = AUTH_KEY . '|' . SECURE_AUTH_SALT . '|mhm_rentiva_audit_encryption';
        return sodium_crypto_generichash($raw_material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /**
     * Encrypts the raw private key using libsodium secretbox.
     *
     * @param string $raw_secret_key
     * @return string Base64 encoded payload [nonce : ciphertext]
     * @throws GovernanceException
     */
    private static function encrypt_private_key(string $raw_secret_key): string
    {
        $key   = self::derive_encryption_key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $ciphertext = sodium_crypto_secretbox($raw_secret_key, $nonce, $key);

        // Pack nonce + ciphertext
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypts the secretbox payload to retrieve the raw private key.
     *
     * @param string $encrypted_payload Base64 encoded payload
     * @return string Raw secret key
     * @throws GovernanceException
     */
    private static function decrypt_private_key(string $encrypted_payload): string
    {
        $decoded = base64_decode($encrypted_payload, true);
        if ($decoded === false) {
            throw new GovernanceException('Corrupted audit key payload (Invalid Base64).');
        }

        if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new GovernanceException('Corrupted audit key payload (Missing Nonce).');
        }

        $nonce      = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key        = self::derive_encryption_key();

        $raw_secret = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($raw_secret === false) {
            throw new GovernanceException('Failed to decrypt audit private key. Server authentication salts may have changed.');
        }

        return $raw_secret;
    }
}
