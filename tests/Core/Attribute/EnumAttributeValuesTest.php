<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Attribute;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use ReflectionClass;
use WP_UnitTestCase;

final class EnumAttributeValuesTest extends WP_UnitTestCase
{
	public function test_status_default_payment_and_service_type_have_required_values(): void
	{
		$allowlist = (new ReflectionClass(AllowlistRegistry::class))
			->getReflectionConstant('ALLOWLIST')
			->getValue();

		$this->assertArrayHasKey('status', $allowlist);
		$this->assertArrayHasKey('default_payment', $allowlist);
		$this->assertArrayHasKey('service_type', $allowlist);

		$statusValues = $allowlist['status']['values'] ?? array();
		$this->assertContains('active', $statusValues);
		$this->assertContains('inactive', $statusValues);
		$this->assertContains('pending', $statusValues);
		$this->assertContains('draft', $statusValues);

		$paymentValues = $allowlist['default_payment']['values'] ?? array();
		$this->assertContains('cash', $paymentValues);
		$this->assertContains('credit_card', $paymentValues);
		$this->assertContains('bank_transfer', $paymentValues);
		$this->assertContains('none', $paymentValues);

		$serviceTypeValues = $allowlist['service_type']['values'] ?? array();
		$this->assertContains('rental', $serviceTypeValues);
		$this->assertContains('transfer', $serviceTypeValues);
		$this->assertContains('booking', $serviceTypeValues);
		$this->assertContains('maintenance', $serviceTypeValues);
	}
}

