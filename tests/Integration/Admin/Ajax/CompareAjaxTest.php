<?php

namespace MHMRentiva\Tests\Integration\Admin\Ajax;

use MHMRentiva\Admin\Services\CompareService;
use WP_Ajax_UnitTestCase;

class CompareAjaxTest extends WP_Ajax_UnitTestCase {
	private static bool $service_registered = false;

	/**
	 * @var int
	 */
	private $user_id;

	/**
	 * @var int
	 */
	private $vehicle_id;

	/**
	 * @var string
	 */
	protected $_last_response;

	public function setUp(): void {
		parent::setUp();

		$this->user_id    = (int) $this->factory->user->create();
		$this->vehicle_id = 12345;

		wp_set_current_user( $this->user_id );
		if ( ! self::$service_registered ) {
			CompareService::register();
			self::$service_registered = true;
		}
		$this->reset_compare_state();
	}

	public function tearDown(): void {
		$this->reset_compare_state();
		wp_logout();
		parent::tearDown();
	}

	public function test_ajax_add_compare_success(): void {
		$_POST['action']     = 'mhm_rentiva_toggle_compare';
		$_POST['nonce']      = wp_create_nonce( 'mhm_rentiva_toggle_compare' );
		$_POST['vehicle_id'] = $this->vehicle_id;

		$this->dispatch_ajax();

		$response = $this->decode_response();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'added', $response['data']['action'] );
		$this->assertTrue( CompareService::is_in_compare( $this->vehicle_id ) );
	}

	public function test_ajax_remove_compare_success(): void {
		CompareService::add( $this->vehicle_id );

		$_POST['action']     = 'mhm_rentiva_toggle_compare';
		$_POST['nonce']      = wp_create_nonce( 'mhm_rentiva_toggle_compare' );
		$_POST['vehicle_id'] = $this->vehicle_id;

		$this->dispatch_ajax();

		$response = $this->decode_response();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'removed', $response['data']['action'] );
		$this->assertFalse( CompareService::is_in_compare( $this->vehicle_id ) );
	}

	public function test_ajax_accepts_secondary_nonce_context(): void {
		$_POST['action']     = 'mhm_rentiva_toggle_compare';
		$_POST['nonce']      = wp_create_nonce( 'mhm_rentiva_vehicles_list' );
		$_POST['vehicle_id'] = $this->vehicle_id;

		$this->dispatch_ajax();

		$response = $this->decode_response();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'added', $response['data']['action'] );
	}

	public function test_ajax_rejects_invalid_nonce(): void {
		$_POST['action']     = 'mhm_rentiva_toggle_compare';
		$_POST['nonce']      = 'invalid_nonce';
		$_POST['vehicle_id'] = $this->vehicle_id;

		$this->dispatch_ajax();

		$response = $this->decode_response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Security check failed', $response['data']['message'] );
	}

	public function test_ajax_rejects_invalid_vehicle_id(): void {
		$_POST['action']     = 'mhm_rentiva_toggle_compare';
		$_POST['nonce']      = wp_create_nonce( 'mhm_rentiva_toggle_compare' );
		$_POST['vehicle_id'] = 0;

		$this->dispatch_ajax();

		$response = $this->decode_response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid vehicle ID', $response['data']['message'] );
	}

	public function test_ajax_returns_limit_error_when_max_items_reached(): void {
		CompareService::add( 1001 );
		CompareService::add( 1002 );
		CompareService::add( 1003 );

		$_POST['action']     = 'mhm_rentiva_toggle_compare';
		$_POST['nonce']      = wp_create_nonce( 'mhm_rentiva_toggle_compare' );
		$_POST['vehicle_id'] = $this->vehicle_id;

		$this->dispatch_ajax();

		$response = $this->decode_response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'compare up to', strtolower( (string) $response['data']['message'] ) );
	}

	public function test_guest_user_can_toggle_compare_via_ajax(): void {
		wp_logout();
		$this->reset_compare_state();

		$_POST['action']     = 'mhm_rentiva_toggle_compare';
		$_POST['nonce']      = wp_create_nonce( 'mhm_rentiva_toggle_compare' );
		$_POST['vehicle_id'] = $this->vehicle_id;

		$this->dispatch_ajax();

		$response = $this->decode_response();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'added', $response['data']['action'] );
		$this->assertSame( 1, $response['data']['count'] );
	}

	private function dispatch_ajax(): void {
		try {
			$this->_handleAjax( 'mhm_rentiva_toggle_compare' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected path for WP_Ajax_UnitTestCase.
		}
	}

	private function reset_compare_state(): void {
		if ( $this->user_id ) {
			delete_user_meta( $this->user_id, 'mhm_rentiva_compare' );
		}

		unset( $_COOKIE['mhm_rentiva_compare'] );

		$cached_list = new \ReflectionProperty( CompareService::class, 'cached_list' );
		$cached_list->setAccessible( true );
		$cached_list->setValue( null );
	}

	/**
	 * WP test stack can prepend DB warnings/html before JSON output.
	 *
	 * @return array<string,mixed>
	 */
	private function decode_response(): array {
		$raw = trim( (string) $this->_last_response );
		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$json_start = strpos( $raw, '{"success"' );
		if ( $json_start === false ) {
			$json_start = strpos( $raw, '{' );
		}

		if ( $json_start !== false ) {
			$decoded = json_decode( substr( $raw, $json_start ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		$this->fail( 'Unable to decode AJAX response as JSON: ' . $raw );
	}
}
