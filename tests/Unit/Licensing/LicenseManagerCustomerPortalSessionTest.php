<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

/**
 * v4.32.0 — LicenseManager::createCustomerPortalSession() public method.
 *
 * The new method mints a Polar customer-portal session URL via the
 * mhm-license-server `/licenses/customer-portal-session` endpoint. The test
 * suite covers the four documented contract paths:
 *
 *   1. License not active locally  → ['success' => false, 'error_code' => 'license_not_active']
 *   2. Server happy path (HTTP 200, signed) → returns customer_portal_url + expires_at
 *   3. Server 404 (license_not_found)
 *   4. Server 422 (license_not_subscription — legacy or non-Polar license)
 *   5. Tampered signature on the response → tampered_response
 *
 * @covers \MHMRentiva\Admin\Licensing\LicenseManager::createCustomerPortalSession
 */
final class LicenseManagerCustomerPortalSessionTest extends WP_UnitTestCase
{
    private const RESPONSE_SECRET = 'test-resp-secret';

    /** @var callable|null */
    private $http_mock;

    /** @var array{url:string,args:array<string,mixed>}|null */
    private ?array $captured_request = null;

    /** @var array{body:array<string,mixed>,code:int}|null */
    private ?array $next_response = null;

    protected function setUp(): void
    {
        parent::setUp();

        update_option(LicenseManager::OPTION, [], false);

        if (!defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=' . self::RESPONSE_SECRET);
        }

        $this->http_mock = function ($preempt, $parsed_args, $url) {
            $this->captured_request = [
                'url'  => (string) $url,
                'args' => is_array($parsed_args) ? $parsed_args : [],
            ];

            $next = $this->next_response ?? ['body' => [], 'code' => 200];

            return [
                'headers'  => [],
                'body'     => wp_json_encode($next['body']),
                'response' => ['code' => $next['code'], 'message' => 'OK'],
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
        $this->captured_request = null;
        $this->next_response    = null;
        parent::tearDown();
    }

    public function test_returns_error_when_license_not_active(): void
    {
        // No license seeded — isActive() will return false on a host without a key.
        update_option(LicenseManager::OPTION, [], false);

        $result = LicenseManager::instance()->createCustomerPortalSession();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('license_not_active', $result['error_code']);
    }

    public function test_returns_url_on_happy_path(): void
    {
        $this->seedActiveLicense('TEST-KEY-1234');

        $this->next_response = [
            'code' => 200,
            'body' => $this->signedResponse([
                'success' => true,
                'data'    => [
                    'customer_portal_url' => 'https://polar.sh/portal/abc',
                    'expires_at'          => '2026-04-26T15:00:00Z',
                ],
            ]),
        ];

        $result = LicenseManager::instance()->createCustomerPortalSession('https://example.com/return');

        $this->assertTrue($result['success']);
        $this->assertSame('https://polar.sh/portal/abc', $result['customer_portal_url']);
        $this->assertSame('2026-04-26T15:00:00Z', $result['expires_at']);

        // Sanity-check the request body carried the expected fields.
        $this->assertNotNull($this->captured_request);
        $this->assertStringContainsString('/licenses/customer-portal-session', $this->captured_request['url']);
        $body = json_decode((string) ($this->captured_request['args']['body'] ?? '{}'), true);
        $this->assertSame('TEST-KEY-1234', $body['license_key']);
        $this->assertSame('https://example.com/return', $body['return_url']);
        $this->assertNotEmpty($body['site_hash']);
    }

    public function test_returns_error_on_server_404(): void
    {
        $this->seedActiveLicense('TEST-KEY-404');

        $this->next_response = [
            'code' => 404,
            'body' => [
                'error'   => 'license_not_found',
                'message' => 'License key not found.',
            ],
        ];

        $result = LicenseManager::instance()->createCustomerPortalSession();

        $this->assertFalse($result['success']);
        $this->assertSame('license_not_found', $result['error_code']);
    }

    public function test_returns_error_on_server_422(): void
    {
        $this->seedActiveLicense('TEST-KEY-422');

        $this->next_response = [
            'code' => 422,
            'body' => [
                'error'   => 'license_not_subscription',
                'message' => 'This license is not tied to a Polar subscription.',
            ],
        ];

        $result = LicenseManager::instance()->createCustomerPortalSession();

        $this->assertFalse($result['success']);
        $this->assertSame('license_not_subscription', $result['error_code']);
    }

    public function test_returns_error_on_signature_mismatch(): void
    {
        $this->seedActiveLicense('TEST-KEY-TAMPERED');

        $body = $this->signedResponse([
            'success' => true,
            'data'    => [
                'customer_portal_url' => 'https://polar.sh/portal/legit',
                'expires_at'          => '2026-04-26T15:00:00Z',
            ],
        ]);
        // Tamper the URL after the HMAC is computed.
        $body['data']['customer_portal_url'] = 'https://attacker.example/steal';

        $this->next_response = [
            'code' => 200,
            'body' => $body,
        ];

        $result = LicenseManager::instance()->createCustomerPortalSession();

        $this->assertFalse($result['success']);
        $this->assertSame('tampered_response', $result['error_code']);
    }

    private function seedActiveLicense(string $key): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => $key,
            'status'        => 'active',
            'plan'          => 'pro',
            'activation_id' => 'act-test-' . substr(md5($key), 0, 6),
            'expires_at'    => time() + (30 * DAY_IN_SECONDS),
            'last_check_at' => time(),
            'hash_v2'       => true,
        ], false);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function signedResponse(array $payload): array
    {
        $this->ksortRecursive($payload);
        $canonical            = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
}
