<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Utilities;

use MHMRentiva\Admin\PostTypes\Utilities\ClientUtilities;
use WP_UnitTestCase;

/**
 * Third member of the spoofable-client-IP class. The independent review found
 * two resolvers (SecurityHelper, RateLimiter); sweeping the class turned up
 * this one, which walked seven client-supplied proxy headers ahead of
 * REMOTE_ADDR exactly like the other two.
 *
 * Its consequence is different, and worth stating precisely: nothing here
 * feeds a rate limiter, so there is no throttle to bypass. Its only callers
 * are AdvancedLogger's `ip_address` field and its security-event context --
 * that is, the audit trail. A caller could therefore write any IP it liked
 * into this plugin's own security log, which is the record a site owner would
 * read after an incident. Log poisoning, not access control.
 *
 * Fixed by delegating to the single house resolver rather than by repeating
 * the trusted-proxy logic a third time.
 */
final class ClientUtilitiesTest extends WP_UnitTestCase
{
	/** @var array<string, mixed> */
	private array $server_backup = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->server_backup = $_SERVER;
	}

	public function tearDown(): void
	{
		$_SERVER = $this->server_backup;
		remove_all_filters('mhmrentiva_trusted_proxy_ip_headers');
		parent::tearDown();
	}

	/**
	 * RED before the fix: returns the spoofed CF/X-Forwarded-For value.
	 */
	public function test_get_client_ip_ignores_client_supplied_proxy_headers_by_default(): void
	{
		$_SERVER['REMOTE_ADDR']             = '203.0.113.10';
		$_SERVER['HTTP_X_FORWARDED_FOR']    = '198.51.100.99';
		$_SERVER['HTTP_CF_CONNECTING_IP']   = '198.51.100.98';
		$_SERVER['HTTP_CLIENT_IP']          = '198.51.100.97';

		$this->assertSame(
			'203.0.113.10',
			ClientUtilities::get_client_ip(),
			'The audit trail must record the actual TCP peer, not a header the caller chose.'
		);
	}

	/**
	 * Negative control: a site that really does sit behind a trusted proxy
	 * must still be able to opt the header back in, through the same filter
	 * the rest of the plugin uses -- so the assertion above is not satisfied
	 * by a resolver that ignores headers unconditionally.
	 */
	public function test_get_client_ip_honours_the_trusted_proxy_filter(): void
	{
		$_SERVER['REMOTE_ADDR']           = '203.0.113.10';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.98';

		add_filter('mhmrentiva_trusted_proxy_ip_headers', static function () {
			return array( 'HTTP_CF_CONNECTING_IP' );
		});

		$this->assertSame(
			'198.51.100.98',
			ClientUtilities::get_client_ip(),
			'An explicitly trusted proxy header must still be honoured.'
		);
	}

	/**
	 * Carried over from the Faz 2 sweep, which closed this same defect
	 * independently: an opted-in X-Forwarded-For may carry a chain, and only
	 * the first hop is the client. Written against the delegate's contract --
	 * SecurityHelper splits on the comma and validates the leading entry.
	 */
	public function test_get_client_ip_takes_the_first_hop_of_a_forwarded_chain(): void
	{
		$_SERVER['REMOTE_ADDR']          = '203.0.113.10';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99, 192.168.1.1';

		add_filter('mhmrentiva_trusted_proxy_ip_headers', static function () {
			return array( 'HTTP_X_FORWARDED_FOR' );
		});

		$this->assertSame(
			'198.51.100.99',
			ClientUtilities::get_client_ip(),
			'A trusted forwarding chain must resolve to its first hop, not the whole header.'
		);
	}

	/**
	 * Also from the Faz 2 sweep. Note the contract changed with the delegation:
	 * this method used to answer 'unknown' with no REMOTE_ADDR, whereas the
	 * house resolver answers '0.0.0.0'. The shipped 6.0.6 behaviour is the
	 * delegate's, and this test pins it so the difference cannot drift back
	 * unnoticed.
	 */
	public function test_get_client_ip_falls_back_to_the_house_sentinel(): void
	{
		unset($_SERVER['REMOTE_ADDR']);

		$this->assertSame(
			'0.0.0.0',
			ClientUtilities::get_client_ip(),
			'With no TCP peer the delegate returns its own sentinel, not this class\'s former one.'
		);
	}
}
