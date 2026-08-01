<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * WP.org T7 round, Görev 10 review follow-up.
 *
 * VehicleColumns::availability_filter() renders four list-screen dropdowns on
 * edit.php?post_type=vehicle. Three of them -- location, lifecycle and owner --
 * read their current value from a `$request` array that is never assigned
 * anywhere in the class. `isset()` on an undefined variable raises nothing in
 * PHP 8, so the bug is silent: the dropdowns render, submit and filter, but the
 * active filter never appears selected. Reloading a filtered URL shows
 * "All locations" / "All lifecycle states" / "All owners" every time.
 *
 * It survived because PHPStan -- the only tool that catches an undefined
 * variable -- had been aborting on a stale ignoreErrors path since the task
 * that deleted SettingsTester.php, so the level-5 job analysed nothing at all.
 *
 * All three keys were already registered in VehicleColumns::PUBLIC_QUERY_VARS,
 * so the fix is the get_query_var() readers the same method already uses for
 * `mhmrentiva_available`. That also keeps the read out of $_GET, so it adds no
 * NonceVerification finding.
 */
final class VehicleFilterSelectedValueTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// The readers under test go through get_query_var(), which needs the keys
		// on the public whitelist. This is the class's own registration path, not
		// a stand-in for it -- register_query_vars() is what the plugin hooks to
		// the `query_vars` filter, and the mutation proof in the task report shows
		// every assertion below still goes red against the pre-fix readers with
		// this wiring in place.
		foreach ( VehicleColumns::register_query_vars( array() ) as $var ) {
			$GLOBALS['wp']->add_query_var( $var );
		}
	}

	/**
	 * Renders the filter row with the given query vars set.
	 */
	private function render_with( array $query_vars ): string {
		foreach ( $query_vars as $key => $value ) {
			set_query_var( $key, $value );
		}

		ob_start();
		VehicleColumns::availability_filter( 'vehicle' );

		return (string) ob_get_clean();
	}

	public function test_lifecycle_filter_marks_the_active_value_selected(): void {
		$html = $this->render_with( array( 'mhmrentiva_lifecycle_filter' => 'paused' ) );

		$this->assertStringContainsString(
			'<option value="paused" selected=\'selected\'>',
			$html,
			'The active lifecycle filter must render as the selected option.'
		);
	}

	public function test_owner_filter_marks_the_active_value_selected(): void {
		$html = $this->render_with( array( 'mhmrentiva_owner_filter' => 'vendor' ) );

		$this->assertStringContainsString(
			'<option value="vendor" selected=\'selected\'>',
			$html,
			'The active owner filter must render as the selected option.'
		);
	}

	public function test_no_option_is_selected_when_no_filter_is_active(): void {
		$html = $this->render_with( array() );

		$this->assertStringContainsString( '<option value="vendor">', $html );
		$this->assertStringContainsString( '<option value="paused">', $html );
	}

	/**
	 * The readers must not reach into $_GET: that would reintroduce the
	 * NonceVerification.Recommended shape this round is eliminating.
	 */
	public function test_the_readers_ignore_a_raw_get_parameter(): void {
		$_GET['mhmrentiva_lifecycle_filter'] = 'expired';

		try {
			$this->assertStringNotContainsString(
				'<option value="expired" selected=\'selected\'>',
				$this->render_with( array() ),
				'The dropdowns must read registered query vars, not $_GET directly.'
			);
		} finally {
			unset( $_GET['mhmrentiva_lifecycle_filter'] );
		}
	}
}
