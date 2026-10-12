<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * The account lists' page var, spec 2026-10-10 Elementor bridge slice 3A §2.5.
 *
 * One namespaced public query var, `mhmrentiva_page`, registered beside the
 * bookings filter vars (AccountController::PUBLIC_QUERY_VARS). Core `paged` is not
 * used: on a WooCommerce account endpoint redirect_canonical() rewrites
 * `?paged=2` to `/page/2/`, which the endpoint rule then swallows as the
 * endpoint's value -- every "Next" lands on page 1 (measured live, Fable r2-1).
 * A plugin var is left alone by redirect_canonical().
 */
final class AccountPageVarTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		set_query_var('mhmrentiva_page', '');
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_page_var_is_public(): void
	{
		$this->assertContains('mhmrentiva_page', AccountController::register_query_vars(array()));
		$this->assertContains('mhmrentiva_page', apply_filters('query_vars', array()), 'Registered on the query_vars whitelist.');

		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Zz Bookings Page',
			)
		);
		$this->go_to(add_query_arg('mhmrentiva_page', '3', get_permalink($page)));
		$this->assertSame('3', get_query_var('mhmrentiva_page'), 'WP::parse_request() fills it on a page.');
		$this->assertTrue(is_page($page), 'The page still resolves.');
		$this->assertSame(3, AccountRenderer::current_page('mhmrentiva_page'));
	}

	public function test_redirect_canonical_keeps_page_var_on_endpoint(): void
	{
		$endpoint = $this->endpoint_url();
		$url      = add_query_arg('mhmrentiva_page', '2', $endpoint);

		$this->go_to($url);
		$this->assertSame('2', get_query_var('mhmrentiva_page'));
		$this->assertTrue(is_page(), 'The endpoint resolved to the account page.');

		$redirect = redirect_canonical($url, false);
		$this->assertTrue(null === $redirect || false === $redirect || false !== strpos((string) $redirect, 'mhmrentiva_page=2'), 'redirect_canonical() dropped mhmrentiva_page: ' . var_export($redirect, true));
		$this->assertSame(2, AccountRenderer::current_page('mhmrentiva_page'));
	}

	/**
	 * Negative, documenting why core `paged` is not used: redirect_canonical()
	 * moves it into the path, where the endpoint rule takes it as its value.
	 */
	public function test_redirect_canonical_drops_core_paged_on_endpoint(): void
	{
		$url = add_query_arg('paged', '2', $this->endpoint_url());

		$this->go_to($url);
		$redirect = redirect_canonical($url, false);

		$this->assertIsString($redirect, 'redirect_canonical() left ?paged=2 alone; the reason for mhmrentiva_page would be gone.');
		$this->assertStringNotContainsString('paged=2', $redirect);
		$this->assertStringContainsString('/page/2', $redirect);
	}

	/**
	 * Review Focus 2: beyond the last page shows the last page; zero, negative or
	 * letters is page 1.
	 */
	public function test_page_var_out_of_range_clamps(): void
	{
		$user     = ShortcodeFixtures::customer();
		$bookings = array();
		foreach (array( 1, 3, 6 ) as $days_ago) {
			$id   = ShortcodeFixtures::booking($user, ShortcodeFixtures::vehicle());
			$date = gmdate('Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS);
			wp_update_post(
				array(
					'ID'            => $id,
					'post_date'     => $date,
					'post_date_gmt' => $date,
				)
			);
			$bookings[] = $id;
		}
		wp_set_current_user($user);

		$expect = array(
			// Overflows PHP_INT_MAX; WP_Query's offset wraps it back to a full page.
			'99999999999999999999' => array( $bookings[2] ),
			'999'  => array( $bookings[2] ),
			'3'    => array( $bookings[2] ),
			'2'    => array( $bookings[1] ),
			'0'    => array( $bookings[0] ),
			'-2'   => array( $bookings[0] ),
			'abc'  => array( $bookings[0] ),
			'2abc' => array( $bookings[0] ),
			''     => array( $bookings[0] ),
		);
		foreach ($expect as $var => $ids) {
			set_query_var('mhmrentiva_page', $var);
			$data = AccountRenderer::get_bookings_data(array( 'limit' => '1' ))['data'];
			$this->assertSame($ids, wp_list_pluck($data['bookings'], 'ID'), "mhmrentiva_page=$var");
			$this->assertSame(3, $data['total_pages'], "mhmrentiva_page=$var total");
			$this->assertLessThanOrEqual(3, $data['current_page'], "mhmrentiva_page=$var current page");

			$html = do_shortcode('[rentiva_my_bookings limit="1"]');
			$this->assertStringContainsString('#' . $ids[0] . '<', $html, "mhmrentiva_page=$var renders the booking");
			$this->assertStringNotContainsString('No Bookings Found', $html, "mhmrentiva_page=$var is not an empty page");
			$this->assertMatchesRegularExpression('#Page [123] / 3#', $html, "mhmrentiva_page=$var pager label");
		}

		// limit 2: (page - 1) * limit overflows to a float, and WP_Query's offset
		// wraps back to a full first page -- not empty, so only the page count
		// can catch it (T7a-1).
		foreach (array( '99999999999999999999', (string) PHP_INT_MAX, '4611686018427387905' ) as $var) {
			set_query_var('mhmrentiva_page', $var);
			$data = AccountRenderer::get_bookings_data(array( 'limit' => '2' ))['data'];
			$this->assertSame(2, $data['current_page'], "mhmrentiva_page=$var at limit 2");
			$this->assertSame(array( $bookings[2] ), wp_list_pluck($data['bookings'], 'ID'), "mhmrentiva_page=$var at limit 2 shows the last page");
			$this->assertStringContainsString('Page 2 / 2', do_shortcode('[rentiva_my_bookings limit="2"]'));
		}

		foreach (array( '0' => 1, '-2' => 1, 'abc' => 1, '' => 1, '7' => 7 ) as $var => $page) {
			set_query_var('mhmrentiva_page', $var);
			$this->assertSame($page, AccountRenderer::current_page('mhmrentiva_page'), "current_page('mhmrentiva_page') for '$var'");
		}
		set_query_var('mhmrentiva_page', array( '2' ));
		$this->assertSame(1, AccountRenderer::current_page('mhmrentiva_page'), 'An array value is page 1.');
	}

	public function test_page_url_keeps_filter_vars(): void
	{
		$_SERVER['REQUEST_URI'] = '/hesabim/rezervasyonlarim/?mhmrentiva_status_filter=pending&mhmrentiva_search_booking=12&mhmrentiva_page=3';

		$two = AccountRenderer::page_url('mhmrentiva_page', 2);
		$this->assertStringContainsString('mhmrentiva_status_filter=pending', $two);
		$this->assertStringContainsString('mhmrentiva_search_booking=12', $two);
		$this->assertStringContainsString('mhmrentiva_page=2', $two);
		$this->assertStringNotContainsString('mhmrentiva_page=3', $two);

		$one = AccountRenderer::page_url('mhmrentiva_page', 1);
		$this->assertStringNotContainsString('mhmrentiva_page', $one, 'Page 1 is the URL without the var.');
		$this->assertStringContainsString('mhmrentiva_status_filter=pending', $one);
	}

	/**
	 * A WooCommerce account page with this plugin's bookings endpoint, pretty
	 * permalinks and the rewrite rules flushed.
	 */
	private function endpoint_url(): string
	{
		$this->assertTrue(class_exists('WooCommerce'), 'The endpoint case needs WooCommerce.');
		$this->set_permalink_structure('/%postname%/');

		$account = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Zz Account',
				'post_name'   => 'zz-account',
				'post_status' => 'publish',
			)
		);
		update_option('woocommerce_myaccount_page_id', $account);
		WooCommerceIntegration::clear_slug_cache();
		WooCommerceIntegration::add_endpoints();
		flush_rewrite_rules(false);

		$slug = WooCommerceIntegration::get_endpoint_slug('bookings');
		$this->assertNotSame('', $slug);

		return trailingslashit(get_permalink($account)) . $slug . '/';
	}
}
