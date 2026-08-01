<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use WP_Ajax_UnitTestCase;

/**
 * The save path is aligned with what the frontend actually renders: card/detail selections are
 * gated through get_available_fields_map() (via sanitize_card_field_selection), so a disabled
 * (unavailable) detail is dropped, while features/equipment -- which the frontend renders ungated
 * -- always survive. Verified end-to-end through the real AJAX handler with sub_action=save_all.
 */
final class VehicleSettingsSaveCascadeTest extends WP_Ajax_UnitTestCase {

	private static bool $ajax_hooks_registered = false;

	/**
	 * @var string
	 */
	protected $_last_response;

	public function setUp(): void {
		parent::setUp();

		// VehicleSettings::register() only runs from Plugin::initialize_services() when
		// is_admin() is true; that condition is not met during the single shared bootstrap
		// this test suite runs under, so the wp_ajax_* hook is registered here explicitly
		// (same pattern as FavoritesAjaxTest / CompareAjaxTest).
		if ( ! self::$ajax_hooks_registered ) {
			VehicleSettings::register();
			self::$ajax_hooks_registered = true;
		}

		// get_available_fields_map() caches in a static that persists across tests in the shared
		// process; reset it so this test sees the mhmrentiva_selected_* options the save writes below.
		self::reset_fields_map_cache();
	}

	public function tearDown(): void {
		foreach ( array( 'action', 'nonce', 'sub_action', 'selected_details', 'selected_features', 'selected_equipment', 'mhmrentiva_vehicle_card_fields' ) as $key ) {
			unset( $_POST[ $key ] );
		}
		wp_set_current_user( 0 );
		self::reset_fields_map_cache();
		parent::tearDown();
	}

	/**
	 * Integration test: invokes the real AJAX handler with sub_action=save_all. The definitions
	 * payload disables a non-core detail ('transmission') that the display payload still tries to
	 * keep on the card, alongside a feature ('navigation') that is not in the selected set. Proves
	 * frontend alignment end-to-end: the disabled detail is dropped by the availability gate, the
	 * feature survives (features are never gated on the frontend), and definitions-first ordering
	 * makes the just-written selection visible to the card sanitisation.
	 */
	public function test_save_all_drops_disabled_detail_but_keeps_feature_on_card(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['action']     = 'mhmrentiva_save_vehicle_settings';
		$_POST['nonce']      = wp_create_nonce( 'vehicle_settings_nonce' );
		$_POST['sub_action'] = 'save_all';

		// Definitions: only 'fuel_type' selected among details (cores are force-added by the handler);
		// 'transmission' is a non-core detail left OUT = disabled. 'navigation' feature is omitted too.
		$_POST['selected_details']   = array( 'fuel_type' );
		$_POST['selected_features']  = array( 'bluetooth' );
		$_POST['selected_equipment'] = array();

		// Display: card still tries to keep the disabled detail AND the "unselected" feature.
		$_POST['mhmrentiva_vehicle_card_fields'] = wp_json_encode( array(
			array( 'type' => 'detail', 'key' => 'transmission' ),
			array( 'type' => 'detail', 'key' => 'fuel_type' ),
			array( 'type' => 'feature', 'key' => 'navigation' ),
		) );

		try {
			$this->_handleAjax( 'mhmrentiva_save_vehicle_settings' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_success() dies in the test suite.
		}

		$response = json_decode( trim( (string) $this->_last_response ), true );
		$this->assertTrue( $response['success'] );

		$settings = get_option( 'mhmrentiva_settings' );
		$ids      = array_map(
			static function ( $f ) {
				return $f['type'] . ':' . $f['key'];
			},
			$settings['mhmrentiva_vehicle_card_fields']
		);

		$this->assertNotContains( 'detail:transmission', $ids, 'a disabled detail must be dropped by the availability gate' );
		$this->assertContains( 'detail:fuel_type', $ids, 'a selected detail is kept' );
		$this->assertContains( 'feature:navigation', $ids, 'features render ungated on the frontend and must survive the save' );
	}

	private static function reset_fields_map_cache(): void {
		$ref = new \ReflectionProperty( VehicleFeatureHelper::class, 'fields_map_cache' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
	}
}
