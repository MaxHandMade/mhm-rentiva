<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_Ajax_UnitTestCase;

/**
 * Removing a custom detail must also clear its mhmrentiva_custom_field_meta entry, or an orphan
 * (type/options for a field that no longer exists) accumulates in the option.
 */
final class VehicleSettingsRemoveCustomFieldTest extends WP_Ajax_UnitTestCase {

	private static bool $registered = false;

	public function setUp(): void {
		parent::setUp();
		if ( ! self::$registered ) {
			VehicleSettings::register();
			self::$registered = true;
		}
	}

	public function tearDown(): void {
		foreach ( array( 'action', 'nonce', 'field_key', 'field_type' ) as $k ) {
			unset( $_POST[ $k ] );
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_removing_custom_detail_clears_its_field_meta(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( 'mhmrentiva_custom_details', array( 'custom_boot' => 'Boot Size' ) );
		update_option( 'mhmrentiva_custom_field_meta', array(
			'custom_boot' => array( 'type' => 'select', 'options' => 'S, M, L' ),
			'keep_me'     => array( 'type' => 'text', 'options' => '' ),
		) );

		$_POST['action']     = 'mhmrentiva_remove_custom_field';
		$_POST['nonce']      = wp_create_nonce( 'vehicle_settings_nonce' );
		$_POST['field_key']  = 'custom_boot';
		$_POST['field_type'] = 'details';

		try {
			$this->_handleAjax( 'mhmrentiva_remove_custom_field' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_success() dies in the test suite.
		}

		$this->assertArrayNotHasKey( 'custom_boot', (array) get_option( 'mhmrentiva_custom_details' ), 'the custom detail itself is removed' );

		$meta = (array) get_option( 'mhmrentiva_custom_field_meta' );
		$this->assertArrayNotHasKey( 'custom_boot', $meta, 'the removed field must not leave orphan field meta' );
		$this->assertArrayHasKey( 'keep_me', $meta, 'unrelated field meta must be preserved' );
	}
}
