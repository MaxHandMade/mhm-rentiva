<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountSurface;
use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * PR D, r3 Y1: three asset classes on My Account.
 *
 *   rvs     dashboard, bookings, booking detail, payment history, favorites:
 *           the `rvs-` account layer, none of the old stylesheets;
 *   wc      WooCommerce's own endpoints: the page frame and menu layer only;
 *   legacy  extension endpoints (the add-on's messages and vendor screens):
 *           the menu layer plus the old stylesheets.
 *
 * The endpoint cases go through a real request (go_to() on a rewritten
 * account URL), so the endpoint is resolved the way WooCommerce resolves it.
 * The extension endpoint is a stand-in slug contributed through
 * `mhmrentiva_account_endpoints`, the seam the add-on uses -- Lite's suite runs
 * without the add-on.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountSurface
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::enqueue_assets
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::enqueue_surface_styles
 */
final class AccountSurfaceAssetsTest extends WP_UnitTestCase
{
	private const EXTENSION_SLUG = 'zz-ext-messages';

	private const LEGACY_STYLES = array(
		'mhm-rentiva-my-account',
		'mhm-rentiva-booking-detail',
		'mhm-rentiva-bookings-page',
		'mhm-rentiva-vehicle-card-css',
	);

	private int $account_page = 0;

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(class_exists('WooCommerce'), 'These cases need WooCommerce.');
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_all_filters('mhmrentiva_account_endpoints');
		update_option('woocommerce_myaccount_page_id', 0);
		WooCommerceIntegration::clear_slug_cache();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	// -- classify() ---------------------------------------------------------

	public function test_lite_screens_classify_as_rvs(): void
	{
		foreach (array( null, '', 'bookings', 'view_booking', 'payment_history', 'favorites' ) as $key) {
			$this->assertSame(AccountSurface::RVS, AccountSurface::classify($key), var_export($key, true));
		}
	}

	public function test_woocommerce_endpoints_classify_as_wc(): void
	{
		foreach (array( 'orders', 'view-order', 'edit-account', 'edit-address', 'downloads', 'payment-methods', 'something-unknown' ) as $key) {
			$this->assertSame(AccountSurface::WC, AccountSurface::classify($key), $key);
		}
	}

	public function test_extension_endpoints_classify_as_legacy(): void
	{
		$this->assertSame(AccountSurface::WC, AccountSurface::classify('messages'), 'Without a contribution, messages is just an unknown endpoint.');

		add_filter('mhmrentiva_account_endpoints', static fn (array $e): array => array_merge($e, array( 'messages', 'vendor-apply', 'vendor-panel' )));

		foreach (array( 'messages', 'vendor-apply', 'vendor-panel' ) as $key) {
			$this->assertSame(AccountSurface::LEGACY, AccountSurface::classify($key), $key);
		}
		$this->assertSame(AccountSurface::WC, AccountSurface::classify('orders'));
	}

	public function test_account_shortcodes_classify_by_tag(): void
	{
		$this->assertSame(AccountSurface::RVS, AccountSurface::classify_shortcode('rentiva_my_bookings'));
		$this->assertSame(AccountSurface::RVS, AccountSurface::classify_shortcode('rentiva_payment_history'));
		$this->assertSame(AccountSurface::RVS, AccountSurface::classify_shortcode('rentiva_my_favorites'));
		// The add-on's subclasses of AbstractAccountShortcode keep the old path.
		$this->assertSame(AccountSurface::LEGACY, AccountSurface::classify_shortcode('rentiva_vendor_bookings'));
		$this->assertSame(AccountSurface::LEGACY, AccountSurface::classify_shortcode('rentiva_messages'));
	}

	// -- what each class enqueues -------------------------------------------

	public function test_rvs_endpoints_load_rvs_account_not_legacy(): void
	{
		foreach (array( null, 'bookings', 'favorites', 'payment_history' ) as $key) {
			$this->visit($key);
			$this->assertSame($key, AccountSurface::current_endpoint_key(), 'Endpoint not resolved: ' . var_export($key, true));

			AccountController::enqueue_assets();

			$label = var_export($key, true);
			$this->assertTrue(wp_style_is('mhm-rentiva-rvs-account', 'enqueued'), "$label: rvs-account not enqueued.");
			foreach (self::LEGACY_STYLES as $handle) {
				$this->assertFalse(wp_style_is($handle, 'enqueued'), "$label: legacy $handle enqueued.");
			}
			$this->assertFalse(wp_style_is(AssetManager::kit_handle('front'), 'enqueued'), "$label: kit front enqueued.");

			$todo = $this->resolved_styles();
			foreach (array( 'mhm-rentiva-rvs-tokens', 'mhm-rentiva-rvs-shell', 'mhm-rentiva-rvs-primitives', 'mhm-rentiva-rvs-data', 'mhm-rentiva-rvs-forms', 'mhm-rentiva-rvs-overlay', 'mhm-rentiva-rvs-account-nav', 'mhm-rentiva-rvs-account' ) as $handle) {
				$this->assertContains($handle, $todo, "$label: $handle missing from the resolved chain.");
			}
			$this->assertTrue(wp_script_is('mhm-rentiva-rvs-dialog', 'enqueued'), "$label: rvs-dialog.js not enqueued.");
			$this->assertTrue(wp_script_is('mhm-rentiva-my-account', 'enqueued'), "$label: my-account.js not enqueued.");

			RequestSimulator::new_request();
		}
	}

	public function test_wc_endpoint_loads_the_navigation_layer_only(): void
	{
		$this->visit('orders');
		$this->assertSame('orders', AccountSurface::current_endpoint_key());

		AccountController::enqueue_assets();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-account-nav', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-account', 'enqueued'));
		foreach (self::LEGACY_STYLES as $handle) {
			$this->assertFalse(wp_style_is($handle, 'enqueued'), "orders: legacy $handle enqueued.");
		}
		$this->assertContains('mhm-rentiva-rvs-tokens', $this->resolved_styles());
		$this->assertNotContains('mhm-rentiva-rvs-data', $this->resolved_styles());
	}

	public function test_non_rvs_endpoint_keeps_legacy_css(): void
	{
		add_filter('mhmrentiva_account_endpoints', static fn (array $e): array => array_merge($e, array( self::EXTENSION_SLUG )));

		$this->visit(self::EXTENSION_SLUG);
		$this->assertSame(self::EXTENSION_SLUG, AccountSurface::current_endpoint_key());

		AccountController::enqueue_assets();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-account-nav', 'enqueued'), 'The menu layer loads on extension screens too.');
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-account', 'enqueued'));
		foreach (self::LEGACY_STYLES as $handle) {
			$this->assertTrue(wp_style_is($handle, 'enqueued'), "extension endpoint: legacy $handle not enqueued.");
		}
	}

	public function test_my_account_js_not_on_plain_page(): void
	{
		$page = self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'Hello' ));
		$this->go_to((string) get_permalink($page));
		RequestSimulator::new_request();

		AccountController::enqueue_assets();

		$this->assertFalse(wp_script_is('mhm-rentiva-my-account', 'enqueued'), 'my-account.js loads off the account pages.');
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-account', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-account-nav', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-my-account', 'enqueued'));
	}

	public function test_each_stylesheet_enqueued_once(): void
	{
		add_filter('mhmrentiva_account_endpoints', static fn (array $e): array => array_merge($e, array( self::EXTENSION_SLUG )));

		foreach (array( null, 'bookings', self::EXTENSION_SLUG ) as $key) {
			$this->visit($key);
			AccountController::enqueue_assets();
			// The shortcode path on the same request must not add a second handle.
			$user = self::factory()->user->create(array( 'role' => 'customer' ));
			wp_set_current_user($user);
			do_shortcode('[rentiva_payment_history]');

			$by_src = array();
			foreach ($this->resolved_styles() as $handle) {
				$src = (string) ( wp_styles()->registered[ $handle ]->src ?? '' );
				if ('' === $src) {
					continue;
				}
				$file              = (string) wp_parse_url($src, PHP_URL_PATH);
				$by_src[ $file ][] = $handle;
			}

			foreach ($by_src as $file => $handles) {
				$this->assertCount(1, $handles, var_export($key, true) . ": $file is enqueued under " . implode(', ', $handles));
			}

			$my_account = array_filter(array_keys($by_src), static fn (string $f): bool => str_ends_with($f, '/my-account.css'));
			if (self::EXTENSION_SLUG === $key) {
				$this->assertCount(1, $my_account, 'The extension screen loads my-account.css once.');
			} else {
				$this->assertCount(0, $my_account, var_export($key, true) . ': my-account.css loads on an rvs screen.');
			}

			wp_set_current_user(0);
			RequestSimulator::new_request();
		}
	}

	public function test_lite_account_shortcode_loads_rvs_and_extension_shortcode_keeps_legacy(): void
	{
		$user = self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($user);

		do_shortcode('[rentiva_my_bookings]');
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-account', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-my-account', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-bookings-page', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-my-account', 'enqueued'));

		RequestSimulator::new_request();

		// The extension's shortcodes inherit AbstractAccountShortcode; the base
		// keeps their old stylesheets. Exercised through a stand-in subclass.
		StandInExtensionAccountShortcode::enqueue_for_test();
		$this->assertTrue(wp_style_is('mhm-rentiva-my-account', 'enqueued'));
		$this->assertTrue(wp_style_is('mhm-rentiva-vehicle-card-css', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-account', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-my-account', 'enqueued'));
	}

	/**
	 * Measured in the browser (2026-10-27): the account page loaded my-account.css
	 * a second time under the account blocks' own handles. Those blocks render
	 * their shortcodes, which load the rvs layer themselves.
	 */
	public function test_account_blocks_register_no_old_stylesheet(): void
	{
		$method = new \ReflectionMethod(\MHMRentiva\Blocks\BlockRegistry::class, 'get_block_config');
		$method->setAccessible(true);
		$blocks = (array) $method->invoke(null);
		foreach (array( 'my-bookings', 'my-favorites', 'payment-history' ) as $slug) {
			$this->assertSame(array(), (array) $blocks[ $slug ]['css'], "$slug block still names a stylesheet.");
			$this->assertFalse(wp_style_is('mhm-' . str_replace('_', '-', $blocks[ $slug ]['tag']), 'registered'), "$slug block style handle registered.");
		}
	}

	// -- helpers -------------------------------------------------------------

	/**
	 * Visit the account page, on the given Lite endpoint key or raw endpoint
	 * slug (`null` = the dashboard).
	 */
	private function visit(?string $key): void
	{
		$this->set_permalink_structure('/%postname%/');

		if (0 === $this->account_page) {
			$this->account_page = self::factory()->post->create(
				array(
					'post_type'    => 'page',
					'post_title'   => 'Zz Account',
					'post_name'    => 'zz-account',
					'post_status'  => 'publish',
					'post_content' => '[woocommerce_my_account]',
				)
			);
		}
		update_option('woocommerce_myaccount_page_id', $this->account_page);

		WooCommerceIntegration::clear_slug_cache();
		WooCommerceIntegration::add_endpoints();
		WC()->query->add_endpoints();
		flush_rewrite_rules(false);

		$url = (string) get_permalink($this->account_page);
		if (null !== $key) {
			$map  = WooCommerceIntegration::get_rentiva_endpoints_map();
			$slug = isset($map[ $key ]) ? WooCommerceIntegration::get_endpoint_slug($key) : $key;
			$url  = trailingslashit($url) . $slug . '/';
		}

		$this->go_to($url);
		RequestSimulator::new_request();
	}

	/**
	 * Every style handle the queue resolves to, dependencies included.
	 *
	 * @return array<int, string>
	 */
	private function resolved_styles(): array
	{
		$styles        = wp_styles();
		$styles->to_do = array();
		$styles->all_deps($styles->queue);
		return array_values($styles->to_do);
	}
}

/**
 * Stand-in for an extension's account shortcode (the add-on's
 * VendorBookings / AccountMessages extend the same base).
 */
final class StandInExtensionAccountShortcode extends \MHMRentiva\Admin\Frontend\Shortcodes\Account\AbstractAccountShortcode
{
	protected static function get_shortcode_tag(): string
	{
		return 'zz_ext_account_screen';
	}

	protected static function get_template_path(): string
	{
		return 'account/bookings';
	}

	protected static function get_default_attributes(): array
	{
		return array();
	}

	protected static function prepare_template_data(array $atts): array
	{
		return array();
	}

	public static function enqueue_for_test(): void
	{
		static::enqueue_assets();
	}
}
