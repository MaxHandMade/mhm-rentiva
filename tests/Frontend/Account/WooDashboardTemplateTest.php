<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use WP_UnitTestCase;

/**
 * PR D, R3 (+ r2 S1/m5): one greeting on the account dashboard.
 *
 * WooCommerce's myaccount/dashboard.php prints its own "Hello ..." and
 * dashboard-description paragraphs above `woocommerce_account_dashboard`,
 * where the Rentiva dashboard greets the customer again. While the Rentiva
 * dashboard is hooked, WooCommerce's default file is swapped for Lite's copy,
 * which fires the same three actions in the same order and prints no
 * paragraph. A theme override is left alone.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration::locate_dashboard_template
 */
final class WooDashboardTemplateTest extends WP_UnitTestCase
{
	private const LITE_TEMPLATE = MHMRENTIVA_PLUGIN_DIR . 'templates/woocommerce/myaccount/dashboard.php';

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(class_exists('WooCommerce'), 'These cases need WooCommerce.');
		wc_clear_template_cache();
	}

	public function tearDown(): void
	{
		wc_clear_template_cache();
		parent::tearDown();
	}

	public function test_filter_is_registered(): void
	{
		$this->assertSame(10, has_filter('woocommerce_locate_template', array( WooCommerceIntegration::class, 'locate_dashboard_template' )));
	}

	public function test_dashboard_template_redirected(): void
	{
		$this->assertSame(
			wp_normalize_path(self::LITE_TEMPLATE),
			wp_normalize_path(wc_locate_template('myaccount/dashboard.php'))
		);
	}

	public function test_other_templates_not_redirected(): void
	{
		foreach (array( 'myaccount/navigation.php', 'myaccount/my-account.php', 'myaccount/orders.php' ) as $name) {
			$located = wp_normalize_path(wc_locate_template($name));
			$this->assertSame(wp_normalize_path(WC()->plugin_path() . '/templates/' . $name), $located, $name);
		}
	}

	public function test_theme_override_wins(): void
	{
		// What wc_locate_template() hands the filter when the theme has a copy.
		$theme_copy = get_stylesheet_directory() . '/woocommerce/myaccount/dashboard.php';

		$this->assertSame(
			$theme_copy,
			WooCommerceIntegration::locate_dashboard_template($theme_copy, 'myaccount/dashboard.php', 'woocommerce/', WC()->plugin_path() . '/templates/')
		);
	}

	public function test_not_redirected_without_the_rentiva_dashboard(): void
	{
		remove_action('woocommerce_account_dashboard', array( WooCommerceIntegration::class, 'render_dashboard' ));
		try {
			$this->assertSame(
				wp_normalize_path(WC()->plugin_path() . '/templates/myaccount/dashboard.php'),
				wp_normalize_path(wc_locate_template('myaccount/dashboard.php'))
			);
		} finally {
			add_action('woocommerce_account_dashboard', array( WooCommerceIntegration::class, 'render_dashboard' ));
		}
	}

	public function test_template_fires_three_hooks_without_greeting(): void
	{
		$fired = array();
		$hooks = array( 'woocommerce_account_dashboard', 'woocommerce_before_my_account', 'woocommerce_after_my_account' );

		// Located while the Rentiva dashboard is hooked (the redirect's condition).
		$template = wc_locate_template('myaccount/dashboard.php');
		$this->assertSame(wp_normalize_path(self::LITE_TEMPLATE), wp_normalize_path($template));

		// Isolate the template: record the three actions, run nothing else.
		$saved = array();
		foreach ($hooks as $hook) {
			$saved[ $hook ] = $GLOBALS['wp_filter'][ $hook ] ?? null;
			unset($GLOBALS['wp_filter'][ $hook ]);
			add_action(
				$hook,
				static function () use (&$fired, $hook): void {
					$fired[] = $hook;
				}
			);
		}

		try {
			$user = self::factory()->user->create(array( 'role' => 'customer', 'display_name' => 'Zz Customer' ));
			wp_set_current_user($user);

			ob_start();
			include $template;
			$html = (string) ob_get_clean();
		} finally {
			foreach ($hooks as $hook) {
				unset($GLOBALS['wp_filter'][ $hook ]);
				if (null !== $saved[ $hook ]) {
					$GLOBALS['wp_filter'][ $hook ] = $saved[ $hook ];
				}
			}
			wp_set_current_user(0);
		}

		$this->assertSame($hooks, $fired);
		$this->assertStringNotContainsString('<p', $html);
		$this->assertStringNotContainsString('Zz Customer', $html);
		$this->assertSame('', trim($html));
	}

	public function test_version_header_matches_core(): void
	{
		$version = static function (string $file): string {
			$head = (string) file_get_contents($file, false, null, 0, 4096); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads template headers.
			preg_match('/@version\s+(\S+)/', $head, $m);
			return $m[1] ?? '';
		};

		$core = $version(WC()->plugin_path() . '/templates/myaccount/dashboard.php');
		$this->assertNotSame('', $core);
		$this->assertSame($core, $version(self::LITE_TEMPLATE), 'WooCommerce bumped its dashboard template: compare it with Lite\'s copy and update both the copy and its @version.');
	}
}
