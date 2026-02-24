<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use WP_UnitTestCase;

final class LicenseManagerResolverTest extends WP_UnitTestCase {

	public function test_resolver_method_exists_and_returns_string(): void {
		$manager = LicenseManager::instance();

		$this->assertTrue(
			method_exists($manager, 'resolve_api_base_url'),
			'LicenseManager must expose resolve_api_base_url() for deterministic endpoint routing.'
		);

		$method = new \ReflectionMethod($manager, 'resolve_api_base_url');
		$method->setAccessible(true);
		$value = $method->invoke($manager);

		$this->assertIsString($value);
		$this->assertNotSame('', $value);
	}
}

