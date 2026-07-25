<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Helpers\Html;
use WP_UnitTestCase;

/**
 * WP.org T6: the admin screens echoed renderer output raw under
 * `// phpcs:ignore ... escaped internally`. Html::echo_markup() replaces the
 * claim with an enforced allowlist. wp_kses_post() alone cannot be used here
 * because it strips every form control, so this suite pins both halves: the
 * controls an options screen needs survive, and script/handler injection does
 * not.
 *
 * @covers \MHMRentiva\Helpers\Html::allowed_markup
 * @covers \MHMRentiva\Helpers\Html::echo_markup
 */
final class AdminHtmlAllowlistTest extends WP_UnitTestCase
{
	private function filter( string $html ): string
	{
		ob_start();
		Html::echo_markup( $html );
		return (string) ob_get_clean();
	}

	public function test_form_controls_survive(): void
	{
		$html = '<input type="text" name="a" value="v" class="regular-text" placeholder="p" />';
		$out  = $this->filter( $html );

		$this->assertStringContainsString( 'type="text"', $out );
		$this->assertStringContainsString( 'name="a"', $out );
		$this->assertStringContainsString( 'value="v"', $out );
		$this->assertStringContainsString( 'placeholder="p"', $out );
	}

	public function test_select_option_and_textarea_survive(): void
	{
		$out = $this->filter(
			'<select name="s"><option value="1" selected>One</option></select>'
			. '<textarea name="t" rows="4">body</textarea>'
		);

		$this->assertStringContainsString( '<select', $out );
		$this->assertStringContainsString( 'value="1"', $out );
		$this->assertStringContainsString( '<textarea', $out );
		$this->assertStringContainsString( 'rows="4"', $out );
	}

	public function test_button_checkbox_state_and_hidden_input_survive(): void
	{
		$out = $this->filter(
			'<input type="checkbox" name="c" checked /><input type="hidden" name="h" value="1" />'
			. '<button type="button" class="button">Go</button>'
		);

		$this->assertStringContainsString( 'checked', $out );
		$this->assertStringContainsString( 'type="hidden"', $out );
		$this->assertStringContainsString( '<button', $out );
	}

	public function test_data_and_aria_attributes_survive(): void
	{
		$out = $this->filter( '<div data-mhm-media-field="logo" aria-label="Logo">x</div>' );

		$this->assertStringContainsString( 'data-mhm-media-field="logo"', $out );
		$this->assertStringContainsString( 'aria-label="Logo"', $out );
	}

	/**
	 * Phase 1 lost the size off every icon by forgetting an SVG attribute in a
	 * kses allowlist. Sparklines and icons go through this one, so it is pinned.
	 */
	public function test_inline_svg_keeps_its_geometry(): void
	{
		$out = $this->filter(
			'<svg xmlns="http://www.w3.org/2000/svg" width="100" height="30" viewBox="0 0 100 30">'
			. '<polyline points="0,10 10,20" fill="none" stroke="#4ade80" stroke-width="2"/></svg>'
		);

		$this->assertStringContainsString( 'width="100"', $out );
		$this->assertStringContainsString( 'height="30"', $out );
		$this->assertStringContainsString( 'points="0,10 10,20"', $out );
		$this->assertStringContainsString( 'stroke="#4ade80"', $out );

		// kses lowercases attribute names, so viewBox comes back as viewbox. That
		// is harmless in an HTML document -- the HTML parser's "adjust SVG
		// attributes" step maps it back before the SVG is built -- but the
		// geometry must survive in some casing, so assert on it explicitly rather
		// than letting a silently dropped viewBox pass as it nearly did in phase 1.
		$this->assertMatchesRegularExpression( '/viewbox="0 0 100 30"/i', $out );
	}

	public function test_script_tags_are_removed(): void
	{
		$out = $this->filter( '<div>ok</div><script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringContainsString( 'ok', $out );
	}

	public function test_event_handler_attributes_are_removed(): void
	{
		$out = $this->filter( '<button onclick="alert(1)" type="button">Go</button>' );

		$this->assertStringNotContainsString( 'onclick', $out );
		$this->assertStringContainsString( '<button', $out );
	}

	public function test_javascript_urls_are_removed(): void
	{
		$out = $this->filter( '<a href="javascript:alert(1)">x</a>' );

		$this->assertStringNotContainsString( 'javascript:', $out );
	}
}
