<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

final class LicenseRequestSigningTest extends WP_UnitTestCase
{
	/** @var callable|null */
	private $http_mock;

	/** @var array{url:string,args:array<string,mixed>}|null */
	private ?array $captured_request = null;

	protected function setUp(): void
	{
		parent::setUp();

		update_option(LicenseManager::OPTION, array(), false);
		putenv('MHM_RENTIVA_LICENSE_API_KEY=');
		putenv('MHM_RENTIVA_LICENSE_HMAC_SECRET=');

		$this->http_mock = function ($preempt, $parsed_args, $url) {
			$this->captured_request = array(
				'url'  => (string) $url,
				'args' => is_array($parsed_args) ? $parsed_args : array(),
			);

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'status'        => 'active',
						'plan'          => 'pro',
						'expires_at'    => time() + DAY_IN_SECONDS,
						'activation_id' => 'act-test',
						'token'         => 'tok-test',
					)
				),
				'response' => array('code' => 200, 'message' => 'OK'),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter('pre_http_request', $this->http_mock, 10, 3);
	}

	protected function tearDown(): void
	{
		if ($this->http_mock !== null) {
			remove_filter('pre_http_request', $this->http_mock, 10);
		}

		delete_option(LicenseManager::OPTION);
		delete_option('mhm_rentiva_disable_dev_mode');
		putenv('MHM_RENTIVA_LICENSE_API_KEY=');
		putenv('MHM_RENTIVA_LICENSE_HMAC_SECRET=');

		parent::tearDown();
	}

	public function test_signed_headers_are_sent_when_credentials_are_available(): void
	{
		if (!defined('MHM_RENTIVA_LICENSE_API_KEY')) {
			putenv('MHM_RENTIVA_LICENSE_API_KEY=test-api-key');
		}

		if (!defined('MHM_RENTIVA_LICENSE_HMAC_SECRET')) {
			putenv('MHM_RENTIVA_LICENSE_HMAC_SECRET=test-hmac-secret');
		}

		$result = LicenseManager::instance()->activate('TEST-KEY-123');

		$this->assertTrue($result);
		$this->assertNotNull($this->captured_request);
		$this->assertArrayHasKey('headers', $this->captured_request['args']);
		$headers = (array) $this->captured_request['args']['headers'];

		$this->assertArrayHasKey('X-MHM-SITE', $headers);
		$this->assertArrayHasKey('X-MHM-API-KEY', $headers);
		$this->assertArrayHasKey('X-MHM-TIMESTAMP', $headers);
		$this->assertArrayHasKey('X-MHM-SIGNATURE', $headers);

		$api_key = defined('MHM_RENTIVA_LICENSE_API_KEY')
			? (string) constant('MHM_RENTIVA_LICENSE_API_KEY')
			: 'test-api-key';
		$hmac_secret = defined('MHM_RENTIVA_LICENSE_HMAC_SECRET')
			? (string) constant('MHM_RENTIVA_LICENSE_HMAC_SECRET')
			: 'test-hmac-secret';

		$this->assertSame($api_key, $headers['X-MHM-API-KEY']);

		$timestamp = (string) $headers['X-MHM-TIMESTAMP'];
		$body = (string) ($this->captured_request['args']['body'] ?? '');
		$message = 'POST|/mhm-license/v1/licenses/activate|' . $timestamp . '|' . hash('sha256', $body);
		$expected_signature = hash_hmac('sha256', $message, $hmac_secret);

		$this->assertSame($expected_signature, $headers['X-MHM-SIGNATURE']);
	}

	public function test_only_site_header_is_sent_without_credentials(): void
	{
		if (defined('MHM_RENTIVA_LICENSE_API_KEY') || defined('MHM_RENTIVA_LICENSE_HMAC_SECRET')) {
			$this->markTestSkipped('Environment defines signing constants; no-credential path cannot be asserted.');
		}

		$result = LicenseManager::instance()->activate('TEST-KEY-456');

		$this->assertTrue($result);
		$this->assertNotNull($this->captured_request);
		$headers = (array) $this->captured_request['args']['headers'];

		$this->assertArrayHasKey('X-MHM-SITE', $headers);
		$this->assertArrayNotHasKey('X-MHM-API-KEY', $headers);
		$this->assertArrayNotHasKey('X-MHM-TIMESTAMP', $headers);
		$this->assertArrayNotHasKey('X-MHM-SIGNATURE', $headers);
	}
}

