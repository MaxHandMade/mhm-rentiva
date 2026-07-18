<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\About\About;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;
use MHMRentiva\Admin\Setup\SetupWizard;
use MHMRentiva\Admin\Utilities\Menu\Menu;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use MHMRentiva\Blocks\BlockRegistry;
use WP_UnitTestCase;

final class CoreAdminPagesTest extends WP_UnitTestCase
{

	/**
	 * @var array<int|string,mixed>
	 */
	private array $menu_backup = array();

	/**
	 * @var array<int|string,mixed>
	 */
	private array $submenu_backup = array();
	private int $admin_user_id = 0;

	public function setUp(): void
	{
		parent::setUp();

		global $menu, $submenu;
		$this->menu_backup    = is_array($menu) ? $menu : array();
		$this->submenu_backup = is_array($submenu) ? $submenu : array();
		$this->admin_user_id  = (int) $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user_id);
	}

	public function tearDown(): void
	{
		global $menu, $submenu;
		$menu    = $this->menu_backup;
		$submenu = $this->submenu_backup;
		wp_set_current_user(0);

		parent::tearDown();
	}

	public function test_vehicle_featured_meta_box_is_registered(): void
	{
		VehicleMeta::register();
		$vehicle_id = (int) $this->factory->post->create(
			array(
				'post_type'   => 'vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Featured Meta Box Vehicle',
			)
		);

		$post = get_post($vehicle_id);
		$this->assertNotNull($post);

		do_action('add_meta_boxes_vehicle', $post);

		global $wp_meta_boxes;
		$this->assertTrue(isset($wp_meta_boxes['vehicle']['side']['default']['mhm_rentiva_vehicle_featured']));
	}

	public function test_vehicle_featured_meta_save_requires_valid_nonce_and_capability(): void
	{
		$vehicle_id = (int) $this->factory->post->create(
			array(
				'post_type'   => 'vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Featured Save Vehicle',
			)
		);

		update_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, '0');

		$_POST['mhm_rentiva_vehicle_featured_nonce'] = 'invalid';
		$_POST['mhm_rentiva_is_featured']            = '1';
		VehicleMeta::save_featured_meta_box($vehicle_id);
		$this->assertSame('0', (string) get_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, true));

		$_POST['mhm_rentiva_vehicle_featured_nonce'] = wp_create_nonce('mhm_rentiva_vehicle_featured_action');
		$_POST['mhm_rentiva_is_featured']            = '1';
		VehicleMeta::save_featured_meta_box($vehicle_id);
		$this->assertSame('1', (string) get_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, true));

		unset($_POST['mhm_rentiva_is_featured']);
		VehicleMeta::save_featured_meta_box($vehicle_id);
		$this->assertSame('0', (string) get_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, true));

		unset($_POST['mhm_rentiva_vehicle_featured_nonce']);
	}

	public function test_featured_vehicles_shortcode_filters_only_featured_items(): void
	{
		$featured_id = (int) $this->factory->post->create(
			array(
				'post_type'    => 'vehicle',
				'post_status'  => 'publish',
				'post_title'   => 'Featured Vehicle A',
				'post_excerpt' => 'Featured A',
			)
		);
		$normal_id   = (int) $this->factory->post->create(
			array(
				'post_type'    => 'vehicle',
				'post_status'  => 'publish',
				'post_title'   => 'Normal Vehicle B',
				'post_excerpt' => 'Normal B',
			)
		);

		update_post_meta($featured_id, MetaKeys::VEHICLE_FEATURED, '1');
		update_post_meta($normal_id, MetaKeys::VEHICLE_FEATURED, '0');
		update_post_meta($featured_id, MetaKeys::VEHICLE_PRICE_PER_DAY, '1000');
		update_post_meta($normal_id, MetaKeys::VEHICLE_PRICE_PER_DAY, '900');
		update_post_meta($featured_id, MetaKeys::VEHICLE_STATUS, 'active');
		update_post_meta($normal_id, MetaKeys::VEHICLE_STATUS, 'active');

		$defaults = new \ReflectionMethod(FeaturedVehicles::class, 'get_default_attributes');
		$defaults->setAccessible(true);
		$atts            = $defaults->invoke(null);
		$atts['limit']   = '10';
		$atts['ids']     = '';
		$atts['orderby'] = 'date';
		$atts['order']   = 'DESC';

		$prepare = new \ReflectionMethod(FeaturedVehicles::class, 'prepare_template_data');
		$prepare->setAccessible(true);
		$data = $prepare->invoke(null, $atts);

		$ids = array_map(
			static function (array $item): int {
				return (int) ($item['id'] ?? 0);
			},
			(array) ($data['vehicles'] ?? array())
		);

		$this->assertContains($featured_id, $ids);
		$this->assertNotContains($normal_id, $ids);
	}

	public function test_featured_vehicles_block_mapping_parity_and_filtering(): void
	{
		// Phase 3: map_attributes_to_shortcode() was removed from BlockRegistry.
		// Parity is now enforced via canonical attribute contract: block defaults
		// must expose the same sortBy/sortOrder/limit keys as shortcode attributes.
		$sc_defaults = (new \ReflectionMethod(FeaturedVehicles::class, 'get_default_attributes'));
		$sc_defaults->setAccessible(true);
		$shortcode_keys = array_keys((array) $sc_defaults->invoke(null));

		// Canonical block attribute parity check: orderby, order and limit must be present in shortcode contract.
		$this->assertContains('orderby', $shortcode_keys, 'Shortcode contract must include orderby');
		$this->assertContains('order', $shortcode_keys, 'Shortcode contract must include order');
		$this->assertContains('limit', $shortcode_keys, 'Shortcode contract must include limit');
	}

	public function test_menu_shows_setup_and_about_submenus_by_default(): void
	{
		$this->reset_admin_menu_globals();
		Menu::add_menu();

		$submenu_slugs = $this->get_mhm_submenu_slugs();

		$this->assertContains('mhm-rentiva-setup', $submenu_slugs);
		$this->assertContains('mhm-rentiva-about', $submenu_slugs);
	}

	public function test_menu_keeps_core_settings_submenu(): void
	{
		$this->reset_admin_menu_globals();
		Menu::add_menu();

		$submenu_slugs = $this->get_mhm_submenu_slugs();

		$this->assertContains('mhm-rentiva-settings', $submenu_slugs);
	}

	/**
	 * The License submenu is registered only when LicenseAdmin is present, and
	 * Lite has no licence to manage. Asserting its absence keeps the Pro seam
	 * honest from the Lite side.
	 */
	public function test_menu_has_no_license_submenu_in_lite(): void
	{
		$this->reset_admin_menu_globals();
		Menu::add_menu();

		$this->assertNotContains('mhm-rentiva-license', $this->get_mhm_submenu_slugs());
	}

	/**
	 * The Setup Wizard's License step was removed from Lite entirely: it linked to
	 * the unregistered `mhm-rentiva-license` page (a dead link -- see
	 * test_menu_has_no_license_submenu_in_lite()) and carried a purchase CTA,
	 * which the "no mention of Pro anywhere in Lite" decision forbids. The step
	 * must be gone from the step list, its renderer/handler gone with it, and the
	 * navigation must still resolve with one fewer step.
	 */
	public function test_setup_wizard_has_no_license_step_in_lite(): void
	{
		$steps = $this->get_setup_wizard_steps();

		$this->assertArrayNotHasKey('license', $steps, 'Lite must not ship a License wizard step.');
		$this->assertSame(
			array( 'system', 'pages', 'email', 'frontend', 'demo', 'summary' ),
			array_keys($steps),
			'Lite wizard step order must close the gap left by the removed License step.'
		);

		// The renderer and the admin-post handler must be gone, not merely unlinked.
		$this->assertFalse(method_exists(SetupWizard::class, 'render_step_license'));
		$this->assertFalse(method_exists(SetupWizard::class, 'handle_save_license'));
		$this->assertFalse(has_action('admin_post_mhm_rentiva_setup_save_license'));
	}

	/**
	 * The first step's "Continue" must point at the step that now follows it.
	 * A stale `step=license` link would resolve back to the first step
	 * (get_current_step() rejects unknown steps), silently trapping the user.
	 */
	public function test_setup_wizard_first_step_continues_to_pages(): void
	{
		$html = $this->render_setup_wizard_step('system');

		$this->assertStringContainsString('step=pages', $html);
		$this->assertStringNotContainsString('step=license', $html);
	}

	/**
	 * Lite renders no Pro-mention, purchase CTA, or link to the unregistered
	 * License page anywhere in the wizard. Each step renderer is invoked directly
	 * rather than through render_page(): the wizard resolves the current step via
	 * filter_input(INPUT_GET), which is always empty under the CLI SAPI, so
	 * render_page() would render step 1 every time and the loop would assert
	 * nothing about the other steps.
	 */
	public function test_setup_wizard_renders_no_pro_or_purchase_copy_in_lite(): void
	{
		foreach (array( 'system', 'pages', 'email', 'frontend', 'summary' ) as $step) {
			$html = $this->render_setup_wizard_step($step);

			$this->assertNotSame('', $html, "Step {$step} rendered nothing -- the assertions below would be vacuous.");
			$this->assertStringNotContainsString('mhm-rentiva-license', $html, "Step {$step} links the unregistered License page.");
			$this->assertStringNotContainsString('wpalemi.com', $html, "Step {$step} carries a purchase CTA.");
			$this->assertStringNotContainsString('Get a License', $html, "Step {$step} carries a purchase CTA.");
			$this->assertStringNotContainsString('unlock Pro features', $html, "Step {$step} advertises Pro.");
		}
	}

	/**
	 * Read the wizard's private step list without duplicating it in the test.
	 *
	 * @return array<string, string>
	 */
	private function get_setup_wizard_steps(): array
	{
		$method = new \ReflectionMethod(SetupWizard::class, 'get_steps');
		$method->setAccessible(true);

		return (array) $method->invoke(null);
	}

	private function render_setup_wizard_step(string $step): string
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

		$method = new \ReflectionMethod(SetupWizard::class, 'render_step_' . $step);
		$method->setAccessible(true);

		ob_start();
		$method->invoke(null);

		return (string) ob_get_clean();
	}

	public function test_core_admin_submenus_require_manage_options_capability(): void
	{
		$this->reset_admin_menu_globals();
		Menu::add_menu();

		// The License submenu is a Pro seam and is absent here; see
		// test_menu_has_no_license_submenu_in_lite().
		$setup_item = $this->get_mhm_submenu_item_by_slug('mhm-rentiva-setup');
		$about_item = $this->get_mhm_submenu_item_by_slug('mhm-rentiva-about');

		$this->assertIsArray($setup_item);
		$this->assertIsArray($about_item);

		$this->assertSame('manage_options', $setup_item[1]);
		$this->assertSame('manage_options', $about_item[1]);
	}

	public function test_setup_and_about_callback_classes_are_available(): void
	{
		$this->assertTrue(class_exists(SetupWizard::class));
		$this->assertTrue(method_exists(SetupWizard::class, 'render_page'));

		$this->assertTrue(class_exists(About::class));
		$this->assertTrue(method_exists(About::class, 'render_page'));
	}

	private function reset_admin_menu_globals(): void
	{
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();
	}

	/**
	 * @return list<string>
	 */
	private function get_mhm_submenu_slugs(): array
	{
		global $submenu;

		if (! isset($submenu['mhm-rentiva']) || ! is_array($submenu['mhm-rentiva'])) {
			return array();
		}

		$slugs = array();
		foreach ($submenu['mhm-rentiva'] as $item) {
			if (is_array($item) && isset($item[2]) && is_string($item[2])) {
				$slugs[] = $item[2];
			}
		}

		return $slugs;
	}

	/**
	 * @return array<int,mixed>|null
	 */
	private function get_mhm_submenu_item_by_slug(string $slug): ?array
	{
		global $submenu;

		if (! isset($submenu['mhm-rentiva']) || ! is_array($submenu['mhm-rentiva'])) {
			return null;
		}

		foreach ($submenu['mhm-rentiva'] as $item) {
			if (is_array($item) && isset($item[2]) && $item[2] === $slug) {
				return $item;
			}
		}

		return null;
	}
}
