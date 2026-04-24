<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Licensing;

use MHMRentiva\Admin\Licensing\VerifyEndpoint;
use WP_REST_Request;
use WP_UnitTestCase;

final class VerifyEndpointTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Force a known PING_SECRET for the test run via env var.
        // The endpoint reads from ClientSecrets which falls back to env when no constant.
        if (!defined('MHM_RENTIVA_LICENSE_PING_SECRET')) {
            putenv('MHM_RENTIVA_LICENSE_PING_SECRET=test-ping-secret');
        }

        VerifyEndpoint::register();

        // Routes added on rest_api_init — trigger it by re-instantiating server.
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init');
    }

    protected function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        if (!defined('MHM_RENTIVA_LICENSE_PING_SECRET')) {
            putenv('MHM_RENTIVA_LICENSE_PING_SECRET=');
        }
        parent::tearDown();
    }

    public function test_route_is_registered(): void
    {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/mhm-rentiva-verify/v1/ping', $routes);
    }

    public function test_returns_hmac_of_challenge_with_ping_secret(): void
    {
        $challenge = 'test-challenge-uuid-12345';
        $request = new WP_REST_Request('GET', '/mhm-rentiva-verify/v1/ping');
        $request->set_header('X-MHM-Challenge', $challenge);

        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('challenge_response', $data);

        $secret = defined('MHM_RENTIVA_LICENSE_PING_SECRET')
            ? (string) constant('MHM_RENTIVA_LICENSE_PING_SECRET')
            : 'test-ping-secret';
        $expected = hash_hmac('sha256', $challenge, $secret);

        $this->assertSame($expected, $data['challenge_response']);
    }

    public function test_response_includes_site_metadata(): void
    {
        $request = new WP_REST_Request('GET', '/mhm-rentiva-verify/v1/ping');
        $request->set_header('X-MHM-Challenge', 'any-challenge');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('site_url', $data);
        $this->assertArrayHasKey('product_slug', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertSame('mhm-rentiva', $data['product_slug']);
        $this->assertSame(home_url(), $data['site_url']);
    }

    public function test_returns_error_when_challenge_header_missing(): void
    {
        $request = new WP_REST_Request('GET', '/mhm-rentiva-verify/v1/ping');
        // No X-MHM-Challenge header

        $response = rest_do_request($request);

        $this->assertSame(400, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('challenge_missing', $data['code'] ?? '');
    }

    public function test_returns_error_when_ping_secret_not_configured(): void
    {
        if (defined('MHM_RENTIVA_LICENSE_PING_SECRET')) {
            $this->markTestSkipped('Constant defined; not-configured path cannot be asserted.');
        }

        // Clear the env var that was set in setUp().
        putenv('MHM_RENTIVA_LICENSE_PING_SECRET=');

        $request = new WP_REST_Request('GET', '/mhm-rentiva-verify/v1/ping');
        $request->set_header('X-MHM-Challenge', 'foo');

        $response = rest_do_request($request);

        $this->assertSame(503, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('ping_secret_not_configured', $data['code'] ?? '');
    }
}
