<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use WP_UnitTestCase;

/**
 * The gallery script is localized once, with the nonce its AJAX handlers
 * verify, and only on the vehicle edit screens (PR 4 plan Task 5, Codex r2).
 * Before: AssetManager localized the same object a second time with another
 * nonce action, and VehicleGallery loaded on every vehicle screen, the list
 * included.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::enqueue_scripts
 */
final class GalleryAssetsTest extends WP_UnitTestCase
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
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	public function tear_down(): void
	{
		$GLOBALS['wp_styles']  = $this->saved_styles;
		$GLOBALS['wp_scripts'] = $this->saved_scripts;
		unset($GLOBALS['post_type'], $GLOBALS['pagenow']);
		set_current_screen('front');
		parent::tear_down();
	}

	private function on(string $page, string $screen): void
	{
		$GLOBALS['post_type'] = 'mhmrentiva_vehicle';
		$GLOBALS['pagenow']   = $page;
		set_current_screen($screen);
	}

	/** @return array<string, mixed> */
	private function localized(): array
	{
		$data = (string) wp_scripts()->get_data('mhm-rentiva-vehicle-gallery', 'data');
		$this->assertSame(1, substr_count($data, 'var mhmVehicleGallery'), 'one localize of mhmVehicleGallery');
		preg_match('/var mhmVehicleGallery = (\{.*?\});/s', $data, $m);
		return (array) json_decode($m[1] ?? '{}', true);
	}

	public function test_gallery_script_is_localized_once_with_the_nonce_its_handlers_verify(): void
	{
		$this->on('post.php', 'mhmrentiva_vehicle');

		AssetManager::enqueue_admin_assets('post.php');
		VehicleGallery::enqueue_scripts();

		$payload = $this->localized();
		$this->assertNotFalse(wp_verify_nonce((string) ( $payload['nonce'] ?? '' ), 'mhmrentiva_vehicle_gallery_nonce'));
		foreach (array( 'addImageError', 'addImages', 'confirmRemove', 'maxImages', 'removeImage', 'removeImageError', 'selectImages', 'uploadError' ) as $key) {
			$this->assertArrayHasKey($key, (array) $payload['strings'], 'vehicle-gallery.js reads strings.' . $key);
		}
	}

	public function test_nothing_on_the_vehicle_list(): void
	{
		$this->on('edit.php', 'edit-mhmrentiva_vehicle');

		VehicleGallery::enqueue_scripts();

		$this->assertFalse(wp_script_is('mhm-rentiva-vehicle-gallery', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-vehicle-gallery', 'enqueued'));
	}

	public function test_loads_on_new_vehicle_too(): void
	{
		$this->on('post-new.php', 'mhmrentiva_vehicle');

		VehicleGallery::enqueue_scripts();

		$this->assertTrue(wp_script_is('mhm-rentiva-vehicle-gallery', 'enqueued'));
	}
}
