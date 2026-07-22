<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_Ajax_UnitTestCase;

/**
 * A field that is not enabled must not survive in card/detail/comparison selections.
 * Enforced server-side, not only in the browser.
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
	}

	public function tearDown(): void {
		foreach ( array( 'action', 'nonce', 'sub_action', 'selected_details', 'selected_features', 'selected_equipment', 'mhm_rentiva_vehicle_card_fields' ) as $key ) {
			unset( $_POST[ $key ] );
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Integration test: invokes the real AJAX handler with sub_action=save_all, where the
	 * definitions payload disables a field that the display payload still selects. Covers
	 * wiring (the filters are actually called from save_display_payload()), ordering
	 * (definitions-first within save_all), and the cascade together -- reflection-only tests
	 * of the two private filters cannot catch a save_display_payload() that never calls them.
	 */
	public function test_save_all_disables_field_and_cascades_out_of_display_selection(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Seed: 'navigation' is currently enabled and already selected on the vehicle card.
		update_option( 'mhm_selected_features', array( 'navigation', 'bluetooth' ) );
		update_option( 'mhm_rentiva_settings', array(
			'mhm_rentiva_vehicle_card_fields' => array(
				array( 'type' => 'feature', 'key' => 'navigation' ),
			),
		) );

		$_POST['action']     = 'mhmrentiva_save_vehicle_settings';
		$_POST['nonce']      = wp_create_nonce( 'vehicle_settings_nonce' );
		$_POST['sub_action'] = 'save_all';

		// Definitions payload: 'navigation' is disabled (omitted), only 'bluetooth' stays enabled.
		$_POST['selected_details']   = array();
		$_POST['selected_features']  = array( 'bluetooth' );
		$_POST['selected_equipment'] = array();

		// Display payload still tries to keep 'navigation' selected on the card.
		$_POST['mhm_rentiva_vehicle_card_fields'] = wp_json_encode( array(
			array( 'type' => 'feature', 'key' => 'navigation' ),
			array( 'type' => 'feature', 'key' => 'bluetooth' ),
		) );

		try {
			$this->_handleAjax( 'mhmrentiva_save_vehicle_settings' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_success() dies in the test suite.
		}

		$response = json_decode( trim( (string) $this->_last_response ), true );
		$this->assertTrue( $response['success'] );

		$settings  = get_option( 'mhm_rentiva_settings' );
		$card_keys = array_column( $settings['mhm_rentiva_vehicle_card_fields'], 'key' );

		$this->assertNotContains( 'navigation', $card_keys, 'disabled field must be cascaded out of the card selection' );
		$this->assertContains( 'bluetooth', $card_keys );
	}

	public function test_disabled_field_is_stripped_from_card_and_detail_selection(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) ); // navigation NOT enabled

		$selection = array(
			array( 'type' => 'feature', 'key' => 'bluetooth' ),
			array( 'type' => 'feature', 'key' => 'navigation' ),
		);

		$filtered = self::call_private( 'filter_selection_by_enabled', array( $selection ) );

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'bluetooth', $filtered[0]['key'] );
	}

	public function test_disabled_field_is_stripped_from_comparison(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) );

		$comparison = array( 'features' => array( 'bluetooth', 'navigation' ) );

		$filtered = self::call_private( 'filter_comparison_by_enabled', array( $comparison ) );

		$this->assertSame( array( 'bluetooth' ), array_values( $filtered['features'] ) );
	}

	/**
	 * Reflection helper — these are private by design; the public surface is the AJAX handler.
	 *
	 * @param array<int,mixed> $args
	 * @return mixed
	 */
	private static function call_private( string $method, array $args ) {
		$ref = new \ReflectionMethod( VehicleSettings::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}
}
