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

	/**
	 * Settings-API globals as they were before init_settings_registration() ran; it registers
	 * more than the main option (dark mode, add-ons, REST, every group's sections and fields),
	 * so the fixture restores all of them, not just the one setting it cares about.
	 * Each entry records whether the global existed, not only its value: "undefined" and
	 * "null" are different states to leave behind.
	 *
	 * @var array<string,array{set:bool,value:mixed}>|null
	 */
	private ?array $settings_globals = null;

	/** register_setting() also aliases new_whitelist_options to new_allowed_options by reference. */
	private const SETTINGS_GLOBALS = array( 'wp_registered_settings', 'new_allowed_options', 'new_whitelist_options', 'wp_settings_sections', 'wp_settings_fields' );

	/** @return array<string,array{set:bool,value:mixed}> */
	private static function snapshot_settings_globals(): array {
		$snapshot = array();
		foreach ( self::SETTINGS_GLOBALS as $name ) {
			$set               = array_key_exists( $name, $GLOBALS );
			$snapshot[ $name ] = array(
				'set'   => $set,
				'value' => $set ? $GLOBALS[ $name ] : null,
			);
		}
		return $snapshot;
	}

	/** @param array<string,array{set:bool,value:mixed}> $snapshot */
	private static function restore_settings_globals( array $snapshot ): void {
		foreach ( $snapshot as $name => $state ) {
			unset( $GLOBALS[ $name ] );
			if ( $state['set'] ) {
				$GLOBALS[ $name ] = $state['value'];
			}
		}
		// Re-create core's reference alias when both names existed.
		if ( $snapshot['new_allowed_options']['set'] && $snapshot['new_whitelist_options']['set'] ) {
			$GLOBALS['new_whitelist_options'] = &$GLOBALS['new_allowed_options'];
		}
	}

	public function tear_down(): void {
		$this->tear_down_settings_globals();
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
		$this->settings_globals = self::snapshot_settings_globals();
		SettingsCore::init_settings_registration();
	}

	private function tear_down_settings_globals(): void {
		if ( null !== $this->settings_globals ) {
			self::restore_settings_globals( $this->settings_globals );
			$this->settings_globals = null;
		}
	}

	/** Run the fixture twice; the Settings API globals (existence and value) must be unchanged. */
	private function assert_fixture_leaves_globals_as_found(): void {
		$before = self::snapshot_settings_globals();

		for ( $i = 0; $i < 2; $i++ ) {
			$this->seed_then_register_sanitizer( null );
			$this->assertArrayHasKey( SettingsCore::OPTION_NAME, $GLOBALS['wp_registered_settings'] );
			$this->tear_down_settings_globals();
		}

		$this->assertSame( $before, self::snapshot_settings_globals() );
	}

	public function test_the_fixture_restores_the_settings_api_globals_as_found(): void {
		$this->assert_fixture_leaves_globals_as_found();
	}

	/** The branch where core has not created the option-group globals yet (no rest_api_init). */
	public function test_the_fixture_restores_the_settings_api_globals_when_they_start_undefined(): void {
		$real = self::snapshot_settings_globals();
		unset( $GLOBALS['new_allowed_options'], $GLOBALS['new_whitelist_options'] );

		try {
			$this->assert_fixture_leaves_globals_as_found();
			$this->assertArrayNotHasKey( 'new_allowed_options', $GLOBALS );
			$this->assertArrayNotHasKey( 'new_whitelist_options', $GLOBALS );
		} finally {
			// Consume any snapshot a failed assertion left pending first, or tear_down() would
			// apply it again after the real state is back.
			$this->tear_down_settings_globals();
			self::restore_settings_globals( $real );
		}
	}

	/** Core links the two option-group names by reference; the fixture must leave them linked. */
	public function test_the_fixture_keeps_the_option_group_alias_linked(): void {
		$real                              = self::snapshot_settings_globals();
		$GLOBALS['new_allowed_options']    = array( 'probe_group' => array( 'probe_option' ) );
		$GLOBALS['new_whitelist_options']  = &$GLOBALS['new_allowed_options'];

		try {
			$this->assert_fixture_leaves_globals_as_found();

			$GLOBALS['new_allowed_options']['probe_link'] = array();
			$this->assertArrayHasKey( 'probe_link', $GLOBALS['new_whitelist_options'], 'new_whitelist_options is no longer a reference to new_allowed_options.' );
		} finally {
			$this->tear_down_settings_globals();
			self::restore_settings_globals( $real );
		}
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
	 * The same reset used to store empty card/detail selections -- and an explicit empty selection
	 * means "show nothing" (VehicleFeatureHelper::get_selected_detail_fields()), so "Reset to
	 * defaults" stripped every feature line from the vehicle cards and the detail page.
	 */
	public function test_resetting_the_display_tab_restores_the_default_card_and_detail_fields(): void {
		$custom = array( array( 'type' => 'detail', 'key' => 'year' ) );
		$this->seed_then_register_sanitizer(
			array(
				'mhmrentiva_vehicle_card_fields'   => $custom,
				'mhmrentiva_vehicle_detail_fields' => $custom,
			)
		);

		$this->run_ajax( array( VehicleSettings::class, 'ajax_reset_settings' ), array( 'tab' => 'display' ) );

		$defaults = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::sanitize_card_field_selection(
			\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_default_card_fields()
		);
		$this->assertNotEmpty( $defaults );
		$this->assertSame( $defaults, \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_selected_card_fields() );
		$this->assertSame( $defaults, \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_selected_detail_fields() );
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

	/**
	 * Every value the vehicle editor offers prints the editor's own label (VehicleMeta), the way
	 * the vehicle page shows it -- the helper used to keep a diverging copy (petrol -> "Gasoline",
	 * cng / hydrogen / cvt printed raw). PR bot P2.
	 */
	public function test_every_editor_value_prints_the_editor_label(): void {
		foreach ( \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types() as $key => $label ) {
			$this->assertSame( $label, \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_fuel_type_label( $key ), "fuel: $key" );
		}
		foreach ( \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types() as $key => $label ) {
			$this->assertSame( $label, \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_transmission_label( $key ), "transmission: $key" );
		}
		// Legacy stored value.
		$this->assertSame(
			\MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types()['petrol'],
			\MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_fuel_type_label( 'gasoline' )
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
