<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 1: the `rvs-` shell. A shortcode flagged $rvs_shell renders inside
 * `.rvs-shell`; only the outermost flagged render is wrapped; the wrap survives
 * the HTML cache and reaches the html filter; the styles load on a cache hit.
 */
final class RvsShellTest extends WP_UnitTestCase
{
	private const OPEN = '<div class="rvs-shell" data-rvs-shortcode="%s">';

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		RvsOuterShortcode::$nest    = false;
		RvsOuterShortcode::$renders = 0;
		RvsPlainShortcode::$nest    = false;
	}

	public function tearDown(): void
	{
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_flagged_shortcode_is_wrapped(): void
	{
		$html = RvsOuterShortcode::render();

		$this->assertStringStartsWith(sprintf(self::OPEN, 'rvs_test_outer'), $html);
		$this->assertStringEndsWith('</div>', $html);
		$this->assertStringContainsString('<p>outer</p>', $html);
	}

	public function test_unflagged_shortcode_is_not_wrapped(): void
	{
		$this->assertSame('<p>plain</p>', RvsPlainShortcode::render());
	}

	public function test_nested_flagged_shortcode_is_wrapped_once(): void
	{
		RvsOuterShortcode::$nest = true;

		$html = RvsOuterShortcode::render();

		$this->assertSame(1, substr_count($html, 'class="rvs-shell"'));
		$this->assertStringContainsString('<p>inner</p>', $html);
	}

	public function test_flagged_inside_unflagged_is_wrapped(): void
	{
		RvsPlainShortcode::$nest = true;

		$html = RvsPlainShortcode::render();

		$this->assertStringStartsWith('<p>plain</p>', $html);
		$this->assertStringContainsString(sprintf(self::OPEN, 'rvs_test_inner') . '<p>inner</p></div>', $html);
	}

	public function test_cache_key_distinguishes_wrapped_and_nested(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');
		RvsOuterShortcode::$nest = true;

		RvsOuterShortcode::render(); // caches the inner render bare (nested)
		$alone = RvsInnerShortcode::render();

		$this->assertStringStartsWith(sprintf(self::OPEN, 'rvs_test_inner'), $alone);
	}

	public function test_wrapped_html_is_served_from_cache_with_shell(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');

		$first  = RvsOuterShortcode::render();
		$second = RvsOuterShortcode::render();

		$this->assertSame($first, $second);
		$this->assertSame(1, RvsOuterShortcode::$renders, 'The second render must come from the cache.');
	}

	public function test_rvs_styles_enqueued_on_cache_hit(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');
		RvsOuterShortcode::render();

		RequestSimulator::new_request();
		AbstractShortcode::reset_enqueued_assets_for_tests();
		RvsOuterShortcode::render();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-primitives', 'enqueued'));
		$this->assertSame(1, RvsOuterShortcode::$renders, 'The second render came from the cache.');
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-fonts', 'registered'));
	}

	public function test_unflagged_shortcode_does_not_enqueue_rvs_styles(): void
	{
		RvsPlainShortcode::render();

		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-primitives', 'enqueued'));
	}

	public function test_html_filter_receives_shell_root(): void
	{
		$seen = '';
		add_filter(
			'mhmrentiva_shortcodes_rvs_test_outer_html',
			static function ($html) use (&$seen) {
				$seen = $html;
				return $html;
			}
		);

		RvsOuterShortcode::render();

		$this->assertStringStartsWith(sprintf(self::OPEN, 'rvs_test_outer'), $seen);
	}

	public function test_preview_badge_stays_inside_shell(): void
	{
		if (! class_exists(\Elementor\Plugin::class)) {
			$this->markTestSkipped('Elementor is absent; the preview context cannot be entered.');
		}
		RvsOuterShortcode::$badge = 'Sample';
		\Elementor\Plugin::$instance->editor->set_edit_mode(true);
		RenderContext::reset();

		try {
			$html = RvsOuterShortcode::render();
		} finally {
			\Elementor\Plugin::$instance->editor->set_edit_mode(false);
			RenderContext::reset();
			RvsOuterShortcode::$badge = '';
		}

		$shell = strpos($html, 'class="rvs-shell"');
		$badge = strpos($html, 'Sample');
		$this->assertNotFalse($shell, $html);
		$this->assertNotFalse($badge, 'Sanity: the preview badge must be rendered: ' . $html);
		$this->assertGreaterThan($shell, $badge, 'The badge sits inside the shell.');
	}
}

final class RvsOuterShortcode extends AbstractShortcode
{
	protected static bool $rvs_shell = true;

	public static bool $nest = false;

	public static int $renders = 0;

	public static string $badge = '';

	protected static function get_shortcode_tag(): string
	{
		return 'rvs_test_outer';
	}

	protected static function get_default_attributes(): array
	{
		return array();
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/none';
	}

	protected static function prepare_template_data(array $atts): array
	{
		$data = array( 'atts' => $atts );
		if ('' !== self::$badge) {
			$data[ self::PREVIEW_BADGE_KEY ] = self::$badge;
		}
		return $data;
	}

	protected static function render_template(array $template_data): string
	{
		++self::$renders;
		$inner = self::$nest ? RvsInnerShortcode::render() : '';
		$html  = '<p>outer</p>' . $inner;
		$badge = (string) ( $template_data[ self::PREVIEW_BADGE_KEY ] ?? '' );
		return '' !== $badge ? RenderContext::badge($badge) . $html : $html;
	}
}

final class RvsInnerShortcode extends AbstractShortcode
{
	protected static bool $rvs_shell = true;

	protected static function get_shortcode_tag(): string
	{
		return 'rvs_test_inner';
	}

	protected static function get_default_attributes(): array
	{
		return array();
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/none';
	}

	protected static function prepare_template_data(array $atts): array
	{
		return array( 'atts' => $atts );
	}

	protected static function render_template(array $template_data): string
	{
		return '<p>inner</p>';
	}
}

final class RvsPlainShortcode extends AbstractShortcode
{
	public static bool $nest = false;

	protected static function get_shortcode_tag(): string
	{
		return 'rvs_test_plain';
	}

	protected static function get_default_attributes(): array
	{
		return array();
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/none';
	}

	protected static function prepare_template_data(array $atts): array
	{
		return array( 'atts' => $atts );
	}

	protected static function render_template(array $template_data): string
	{
		return '<p>plain</p>' . ( self::$nest ? RvsInnerShortcode::render() : '' );
	}
}
