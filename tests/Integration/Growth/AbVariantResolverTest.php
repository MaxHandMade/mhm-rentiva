<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Growth;

use MHMRentiva\Admin\Growth\AbVariantResolver;
use WP_UnitTestCase;

final class AbVariantResolverTest extends WP_UnitTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		AbVariantResolver::reset_request_cache_for_tests();
	}

	protected function tearDown(): void
	{
		AbVariantResolver::reset_request_cache_for_tests();
		parent::tearDown();
	}

	public function test_same_user_id_gets_same_variant_deterministically(): void
	{
		$resolver = new AbVariantResolver();

		$first = $resolver->resolve(42);
		$second = $resolver->resolve(42);

		$this->assertSame($first, $second);
		$this->assertContains($first, array('A', 'B'));
	}

	public function test_distribution_is_sane_across_first_hundred_users(): void
	{
		$resolver = new AbVariantResolver();
		$count_a = 0;
		$count_b = 0;

		for ($user_id = 1; $user_id <= 100; $user_id++) {
			$variant = $resolver->resolve($user_id);
			if ('A' === $variant) {
				$count_a++;
			} else {
				$count_b++;
			}
		}

		$this->assertGreaterThan(0, $count_a);
		$this->assertGreaterThan(0, $count_b);
		$this->assertLessThanOrEqual(40, abs($count_a - $count_b));
	}

	public function test_anonymous_user_falls_back_to_variant_a(): void
	{
		$resolver = new AbVariantResolver();

		$this->assertSame('A', $resolver->resolve(null));
	}
}
