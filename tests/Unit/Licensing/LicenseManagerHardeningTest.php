<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

/**
 * Phase B (v4.30.0+) — LicenseManager response signing + feature token integration.
 */
final class LicenseManagerHardeningTest extends WP_UnitTestCase
{
    private const RESPONSE_SECRET = 'test-resp-secret';
    private const FEATURE_SECRET  = 'test-feature-secret';

    /** @var callable|null */
    private $http_mock;

    /** @var array{url:string,args:array<string,mixed>}|null */
    private ?array $captured_request = null;

    /** @var array<string,mixed>|null */
    private ?array $next_response_body = null;

    protected function setUp(): void
    {
        parent::setUp();

        update_option(LicenseManager::OPTION, [], false);

        if (!defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=' . self::RESPONSE_SECRET);
        }
        if (!defined('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY')) {
            putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=' . self::FEATURE_SECRET);
        }

        $this->http_mock = function ($preempt, $parsed_args, $url) {
            $this->captured_request = [
                'url'  => (string) $url,
                'args' => is_array($parsed_args) ? $parsed_args : [],
            ];

            $body = $this->next_response_body ?? [
                'status'        => 'active',
                'plan'          => 'pro',
                'expires_at'    => time() + DAY_IN_SECONDS,
                'activation_id' => 'act-test',
            ];

            return [
                'headers'  => [],
                'body'     => wp_json_encode($body),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        };

        add_filter('pre_http_request', $this->http_mock, 10, 3);
    }

    protected function tearDown(): void
    {
        if ($this->http_mock !== null) {
            remove_filter('pre_http_request', $this->http_mock, 10);
        }
        delete_option(LicenseManager::OPTION);
        if (!defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=');
        }
        if (!defined('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY')) {
            putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=');
        }
        $this->captured_request = null;
        $this->next_response_body = null;
        parent::tearDown();
    }

    public function test_activate_request_body_includes_client_version(): void
    {
        $this->next_response_body = $this->signedResponse([
            'status' => 'active', 'plan' => 'pro', 'activation_id' => 'a1',
            'expires_at' => time() + DAY_IN_SECONDS,
        ]);

        LicenseManager::instance()->activate('TEST-V430-001');

        $this->assertNotNull($this->captured_request);
        $body = json_decode((string) ($this->captured_request['args']['body'] ?? '{}'), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('client_version', $body);

        $expected = defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : '';
        $this->assertSame($expected, $body['client_version']);
    }

    public function test_activate_succeeds_when_response_signature_is_valid(): void
    {
        $this->next_response_body = $this->signedResponse([
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + DAY_IN_SECONDS,
            'activation_id' => 'a1',
            'feature_token' => $this->buildFeatureToken(),
        ]);

        $result = LicenseManager::instance()->activate('TEST-V430-002');
        $this->assertTrue($result);
    }

    public function test_activate_rejects_when_response_signature_invalid(): void
    {
        $body = $this->signedResponse([
            'status' => 'active', 'activation_id' => 'a1',
            'expires_at' => time() + DAY_IN_SECONDS,
        ]);
        // Tamper after signing
        $body['plan'] = 'free';

        $this->next_response_body = $body;

        $result = LicenseManager::instance()->activate('TEST-V430-003');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('tampered_response', $result->get_error_code());
    }

    public function test_activate_accepts_legacy_server_without_signature_field(): void
    {
        // No signature — simulating eski server (v1.8.x)
        $this->next_response_body = [
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + DAY_IN_SECONDS,
            'activation_id' => 'a1',
        ];

        $result = LicenseManager::instance()->activate('TEST-V430-004');
        $this->assertTrue($result);
    }

    public function test_activate_stores_feature_token_in_local_option(): void
    {
        $token = $this->buildFeatureToken();
        $this->next_response_body = $this->signedResponse([
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + DAY_IN_SECONDS,
            'activation_id' => 'a1',
            'feature_token' => $token,
        ]);

        LicenseManager::instance()->activate('TEST-V430-005');

        $stored = get_option(LicenseManager::OPTION, []);
        $this->assertArrayHasKey('feature_token', $stored);
        $this->assertSame($token, $stored['feature_token']);
    }

    public function test_get_feature_token_returns_stored_value(): void
    {
        update_option(LicenseManager::OPTION, [
            'key' => 'k', 'status' => 'active', 'feature_token' => 'stored-token-abc',
        ], false);

        $this->assertSame('stored-token-abc', LicenseManager::instance()->getFeatureToken());
    }

    public function test_get_feature_token_returns_empty_when_missing(): void
    {
        update_option(LicenseManager::OPTION, ['key' => 'k', 'status' => 'active'], false);

        $this->assertSame('', LicenseManager::instance()->getFeatureToken());
    }

    public function test_validate_refreshes_feature_token_from_server_response(): void
    {
        // Seed: existing license + old token
        update_option(LicenseManager::OPTION, [
            'key'           => 'EXISTING-KEY',
            'status'        => 'active',
            'activation_id' => 'a-existing',
            'feature_token' => 'old-token-xyz',
            'last_check_at' => time() - HOUR_IN_SECONDS,
        ], false);

        $new_token = $this->buildFeatureToken();
        $this->next_response_body = $this->signedResponse([
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + DAY_IN_SECONDS,
            'feature_token' => $new_token,
        ]);

        $result = LicenseManager::instance()->validate(true);

        $this->assertTrue($result);
        $stored = get_option(LicenseManager::OPTION, []);
        $this->assertSame($new_token, $stored['feature_token']);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function signedResponse(array $payload): array
    {
        $this->ksortRecursive($payload);
        $canonical = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload['signature'] = hash_hmac('sha256', $canonical, self::RESPONSE_SECRET);
        return $payload;
    }

    /** @param array<mixed,mixed> $data */
    private function ksortRecursive(array &$data): void
    {
        foreach ($data as &$v) {
            if (is_array($v)) {
                $this->ksortRecursive($v);
            }
        }
        unset($v);
        ksort($data);
    }

    private function buildFeatureToken(): string
    {
        $payload = [
            'license_key_hash' => 'h',
            'product_slug'     => 'mhm-rentiva',
            'plan'             => 'pro',
            'features'         => ['vendor_marketplace' => true, 'messaging' => true],
            'site_hash'        => 's',
            'issued_at'        => time(),
            'expires_at'       => time() + 86400,
        ];
        $b64 = base64_encode((string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $b64 . '.' . hash_hmac('sha256', $b64, self::FEATURE_SECRET);
    }
}
