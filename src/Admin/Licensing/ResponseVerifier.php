<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifies HMAC-SHA256 signed responses from mhm-license-server v1.9.0+.
 *
 * MUST stay in lockstep with the server's `ResponseSigner` canonicalization:
 * recursive ksort + wp_json_encode(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).
 * If the server changes its canonical form, this client cannot verify and
 * legitimate responses will look tampered.
 */
final class ResponseVerifier {

    public const SIGNATURE_FIELD = 'signature';

    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * @param array<string,mixed> $signed
     */
    public function verify(array $signed): bool
    {
        if (!isset($signed[ self::SIGNATURE_FIELD ]) || !is_string($signed[ self::SIGNATURE_FIELD ])) {
            return false;
        }

        $signature = $signed[ self::SIGNATURE_FIELD ];
        unset($signed[ self::SIGNATURE_FIELD ]);

        $expected = hash_hmac('sha256', $this->canonicalize($signed), $this->secret);

        return hash_equals($expected, $signature);
    }

    /** @param array<string,mixed> $data */
    private function canonicalize(array $data): string
    {
        $this->ksortRecursive($data);
        return (string) wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<mixed,mixed> $data */
    private function ksortRecursive(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
        unset($value);
        ksort($data);
    }
}
