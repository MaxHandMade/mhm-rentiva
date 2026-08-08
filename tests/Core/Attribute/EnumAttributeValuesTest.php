<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Attribute;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use ReflectionClass;
use WP_UnitTestCase;

final class EnumAttributeValuesTest extends WP_UnitTestCase
{
	public function test_status_and_default_payment_have_required_values(): void
	{
		$allowlist = (new ReflectionClass(AllowlistRegistry::class))
			->getReflectionConstant('ALLOWLIST')
			->getValue();

		$this->assertArrayHasKey('status', $allowlist);
		$this->assertArrayHasKey('default_payment', $allowlist);

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
	}

	/**
	 * The paid-surface carve removed these attributes; nothing in the shipped
	 * tree consumes them any more, so their return would mean paid surface
	 * leaking back into the allowlist.
	 */
	public function test_carved_paid_attributes_stay_removed(): void
	{
		$allowlist = (new ReflectionClass(AllowlistRegistry::class))
			->getReflectionConstant('ALLOWLIST')
			->getValue();

		$this->assertArrayNotHasKey('service_type', $allowlist);
		$this->assertArrayNotHasKey('default_tab', $allowlist);
		$this->assertArrayNotHasKey('show_transfer_tab', $allowlist);
	}
}

