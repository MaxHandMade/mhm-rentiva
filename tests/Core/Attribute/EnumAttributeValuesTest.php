<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Attribute;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use ReflectionClass;
use WP_UnitTestCase;

final class EnumAttributeValuesTest extends WP_UnitTestCase
{
	/**
	 * default_payment was removed in slice 3A Task 6 (the booking form has no payment
	 * choice; payment type is chosen at the WooCommerce checkout).
	 */
	public function test_status_has_required_values(): void
	{
		$allowlist = (new ReflectionClass(AllowlistRegistry::class))
			->getReflectionConstant('ALLOWLIST')
			->getValue();

		$this->assertArrayHasKey('status', $allowlist);
		$this->assertArrayNotHasKey('default_payment', $allowlist);

		$statusValues = $allowlist['status']['values'] ?? array();
		$this->assertContains('active', $statusValues);
		$this->assertContains('inactive', $statusValues);
		$this->assertContains('pending', $statusValues);
		$this->assertContains('draft', $statusValues);
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

