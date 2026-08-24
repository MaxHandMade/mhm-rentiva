<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Addons\AddonScreen;
use WP_UnitTestCase;

/**
 * Where WordPress is allowed to put an admin notice on our screens.
 *
 * HOW THE NOTICE ACTUALLY MOVES
 * -----------------------------
 * `admin_notices` fires before the screen opens its `.wrap`, so every notice is
 * printed ABOVE the heading. WordPress then relocates it on DOM ready:
 * common.js looks for `.wp-header-end` inside `.wrap` and falls back to the
 * first h1/h2 when there is none. The move is what the operator sees as a jump
 * -- the notice appears above the title and hops below it a moment later, and
 * on a heavy screen that moment is long enough to read.
 *
 * WHY THE MARKER COUNT IS THE THING BEING TESTED
 * ----------------------------------------------
 * Zero markers is not "no notice" -- it is the fallback, which lands the notice
 * wherever the first heading happens to be, so the position drifts from screen
 * to screen. That is exactly the inconsistency this test exists to stop.
 *
 * TWO markers is worse than none: WP's relocator runs `.before()` against every
 * match, which CLONES the notice. AdminHelperTrait carries $skip_wp_header_end
 * for precisely the screens where core already printed its own marker.
 *
 * So the rule is: exactly one, and after the heading.
 */
final class AdminNoticePlacementTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function renderAddonScreen(): string {
		ob_start();
		try {
			AddonScreen::render_page();
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	public function test_the_addons_screen_emits_exactly_one_header_end_marker(): void {
		$html = $this->renderAddonScreen();

		$this->assertSame(
			1,
			substr_count( $html, 'wp-header-end' ),
			'Zero leaves WordPress guessing from the first heading; two make it clone the notice.'
		);
	}

	public function test_the_marker_comes_after_the_heading(): void {
		$html = $this->renderAddonScreen();

		$heading = strpos( $html, '<h1' );
		$marker  = strpos( $html, 'wp-header-end' );

		$this->assertNotFalse( $heading, 'The screen must have a heading to place the notice against.' );
		$this->assertNotFalse( $marker );
		$this->assertGreaterThan(
			$heading,
			$marker,
			'A marker before the heading puts the notice back above the title -- the bug this fixes.'
		);
	}

	/**
	 * The marker only helps if the notice is inside the container WordPress
	 * searches. `.wrap` is what common.js scopes its lookup to.
	 */
	public function test_the_screen_opens_a_wrap_for_the_relocation_to_target(): void {
		$this->assertStringContainsString( 'class="wrap"', $this->renderAddonScreen() );
	}
}
