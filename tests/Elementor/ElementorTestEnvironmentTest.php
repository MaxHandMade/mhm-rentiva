<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use WP_UnitTestCase;

/**
 * Elementor must be present, and at the version the dev site runs.
 *
 * The widget-to-shortcode bridge can only be measured by instantiating real
 * Elementor widgets, so a suite without Elementor measures an absence -- and
 * tests that guard on class_exists() would skip silently instead of failing.
 * This file is the environment's own gate: the version is pinned so CI and a
 * developer's machine answer the same question, and a dropped workflow step or
 * an empty download shows up here by name.
 */
final class ElementorTestEnvironmentTest extends WP_UnitTestCase
{
	public function test_elementor_is_loaded(): void
	{
		$this->assertTrue(
			defined( 'ELEMENTOR_VERSION' ),
			'Elementor is not loaded in the test environment; every Elementor widget test is measuring an absence.'
		);
	}

	public function test_elementor_is_the_pinned_version(): void
	{
		$this->assertTrue( defined( 'ELEMENTOR_VERSION' ), 'Elementor is not loaded.' );

		$this->assertSame(
			'4.3.2',
			ELEMENTOR_VERSION,
			'Elementor differs from the version pinned in tests/bootstrap.php and .github/workflows/testing.yml.'
		);
	}
}
