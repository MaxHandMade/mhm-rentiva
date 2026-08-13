<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Utilities;

use MHMRentiva\Admin\PostTypes\Utilities\ClientUtilities;
use WP_UnitTestCase;

/**
 * ClientUtilities::get_client_ip() feeds AdvancedLogger's 'security' and
 * general log categories -- a forensic/audit field, not a security decision
 * (nothing in this plugin bans, throttles, or blocks on it). It used to walk
 * X-Forwarded-For/Client-IP/... ahead of REMOTE_ADDR regardless, which meant
 * any visitor could choose the IP this plugin's own security log recorded
 * for them: worthless, or actively misleading, as a forensic record.
 * REMOTE_ADDR is now the only value trusted by default, matching the house
 * pattern in SecurityHelper::get_client_ip(); a site behind a real proxy can
 * opt specific headers back in via the shared
 * `mhmrentiva_trusted_proxy_ip_headers` filter.
 *
 * @covers \MHMRentiva\Admin\PostTypes\Utilities\ClientUtilities::get_client_ip
 */
final class ClientUtilitiesTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();

		$_SERVER = array(
			'REQUEST_URI'    => '/',
			'REQUEST_METHOD' => 'GET',
		);
	}

	public function tearDown(): void
	{
		$_SERVER = array(
			'REQUEST_URI'    => '/',
			'REQUEST_METHOD' => 'GET',
		);

		parent::tearDown();
	}

	/** @test */
	public function it_gets_ip_from_remote_addr(): void
	{
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		$this->assertSame('192.168.1.100', ClientUtilities::get_client_ip());
	}

	/** @test */
	public function it_ignores_http_client_ip_by_default_and_uses_remote_addr(): void
	{
		$_SERVER['HTTP_CLIENT_IP'] = '203.0.113.1';
		$_SERVER['REMOTE_ADDR']    = '192.168.1.100';

		$this->assertSame('192.168.1.100', ClientUtilities::get_client_ip());
	}

	/** @test */
	public function it_ignores_x_forwarded_for_by_default_and_uses_remote_addr(): void
	{
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.1';
		$_SERVER['REMOTE_ADDR']          = '192.168.1.100';

		$this->assertSame('192.168.1.100', ClientUtilities::get_client_ip());
	}

	/** @test */
	public function it_falls_back_to_unknown_when_nothing_is_available(): void
	{
		unset($_SERVER['REMOTE_ADDR']);

		$this->assertSame('unknown', ClientUtilities::get_client_ip());
	}

	/**
	 * @test
	 *
	 * Negative control: proves headers are not unconditionally dead code --
	 * a site that explicitly opts a header in via the trusted-proxy filter
	 * still gets it honored, same as SecurityHelper::get_client_ip().
	 */
	public function it_trusts_a_header_only_when_opted_in_via_filter(): void
	{
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.1';
		$_SERVER['REMOTE_ADDR']          = '192.168.1.100';

		$filter = static function () {
			return array( 'HTTP_X_FORWARDED_FOR' );
		};
		add_filter('mhmrentiva_trusted_proxy_ip_headers', $filter);

		try {
			$this->assertSame('203.0.113.1', ClientUtilities::get_client_ip());
		} finally {
			remove_filter('mhmrentiva_trusted_proxy_ip_headers', $filter);
		}
	}
}
