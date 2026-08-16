<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\SecurityHelper;
use MHMRentiva\Admin\Core\Utilities\RateLimiter;
use WP_UnitTestCase;

/**
 * Finding L1: both rate limiters counted with read-modify-write --
 * get_transient(), compare, set_transient(current + 1). Two requests that
 * read the same value both write the same increment, so N concurrent
 * requests can advance the counter by one. The limiter undercounts exactly
 * when it matters: under load. 6.0.5 routes the increment through
 * wp_cache_incr() when a persistent object cache is available, which Redis
 * and Memcached implement as a single atomic operation.
 *
 * 🔴 WHAT THESE TESTS DO NOT PROVE -- stated plainly so nobody reads more
 * into a green run than is there. They do NOT distinguish the atomic path
 * from the read-modify-write one. PHPUnit runs a single process, and with a
 * persistent cache present WordPress stores transients in that same cache
 * anyway, so in one process both mechanisms produce identical, correct
 * counts. Reverting to read-modify-write would leave these green. The
 * benefit of the change is only observable under real concurrency, which
 * this harness cannot stage.
 *
 * What they DO fence is the regression the change itself risks, which is
 * real and was worth writing down: adding an object-cache path is exactly
 * how a limiter stops limiting on the sites that do NOT have a persistent
 * cache (wp_cache_* is request-scoped there, so a counter kept only in it
 * resets on every request and nothing is ever refused). Both storage paths
 * are therefore exercised, each with its own exhaustion assertion.
 */
final class RateLimitCounterAtomicityTest extends WP_UnitTestCase
{
	private ?bool $was_using_ext_cache = null;

	public function setUp(): void
	{
		parent::setUp();
		$this->was_using_ext_cache = (bool) wp_using_ext_object_cache();
	}

	public function tearDown(): void
	{
		wp_using_ext_object_cache((bool) $this->was_using_ext_cache);
		wp_cache_flush();
		parent::tearDown();
	}

	/**
	 * The path most sites are on. A counter kept only in a request-scoped
	 * object cache would let every request through here.
	 */
	public function test_transient_fallback_still_limits_without_a_persistent_cache(): void
	{
		wp_using_ext_object_cache(false);

		for ($i = 0; $i < 3; $i++) {
			$this->assertTrue(
				SecurityHelper::check_rate_limit('fallback_probe', 3, 300, 4243),
				'The first three calls must be allowed.'
			);
		}

		$this->assertFalse(
			SecurityHelper::check_rate_limit('fallback_probe', 3, 300, 4243),
			'The fourth call must be refused; without this the fallback would not be limiting at all.'
		);
	}

	/**
	 * The path the atomic increment runs on. Same budget, same outcome.
	 */
	public function test_object_cache_path_limits_with_a_persistent_cache(): void
	{
		wp_using_ext_object_cache(true);

		for ($i = 0; $i < 3; $i++) {
			$this->assertTrue(
				SecurityHelper::check_rate_limit('atomic_probe', 3, 300, 4244),
				'The first three calls must be allowed.'
			);
		}

		$this->assertFalse(
			SecurityHelper::check_rate_limit('atomic_probe', 3, 300, 4244),
			'The fourth call must be refused on the object-cache path too.'
		);
	}

	/**
	 * Counts must be exact, not merely "at least the limit", on both paths --
	 * an off-by-one in either storage backend would refuse a request the
	 * site owner paid for or allow one they did not.
	 */
	public function test_counts_are_exact_on_both_storage_paths(): void
	{
		wp_using_ext_object_cache(true);
		SecurityHelper::check_rate_limit('exactness_probe', 10, 300, 4245);
		SecurityHelper::check_rate_limit('exactness_probe', 10, 300, 4245);
		$this->assertSame(
			2,
			SecurityHelper::get_rate_limit_count('exactness_probe', 4245),
			'Two calls on the object-cache path must read back as exactly 2.'
		);

		wp_using_ext_object_cache(false);
		SecurityHelper::check_rate_limit('exactness_probe_t', 10, 300, 4246);
		SecurityHelper::check_rate_limit('exactness_probe_t', 10, 300, 4246);
		$this->assertSame(
			2,
			SecurityHelper::get_rate_limit_count('exactness_probe_t', 4246),
			'Two calls on the transient path must read back as exactly 2.'
		);
	}

	/**
	 * The window must not slide.
	 *
	 * The object-cache path gets this for free: wp_cache_add() sets the TTL
	 * once and wp_cache_incr() does not touch it. The transient path had to
	 * be made to match -- a plain set_transient() on every hit resets the
	 * expiry, so a client that keeps knocking after being refused extends its
	 * own block indefinitely instead of serving out a fixed window. (The
	 * pre-6.0.5 code avoided this by not writing at all once the limit was
	 * hit; counting every request is the deliberate change, sliding the
	 * window with it was not.)
	 *
	 * The stored expiry is pushed to 100s from now first, so a run that
	 * completes inside one second still distinguishes "preserved" from
	 * "reset to the full 300".
	 */
	public function test_transient_window_does_not_slide_on_repeated_hits(): void
	{
		wp_using_ext_object_cache(false);

		$key = 'mhmrentiva_rate_limit_window_probe';
		SecurityHelper::increment_counter($key, 300);

		$near_expiry = time() + 100;
		update_option('_transient_timeout_' . $key, $near_expiry);

		SecurityHelper::increment_counter($key, 300);

		$this->assertLessThanOrEqual(
			$near_expiry,
			(int) get_option('_transient_timeout_' . $key),
			'A later hit must not push the expiry back out to the full window.'
		);
		$this->assertSame(2, SecurityHelper::read_counter($key), 'The hit must still be counted.');
	}

	/**
	 * Negative control: once the window has genuinely elapsed the counter
	 * must start over, or a bucket would be blocked forever.
	 */
	public function test_transient_counter_restarts_after_the_window_elapses(): void
	{
		wp_using_ext_object_cache(false);

		$key = 'mhmrentiva_rate_limit_elapsed_probe';
		SecurityHelper::increment_counter($key, 300);
		SecurityHelper::increment_counter($key, 300);
		$this->assertSame(2, SecurityHelper::read_counter($key));

		// Expire the window the way time passing would.
		update_option('_transient_timeout_' . $key, time() - 1);

		$this->assertSame(
			1,
			SecurityHelper::increment_counter($key, 300),
			'A hit after the window elapsed must start a fresh count.'
		);
	}

	/**
	 * The second limiter, whose three windows share one increment path.
	 */
	public function test_rate_limiter_counts_each_hit_once_across_its_windows(): void
	{
		wp_using_ext_object_cache(true);

		RateLimiter::check('atomicity-identifier', 'general');
		RateLimiter::check('atomicity-identifier', 'general');

		$stats = RateLimiter::getStats('atomicity-identifier', 'general');

		$this->assertSame(2, (int) $stats['minute']['current'], 'Two calls must be counted twice in the minute window.');
		$this->assertSame(2, (int) $stats['hour']['current'], 'Two calls must be counted twice in the hour window.');
		$this->assertSame(2, (int) $stats['day']['current'], 'Two calls must be counted twice in the day window.');
	}

	/**
	 * Negative control for the limiter above: it must still refuse once the
	 * budget is spent, so the counting assertions cannot be satisfied by a
	 * limiter that counts but never limits.
	 */
	public function test_rate_limiter_still_refuses_once_the_minute_budget_is_spent(): void
	{
		wp_using_ext_object_cache(true);

		$budget  = (int) RateLimiter::getStats('exhaustion-identifier', 'general')['minute']['limit'];
		$allowed = 0;

		for ($i = 0; $i < $budget + 1; $i++) {
			if (RateLimiter::check('exhaustion-identifier', 'general')) {
				++$allowed;
			}
		}

		$this->assertSame($budget, $allowed, 'Exactly the minute budget must be allowed through, no more.');
	}
}
