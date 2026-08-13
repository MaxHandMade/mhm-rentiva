<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\RateLimiter;
use MHMRentiva\Admin\REST\Settings\RESTSettings;
use WP_UnitTestCase;

/**
 * RateLimiter::getClientIP() used to walk HTTP_CF_CONNECTING_IP / HTTP_CLIENT_IP /
 * X-Forwarded-For / ... ahead of REMOTE_ADDR -- every one of those is an ordinary
 * request header a direct caller controls, so the SHA-256 bucket key built from it
 * (see check(), middleware()) could be reset on every request simply by sending a
 * new header value. The abuse/scrape throttle behind
 * Availability::permission_check() never actually engaged. getClientIP() now
 * delegates to SecurityHelper::get_client_ip() (REMOTE_ADDR by default, opt-in
 * trusted-proxy filter) -- the same house pattern already covered end-to-end in
 * SecurityHelperTest.
 *
 * check() also has a second, independent defect class covered here: when the
 * "Enable API Rate Limiting" admin setting is off, it returns true unconditionally.
 * That is intended admin behavior (a documented kill-switch), not a fail-open-on-
 * error path -- but it must not be confused with "the limiter is broken" and it
 * must not silently mask a limiter that no longer limits when the setting IS on.
 * Both directions are asserted below.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\RateLimiter::getClientIP
 * @covers \MHMRentiva\Admin\Core\Utilities\RateLimiter::check
 */
final class RateLimiterTest extends WP_UnitTestCase
{
	private const ACTION = 'rl_sweep_test_action';

	public function setUp(): void
	{
		parent::setUp();

		$_SERVER = array(
			'REQUEST_URI'    => '/',
			'REQUEST_METHOD' => 'GET',
		);

		delete_option(RESTSettings::OPTION_NAME);
		RateLimiter::clear('192.168.1.100', self::ACTION);
	}

	public function tearDown(): void
	{
		delete_option(RESTSettings::OPTION_NAME);
		RateLimiter::clear('192.168.1.100', self::ACTION);

		$_SERVER = array(
			'REQUEST_URI'    => '/',
			'REQUEST_METHOD' => 'GET',
		);

		parent::tearDown();
	}

	private function configure_rate_limiting(bool $enabled, int $default_limit = 60): void
	{
		update_option(
			RESTSettings::OPTION_NAME,
			array(
				'rate_limiting' => array(
					'enabled'        => $enabled,
					'default_limit'  => $default_limit,
					'default_window' => 60,
					'strict_limit'   => 10,
					'strict_window'  => 60,
					'burst_limit'    => 100,
					'burst_window'   => 300,
				),
				'api'           => RESTSettings::get_default_settings()['api'],
			)
		);
	}

	/** @test */
	public function it_ignores_spoofed_headers_and_returns_remote_addr(): void
	{
		$_SERVER['HTTP_CLIENT_IP']       = '203.0.113.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2, 10.0.0.1';
		$_SERVER['REMOTE_ADDR']          = '192.168.1.100';

		$this->assertSame('192.168.1.100', RateLimiter::getClientIP());
	}

	/**
	 * @test
	 *
	 * End-to-end proof (mirrors Availability::permission_check(), the only
	 * production caller of check()): a caller sending the SAME REMOTE_ADDR but
	 * a DIFFERENT spoofed header on every request must land in the SAME
	 * bucket, and the bucket must still trip once its limit is reached.
	 */
	public function it_derives_the_bucket_from_remote_addr_not_a_spoofable_header(): void
	{
		$this->configure_rate_limiting(true, 3);
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		$spoofed_ips = array( '203.0.113.1', '203.0.113.2', '203.0.113.3' );
		foreach ($spoofed_ips as $spoofed_ip) {
			$_SERVER['HTTP_CLIENT_IP'] = $spoofed_ip;
			$identifier                = RateLimiter::getClientIP();
			$this->assertSame('192.168.1.100', $identifier, 'A spoofed header must not change the derived identifier.');
			$this->assertTrue(RateLimiter::check($identifier, self::ACTION), "Request behind spoofed header {$spoofed_ip} should still be within the limit.");
		}

		// A 4th request, yet another spoofed header value, must land in the
		// SAME bucket as the previous three and trip the limit.
		$_SERVER['HTTP_CLIENT_IP'] = '198.51.100.99';
		$identifier                = RateLimiter::getClientIP();
		$this->assertFalse(RateLimiter::check($identifier, self::ACTION), 'A new spoofed header value must not reset the rate limit bucket.');
	}

	/**
	 * @test
	 *
	 * The admin kill-switch: when "Enable API Rate Limiting" is off, check()
	 * must return true unconditionally, for every call, regardless of how many
	 * requests came in -- this is deliberate, not a bug on its own.
	 */
	public function it_returns_true_unconditionally_when_rate_limiting_is_disabled(): void
	{
		$this->configure_rate_limiting(false, 1);
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
		$identifier             = RateLimiter::getClientIP();

		for ($i = 0; $i < 10; $i++) {
			$this->assertTrue(RateLimiter::check($identifier, self::ACTION), 'Disabled rate limiting must never block a request.');
		}
	}

	/**
	 * @test
	 *
	 * Negative control for the test above: proves the "disabled -> always
	 * true" assertion is meaningful because the limiter genuinely limits when
	 * the setting IS on. Without this, a limiter that was permanently broken
	 * open would pass the disabled-path test too, for the wrong reason.
	 */
	public function it_still_limits_when_rate_limiting_is_enabled(): void
	{
		$this->configure_rate_limiting(true, 2);
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
		$identifier             = RateLimiter::getClientIP();

		$this->assertTrue(RateLimiter::check($identifier, self::ACTION));
		$this->assertTrue(RateLimiter::check($identifier, self::ACTION));
		$this->assertFalse(RateLimiter::check($identifier, self::ACTION), 'The 3rd request must exceed a limit of 2.');
	}
}
