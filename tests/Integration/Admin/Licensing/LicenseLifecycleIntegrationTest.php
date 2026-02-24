<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

final class LicenseLifecycleIntegrationTest extends WP_UnitTestCase {

	private const LICENSE_KEY = 'TEST-KEY-1234';

	/**
	 * @var array<int, string>
	 */
	private array $request_paths = array();

	public function setUp(): void {
		parent::setUp();

		delete_option(LicenseManager::OPTION);
		$this->request_paths = array();
		putenv('MHM_RENTIVA_LICENSE_API_BASE=https://example.com/v1');

		add_filter('pre_http_request', array($this, 'mock_license_server'), 10, 3);
	}

	public function tearDown(): void {
		remove_filter('pre_http_request', array($this, 'mock_license_server'), 10);
		delete_option(LicenseManager::OPTION);
		putenv('MHM_RENTIVA_LICENSE_API_BASE');

		parent::tearDown();
	}

	public function test_activate_validate_and_deactivate_lifecycle(): void {
		$manager = LicenseManager::instance();

		$activate_result = $manager->activate(self::LICENSE_KEY);
		$this->assertTrue($activate_result);

		$stored = $manager->get();
		$this->assertSame(self::LICENSE_KEY, (string) ($stored['key'] ?? ''));
		$this->assertSame('active', (string) ($stored['status'] ?? ''));
		$this->assertSame('act_test_123', (string) ($stored['activation_id'] ?? ''));

		$validate_result = $manager->validate();
		$this->assertTrue($validate_result);

		$validated = $manager->get();
		$this->assertSame('active', (string) ($validated['status'] ?? ''));

		$deactivate_result = $manager->deactivate();
		$this->assertTrue($deactivate_result);
		$this->assertSame(array(), $manager->get());

		$this->assertTrue($this->has_request_path('/licenses/activate'));
		$this->assertTrue($this->has_request_path('/licenses/validate'));
		$this->assertTrue($this->has_request_path('/licenses/deactivate'));
	}

	/**
	 * @param mixed                    $preempt
	 * @param array<string,mixed>      $request_args
	 * @param string                   $url
	 * @return mixed
	 */
	public function mock_license_server($preempt, array $request_args, string $url) {
		$path = (string) wp_parse_url($url, PHP_URL_PATH);
		$this->request_paths[] = $path;

		if (str_ends_with($path, '/licenses/activate')) {
			return $this->build_http_response(
				array(
					'status'        => 'active',
					'plan'          => 'pro',
					'expires_at'    => time() + DAY_IN_SECONDS,
					'activation_id' => 'act_test_123',
					'token'         => 'token_123',
				)
			);
		}

		if (str_ends_with($path, '/licenses/validate')) {
			return $this->build_http_response(
				array(
					'status'     => 'active',
					'plan'       => 'pro',
					'expires_at' => time() + DAY_IN_SECONDS,
				)
			);
		}

		if (str_ends_with($path, '/licenses/deactivate')) {
			return $this->build_http_response(
				array(
					'success' => true,
					'message' => 'ok',
				)
			);
		}

		return $preempt;
	}

	/**
	 * @param array<string,mixed> $body
	 * @return array<string,mixed>
	 */
	private function build_http_response(array $body): array {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode($body),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function has_request_path(string $suffix): bool {
		foreach ($this->request_paths as $request_path) {
			if (str_ends_with($request_path, $suffix)) {
				return true;
			}
		}

		return false;
	}
}
