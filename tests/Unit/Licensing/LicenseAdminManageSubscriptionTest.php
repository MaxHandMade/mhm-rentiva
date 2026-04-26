<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseAdmin;
use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

/**
 * v4.32.0 — admin-post handler for "Manage Subscription".
 *
 * Walks the four contract paths of `LicenseAdmin::handle_manage_subscription()`:
 *
 *   1. Non-admin user → wp_die() (capability gate)
 *   2. Bad nonce → wp_die() (CSRF gate)
 *   3. createCustomerPortalSession success → wp_redirect to portal URL
 *   4. createCustomerPortalSession failure → wp_safe_redirect to license page
 *      with `?license=manage_unavailable&reason=<sanitized error_code>`
 *
 * `wp_redirect`/`wp_safe_redirect` are intercepted via the `wp_redirect`
 * filter — when the filter throws, the surrounding `exit;` is bypassed and
 * the captured destination URL is exposed to the assertions.
 *
 * @covers \MHMRentiva\Admin\Licensing\LicenseAdmin::handle_manage_subscription
 */
final class LicenseAdminManageSubscriptionTest extends WP_UnitTestCase
{
    /** @var array<string,mixed> */
    private array $get_backup = [];

    /** @var array<string,mixed> */
    private array $request_backup = [];

    /** @var callable|null */
    private $http_mock = null;

    /** @var array{body:array<string,mixed>,code:int}|null */
    private ?array $next_response = null;

    private const RESPONSE_SECRET = 'test-resp-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->get_backup     = $_GET;
        $this->request_backup = $_REQUEST;
        $_GET                 = [];
        $_REQUEST             = [];

        update_option(LicenseManager::OPTION, [], false);

        if (!defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=' . self::RESPONSE_SECRET);
        }

        $this->http_mock = function ($preempt, $parsed_args, $url) {
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
        $_GET     = $this->get_backup;
        $_REQUEST = $this->request_backup;
        wp_set_current_user(0);
        delete_option(LicenseManager::OPTION);
        if (!defined('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET')) {
            putenv('MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET=');
        }
        $this->next_response = null;
        parent::tearDown();
    }

    public function test_handler_requires_manage_options_capability(): void
    {
        $subscriber = self::factory()->user->create([ 'role' => 'subscriber' ]);
        wp_set_current_user($subscriber);

        $this->expectException(\WPDieException::class);
        LicenseAdmin::handle_manage_subscription();
    }

    public function test_handler_verifies_nonce(): void
    {
        $admin = self::factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($admin);

        $_REQUEST['_wpnonce'] = 'invalid-nonce';

        $this->expectException(\WPDieException::class);
        LicenseAdmin::handle_manage_subscription();
    }

    public function test_handler_redirects_to_portal_url_on_success(): void
    {
        $admin = self::factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($admin);

        $this->seedActiveLicense('TEST-MANAGE-OK');

        $_REQUEST['_wpnonce'] = wp_create_nonce('mhm_rentiva_manage_subscription');

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

        $captured = '';
        add_filter('wp_redirect', function ($location) use (&$captured) {
            $captured = (string) $location;
            throw new \RuntimeException('intercepted-redirect');
        }, 10, 1);

        try {
            LicenseAdmin::handle_manage_subscription();
            $this->fail('Expected redirect to be intercepted.');
        } catch (\RuntimeException $e) {
            $this->assertSame('intercepted-redirect', $e->getMessage());
        }

        $this->assertSame('https://polar.sh/portal/abc', $captured);

        remove_all_filters('wp_redirect');
    }

    public function test_handler_redirects_to_error_page_on_failure(): void
    {
        $admin = self::factory()->user->create([ 'role' => 'administrator' ]);
        wp_set_current_user($admin);

        $this->seedActiveLicense('TEST-MANAGE-422');

        $_REQUEST['_wpnonce'] = wp_create_nonce('mhm_rentiva_manage_subscription');

        // 422 → license_not_subscription via mhm-license-server.
        $this->next_response = [
            'code' => 422,
            'body' => [
                'error'   => 'license_not_subscription',
                'message' => 'Legacy license — no Polar subscription bound.',
            ],
        ];

        $captured = '';
        add_filter('wp_redirect', function ($location) use (&$captured) {
            $captured = (string) $location;
            throw new \RuntimeException('intercepted-redirect');
        }, 10, 1);

        try {
            LicenseAdmin::handle_manage_subscription();
            $this->fail('Expected redirect to be intercepted.');
        } catch (\RuntimeException $e) {
            $this->assertSame('intercepted-redirect', $e->getMessage());
        }

        $this->assertStringContainsString('page=mhm-rentiva-license', $captured);
        $this->assertStringContainsString('license=manage_unavailable', $captured);
        $this->assertStringContainsString('reason=license_not_subscription', $captured);

        remove_all_filters('wp_redirect');
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
