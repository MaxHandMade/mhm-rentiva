<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleComparison;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * Plan 0.5 (measured on the dev site): a site that never saved a comparison selection got a
 * comparison table with no feature rows -- and an admin who tried to configure it could not:
 * the first save of comparison_fields was dropped by the settings sanitizer.
 *
 * Contract: never saved -> DEFAULT_FIELDS, shown identically by the frontend table and the admin
 * screen; the admin's first save sticks; a saved empty selection compares nothing; resetting the
 * Display tab restores the defaults.
 *
 * The save/reset tests run with the register_setting() sanitizer attached, as admin-ajax does
 * (admin_init); without it update_option() skips SettingsSanitizer and the tests would pass
 * against a path production never takes.
 */
final class VehicleComparisonDefaultFieldsTest extends WP_UnitTestCase {

	private bool $sanitizer_registered = false;

	public function tear_down(): void {
		if ( $this->sanitizer_registered ) {
			unregister_setting( SettingsCore::GROUP, SettingsCore::OPTION_NAME );
		}
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		unset( $_POST['tab'], $_POST['nonce'], $_POST['sub_action'], $_POST['comparison_fields'], $_POST['mhmrentiva_vehicle_matrix_order'], $_REQUEST['nonce'] );
		parent::tear_down();
	}

	/** Seed the stored option exactly (no sanitizer yet), then attach the production sanitizer. */
	private function seed_then_register_sanitizer( ?array $settings ): void {
		if ( null === $settings ) {
			delete_option( SettingsCore::OPTION_NAME );
		} else {
			update_option( SettingsCore::OPTION_NAME, $settings );
		}
		SettingsCore::init_settings_registration();
		$this->sanitizer_registered = true;
	}

	/** Default rows that pass the Active-field gate (a Passive field is never a row). */
	private static function expected_default_rows(): array {
		$rows = array_values( array_filter(
			VehicleComparison::DEFAULT_FIELDS['details'],
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
		return array_keys( $ref->invoke( null, array() ) );
	}

	/** Run a vehicle-settings AJAX handler the way admin-ajax does, as an administrator. */
	private function run_ajax( callable $handler, array $post ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST             = array_merge( $post, array( 'nonce' => wp_create_nonce( 'vehicle_settings_nonce' ) ) );
		$_REQUEST['nonce'] = $_POST['nonce'];
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', static fn() => static function () {
			throw new \RuntimeException( 'ajax-done' );
		} );

		ob_start();
		try {
			$handler();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'ajax-done', $e->getMessage() );
		} finally {
			$out = (string) ob_get_clean();
		}
		$this->assertStringContainsString( '"success":true', $out, 'The handler reported failure: ' . $out );
	}

	public function test_an_unconfigured_site_compares_the_default_rows(): void {
		$this->seed_then_register_sanitizer( null );

		$this->assertSame( self::expected_default_rows(), self::rows() );
	}

	public function test_an_explicitly_emptied_selection_compares_nothing(): void {
		$this->seed_then_register_sanitizer( array( 'comparison_fields' => array() ) );

		$this->assertSame( array(), self::rows() );
	}

	public function test_the_admin_screen_ticks_exactly_the_rows_the_table_shows(): void {
		$this->seed_then_register_sanitizer( null );

		$ticked = array();
		foreach ( VehicleSettings::build_settings_state()['fields'] as $f ) {
			if ( $f['compare'] && $f['enabled'] ) {
				$ticked[] = $f['key'];
			}
		}

		$rows = self::rows();
		sort( $ticked );
		sort( $rows );
		$this->assertSame( $rows, $ticked );
		$this->assertNotEmpty( $rows );
	}

	public function test_the_first_save_on_an_unconfigured_site_sticks(): void {
		$this->seed_then_register_sanitizer( null );

		$this->run_ajax(
			array( VehicleSettings::class, 'ajax_save_settings' ),
			array(
				'sub_action'        => 'save_display_settings',
				'comparison_fields' => array( 'details' => array( 'mileage' ) ),
			)
		);

		$this->assertSame( array( 'mileage' ), self::rows() );
	}

	public function test_saving_an_empty_selection_on_an_unconfigured_site_sticks(): void {
		$this->seed_then_register_sanitizer( null );

		$this->run_ajax(
			array( VehicleSettings::class, 'ajax_save_settings' ),
			array( 'sub_action' => 'save_display_settings' )
		);

		$this->assertSame( array(), self::rows() );
	}

	public function test_the_first_save_of_the_field_order_sticks(): void {
		$this->seed_then_register_sanitizer( null );

		$this->run_ajax(
			array( VehicleSettings::class, 'ajax_save_settings' ),
			array(
				'sub_action'                      => 'save_display_settings',
				'mhmrentiva_vehicle_matrix_order' => wp_json_encode( array( 'detail:year', 'detail:brand' ) ),
			)
		);

		$stored = get_option( SettingsCore::OPTION_NAME );
		$this->assertSame( array( 'detail:year', 'detail:brand' ), $stored['mhmrentiva_vehicle_matrix_order'] ?? null );
	}

	/**
	 * @dataProvider saved_selections
	 */
	public function test_resetting_the_display_tab_restores_the_default_rows( array $saved ): void {
		$this->seed_then_register_sanitizer( array( 'comparison_fields' => $saved ) );

		$this->run_ajax( array( VehicleSettings::class, 'ajax_reset_settings' ), array( 'tab' => 'display' ) );

		$this->assertSame( self::expected_default_rows(), self::rows() );
	}

	/**
	 * @return array<string,array{0:array}>
	 */
	public static function saved_selections(): array {
		return array(
			'a custom selection' => array( array( 'details' => array( 'mileage' ) ) ),
			'an empty selection' => array( array() ),
		);
	}

	public function test_fuel_and_transmission_show_their_labels_not_stored_keys(): void {
		$this->seed_then_register_sanitizer( null );
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
}
