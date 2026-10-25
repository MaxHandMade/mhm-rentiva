<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 1: the dev stack's [rentiva_rvs_probe] (mu-plugins/, not shipped)
 * renders every primitive inside the shell. Skipped where the dev stack's
 * mu-plugins are not loaded (CI).
 */
final class RvsProbeTest extends WP_UnitTestCase
{
	public function test_probe_renders_inside_shell_with_the_primitives(): void
	{
		if (! shortcode_exists('rentiva_rvs_probe')) {
			$this->markTestSkipped('The dev stack\'s mu-plugins/rentiva-rvs-probe.php is not loaded here.');
		}
		RequestSimulator::new_request();

		$html = do_shortcode('[rentiva_rvs_probe]');

		$this->assertStringStartsWith('<div class="rvs-shell" data-rvs-shortcode="rentiva_rvs_probe">', $html);
		foreach (array( 'rvs-btn--primary', 'rvs-btn--secondary', 'rvs-btn--ghost', 'rvs-input', 'rvs-check', 'rvs-tag', 'rvs-chip', 'rvs-kicker', 'rvs-error' ) as $class) {
			$this->assertStringContainsString($class, $html, $class);
		}
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-primitives', 'enqueued'));
	}
}
