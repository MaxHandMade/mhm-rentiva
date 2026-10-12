<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Slice 3B-2a Task 4 (U4): the rating form binds to a vehicle, never to the
 * page it happens to sit on, and the controller is the only place that
 * resolves the id (spec §2.5).
 */
final class RatingFormBindingTest extends WP_UnitTestCase
{
	use ResetsRenderContext {
		tear_down as private reset_context_and_tear_down;
	}

	private const SAMPLE_BADGE  = 'Preview: sample vehicle. On a vehicle page it shows that vehicle.';
	private const INVALID_BADGE = 'Preview: invalid vehicle. Check the vehicle ID in the widget settings.';

	public function set_up(): void
	{
		parent::set_up();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; a preview cannot be entered.');
		RenderContext::reset();
		PreviewSamples::reset();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tear_down(): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode(false);
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		unset($GLOBALS['post']);
		wp_set_current_user(0);
		$this->reset_context_and_tear_down();
	}

	private function set_preview(bool $on): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode($on);
	}

	private function render(string $atts = ''): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_vehicle_rating_form' . $atts . ']');
	}

	private function on_page(int $post_id): void
	{
		$GLOBALS['post'] = get_post($post_id);
		setup_postdata($GLOBALS['post']);
	}

	public function test_page_id_never_bound(): void
	{
		ShortcodeFixtures::vehicle();
		$page = (int) self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish' ));
		$this->on_page($page);

		$html = $this->render();
		$this->assertStringNotContainsString('data-vehicle-id="' . $page . '"', $html);
		$this->assertStringNotContainsString('name="vehicle_id" value="' . $page . '"', $html);
		$this->assertStringContainsString('rv-rating-form-error', $html, 'Front: a helpful empty state, not an empty string.');

		$this->set_preview(true);
		$html = $this->render();
		$this->assertStringNotContainsString('data-vehicle-id="' . $page . '"', $html);
	}

	public function test_explicit_invalid_id_rejected(): void
	{
		$sample = ShortcodeFixtures::vehicle();
		$draft  = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'draft' ));
		$locked = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_password' => 'secret' ));
		$page   = (int) self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish' ));

		foreach (array( 999999, $draft, $locked, $page ) as $bad) {
			$front = $this->render(' vehicle_id="' . $bad . '"');
			$this->assertStringContainsString('rv-rating-form-error', $front, "front, id $bad");
			$this->assertStringNotContainsString('data-vehicle-id', $front, "front, id $bad");
			$this->assertStringNotContainsString('name="vehicle_id"', $front, "front, id $bad");
		}

		$this->set_preview(true);
		foreach (array( 999999, $draft, $locked, $page ) as $bad) {
			$html = $this->render(' vehicle_id="' . $bad . '"');
			$this->assertStringContainsString(self::INVALID_BADGE, $html, "preview, id $bad");
			$this->assertStringNotContainsString(self::SAMPLE_BADGE, $html, "preview, id $bad");
			$this->assertStringContainsString('rv-rating-form-error', $html, "preview, id $bad");
			$this->assertStringNotContainsString('data-vehicle-id="' . $sample . '"', $html, "preview, id $bad must not fall to the sample");
		}
	}

	public function test_vehicle_page_binds_vehicle(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$this->on_page($vehicle);

		$html = $this->render();
		$this->assertStringContainsString('data-vehicle-id="' . $vehicle . '"', $html);
		$this->assertStringNotContainsString('rv-rating-form-error', $html);
		$this->assertStringNotContainsString('mhm-rv-preview-badge', $html);

		// An explicit id beats the page.
		$other = ShortcodeFixtures::vehicle();
		$html  = $this->render(' vehicle_id="' . $other . '"');
		$this->assertStringContainsString('data-vehicle-id="' . $other . '"', $html);
	}

	public function test_preview_without_context_uses_sample(): void
	{
		$first = ShortcodeFixtures::vehicle();
		ShortcodeFixtures::vehicle();
		$this->set_preview(true);

		$html = $this->render();
		$this->assertSame($first, PreviewSamples::vehicle_id());
		$this->assertStringContainsString('data-vehicle-id="' . $first . '"', $html);
		$this->assertStringContainsString(self::SAMPLE_BADGE, $html);
		$this->assertStringContainsString('data-rv-render="preview"', $html);

		// On a vehicle page the preview shows that vehicle, without the sample badge.
		$second = ShortcodeFixtures::vehicle();
		$this->on_page($second);
		$html = $this->render();
		$this->assertStringContainsString('data-vehicle-id="' . $second . '"', $html);
		$this->assertStringNotContainsString(self::SAMPLE_BADGE, $html);
	}

	public function test_template_does_not_resolve_its_own_page_fallback(): void
	{
		$src = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/shortcodes/vehicle-rating-form.php'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source.
		$this->assertStringNotContainsString('get_the_ID', $src);
		$this->assertStringNotContainsString('global $post', $src);
	}

	public function test_no_debug_output(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$this->on_page($vehicle);
		$html = $this->render();
		$this->assertStringContainsString('rv-rating-form', $html);
		foreach (array( 'data-debug-', 'data-render-time', '<!-- Debug:' ) as $needle) {
			$this->assertSame(0, substr_count($html, $needle), $needle);
		}
	}
}
