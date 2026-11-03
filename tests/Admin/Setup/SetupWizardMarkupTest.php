<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Setup;

use MHMRentiva\Admin\Setup\SetupWizard;
use WP_UnitTestCase;

/**
 * The wizard's markup against the approved design (canvas Yon-Sihirbaz v111):
 * numbered step strip, wizard-owned badge class, no emoji, no inline styles.
 *
 * @covers \MHMRentiva\Admin\Setup\SetupWizard
 */
final class SetupWizardMarkupTest extends WP_UnitTestCase
{
	private const STEPS = array( 'system', 'pages', 'email', 'frontend', 'summary' );

	/** Emoji blocks; the approved tick (U+2713) is outside them. */
	private const EMOJI = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{26FF}\x{FE0F}]/u';

	public function set_up(): void
	{
		parent::set_up();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		delete_option('mhmrentiva_setup_frontend_saved');
		delete_option('mhmrentiva_setup_completed');
	}

	public function tear_down(): void
	{
		delete_option('mhmrentiva_setup_frontend_saved');
		delete_option('mhmrentiva_setup_completed');
		delete_transient('mhmrentiva_permalink_notice');
		parent::tear_down();
	}

	/** @param mixed ...$args */
	private function render(string $method, ...$args): string
	{
		$reflection = new \ReflectionMethod(SetupWizard::class, $method);
		$reflection->setAccessible(true);
		ob_start();
		$result = $reflection->invoke(null, ...$args);
		$out    = (string) ob_get_clean();
		return is_string($result) ? $result : $out;
	}

	/** @return array<string, string> */
	private function steps(): array
	{
		$reflection = new \ReflectionMethod(SetupWizard::class, 'get_steps');
		$reflection->setAccessible(true);
		return (array) $reflection->invoke(null);
	}

	public function test_step_nav_numbers_each_step(): void
	{
		update_option('mhmrentiva_setup_frontend_saved', '1');

		$html = $this->render('render_step_navigation', $this->steps(), 'summary');

		$this->assertSame(5, substr_count($html, '<li'));
		$this->assertSame(5, substr_count($html, 'mhm-setup-steps__num'));
		$this->assertStringContainsString('aria-current="step"', $html);
		// Step 4 is saved and not current: a tick, with its state for screen readers.
		$this->assertMatchesRegularExpression('/class="completed"[^>]*><span class="mhm-setup-steps__num"[^>]*>✓<span class="screen-reader-text">/u', $html);
	}

	public function test_status_badge_uses_wizard_class(): void
	{
		$html = $this->render('format_status_badge', 'ok');

		$this->assertStringContainsString('mhm-setup-badge--ok', $html);
		$this->assertStringNotContainsString('mhm-status', $html);
	}

	public function test_wizard_markup_has_no_emoji(): void
	{
		foreach (self::STEPS as $step) {
			$this->assertDoesNotMatchRegularExpression(self::EMOJI, $this->render('render_step_' . $step), $step);
		}

		set_transient('mhmrentiva_permalink_notice', '1', 60);
		update_option('permalink_structure', '');
		set_current_screen('mhm-rentiva_page_mhm-rentiva-setup');
		ob_start();
		SetupWizard::show_permalink_notice();
		$this->assertDoesNotMatchRegularExpression(self::EMOJI, (string) ob_get_clean(), 'permalink notice');
	}

	public function test_step_bodies_have_no_inline_style(): void
	{
		foreach (self::STEPS as $step) {
			$html = $this->render('render_step_' . $step);
			// The shared Colors field carries a dynamic swatch style; it is not wizard markup.
			$html = (string) preg_replace('#<div class="mhm-rentiva-accent".*</div>\s*<p class="description">#s', '', $html);
			$this->assertStringNotContainsString('style="', $html, $step);
		}
	}
}
