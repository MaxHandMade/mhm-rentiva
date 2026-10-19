<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleComparison;
use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * Plan 0.5 (measured on the dev site): a site that never opened the comparison settings got a
 * comparison table with no feature rows -- nothing in setup said a selection was needed.
 *
 * Contract: never configured -> a sensible default row set, shown identically by the frontend
 * table and the admin screen; explicitly emptied -> nothing compares (the admin's choice).
 */
final class VehicleComparisonDefaultFieldsTest extends WP_UnitTestCase {

	private const DEFAULT_ROWS = array( 'price_per_day', 'brand', 'model', 'fuel_type', 'transmission', 'seats', 'doors', 'year' );

	/** Default rows that pass the Active-field gate (a Passive field is never a row). */
	private static function expected_default_rows(): array {
		$rows = array_values( array_filter(
			self::DEFAULT_ROWS,
			static fn( $key ) => \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::is_field_active( 'detail', $key )
		) );
		// Guard against a vacuous pass: the core identity/price rows are always active.
		foreach ( array( 'price_per_day', 'brand', 'model' ) as $core ) {
			\PHPUnit\Framework\Assert::assertContains( $core, $rows );
		}
		return $rows;
	}

	private static function rows(): array {
		$ref = new \ReflectionMethod( VehicleComparison::class, 'get_dynamic_features' );
		$ref->setAccessible( true );
		return $ref->invoke( null, array() );
	}

	public function test_an_unconfigured_site_compares_the_default_rows(): void {
		update_option( 'mhmrentiva_settings', array() );

		$this->assertSame( self::expected_default_rows(), array_keys( self::rows() ) );
	}

	public function test_an_explicitly_emptied_selection_compares_nothing(): void {
		update_option( 'mhmrentiva_settings', array( 'comparison_fields' => array() ) );

		$this->assertSame( array(), self::rows() );
	}

	public function test_the_admin_screen_ticks_exactly_the_rows_the_table_shows(): void {
		update_option( 'mhmrentiva_settings', array() );

		$state   = VehicleSettings::build_settings_state();
		$ticked  = array();
		foreach ( $state['fields'] as $f ) {
			if ( $f['compare'] ) {
				$ticked[] = $f['key'];
			}
		}

		sort( $ticked );
		$expected = self::DEFAULT_ROWS;
		sort( $expected );
		$this->assertSame( $expected, $ticked );
	}

	public function test_fuel_and_transmission_show_their_labels_not_stored_keys(): void {
		update_option( 'mhmrentiva_settings', array() );
		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle_id, '_mhmrentiva_fuel_type', 'electric' );
		update_post_meta( $vehicle_id, '_mhmrentiva_transmission', 'auto' );

		$ref = new \ReflectionMethod( VehicleComparison::class, 'get_vehicle_data' );
		$ref->setAccessible( true );
		$data = $ref->invoke( null, $vehicle_id );

		$this->assertIsArray( $data, 'get_vehicle_data() must resolve the fixture vehicle' );
		$this->assertSame( 'Electric', $data['features']['fuel_type'] ?? null );
		$this->assertSame( 'Automatic', $data['features']['transmission'] ?? null );
	}

	public function test_resetting_the_display_tab_restores_the_default_rows(): void {
		update_option( 'mhmrentiva_settings', array( 'comparison_fields' => array( 'details' => array( 'color' ) ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['tab']        = 'display';
		$_POST['nonce']      = wp_create_nonce( 'vehicle_settings_nonce' );
		$_REQUEST['nonce']   = $_POST['nonce'];
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', static fn() => static function () {
			throw new \RuntimeException( 'ajax-done' );
		} );

		try {
			ob_start();
			VehicleSettings::ajax_reset_settings();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'ajax-done', $e->getMessage() );
		} finally {
			ob_end_clean();
			unset( $_POST['tab'], $_POST['nonce'], $_REQUEST['nonce'] );
		}

		$this->assertSame( self::expected_default_rows(), array_keys( self::rows() ) );
	}
}
