<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * PR D Task 1, fix round 1 (I1). my-account.js now loads from
 * AccountController::enqueue_assets() on account pages only. Two favorites
 * renders exist, and they need different things:
 *
 *  - templates/account/favorites.php (AccountRenderer::get_favorites_data())
 *    carries "Clear all favorites" (#clear-all-favorites) and the account
 *    wrapper my-account.js binds to, and localizes mhmRentivaVehiclesList
 *    onto my-account.js. It enqueues the script itself, so a render outside
 *    the account page still gets a working button and a registered handle.
 *  - [rentiva_my_favorites] (block, Elementor widget, shortcode) renders the
 *    vehicles-grid template: no clear button, no account wrapper, so no
 *    my-account.js on an arbitrary page (spec m5).
 *
 * Both are exercised on a plain page, where AccountController::enqueue_assets()
 * enqueues nothing.
 */
final class FavoritesAccountScriptTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$page = self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'Plain' ));
		$this->go_to((string) get_permalink($page));
		RequestSimulator::new_request();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'customer' )));
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_account_favorites_template_brings_my_account_js_on_a_plain_page(): void
	{
		\MHMRentiva\Admin\Frontend\Account\AccountController::enqueue_assets();
		$this->assertFalse(wp_script_is('mhm-rentiva-my-account', 'enqueued'), 'Premise: a plain page loads no my-account.js by itself.');

		$html = AccountRenderer::render_favorites(array( 'hide_nav' => true ));

		$this->assertStringContainsString('mhm-rentiva-account-page', $html, 'Premise: the account favorites template rendered.');
		$this->assertTrue(wp_script_is('mhm-rentiva-my-account', 'enqueued'), 'The account favorites template needs my-account.js (clear all, empty state).');

		$data = (string) wp_scripts()->get_data('mhm-rentiva-my-account', 'data');
		$this->assertStringContainsString('var mhmRentivaVehiclesList', $data, 'mhmRentivaVehiclesList is not localized onto my-account.js.');
		$this->assertStringContainsString('var mhmRentivaAccount', $data, 'my-account.js lost its own payload.');
	}

	public function test_favorites_shortcode_has_no_account_controls_and_no_my_account_js(): void
	{
		$html = do_shortcode('[rentiva_my_favorites]');

		$this->assertNotSame('', trim($html), 'Premise: the shortcode rendered.');
		$this->assertStringNotContainsString('clear-all-favorites', $html);
		$this->assertStringNotContainsString('mhm-rentiva-account-page', $html);
		$this->assertFalse(wp_script_is('mhm-rentiva-my-account', 'enqueued'), 'my-account.js has nothing to bind to on the favorites shortcode.');
	}
}
