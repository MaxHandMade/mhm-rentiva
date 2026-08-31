<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use WP_UnitTestCase;

/**
 * The probe for SiteClock.
 *
 * SiteClock exists so that tests about "later today" stop depending on what
 * time the suite happens to run. A helper that silently failed to move the
 * clock would hand those tests the same hidden dependency back, so it is
 * pinned from both ends: the hour it claims to set is the hour the plugin's
 * own time functions report, and nothing about it survives the test.
 */
final class SiteClockTest extends WP_UnitTestCase
{
	use SiteClock;

	public function test_pinning_moves_the_hour_the_plugin_reads(): void
	{
		$this->pin_site_hour( 23 );

		$this->assertSame(
			23,
			(int) wp_date( 'G' ),
			'wp_date() is what the production code under test formats with.'
		);
	}

	public function test_pinning_works_for_any_hour_of_the_day(): void
	{
		foreach ( range( 0, 23 ) as $hour ) {
			$this->release_site_clock();
			$this->pin_site_hour( $hour );

			$this->assertSame(
				$hour,
				(int) wp_date( 'G' ),
				"Hour {$hour} must be reachable whatever the UTC hour happens to be; the offset range is -12..+14 and the residue class always has a representative in it."
			);
		}
	}

	/**
	 * The two spellings must agree, and this is not pedantry.
	 *
	 * `wp_date( 'G', current_time( 'timestamp' ) )` -- which the tests this
	 * helper serves were using -- applies the offset twice: current_time()
	 * already shifted it, and wp_date() shifts the timestamp it is handed
	 * again. On a UTC test site the offset is 0 and the bug is invisible.
	 * Moving the clock is exactly what makes it visible, so it is asserted
	 * here rather than left to surface as a confusing failure elsewhere.
	 */
	public function test_the_correct_spellings_agree_once_the_clock_moves(): void
	{
		$this->pin_site_hour( 23 );

		$this->assertSame( 23, (int) wp_date( 'G' ), 'wp_date() with no timestamp is site-local now.' );
		$this->assertSame( 23, (int) current_time( 'G' ), 'current_time() with a format is site-local now.' );
		$this->assertNotSame(
			23,
			(int) wp_date( 'G', (int) current_time( 'timestamp' ) ),
			'And the double-offset spelling is wrong -- pinned here so nobody reintroduces it believing it works.'
		);
	}


	/**
	 * The claim the two rescued tests actually rest on: this works whatever
	 * hour the suite starts at. No test can move the real clock, so the
	 * arithmetic is enumerated instead -- all 576 pairs.
	 */
	public function test_every_utc_hour_can_reach_every_target_hour(): void
	{
		foreach ( range( 0, 23 ) as $utc_hour ) {
			foreach ( range( 0, 23 ) as $target ) {
				$offset = self::offset_for_hour( $target, $utc_hour );

				$this->assertGreaterThanOrEqual( -12, $offset, "utc={$utc_hour} target={$target}: below WordPress's minimum offset." );
				$this->assertLessThanOrEqual( 14, $offset, "utc={$utc_hour} target={$target}: above WordPress's maximum offset." );
				$this->assertSame(
					$target,
					( $utc_hour + $offset + 48 ) % 24,
					"utc={$utc_hour} target={$target}: the offset does not land on the requested hour."
				);
			}
		}
	}


	/**
	 * The negative control. Without this the helper could be "always 23".
	 */
	public function test_releasing_puts_the_clock_back(): void
	{
		$before = (int) wp_date( 'G' );

		$this->pin_site_hour( ( $before + 5 ) % 24 );
		$this->assertNotSame( $before, (int) wp_date( 'G' ), 'Precondition: the clock actually moved.' );

		$this->release_site_clock();

		$this->assertSame(
			$before,
			(int) wp_date( 'G' ),
			'Releasing must restore the real site hour.'
		);
	}

	/**
	 * Nothing is written. Options survive this suite's mid-test transaction
	 * commit, and a leaked gmt_offset would quietly move the clock for every
	 * later test that does date arithmetic -- the worst possible leak for a
	 * helper whose whole purpose is making time predictable.
	 */
	public function test_pinning_writes_nothing_to_the_database(): void
	{
		global $wpdb;

		$before = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'gmt_offset' ) );

		$this->pin_site_hour( 23 );

		$after = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'gmt_offset' ) );

		$this->assertSame( $before, $after, 'The helper must filter, not write.' );
	}
}
