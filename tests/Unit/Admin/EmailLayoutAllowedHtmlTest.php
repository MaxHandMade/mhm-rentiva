<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin;

use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

/**
 * The layout filters the template body before printing it. The filter used to
 * be a short hand-written list with no `div`, `small` or `h4`, and no `class`
 * or `style` on links. Every shipped e-mail template uses those, so buttons
 * went out as plain links and boxes lost their frames. The layout now uses
 * WordPress's post list, which keeps that markup and still drops scripts,
 * event handlers and script URLs.
 */
final class EmailLayoutAllowedHtmlTest extends WP_UnitTestCase {

	private function layout( string $inner ): string {
		return Templates::wrapWithLayout( array(), 'Test subject', $inner );
	}

	public function test_a_button_link_keeps_its_class_and_style(): void {
		$html = $this->layout( '<a class="cta-button" href="https://example.com/a" style="display: inline-block; padding: 12px 22px; background: #2271b1; color: #fff;">Go</a>' );

		$this->assertStringContainsString( 'class="cta-button"', $html );
		$this->assertStringContainsString( 'padding: 12px 22px', $html );
		$this->assertStringContainsString( 'background: #2271b1', $html );
	}

	public function test_boxes_and_small_print_survive(): void {
		$html = $this->layout( '<div class="info-box" style="border: 1px solid #e5e7eb; padding: 16px;"><h4 style="margin: 0;">Title</h4><small>Note</small><hr></div>' );

		$this->assertStringContainsString( '<div class="info-box" style="border: 1px solid #e5e7eb;padding: 16px"', str_replace( '; ', ';', $html ) );
		$this->assertStringContainsString( '<h4', $html );
		$this->assertStringContainsString( '<small>Note</small>', $html );
		$this->assertStringContainsString( '<hr', $html );
	}

	public function test_scripts_handlers_and_script_urls_are_still_dropped(): void {
		$html = $this->layout( '<p onclick="steal()">Hi</p><script>steal()</script><a href="javascript:steal()">x</a><div style="background: url(javascript:steal())">y</div>' );

		$body = substr( $html, (int) strpos( $html, '<div class="content">' ) );

		$this->assertStringNotContainsString( 'onclick', $body );
		$this->assertStringNotContainsString( '<script', $body );
		$this->assertStringNotContainsString( 'javascript:', $body );
	}

	public function test_an_add_on_can_extend_the_list(): void {
		$extend = static function ( array $allowed ): array {
			$allowed['center'] = array();
			return $allowed;
		};
		add_filter( 'mhmrentiva_email_layout_allowed_html', $extend );

		$html = $this->layout( '<center>Middle</center>' );

		remove_filter( 'mhmrentiva_email_layout_allowed_html', $extend );

		$this->assertStringContainsString( '<center>Middle</center>', $html );
	}
}
