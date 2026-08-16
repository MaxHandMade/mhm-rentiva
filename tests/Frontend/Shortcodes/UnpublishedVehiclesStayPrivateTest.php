<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_Ajax_UnitTestCase;

/**
 * A vehicle that is not published must not reach the public surface.
 *
 * Three public entry points resolve a vehicle without ever asking whether it is
 * published: the availability calendar picks a default vehicle and builds its
 * dropdown from `array('publish','draft','private')`, its
 * `mhmrentiva_get_vehicle_info` endpoint checks only `post_type`, and the
 * vehicle-details shortcode and its `mhmrentiva_get_calendar` endpoint do the
 * same. Two of those endpoints are nopriv, so a draft ID that is known or
 * guessed returns the vehicle's title, media, specification and price.
 *
 * WordPress already draws this line for post content; these surfaces stepped
 * around it in the application layer.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails
 */
final class UnpublishedVehiclesStayPrivateTest extends WP_Ajax_UnitTestCase
{
	private int $published_id;
	private int $draft_id;
	private int $private_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->published_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Published Roadster',
			)
		);

		$this->draft_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'draft',
				'post_title'  => 'Unannounced Draft Coupe',
			)
		);

		$this->private_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'private',
				'post_title'  => 'Private Fleet Van',
			)
		);

		wp_set_current_user( 0 );
	}

	public function test_the_calendar_dropdown_lists_only_published_vehicles(): void
	{
		$output = do_shortcode( '[rentiva_availability_calendar vehicle_id="' . $this->published_id . '"]' );

		$this->assertStringContainsString( 'Published Roadster', $output );
		$this->assertStringNotContainsString(
			'Unannounced Draft Coupe',
			$output,
			'A draft vehicle appeared in the public calendar dropdown.'
		);
		$this->assertStringNotContainsString(
			'Private Fleet Van',
			$output,
			'A private vehicle appeared in the public calendar dropdown.'
		);
	}

	public function test_the_vehicle_info_endpoint_refuses_a_draft(): void
	{
		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id' => (string) $this->draft_id,
		);

		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_info' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the response is the check.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'The public endpoint returned a draft vehicle: ' . $this->_last_response
		);
		$this->assertStringNotContainsString( 'Unannounced Draft Coupe', $this->_last_response );
	}

	public function test_the_vehicle_info_endpoint_refuses_a_private_vehicle(): void
	{
		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id' => (string) $this->private_id,
		);

		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_info' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'The public endpoint returned a private vehicle: ' . $this->_last_response
		);
	}

	public function test_the_vehicle_info_endpoint_still_serves_a_published_vehicle(): void
	{
		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id' => (string) $this->published_id,
		);

		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_info' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue(
			(bool) ( $response['success'] ?? false ),
			'A published vehicle must still resolve: ' . $this->_last_response
		);
	}

	public function test_the_details_shortcode_does_not_render_a_draft(): void
	{
		$output = do_shortcode( '[rentiva_vehicle_details vehicle_id="' . $this->draft_id . '"]' );

		$this->assertStringNotContainsString(
			'Unannounced Draft Coupe',
			$output,
			'The public details shortcode rendered a draft vehicle.'
		);
	}

	public function test_the_details_calendar_endpoint_refuses_a_draft(): void
	{
		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_calendar_nonce' ),
			'vehicle_id' => (string) $this->draft_id,
			'month'      => '8',
			'year'       => '2026',
		);

		try {
			$this->_handleAjax( 'mhmrentiva_get_calendar' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// See above.
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'The public calendar endpoint served a draft vehicle: ' . $this->_last_response
		);
	}
}
