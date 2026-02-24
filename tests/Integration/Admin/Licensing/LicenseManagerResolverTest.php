<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_Error;
use WP_UnitTestCase;

final class LicenseManagerResolverTest extends WP_UnitTestCase {

	public function test_resolver_method_exists_and_returns_string(): void {
		$manager = LicenseManager::instance();

		$this->assertTrue(
			method_exists($manager, 'resolve_api_base_url'),
			'LicenseManager must expose resolve_api_base_url() for deterministic endpoint routing.'
		);

		$value = $this->get_resolved_url($manager);

		$this->assertIsString($value);
		$this->assertNotSame('', $value);
	}

	public function test_resolver_prefers_explicit_base_env_when_not_empty(): void {
		putenv('MHM_RENTIVA_LICENSE_API_BASE=https://example.test/v1');
		$manager = LicenseManager::instance();
		$value   = $this->get_resolved_url($manager);

		$this->assertSame('https://example.test/v1', $value);
		putenv('MHM_RENTIVA_LICENSE_API_BASE');
	}

	public function test_resolver_ignores_empty_explicit_base_env(): void {
		putenv('MHM_RENTIVA_LICENSE_API_BASE=');
		$manager = LicenseManager::instance();
		$value   = $this->get_resolved_url($manager);

		$this->assertSame('https://api.maxhandmade.com/v1', $value);
		putenv('MHM_RENTIVA_LICENSE_API_BASE');
	}

	public function test_request_returns_config_error_for_invalid_base_url(): void {
		putenv('MHM_RENTIVA_LICENSE_API_BASE=invalid-url');
		$manager = LicenseManager::instance();

		$method = new \ReflectionMethod($manager, 'request');
		$method->setAccessible(true);

		$result = $method->invoke(
			$manager,
			'/licenses/validate',
			array(
				'license_key' => 'TEST-KEY',
				'site_hash'   => 'test-site-hash',
			)
		);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('license_config_error', $result->get_error_code());
		putenv('MHM_RENTIVA_LICENSE_API_BASE');
	}

	private function get_resolved_url(LicenseManager $manager): string {
		$method = new \ReflectionMethod($manager, 'resolve_api_base_url');
		$method->setAccessible(true);

		/**
		 * @var string $value
		 */
		$value = $method->invoke($manager);
		return $value;
	}
}
