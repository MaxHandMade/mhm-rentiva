<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\VehicleEditLayout;
use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * The vehicle screen's boxes in the canvas order (Yon-Arac-Duzenle v144), as
 * the default of the user's saved order -- someone who dragged the boxes keeps
 * their own. Core draws 'high' before 'sorted' (template.php do_meta_boxes),
 * which is why the order comes from this default and not from rewriting the
 * buckets (PR 4 plan, spec correction 1). Extensions add their own boxes
 * through mhmrentiva_vehicle_edit_box_order; Lite names none of them.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleEditLayout
 */
final class VehicleEditLayoutTest extends WP_UnitTestCase
{
	private int $user;

	public function setUp(): void
	{
		parent::setUp();
		$this->user = self::factory()->user->create(array( 'role' => 'administrator' ));
		wp_set_current_user($this->user);
		VehicleEditLayout::register();
	}

	public function tearDown(): void
	{
		remove_all_filters('mhmrentiva_vehicle_edit_box_order');
		unset($GLOBALS['wp_meta_boxes']['mhmrentiva_vehicle']);
		parent::tearDown();
	}

	public function test_empty_preference_gets_the_canvas_order(): void
	{
		$order = get_user_option('meta-box-order_mhmrentiva_vehicle', $this->user);

		$this->assertSame('submitdiv,mhmrentiva_vehicle_featured,mhmrentiva_vehicle_categorydiv,postimagediv', $order['side'] ?? null);
		$this->assertSame('mhmrentiva_vehicle_gallery,mhmrentiva_vehicle_details,mhmrentiva_blocked_dates', $order['normal'] ?? null);
	}

	public function test_saved_preference_is_returned_unchanged(): void
	{
		$mine = array( 'side' => 'mhmrentiva_vehicle_featured,submitdiv', 'normal' => 'mhmrentiva_blocked_dates' );
		update_user_option($this->user, 'meta-box-order_mhmrentiva_vehicle', $mine, true);

		$this->assertSame($mine, get_user_option('meta-box-order_mhmrentiva_vehicle', $this->user));
	}

	public function test_extensions_can_append_to_the_default_order(): void
	{
		add_filter(
			'mhmrentiva_vehicle_edit_box_order',
			static function (array $order): array {
				$order['side'] .= ',x_box';
				return $order;
			}
		);

		$order = get_user_option('meta-box-order_mhmrentiva_vehicle', $this->user);

		$this->assertStringEndsWith(',postimagediv,x_box', (string) ( $order['side'] ?? '' ));
	}

	public function test_extension_filter_is_not_applied_to_a_saved_preference(): void
	{
		$mine = array( 'side' => 'submitdiv', 'normal' => '' );
		update_user_option($this->user, 'meta-box-order_mhmrentiva_vehicle', $mine, true);
		$called = false;
		add_filter(
			'mhmrentiva_vehicle_edit_box_order',
			static function (array $order) use (&$called): array {
				$called = true;
				return $order;
			}
		);

		$this->assertSame($mine, get_user_option('meta-box-order_mhmrentiva_vehicle', $this->user));
		$this->assertFalse($called);
	}

	public function test_gallery_and_details_register_in_the_main_column(): void
	{
		$post = self::factory()->post->create_and_get(array( 'post_type' => 'mhmrentiva_vehicle' ));
		require_once ABSPATH . 'wp-admin/includes/template.php';
		set_current_screen('mhmrentiva_vehicle');

		VehicleMeta::add_meta_boxes();
		VehicleGallery::add_meta_boxes();

		$boxes = $GLOBALS['wp_meta_boxes']['mhmrentiva_vehicle'];
		$this->assertNotEmpty($boxes['normal']['high']['mhmrentiva_vehicle_gallery'] ?? null, 'gallery: normal/high');
		$this->assertNotEmpty($boxes['normal']['default']['mhmrentiva_vehicle_details'] ?? null, 'details: normal/default');
		$this->assertEmpty($boxes['side']['high']['mhmrentiva_vehicle_gallery'] ?? null);
		$this->assertEmpty($boxes['advanced']['high']['mhmrentiva_vehicle_details'] ?? null);
		unset($post);
	}

	public function test_reorder_meta_boxes_is_gone(): void
	{
		VehicleMeta::register();

		$this->assertFalse(method_exists(VehicleMeta::class, 'reorder_meta_boxes'));
		$this->assertFalse(has_action('add_meta_boxes', array( VehicleMeta::class, 'reorder_meta_boxes' )));
	}

	public function test_lite_order_names_no_pro_box(): void
	{
		$order = VehicleEditLayout::default_order(false);

		$this->assertDoesNotMatchRegularExpression('/lifecycle|documents|transfer/', implode(',', (array) $order));
	}
}
