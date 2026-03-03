<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Audit\Crypto;

use MHMRentiva\Core\Financial\Exceptions\GovernanceException;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Creates Detached Cryptographic Signatures for raw Audit Exports.
 *
 * Enforces strictly hex-encoded Ed25519 Detached Signatures.
 *
 * @since 4.22.0
 */
class ExportSignatureService
{
    /**
     * Reads a file, hashes it via SHA-256 (in Hex), and signs the Hex Hash using the active Private Key.
     *
     * @param string $file_path Absolute path to the generated CSV payload.
     * @return array{ signature: string, file_hash: string, key_id: string }
     * @throws GovernanceException
     */
    public static function sign_file(string $file_path): array
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
            throw new GovernanceException(sprintf('Cannot sign export. File not found or unreadable: %s', $file_path));
        }

        // 1. Generate canonical Hex Hash (Not binary)
        $file_hash = hash_file('sha256', $file_path, false);
        if ($file_hash === false) {
            throw new GovernanceException('Failed to compute SHA-256 hash for export file.');
        }

        // 2. Load the System Private Key
        $keypair_data = KeyPairManager::get_active_keypair();
        $secret_key   = $keypair_data['secret_key'];

        if ($keypair_data['algo'] !== 'ed25519') {
            throw new GovernanceException('Only Ed25519 signatures are supported in this system version.');
        }

        // 3. Generate Detached Signature from the HEX HASH using Libsodium
        // signature is raw binary
        $raw_signature = sodium_crypto_sign_detached($file_hash, $secret_key);

        // 4. Return as clear Hex Strings per constraint
        return [
            'signature' => sodium_bin2hex($raw_signature),
            'file_hash' => $file_hash,
            'key_id'    => $keypair_data['key_id'],
        ];
    }

    /**
     * Public verification algorithm explicitly matching the external auditor sequence.
     *
     * @param string $file_path
     * @param string $hex_signature
     * @param string $public_key_base64
     * @return bool True if authentic and untampered, else false.
     */
    public static function verify_external(string $file_path, string $hex_signature, string $public_key_base64): bool
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        $file_hash  = hash_file('sha256', $file_path, false);
        $public_key = base64_decode($public_key_base64, true);

        try {
            $raw_signature = sodium_hex2bin($hex_signature);
            return sodium_crypto_sign_verify_detached($raw_signature, (string) $file_hash, (string) $public_key);
        } catch (\SodiumException $e) {
            return false;
        }
    }
}
