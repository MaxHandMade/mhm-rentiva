<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Move the site clock so that "later today" stops meaning "unless you run the
 * suite in the evening".
 *
 * Several tests are about a booking due back later the same day. They built
 * that fixture from the current moment -- `now + 6 hours`, `now + 2 hours` --
 * and skipped themselves when the arithmetic crossed midnight. The result was
 * a test that is real in the morning and decorative at night, and in CI a coin
 * flip on the time the job happened to start. A skipped test reports success.
 *
 * The fixture was never the problem; deriving it from an uncontrolled clock
 * was. The production code under test reads the SITE timezone (`current_time`,
 * `wp_date`), and the site timezone is an option, so a test can decide what
 * hour it is and stop guessing.
 *
 * Nothing is written to the database. `update_option( 'gmt_offset', ... )`
 * would be the obvious spelling and it leaks: this suite commits the PHPUnit
 * transaction mid-test, so the write outlives the test that made it and every
 * later test doing date arithmetic silently inherits a shifted clock -- the
 * worst possible leak for a helper whose whole point is a predictable one.
 * Hooks are restored after every test; options are not.
 */
trait SiteClock
{
	/**
	 * The offset currently forced, in hours, or null when not pinned.
	 */
	private ?int $pinned_offset_hours = null;

	/**
	 * Make the site's local hour be $hour, right now.
	 *
	 * Minutes and seconds are untouched: the shift is a whole number of hours,
	 * so a fixture built as "+6 hours" from the pinned moment stays inside the
	 * same day for any $hour up to 17.
	 */
	protected function pin_site_hour( int $hour ): void
	{
		$this->release_site_clock();
		$this->pinned_offset_hours = self::offset_for_hour( $hour, (int) gmdate( 'G' ) );

		add_filter( 'pre_option_gmt_offset', array( $this, 'filter_pinned_gmt_offset' ) );
		add_filter( 'pre_option_timezone_string', array( $this, 'filter_pinned_timezone_string' ) );
	}

	/**
	 * The whole hour-independence claim, as arithmetic.
	 *
	 * Pure and static on purpose: the useful assertion is "this works whatever
	 * hour the suite starts at", and a test cannot move the real clock to check
	 * that. It can enumerate every (target, utc_hour) pair here.
	 *
	 * The residue class of (target - utc) mod 24 always has a representative
	 * inside WordPress's -12..+14 offset range, because that range is 27 hours
	 * wide. Normalising anything above +14 lands it in -9..-1.
	 */
	public static function offset_for_hour( int $target_hour, int $utc_hour ): int
	{
		$offset = ( $target_hour - $utc_hour + 24 ) % 24;

		return $offset > 14 ? $offset - 24 : $offset;
	}

	/**
	 * Put the clock back. Called automatically between tests by WordPress's
	 * hook restoration; explicit here for tests that pin more than one hour.
	 */
	protected function release_site_clock(): void
	{
		remove_filter( 'pre_option_gmt_offset', array( $this, 'filter_pinned_gmt_offset' ) );
		remove_filter( 'pre_option_timezone_string', array( $this, 'filter_pinned_timezone_string' ) );
		$this->pinned_offset_hours = null;
	}

	/**
	 * @return string|false
	 */
	public function filter_pinned_gmt_offset() {
		return null === $this->pinned_offset_hours ? false : (string) $this->pinned_offset_hours;
	}

	/**
	 * An offset only takes effect while `timezone_string` is empty -- core
	 * prefers the named zone whenever there is one.
	 *
	 * @return string|false
	 */
	public function filter_pinned_timezone_string() {
		return null === $this->pinned_offset_hours ? false : '';
	}
}
