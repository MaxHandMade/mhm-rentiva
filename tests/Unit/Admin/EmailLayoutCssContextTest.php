<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin;

use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

/**
 * Second instance of WP.org T7's named CSS-context finding, found by sweeping
 * the class rather than the finding list.
 *
 * Templates::wrapWithLayout() interpolates the email branding colour into a
 * <style> block. It used to do so through esc_attr(), which is on WPCS's
 * escaping list -- so neither our shape gate nor Plugin Check ever flagged it --
 * while being the wrong instrument: esc_attr() leaves `;` and `}` intact, so a
 * malformed setting value could close the declaration and open its own rule.
 *
 * The value is now validated with sanitize_hex_color() and falls back to the
 * shipped default, matching AssetManager::sanitize_css_declaration_value()'s
 * "drop, don't escape" contract.
 */
final class EmailLayoutCssContextTest extends WP_UnitTestCase {

	private const DEFAULT_BASE_COLOR = '#1e88e5';

	protected function tearDown(): void {
		delete_option( 'mhmrentiva_settings' );
		parent::tearDown();
	}

	private function set_base_color( string $value ): void {
		$settings = (array) get_option( 'mhmrentiva_settings', array() );
		$settings['mhmrentiva_email_base_color'] = $value;
		update_option( 'mhmrentiva_settings', $settings );
	}

	private function layout(): string {
		return Templates::wrapWithLayout( array(), 'Test subject', '<p>Body</p>' );
	}

	public function test_a_block_escaping_base_colour_never_reaches_the_style_block(): void {
		$this->set_base_color( '#fff; } body { background: url(//evil) ' );

		$html = $this->layout();

		$this->assertStringNotContainsString( 'url(', $html, 'A dropped colour must not reintroduce a CSS function.' );
		$this->assertStringNotContainsString( 'evil', $html );
		$this->assertStringContainsString(
			'background: ' . self::DEFAULT_BASE_COLOR . ';',
			$html,
			'A rejected colour must fall back to the shipped default.'
		);
	}

	public function test_a_non_hex_base_colour_falls_back_to_the_default(): void {
		$this->set_base_color( 'rebeccapurple' );

		$this->assertStringContainsString( 'background: ' . self::DEFAULT_BASE_COLOR . ';', $this->layout() );
	}

	public function test_a_legitimate_base_colour_still_reaches_the_style_block(): void {
		$this->set_base_color( '#ff0000' );

		$html = $this->layout();

		$this->assertStringContainsString( 'background: #ff0000;', $html );
		// The gradient end-stop is derived from the same value, so it must still
		// compute rather than collapse to the default alongside it.
		$this->assertStringContainsString( 'linear-gradient(135deg, #ff0000 0%, #d90000 100%)', $html );
	}
}
