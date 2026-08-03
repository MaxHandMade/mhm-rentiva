<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * T8 F20: assets/js/components/vehicle-meta.js was queued under TWO separate
 * handles on the vehicle edit screen, both firing from admin_enqueue_scripts --
 *
 *  - 'mhm-rentiva-vehicle-meta'    AssetManager::enqueue_component_js('vehicle-meta')
 *                                  (config AssetManager.php :481-484, enqueue :493-499,
 *                                  called from enqueue_screen_specific_scripts() :723,
 *                                  gated on get_current_screen())
 *  - 'mhm-rentiva-vehicle-meta-js' VehicleMeta::enqueue_scripts() direct call
 *                                  (VehicleMeta.php :166-172, gated on the raw
 *                                  global $post_type/$pagenow instead)
 *
 * Same <script src> printed twice -> every jQuery handler vehicle-meta.js binds
 * ($(document).on(...) for every control in the metabox) bound twice, so every
 * click/change/submit in that metabox double-fired (double AJAX saves, double
 * prompts, etc.).
 *
 * The template (Templates/vehicle-meta.php :370-376) attaches
 * window.availableVehicle{Details,Features,Equipment} via wp_add_inline_script()
 * to 'mhm-rentiva-vehicle-meta' BY NAME by string literal (position 'before'),
 * so that handle -- not its '-js' sibling -- has to be the survivor, or the
 * template's inline data silently stops reaching the page.
 *
 * Key-by-key map of both wp_localize_script() payloads (both target the SAME
 * JS global `mhmVehicleMeta`; re-verified against the live files, not assumed
 * from the brief):
 *
 * | key                            | mhm-rentiva-vehicle-meta | -js (VehicleMeta) | read by vehicle-meta.js at |
 * |----------------------------------|:---:|:---:|---|
 * | ajaxUrl                          | yes | yes (same value)                       | :263 |
 * | nonce                            | yes | yes (same nonce action)                | :270 |
 * | strings.orderUpdated             | yes | yes | :306 |
 * | strings.orderSaveError           | yes | yes | :276 |
 * | strings.ajaxError                | yes | yes | :283 |
 * | strings.enterNewFeature          | yes | yes | :359 |
 * | strings.enterNewEquipment        | yes | yes | :379 |
 * | strings.enterNewDetail           | yes | yes | :399 |
 * | strings.confirmRemoveFeature     | yes | yes | :418 |
 * | strings.confirmRemoveEquipment   | yes | yes | :425 |
 * | strings.enterValue               | yes | yes | :520 |
 * | strings.comingSoonCustomAdd      | yes | yes (different EN wording, same key)   | :565, :572 |
 * | strings.redirectingToSettings    | yes | yes (different EN wording, same key)   | :579 |
 * | strings.remove                   | yes | NO  | :481, :500, :521 |
 * | strings.available                | yes | NO  | :615 |
 * | strings.notAvailable             | yes | NO  | :619 |
 * | strings.validFormat              | yes | NO  | :636 |
 * | strings.invalidFormat            | yes | NO  | :642 |
 * | strings.depositFormatHelp        | yes | NO  | :639 |
 * | strings.depositPlaceholder       | yes | NO  | :648 |
 * | strings.comingSoonCustomRemove   | NO (added here to close the union) | yes | never (dead key even before the fix) |
 * | deps                             | ['jquery','jquery-ui-sortable'] | identical | n/a |
 * | inline (template attachment)     | window.availableVehicle{Details,Features,Equipment} | none | n/a |
 *
 * 'mhm-rentiva-vehicle-meta' was already the richer side (7 exclusive keys, all
 * actively read by the JS, vs. the loser's 1 exclusive key which nothing reads)
 * AND the one the template feeds -- so it survives. The one key the loser
 * carried that the survivor did not ('comingSoonCustomRemove') was added to
 * AssetManager::localize_component_script()'s 'vehicle-meta' case so the
 * survivor is a strict superset of both -- nothing either handle carried
 * before is lost.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::enqueue_scripts
 * @covers \MHMRentiva\Admin\Core\AssetManager::enqueue_component_js
 * @covers \MHMRentiva\Admin\Core\AssetManager::localize_component_script
 */
final class VehicleMetaSingleEnqueueTest extends WP_UnitTestCase {

	private const SURVIVOR_HANDLE = 'mhm-rentiva-vehicle-meta';
	private const REMOVED_HANDLE  = 'mhm-rentiva-vehicle-meta-js';

	/** Union of every strings.* key either handle localized before the fix. */
	private const EXPECTED_STRING_KEYS = array(
		'orderUpdated',
		'orderSaveError',
		'ajaxError',
		'enterNewFeature',
		'enterNewEquipment',
		'enterNewDetail',
		'confirmRemoveFeature',
		'confirmRemoveEquipment',
		'enterValue',
		'remove',
		'available',
		'notAvailable',
		'validFormat',
		'invalidFormat',
		'depositFormatHelp',
		'depositPlaceholder',
		'comingSoonCustomAdd',
		'redirectingToSettings',
		'comingSoonCustomRemove',
	);

	/** @var mixed */
	private $prev_post_type;
	/** @var mixed */
	private $prev_pagenow;

	protected function setUp(): void {
		parent::setUp();

		global $post_type, $pagenow;
		$this->prev_post_type = $post_type ?? null;
		$this->prev_pagenow   = $pagenow ?? null;

		$this->reset_handles();

		// Simulate the vehicle edit screen context both enqueue sites gate on:
		// AssetManager reads get_current_screen(), VehicleMeta reads the raw
		// globals directly -- a real post.php?post={id} load sets both.
		set_current_screen( 'mhmrentiva_vehicle' );
		$post_type = 'mhmrentiva_vehicle';
		$pagenow   = 'post.php';
	}

	protected function tearDown(): void {
		global $post_type, $pagenow;
		$post_type = $this->prev_post_type;
		$pagenow   = $this->prev_pagenow;

		set_current_screen( 'front' );
		$this->reset_handles();
		parent::tearDown();
	}

	public function test_only_one_handle_points_at_vehicle_meta_js(): void {
		$this->trigger_vehicle_edit_screen_enqueue();

		$matches = array();
		foreach ( wp_scripts()->registered as $handle => $dep ) {
			// Alias/meta handles (e.g. jquery's own umbrella handle) register
			// with src === false; only compare handles that actually point at a
			// file.
			if ( is_string( $dep->src ) && str_ends_with( $dep->src, 'assets/js/components/vehicle-meta.js' ) ) {
				$matches[] = $handle;
			}
		}

		$this->assertCount(
			1,
			$matches,
			'Exactly one registered handle must point at assets/js/components/vehicle-meta.js on the vehicle edit screen (double-registration double-binds every jQuery handler in the file); found: ' . implode( ', ', $matches )
		);
		$this->assertSame(
			self::SURVIVOR_HANDLE,
			$matches[0] ?? null,
			"The surviving handle must be '" . self::SURVIVOR_HANDLE . "' -- that is the handle Templates/vehicle-meta.php:370-376 attaches window.availableVehicle* to by name; any other survivor silently kills the template's inline data."
		);
	}

	public function test_removed_handle_is_gone(): void {
		$this->trigger_vehicle_edit_screen_enqueue();

		$this->assertFalse( wp_script_is( self::REMOVED_HANDLE, 'registered' ), "'" . self::REMOVED_HANDLE . "' must no longer be registered." );
		$this->assertFalse( wp_script_is( self::REMOVED_HANDLE, 'enqueued' ), "'" . self::REMOVED_HANDLE . "' must no longer be enqueued." );
	}

	public function test_surviving_handle_is_enqueued_and_carries_original_deps(): void {
		$this->trigger_vehicle_edit_screen_enqueue();

		$this->assertTrue( wp_script_is( self::SURVIVOR_HANDLE, 'enqueued' ) );

		$deps = wp_scripts()->registered[ self::SURVIVOR_HANDLE ]->deps;
		$this->assertContains( 'jquery', $deps );
		$this->assertContains( 'jquery-ui-sortable', $deps );
	}

	/**
	 * @dataProvider expectedStringKeyProvider
	 */
	public function test_surviving_handle_localizes_every_string_key_from_the_map( string $key ): void {
		$this->trigger_vehicle_edit_screen_enqueue();

		$strings = $this->get_localized_strings();

		$this->assertArrayHasKey(
			$key,
			$strings,
			"Surviving handle '" . self::SURVIVOR_HANDLE . "' is missing strings.$key -- the surviving handle must carry the UNION of both handles' localized data, not just its own original set."
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function expectedStringKeyProvider(): array {
		$cases = array();
		foreach ( self::EXPECTED_STRING_KEYS as $key ) {
			$cases[ $key ] = array( $key );
		}
		return $cases;
	}

	public function test_surviving_handle_localizes_ajax_url_and_nonce(): void {
		$this->trigger_vehicle_edit_screen_enqueue();

		$decoded = $this->decode_localized_payload();

		$this->assertArrayHasKey( 'ajaxUrl', $decoded );
		$this->assertArrayHasKey( 'nonce', $decoded );
		$this->assertNotEmpty( $decoded['nonce'] );
	}

	/**
	 * Both real admin_enqueue_scripts callbacks, called the way a real vehicle
	 * edit page load fires them (both are hooked to the same action; called
	 * directly here -- same convention as AssetManagerComponentJsTest /
	 * ToastDependencyTest -- so the assertions do not depend on plugin
	 * bootstrap hook-registration order).
	 */
	private function trigger_vehicle_edit_screen_enqueue(): void {
		AssetManager::enqueue_admin_assets();
		VehicleMeta::enqueue_scripts();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_localized_strings(): array {
		$decoded = $this->decode_localized_payload();
		$this->assertArrayHasKey( 'strings', $decoded );
		return $decoded['strings'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decode_localized_payload(): array {
		$raw = wp_scripts()->get_data( self::SURVIVOR_HANDLE, 'data' );
		$this->assertIsString( $raw, "Surviving handle '" . self::SURVIVOR_HANDLE . "' has no localized data at all." );

		$this->assertMatchesRegularExpression( '/var mhmVehicleMeta = (\{.*\});/s', $raw );
		preg_match( '/var mhmVehicleMeta = (\{.*\});/s', $raw, $found );
		$decoded = json_decode( $found[1], true );
		$this->assertIsArray( $decoded, 'mhmVehicleMeta payload did not decode as JSON: ' . $raw );

		return $decoded;
	}

	private function reset_handles(): void {
		foreach ( array( self::SURVIVOR_HANDLE, self::REMOVED_HANDLE, 'mhm-rentiva-vehicle-gallery', 'mhm-rentiva-vehicle-quick-edit' ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
