<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * hide_nav has an effect (spec 2026-10-10-elementor-kopru-dilim-3a §3 G1, Fable I8).
 *
 * G1 cannot render this effect here. AccountRenderer::get_navigation() returns []
 * whenever WooCommerce is loaded (AccountRenderer.php:444-450), and the test suite
 * always loads it, so the six hide_nav rows of setting-effect-data-dependent.php
 * (my-bookings and payment-history here, Pro's messages in Pro's twin of this
 * test) are pinned as "unavailable" in SettingHasEffectTest. This test closes
 * that gap link by link:
 *   1. each surface hands hide_nav to the shortcode (the block attribute and the
 *      widget switcher, read off the shortcode's _html filter);
 *   2. the renderer turns hide_nav into an empty navigation;
 *   3. the template renders a non-empty navigation and hides it when the
 *      navigation is empty.
 * Step 3 renders the account template directly. Its data comes from the renderer
 * (hide_nav on and off); only the navigation is swapped for the non-WooCommerce
 * one, with the keys AccountRenderer::get_navigation() builds.
 */
final class HideNavHasEffectTest extends WP_UnitTestCase
{
	/**
	 * Tag => array{block slug, widget, renderer data method, template}.
	 */
	private const SURFACES = array(
		'rentiva_my_bookings'     => array( 'my-bookings', 'MyBookingsWidget', 'get_bookings_data', 'account/bookings' ),
		'rentiva_payment_history' => array( 'payment-history', 'PaymentHistoryWidget', 'get_payment_history_data', 'account/payment-history' ),
	);

	public function setUp(): void
	{
		parent::setUp();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$user = ShortcodeFixtures::customer();
		ShortcodeFixtures::booking($user, ShortcodeFixtures::vehicle());
		wp_set_current_user($user);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_both_surfaces_hand_hide_nav_to_the_shortcode(): void
	{
		foreach (self::SURFACES as $tag => $s) {
			$this->assertTrue($this->received($tag, fn() => render_block($this->block($s[0], true))), "$tag block: hideNav=true does not reach the shortcode.");
			$this->assertFalse($this->received($tag, fn() => render_block($this->block($s[0], false))), "$tag block: hideNav=false arrives as on.");
			$this->assertTrue($this->received($tag, fn() => $this->widget($s[1], 'yes')), "$tag widget: hide_nav=yes does not reach the shortcode.");
			$this->assertFalse($this->received($tag, fn() => $this->widget($s[1], '')), "$tag widget: hide_nav off arrives as on.");
		}
	}

	public function test_the_renderer_empties_the_navigation(): void
	{
		$this->assertTrue(class_exists('WooCommerce'), 'This test documents the WooCommerce precondition; without WooCommerce G1 renders hide_nav itself.');
		foreach (self::SURFACES as $tag => $s) {
			$this->assertSame(array(), AccountRenderer::{$s[2]}(array( 'hide_nav' => '1' ))['data']['navigation'], "$tag: hide_nav on keeps a navigation.");
		}
	}

	public function test_the_template_shows_a_navigation_and_hides_an_empty_one(): void
	{
		foreach (self::SURFACES as $tag => $s) {
			$shown       = AccountRenderer::{$s[2]}(array( 'hide_nav' => '0' ));
			$shown['data']['navigation'] = $this->navigation();
			$hidden      = AccountRenderer::{$s[2]}(array( 'hide_nav' => '1' ));

			$with    = Templates::render($s[3], $shown, true);
			$without = Templates::render($s[3], $hidden, true);

			$this->assertStringContainsString('mhm-account-navigation', $with, "$tag: the navigation does not render.");
			$this->assertStringNotContainsString('mhm-account-navigation', $without, "$tag: hide_nav on still renders the navigation.");
			$this->assertStringContainsString('mhm-integrated', $without, "$tag: hide_nav on does not mark the page integrated.");
			$this->assertStringNotContainsString('mhm-integrated', $with, "$tag: a page with navigation is marked integrated.");
		}
	}

	/**
	 * The navigation AccountRenderer::get_navigation() builds without WooCommerce (same keys and fields).
	 *
	 * @return array<string,array{title:string,url:string,icon:string}>
	 */
	private function navigation(): array
	{
		$out = array();
		foreach (array( 'dashboard', 'bookings', 'favorites', 'payment-history', 'messages', 'edit-account', 'logout' ) as $key) {
			$out[ $key ] = array(
				'title' => ucfirst($key),
				'url'   => 'https://example.test/account/?endpoint=' . $key,
				'icon'  => '*',
			);
		}
		return $out;
	}

	/**
	 * Whether the shortcode received hide_nav as on, read off its _html filter.
	 */
	private function received(string $tag, callable $render): bool
	{
		$seen   = null;
		$filter = static function ($html, $atts) use (&$seen) {
			$seen ??= (array) $atts;
			return $html;
		};
		add_filter('mhmrentiva_shortcodes_' . $tag . '_html', $filter, 10, 2);
		RequestSimulator::new_request();
		ob_start();
		try {
			$render();
		} finally {
			ob_end_clean();
			remove_filter('mhmrentiva_shortcodes_' . $tag . '_html', $filter, 10);
		}
		$this->assertNotNull($seen, "$tag: the shortcode's _html filter never ran.");
		return in_array($seen['hide_nav'] ?? null, array( true, 1, '1', 'yes', 'true' ), true);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function block(string $slug, bool $hide): array
	{
		return array(
			'blockName'    => 'mhm-rentiva/' . $slug,
			'attrs'        => array( 'hideNav' => $hide ),
			'innerHTML'    => '',
			'innerContent' => array(),
			'innerBlocks'  => array(),
		);
	}

	private function widget(string $short, string $value): void
	{
		WidgetFactory::make('MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\' . $short, array( 'hide_nav' => $value ))->render_content();
	}
}
