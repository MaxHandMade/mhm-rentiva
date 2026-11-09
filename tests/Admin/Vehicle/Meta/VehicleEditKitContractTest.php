<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * The vehicle edit screen (post.php / post-new.php for mhmrentiva_vehicle)
 * styles under the ui-core kit and body.mhmui-admin (spec §Ortak katman M2,
 * PR 4 plan Task 1; pattern ManualBookingScreenKitTest).
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::enqueue_scripts
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::add_body_class
 */
final class VehicleEditKitContractTest extends WP_UnitTestCase
{
	/** @var mixed */
	private $saved_styles;

	/** @var mixed */
	private $saved_scripts;

	public function set_up(): void
	{
		parent::set_up();
		$this->saved_styles    = $GLOBALS['wp_styles'] ?? null;
		$this->saved_scripts   = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		remove_filter('admin_body_class', array( VehicleMeta::class, 'add_body_class' ));
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	public function tear_down(): void
	{
		remove_filter('admin_body_class', array( VehicleMeta::class, 'add_body_class' ));
		$GLOBALS['wp_styles']  = $this->saved_styles;
		$GLOBALS['wp_scripts'] = $this->saved_scripts;
		unset($GLOBALS['post_type'], $GLOBALS['pagenow']);
		parent::tear_down();
	}

	private function on_vehicle_screen(string $page): void
	{
		$GLOBALS['post_type'] = 'mhmrentiva_vehicle';
		$GLOBALS['pagenow']   = $page;
	}

	private function enqueue_all(string $page): void
	{
		VehicleMeta::enqueue_scripts();
		VehicleGallery::enqueue_scripts();
		BlockedDatesMetaBox::enqueue_scripts($page);
	}

	public function test_vehicle_edit_styles_depend_on_the_kit(): void
	{
		$this->on_vehicle_screen('post.php');

		$this->enqueue_all('post.php');

		$kit = AssetManager::kit_handle('admin');
		$this->assertSame('mhmuicore-admin', $kit);
		foreach (array( 'mhm-rentiva-vehicle-meta-css', 'mhm-rentiva-vehicle-gallery', 'mhm-rentiva-blocked-dates' ) as $handle) {
			$this->assertArrayHasKey($handle, wp_styles()->registered, $handle);
			$this->assertContains($kit, wp_styles()->registered[ $handle ]->deps, $handle);
		}
	}

	public function test_kit_is_enqueued_on_edit_and_new_vehicle(): void
	{
		foreach (array( 'post.php', 'post-new.php' ) as $page) {
			$GLOBALS['wp_styles'] = null;
			$this->on_vehicle_screen($page);

			VehicleMeta::enqueue_scripts();

			$this->assertTrue(wp_style_is('mhmuicore-admin', 'enqueued'), $page);
		}
	}

	public function test_body_class_adds_mhmui_admin_and_keeps_input(): void
	{
		$this->on_vehicle_screen('post.php');

		VehicleMeta::enqueue_scripts();

		$this->assertNotFalse(has_filter('admin_body_class', array( VehicleMeta::class, 'add_body_class' )));
		$classes = preg_split('/\s+/', trim(VehicleMeta::add_body_class('a')));
		$this->assertContains('mhmui-admin', $classes);
		$this->assertContains('a', $classes);
	}

	public function test_nothing_on_another_screen(): void
	{
		$GLOBALS['post_type'] = 'mhmrentiva_booking';
		$GLOBALS['pagenow']   = 'post.php';
		VehicleMeta::enqueue_scripts();
		$GLOBALS['post_type'] = 'mhmrentiva_vehicle';
		$GLOBALS['pagenow']   = 'edit.php';
		VehicleMeta::enqueue_scripts();

		$this->assertFalse(wp_style_is('mhmuicore-admin', 'enqueued'));
		$this->assertFalse(has_filter('admin_body_class', array( VehicleMeta::class, 'add_body_class' )));
	}

	public function test_vehicle_meta_css_version_is_the_plugin_version(): void
	{
		$this->on_vehicle_screen('post.php');

		VehicleMeta::enqueue_scripts();

		$this->assertSame(\MHMRENTIVA_VERSION, wp_styles()->registered['mhm-rentiva-vehicle-meta-css']->ver);
	}
}
