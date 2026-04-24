<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\ResponseVerifier;
use WP_UnitTestCase;

/**
 * Mirror of mhm-license-server v1.9.0 ResponseSigner — verify side only.
 *
 * Both sides MUST produce the same canonical JSON for the HMAC to match;
 * if these tests start failing it usually means the server's canonicalization
 * (recursive ksort + JSON_UNESCAPED_SLASHES|UNICODE) drifted.
 */
final class ResponseVerifierTest extends WP_UnitTestCase
{
    private const SECRET = 'test-response-hmac';

    public function test_verifies_response_signed_by_server_style_canonicalization(): void
    {
        $payload = ['status' => 'active', 'plan' => 'pro', 'expires_at' => 1234567890];
        $canonical = $this->serverStyleCanonicalize($payload);
        $payload['signature'] = hash_hmac('sha256', $canonical, self::SECRET);

        $verifier = new ResponseVerifier(self::SECRET);
        $this->assertTrue($verifier->verify($payload));
    }

    public function test_fails_when_payload_field_tampered(): void
    {
        $payload = ['status' => 'active', 'plan' => 'pro'];
        $canonical = $this->serverStyleCanonicalize($payload);
        $payload['signature'] = hash_hmac('sha256', $canonical, self::SECRET);

        $payload['plan'] = 'free';

        $verifier = new ResponseVerifier(self::SECRET);
        $this->assertFalse($verifier->verify($payload));
    }

    public function test_fails_when_signature_field_missing(): void
    {
        $verifier = new ResponseVerifier(self::SECRET);
        $this->assertFalse($verifier->verify(['status' => 'active']));
        $this->assertFalse($verifier->verify([]));
    }

    public function test_fails_with_different_secret(): void
    {
        $payload = ['status' => 'active'];
        $canonical = $this->serverStyleCanonicalize($payload);
        $payload['signature'] = hash_hmac('sha256', $canonical, 'server-secret');

        $verifier = new ResponseVerifier('client-secret-mismatch');
        $this->assertFalse($verifier->verify($payload));
    }

    public function test_canonicalization_handles_nested_arrays_with_any_key_order(): void
    {
        // Server emits keys in some order; client must verify regardless.
        $payload = [
            'status' => 'active',
            'features' => ['vendor_marketplace' => true, 'messaging' => true],
        ];
        $canonical = $this->serverStyleCanonicalize($payload);
        $payload['signature'] = hash_hmac('sha256', $canonical, self::SECRET);

        // Reorder nested keys → still valid (recursive ksort)
        $payload['features'] = ['messaging' => true, 'vendor_marketplace' => true];

        $verifier = new ResponseVerifier(self::SECRET);
        $this->assertTrue($verifier->verify($payload));
    }

    public function test_fails_when_extra_field_added_after_signing(): void
    {
        $payload = ['status' => 'active'];
        $canonical = $this->serverStyleCanonicalize($payload);
        $payload['signature'] = hash_hmac('sha256', $canonical, self::SECRET);

        $payload['injected'] = 'malicious';

        $verifier = new ResponseVerifier(self::SECRET);
        $this->assertFalse($verifier->verify($payload));
    }

    /**
     * Reproduces server-side ResponseSigner::canonicalize() exactly.
     * If you change one, change the other.
     *
     * @param array<string,mixed> $data
     */
    private function serverStyleCanonicalize(array $data): string
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
