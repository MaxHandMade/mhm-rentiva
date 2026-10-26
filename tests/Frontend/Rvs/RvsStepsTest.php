<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsSteps;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR C: the journey's three steps (dates and extras -> details and
 * payment -> confirmation), the partial both layouts print, and the form
 * primitives' assets.
 */
final class RvsStepsTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		RequestSimulator::new_request();
		parent::tearDown();
	}

	private static function render(int $current, bool $compact = false): string
	{
		return (string) Templates::render(
			'partials/rvs/steps',
			array(
				'items'   => RvsSteps::items($current),
				'compact' => $compact,
			),
			true
		);
	}

	public function test_steps_mark_current_and_done(): void
	{
		$items = RvsSteps::items(2);

		$this->assertSame(array( 1, 2, 3 ), array_column($items, 'num'));
		$this->assertSame(array( 'done', 'current', 'todo' ), array_column($items, 'state'));
		$this->assertSame(array( 'Dates and extras', 'Details and payment', 'Confirmation' ), array_column($items, 'label'));

		$html = self::render(2);
		preg_match_all('/<li\b[^>]*>/', $html, $li);
		$this->assertCount(3, $li[0]);
		$this->assertStringNotContainsString('aria-current', $li[0][0]);
		$this->assertStringContainsString('aria-current="step"', $li[0][1]);
		$this->assertStringNotContainsString('aria-current', $li[0][2]);
		$this->assertSame(1, substr_count($html, 'aria-current'));
	}

	public function test_out_of_range_step_is_clamped(): void
	{
		$this->assertSame(array( 'current', 'todo', 'todo' ), array_column(RvsSteps::items(0), 'state'));
		$this->assertSame(array( 'done', 'done', 'current' ), array_column(RvsSteps::items(9), 'state'));
	}

	public function test_done_is_told_in_text_not_only_colour(): void
	{
		$html = self::render(3);

		$this->assertSame(2, substr_count($html, 'Step 1 · Done') + substr_count($html, 'Step 2 · Done'));
		$this->assertStringContainsString('Step 3', $html);
		$this->assertStringNotContainsString('Step 3 · Done', $html);
		$this->assertMatchesRegularExpression('/<ol class="rvs-steps" aria-label="[^"]+">/', $html);
	}

	public function test_compact_text_and_progressbar(): void
	{
		$html = self::render(1, true);

		$this->assertMatchesRegularExpression('/<ol class="rvs-steps rvs-steps--compact" aria-label="[^"]+">/', $html);
		$this->assertStringContainsString('1 / 3 · Dates and extras', $html);
		$this->assertStringContainsString('Next: Details and payment', $html);
		$this->assertMatchesRegularExpression('/<div class="rvs-steps__bar" role="progressbar" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1" aria-valuetext="1 \/ 3 · Dates and extras" aria-label="Booking progress">/', $html);

		// The last step has nothing after it.
		$last = self::render(3, true);
		$this->assertStringContainsString('3 / 3 · Confirmation', $last);
		$this->assertStringNotContainsString('Next:', $last);
		$this->assertStringContainsString('aria-valuenow="3"', $last);
	}

	public function test_partial_prints_nothing_without_items(): void
	{
		$html = (string) Templates::render('partials/rvs/steps', array( 'items' => array() ), true);

		$this->assertSame('', trim($html));
	}

	public function test_forms_style_and_stepper_script_are_registered(): void
	{
		RequestSimulator::new_request();
		AssetManager::register_core_styles();

		$this->assertSame(array( 'mhm-rentiva-rvs-primitives' ), wp_styles()->registered['mhm-rentiva-rvs-forms']->deps);
		$this->assertSame(array(), wp_scripts()->registered['mhm-rentiva-rvs-stepper']->deps);
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-forms', 'enqueued'));

		foreach (
			array(
				(string) wp_styles()->registered['mhm-rentiva-rvs-forms']->src,
				(string) wp_scripts()->registered['mhm-rentiva-rvs-stepper']->src,
			) as $src
		) {
			$this->assertFileExists(MHMRENTIVA_PLUGIN_DIR . str_replace(MHMRENTIVA_PLUGIN_URL, '', $src));
		}

		AssetManager::enqueue_rvs_forms();
		wp_styles()->all_deps(wp_styles()->queue);
		$this->assertContains('mhm-rentiva-rvs-tokens', wp_styles()->to_do);
		$this->assertContains('mhm-rentiva-rvs-forms', wp_styles()->to_do);
		$this->assertTrue(wp_script_is('mhm-rentiva-rvs-stepper', 'enqueued'));
	}

	public function test_forms_css_meets_the_interactive_minimums(): void
	{
		$css = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/css/rvs/rvs-forms.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped asset.
		$css = (string) preg_replace('!/\*.*?\*/!s', '', $css);

		$this->assertMatchesRegularExpression('/\.rvs-shell \.rvs-stepper button \{[^}]*min-width: 44px;[^}]*min-height: 44px;/', $css);
		$this->assertMatchesRegularExpression('/\.rvs-shell \.rvs-option \{[^}]*min-height: 44px;/', $css);
		// Done and current differ by the top border's weight, not by colour alone.
		$this->assertMatchesRegularExpression('/\.rvs-steps__item\.is-current \{[^}]*border-top-width: 4px;/', $css);
		// Every selector sits under the shell (tokens live there).
		preg_match_all('/([^{};]+)\{/', $css, $m);
		$this->assertGreaterThan(10, count($m[1]));
		foreach ($m[1] as $selectors) {
			if (str_starts_with(trim($selectors), '@')) {
				continue;
			}
			foreach (explode(',', $selectors) as $selector) {
				$selector = trim($selector);
				if ('' === $selector) {
					continue;
				}
				$this->assertStringStartsWith('.rvs-shell ', $selector, $selector);
			}
		}
		// Colours come from tokens.
		$this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b/i', $css);
	}
}
