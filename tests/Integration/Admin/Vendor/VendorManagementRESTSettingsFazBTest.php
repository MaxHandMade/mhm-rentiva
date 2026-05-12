<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Vendor;

use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @group vendor-management
 * @group vendor-management-faz-b
 */
final class VendorManagementRESTSettingsFazBTest extends WP_UnitTestCase {

	private static WP_REST_Server $server;
	private int $admin_id = 0;

	/** @var string[] */
	private const OPTION_KEYS = array(
		'mhm_rentiva_global_payout_freeze',
		'mhm_min_payout_amount',
		'mhm_vehicle_min_photos',
		'mhm_vehicle_max_photos',
		'mhm_vendor_doc_max_file_size_mb',
		'mhm_vehicle_min_year',
		'mhm_vendor_bio_max_length',
		'mhm_vendor_service_cities',
	);

	public function setUp(): void {
		parent::setUp();

		// Ensure clean option state.
		foreach ( self::OPTION_KEYS as $key ) {
			delete_option( $key );
		}

		add_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		self::$server   = $wp_rest_server;
		do_action( 'rest_api_init', self::$server );

		$this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function tearDown(): void {
		global $wp_rest_server;

		foreach ( self::OPTION_KEYS as $key ) {
			delete_option( $key );
		}

		wp_delete_user( $this->admin_id );

		$wp_rest_server = null;
		wp_set_current_user( 0 );
		remove_action( 'rest_api_init', array( \MHMRentiva\Admin\Vendor\REST\VendorManagementRestController::class, 'register_routes' ) );
		parent::tearDown();
	}

	public function test_unauthenticated_settings_returns_401(): void {
		wp_set_current_user( 0 );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/settings' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_get_settings_returns_all_keys_with_defaults(): void {
		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/settings' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'payout_freeze',  $data );
		$this->assertArrayHasKey( 'min_payout',     $data );
		$this->assertArrayHasKey( 'min_photos',     $data );
		$this->assertArrayHasKey( 'max_photos',     $data );
		$this->assertArrayHasKey( 'doc_max_mb',     $data );
		$this->assertArrayHasKey( 'min_year',       $data );
		$this->assertArrayHasKey( 'bio_max_chars',  $data );
		$this->assertArrayHasKey( 'service_cities', $data );
		$this->assertIsBool(  $data['payout_freeze'],  'payout_freeze must be a boolean' );
		$this->assertIsArray( $data['service_cities'], 'service_cities must be an array' );
	}

	public function test_get_settings_returns_saved_values(): void {
		update_option( 'mhm_min_payout_amount',            200 );
		update_option( 'mhm_rentiva_global_payout_freeze', 'yes' );

		wp_set_current_user( $this->admin_id );
		$response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vendors/settings' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 200.0, $data['min_payout'],    'min_payout must reflect saved value' );
		$this->assertTrue( $data['payout_freeze'],        'payout_freeze must be true when option is "yes"' );
	}

	public function test_save_settings_updates_options(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/vendors/settings' );
		$request->set_body_params( array(
			'min_payout'    => 150,
			'payout_freeze' => false,
			'min_photos'    => 5,
			'max_photos'    => 10,
			'doc_max_mb'    => 8,
			'min_year'      => 2000,
			'bio_max_chars' => 500,
			'service_cities' => array( 'Istanbul', 'Ankara' ),
		) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertEquals( 150, get_option( 'mhm_min_payout_amount' ),    'mhm_min_payout_amount must be saved' );
		$this->assertEquals( 5,   get_option( 'mhm_vehicle_min_photos' ),   'mhm_vehicle_min_photos must be saved' );
	}

	public function test_save_settings_serializes_service_cities_as_array(): void {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/vendors/settings' );
		$request->set_body_params( array(
			'service_cities' => array( 'Istanbul', 'Izmir' ),
		) );
		$response = self::$server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$saved = maybe_unserialize( get_option( 'mhm_vendor_service_cities' ) );
		$this->assertIsArray( $saved );
		$this->assertContains( 'Istanbul', $saved );
		$this->assertContains( 'Izmir',    $saved );
	}
}
