<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;
use MHMRentiva\Core\Attribute\CanonicalAttributeMapper;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Slice 3B-2a Task 7 (U5: 1, 2, 5): three render bugs.
 *
 *  1. The "similar vehicles" list on a vehicle page listed the vehicle itself.
 *  2. My bookings and payment history shared one page variable, so paging one
 *     list paged the other.
 *  5. BlockRegistry built a `style` attribute that nothing read.
 */
final class RenderBugsTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tear_down(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		set_query_var('mhmrentiva_page', '');
		set_query_var('mhmrentiva_payments_page', '');
		unset($GLOBALS['post']);
		RequestSimulator::new_request();
		parent::tear_down();
	}

	/**
	 * U5-1: five featured vehicles, one of them the page's. The similar list
	 * shows the other four (limit 12 so none is cut off by the limit).
	 */
	public function test_similar_vehicles_excludes_current(): void
	{
		$ids    = array();
		$titles = array();
		for ($i = 0; $i < 5; $i++) {
			$id = ShortcodeFixtures::vehicle();
			update_post_meta($id, MetaKeys::VEHICLE_FEATURED, '1');
			wp_update_post(array( 'ID' => $id, 'post_title' => 'RV-SIM-' . $i . '-' . uniqid() ));
			$ids[]    = $id;
			$titles[] = get_the_title($id);
		}
		$current = $ids[2];

		$GLOBALS['post'] = get_post($current);
		setup_postdata($GLOBALS['post']);
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $current . '" similar_vehicles_limit="12"]');

		$this->assertSame(1, preg_match('#<section class="rv-vd2-related">(.*?)</section>#s', $html, $m), 'The similar section is drawn.');
		$related = $m[1];
		foreach ($titles as $i => $title) {
			if ($ids[ $i ] === $current) {
				$this->assertStringNotContainsString($title, $related, 'The current vehicle is in its own similar list.');
				continue;
			}
			$this->assertStringContainsString($title, $related, 'Another featured vehicle is missing from the similar list.');
		}
	}

	/**
	 * The only featured vehicle is the page's own: the similar list would be empty,
	 * so the section (heading and all) is not drawn.
	 */
	public function test_similar_section_hidden_when_no_other_featured_vehicle(): void
	{
		$current = ShortcodeFixtures::vehicle();
		update_post_meta($current, MetaKeys::VEHICLE_FEATURED, '1');

		$GLOBALS['post'] = get_post($current);
		setup_postdata($GLOBALS['post']);
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $current . '"]');

		$this->assertNotSame('', $html, 'The vehicle page itself is drawn.');
		$this->assertStringNotContainsString('rv-vd2-related-title', $html, 'An empty similar list still draws its heading.');
		$this->assertStringNotContainsString('rv-vd2-related', $html, 'An empty similar list still draws its section.');
		$this->assertStringNotContainsString('No featured vehicles found', $html, 'The empty state leaks onto the vehicle page.');
	}

	/**
	 * With another featured vehicle the section is drawn (heading included).
	 */
	public function test_similar_section_drawn_when_another_featured_vehicle_exists(): void
	{
		$current = ShortcodeFixtures::vehicle();
		$other   = ShortcodeFixtures::vehicle();
		update_post_meta($current, MetaKeys::VEHICLE_FEATURED, '1');
		update_post_meta($other, MetaKeys::VEHICLE_FEATURED, '1');

		$GLOBALS['post'] = get_post($current);
		setup_postdata($GLOBALS['post']);
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $current . '"]');

		$this->assertStringContainsString('rv-vd2-related-title', $html);
		$this->assertStringNotContainsString('No featured vehicles found', $html);
	}

	/**
	 * The attribute must reach the query: it is a default of the shortcode and
	 * in the featured schema, or CanonicalAttributeMapper (strict) drops it.
	 */
	public function test_exclude_attribute_survives_cam(): void
	{
		$mapped = CanonicalAttributeMapper::map('rentiva_featured_vehicles', array( 'exclude' => '12, 7,abc,12' ));
		$this->assertArrayHasKey('exclude', $mapped, 'CAM dropped `exclude`.');
		$this->assertSame('12,7', $mapped['exclude'], 'A list of ints: sanitized, de-duplicated.');

		$defaults = new \ReflectionMethod(FeaturedVehicles::class, 'get_default_attributes');
		$defaults->setAccessible(true);
		$this->assertArrayHasKey('exclude', $defaults->invoke(null));

		// The shortcode honours it.
		$a = ShortcodeFixtures::vehicle();
		$b = ShortcodeFixtures::vehicle();
		foreach (array( $a, $b ) as $id) {
			update_post_meta($id, MetaKeys::VEHICLE_FEATURED, '1');
		}
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_featured_vehicles exclude="' . $a . '" layout="grid"]');
		$this->assertStringContainsString(get_the_title($b), $html);
		$this->assertStringNotContainsString(get_the_title($a), $html);
	}

	/**
	 * `exclude` must also apply when `ids` is set (WP_Query ignores
	 * post__not_in once post__in is present).
	 */
	public function test_exclude_applies_when_ids_given(): void
	{
		$a = ShortcodeFixtures::vehicle();
		$b = ShortcodeFixtures::vehicle();
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_featured_vehicles ids="' . $a . ',' . $b . '" exclude="' . $a . '" layout="grid"]');
		$this->assertStringContainsString(get_the_title($b), $html);
		$this->assertStringNotContainsString(get_the_title($a), $html);
	}

	/**
	 * U5-2: bookings and payments page separately; an old `mhmrentiva_page`
	 * link keeps paging the bookings list.
	 */
	public function test_two_paginated_lists_independent(): void
	{
		$registered = AccountController::register_query_vars(array());
		$this->assertContains('mhmrentiva_page', $registered, 'Back-compat: the old variable stays.');
		$this->assertContains('mhmrentiva_payments_page', $registered);

		$user = ShortcodeFixtures::customer();
		for ($i = 0; $i < 12; $i++) {
			$id = ShortcodeFixtures::booking($user, ShortcodeFixtures::vehicle(), 'confirmed', '100', -20 - $i);
			update_post_meta($id, '_mhmrentiva_payment_status', 'paid');
		}
		wp_set_current_user($user);

		// An old link: ?mhmrentiva_page=2 pages the bookings; payments stay on page 1.
		set_query_var('mhmrentiva_page', '2');
		$this->assertSame(2, AccountRenderer::current_page('mhmrentiva_page'));
		$this->assertSame(1, AccountRenderer::current_page('mhmrentiva_payments_page'));
		RequestSimulator::new_request();
		$this->assertStringContainsString('Page 2 / 3', do_shortcode('[rentiva_my_bookings limit="5"]'));
		RequestSimulator::new_request();
		$this->assertStringContainsString('Page 1 / 3', do_shortcode('[rentiva_payment_history limit="5"]'));

		// Payments page 3 leaves the bookings on page 2.
		set_query_var('mhmrentiva_payments_page', '3');
		$this->assertSame(3, AccountRenderer::current_page('mhmrentiva_payments_page'));
		RequestSimulator::new_request();
		$this->assertStringContainsString('Page 3 / 3', do_shortcode('[rentiva_payment_history limit="5"]'));
		RequestSimulator::new_request();
		$this->assertStringContainsString('Page 2 / 3', do_shortcode('[rentiva_my_bookings limit="5"]'));

		// Pager URLs touch only their own variable.
		$_SERVER['REQUEST_URI'] = '/account/?mhmrentiva_page=2&mhmrentiva_payments_page=3&keep=1';
		$payments               = AccountRenderer::page_url('mhmrentiva_payments_page', 2);
		$this->assertStringContainsString('mhmrentiva_payments_page=2', $payments);
		$this->assertStringContainsString('mhmrentiva_page=2', $payments, 'The other list keeps its page.');
		$this->assertStringContainsString('keep=1', $payments);
		$bookings = AccountRenderer::page_url('mhmrentiva_page', 1);
		$this->assertStringNotContainsString('mhmrentiva_page', $bookings, 'Page 1 drops the variable.');
		$this->assertStringContainsString('mhmrentiva_payments_page=3', $bookings, 'The other list keeps its page.');
	}

	/**
	 * U5-5: the `style` attribute BlockRegistry put on the attributes nothing
	 * read (the wrapper builds its own style). The wrapper is the same.
	 */
	public function test_block_registry_no_dead_style_attribute(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Blocks/BlockRegistry.php'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads source.
		$this->assertStringNotContainsString("\$attributes['style']", $source, 'The dead style branch is back.');

		RequestSimulator::new_request();
		$html = render_block(
			array(
				'blockName'    => 'mhm-rentiva/availability-calendar',
				'attrs'        => array( 'calendarHeight' => '420px' ),
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
		$this->assertMatchesRegularExpression('#^<div [^>]*style="height:420px"#', $html, 'The wrapper still carries the height.');
		$this->assertSame(1, substr_count($html, 'style="height:420px"'), 'Exactly one style on the wrapper.');
	}
}
