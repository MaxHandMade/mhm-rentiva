<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * VehiclesGrid overrides is_caching_enabled(); its decision must still go through
 * the shared `mhmrentiva_shortcode_html_cache_enabled` seam. Before the fix the
 * override ignored the filter, so with WP_DEBUG off a guest's second identical
 * render was a transient hit that skipped the `_html` filter.
 */
final class VehiclesGridCacheSeamTest extends WP_UnitTestCase
{
	private int $fired = 0;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		ShortcodeFixtures::vehicle();
		add_filter('mhmrentiva_shortcodes_rentiva_vehicles_grid_html', array( $this, 'count_render' ));
	}

	public function tearDown(): void
	{
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @param string $html Shortcode markup.
	 */
	public function count_render(string $html): string
	{
		++$this->fired;
		return $html;
	}

	private function render_twice(): void
	{
		do_shortcode('[rentiva_vehicles_grid limit="3"]');
		RequestSimulator::new_request();
		do_shortcode('[rentiva_vehicles_grid limit="3"]');
	}

	public function test_seam_off_renders_every_time(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');

		$this->render_twice();

		$this->assertSame(2, $this->fired, 'The seam said "no cache" but the grid served a cached render.');
	}

	public function test_seam_on_serves_the_cache(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');

		$this->render_twice();

		$this->assertSame(1, $this->fired, 'The seam said "cache" but the grid rendered twice.');
	}

	public function test_seam_receives_the_grid_tag(): void
	{
		$tags = array();
		add_filter(
			'mhmrentiva_shortcode_html_cache_enabled',
			static function ($enabled, $tag) use (&$tags) {
				$tags[] = $tag;
				return false;
			},
			10,
			2
		);

		do_shortcode('[rentiva_vehicles_grid]');

		$this->assertContains('rentiva_vehicles_grid', $tags);
	}
}
