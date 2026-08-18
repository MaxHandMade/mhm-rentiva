<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\SecurityHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use WP_Ajax_UnitTestCase;

/**
 * One defect class, swept across every public surface that accepts a vehicle id.
 *
 * The reported instance was `AvailabilityCalendar`: two `get_posts()` calls
 * asking for `array('publish','draft','private')` on a public shortcode.
 * `get_posts()` applies no capability check of its own, so an anonymous visitor
 * was served unpublished vehicles' titles, excerpts and prices, and the newest
 * unpublished vehicle became the calendar's default.
 *
 * Enumerating the class turned up the same shape at six more public entry
 * points, all sharing one wrong assumption: that a nonce implies an authorized
 * caller. It does not. Every nonce involved is minted into a public page, so
 * every anonymous visitor holds a valid one; a nonce proves same-origin, never
 * authorization.
 *
 * Each surface therefore gets a pair of tests:
 *   - the leak is closed for an unpublished vehicle, and
 *   - the published vehicle still works.
 *
 * The second half is not ceremony. A test that only asserts absence passes just
 * as happily when the feature has been broken outright, which is the most likely
 * way a fix like this goes wrong.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::is_publicly_readable
 * @covers \MHMRentiva\Admin\Core\SecurityHelper::validate_public_vehicle_id
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::prepare_template_data
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::ajax_get_vehicle_info
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::ajax_unified_availability
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_booking_form
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_check_availability
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::ajax_get_calendar
 */
final class PublicSurfaceUnpublishedDisclosureTest extends WP_Ajax_UnitTestCase
{
	private const PUBLISHED_TITLE = 'Published Runabout';
	private const DRAFT_TITLE     = 'Secret Draft Hypercar';
	private const PRIVATE_TITLE   = 'Secret Private Limousine';

	private int $published_id;
	private int $draft_id;
	private int $private_id;

	/** @var string */
	protected $_last_response;

	public function setUp(): void
	{
		parent::setUp();

		// `ShortcodeServiceProvider::process_registration()` already calls
		// each of these classes' `register()` once, unconditionally, at
		// plugin bootstrap (`Plugin.php` -> `ShortcodeServiceProvider::
		// register()`), so the nopriv actions are already hooked before this
		// test runs -- these calls are redundant with that, not required to
		// make it happen. They stay as a defensive, explicit pin: each
		// `register()` is idempotent (`add_action` on an already-hooked
		// callback is a no-op) and keeps this test readable and independent
		// of the bootstrap's registry contents if that ever changes.
		AvailabilityCalendar::register();
		BookingForm::register();
		VehicleDetails::register();

		$this->published_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => self::PUBLISHED_TITLE,
			)
		);
		$this->draft_id     = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'draft',
				'post_title'  => self::DRAFT_TITLE,
			)
		);
		$this->private_id   = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'private',
				'post_title'  => self::PRIVATE_TITLE,
			)
		);

		foreach ( array( $this->published_id, $this->draft_id, $this->private_id ) as $id ) {
			update_post_meta( $id, '_mhmrentiva_price_per_day', 1000 );
			update_post_meta( $id, '_mhmrentiva_status', 'active' );
		}

		// Every assertion in this file is about the anonymous visitor.
		wp_set_current_user( 0 );
	}

	// ---------------------------------------------------------------- helper

	public function test_the_shared_gate_admits_only_publicly_readable_vehicles(): void
	{
		$this->assertTrue( VehicleDataHelper::is_publicly_readable( $this->published_id ) );
		$this->assertFalse( VehicleDataHelper::is_publicly_readable( $this->draft_id ) );
		$this->assertFalse( VehicleDataHelper::is_publicly_readable( $this->private_id ) );
	}

	public function test_the_shared_gate_rejects_other_post_types_and_missing_ids(): void
	{
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$this->assertFalse( VehicleDataHelper::is_publicly_readable( $page ) );
		$this->assertFalse( VehicleDataHelper::is_publicly_readable( 0 ) );
		$this->assertFalse( VehicleDataHelper::is_publicly_readable( 987654 ) );
	}

	public function test_the_shared_gate_rejects_a_password_protected_vehicle(): void
	{
		// Core counts a password-protected post as publicly viewable, but its
		// content sits behind the password -- so its price and schedule must too.
		$locked = self::factory()->post->create(
			array(
				'post_type'     => 'mhmrentiva_vehicle',
				'post_status'   => 'publish',
				'post_password' => 'hunter2',
			)
		);

		$this->assertFalse( VehicleDataHelper::is_publicly_readable( $locked ) );
	}

	public function test_the_shared_gate_does_not_widen_for_an_administrator(): void
	{
		/*
		 * Deliberate: several consumers cache their result in a role-agnostic
		 * transient, so a capability-dependent answer would let one privileged
		 * page-view poison a shared cache with unpublished data and serve it to
		 * the next anonymous visitor.
		 */
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertFalse( VehicleDataHelper::is_publicly_readable( $this->draft_id ) );
	}

	// ------------------------------------------- surface 1: calendar render

	public function test_calendar_does_not_offer_unpublished_vehicles_to_an_anonymous_visitor(): void
	{
		$output = do_shortcode( '[rentiva_availability_calendar]' );

		$this->assertStringNotContainsString( self::DRAFT_TITLE, $output );
		$this->assertStringNotContainsString( self::PRIVATE_TITLE, $output );
	}

	public function test_calendar_still_offers_published_vehicles_to_an_anonymous_visitor(): void
	{
		$output = do_shortcode( '[rentiva_availability_calendar]' );

		$this->assertStringContainsString( self::PUBLISHED_TITLE, $output );
	}

	public function test_calendar_refuses_to_pin_an_unpublished_vehicle_named_by_the_shortcode(): void
	{
		$output = do_shortcode( '[rentiva_availability_calendar vehicle_id="' . $this->draft_id . '"]' );

		$this->assertStringNotContainsString( self::DRAFT_TITLE, $output );
	}

	public function test_calendar_still_pins_a_published_vehicle_named_by_the_shortcode(): void
	{
		$output = do_shortcode( '[rentiva_availability_calendar vehicle_id="' . $this->published_id . '"]' );

		$this->assertStringContainsString( 'rv-availability-calendar', $output );
		$this->assertStringContainsString( self::PUBLISHED_TITLE, $output );
	}

	// ------------------------------- surface 2: booking identity in payload

	public function test_availability_payload_withholds_other_customers_booking_identity(): void
	{
		$booking_title = 'Booking for Jane Doe 0555 111 2233';
		$this->seed_booking( $this->published_id, $booking_title );

		$days = $this->availability_days_for( $this->published_id );
		$busy = $this->first_occupied_day( $days );

		$this->assertNotNull( $busy, 'The seeded booking must occupy a day, or this test proves nothing.' );
		$this->assertArrayNotHasKey( 'bookings', $busy, 'Booking rows must not cross the public payload boundary.' );
		$this->assertStringNotContainsString( $booking_title, wp_json_encode( $days ) );
	}

	public function test_availability_payload_still_reports_occupancy_for_published_vehicles(): void
	{
		$this->seed_booking( $this->published_id, 'Booking for Jane Doe' );

		$busy = $this->first_occupied_day( $this->availability_days_for( $this->published_id ) );

		$this->assertNotNull( $busy );
		$this->assertSame( 1, $busy['occupancy'], 'Occupancy is the public fact the calendar exists to show.' );
		$this->assertContains( $busy['status'], array( 'booked', 'partial', 'maintenance' ) );
	}

	public function test_rendered_calendar_does_not_publish_a_booking_title(): void
	{
		$booking_title = 'Booking for Jane Doe 0555 111 2233';
		$this->seed_booking( $this->published_id, $booking_title );

		$output = do_shortcode( '[rentiva_availability_calendar vehicle_id="' . $this->published_id . '"]' );

		$this->assertStringNotContainsString( $booking_title, $output );
		$this->assertStringNotContainsString( 'Jane Doe', $output );
	}

	// ---------------------------------------- surface 3: nopriv AJAX vehicle

	public function test_get_vehicle_info_refuses_an_unpublished_vehicle(): void
	{
		$response = $this->dispatch_ajax( 'mhmrentiva_get_vehicle_info', 'mhmrentiva_availability_nonce', $this->draft_id );

		$this->assertFalse( $response['success'] );
		$this->assertStringNotContainsString( self::DRAFT_TITLE, wp_json_encode( $response ) );
	}

	public function test_get_vehicle_info_still_serves_a_published_vehicle(): void
	{
		$response = $this->dispatch_ajax( 'mhmrentiva_get_vehicle_info', 'mhmrentiva_availability_nonce', $this->published_id );

		$this->assertTrue( $response['success'] );
		$this->assertSame( self::PUBLISHED_TITLE, $response['data']['title'] );
	}

	public function test_unified_availability_refuses_an_unpublished_vehicle(): void
	{
		$response = $this->dispatch_ajax( 'mhmrentiva_availability_unified', 'mhmrentiva_availability_nonce', $this->private_id );

		$this->assertFalse( $response['success'] );
		$this->assertArrayNotHasKey( 'pricing_data', (array) ( $response['data'] ?? array() ) );
	}

	public function test_unified_availability_still_serves_a_published_vehicle(): void
	{
		$response = $this->dispatch_ajax( 'mhmrentiva_availability_unified', 'mhmrentiva_availability_nonce', $this->published_id );

		$this->assertTrue( $response['success'] );
		$this->assertNotEmpty( $response['data']['availability_data'] );
	}

	// -------------------------------------- surface 4: public id validation

	public function test_public_vehicle_validator_rejects_unpublished_ids(): void
	{
		$this->expectException( \InvalidArgumentException::class );

		SecurityHelper::validate_public_vehicle_id( $this->draft_id );
	}

	public function test_public_vehicle_validator_accepts_published_ids(): void
	{
		$this->assertSame( $this->published_id, SecurityHelper::validate_public_vehicle_id( $this->published_id ) );
	}

	public function test_shape_validator_keeps_its_narrower_contract(): void
	{
		// The authorization rule lives in the PUBLIC validator on purpose; the
		// shape validator has honest non-public callers and must not acquire it.
		$this->assertSame( $this->draft_id, SecurityHelper::validate_vehicle_id( $this->draft_id ) );
	}

	// ------------------------------------- surface 5: VehicleDetails calendar

	public function test_vehicle_details_calendar_refuses_an_unpublished_vehicle(): void
	{
		$response = $this->dispatch_calendar( $this->draft_id );

		$this->assertFalse( $response['success'] );
		$this->assertStringNotContainsString( self::DRAFT_TITLE, wp_json_encode( $response ) );
	}

	public function test_vehicle_details_calendar_still_serves_a_published_vehicle(): void
	{
		$response = $this->dispatch_calendar( $this->published_id );

		$this->assertTrue( $response['success'] );
		$this->assertNotEmpty( $response['data']['calendar_html'] );
	}

	// ------------------------------------- surface 6: booking form submission

	public function test_booking_form_refuses_an_unpublished_vehicle(): void
	{
		$response = $this->dispatch_booking_form( $this->draft_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid vehicle ID.', $response['data']['message'] );
	}

	public function test_booking_form_still_processes_a_published_vehicle(): void
	{
		$response = $this->dispatch_booking_form( $this->published_id );

		/*
		 * A published vehicle runs the whole submit path and hands over to the
		 * gateway: it passed validate_public_vehicle_id(), the availability
		 * check, the duration check, the locked overlap check and the deposit
		 * calculation to get here. A regression that rejected published vehicles
		 * at the gate would fail this with "Invalid vehicle ID." instead.
		 *
		 * Until 2026-08-18 this asserted the OPPOSITE -- that the answer was "No
		 * payment gateway is available" -- because the suite ran without
		 * WooCommerce and that was the terminal branch. It was measuring the
		 * harness, not the plugin.
		 */
		$this->assertTrue(
			$response["success"],
			"A published vehicle must reach the payment handover: " . wp_json_encode( $response )
		);
	}

	// -------------------------------------------- surface 7: price calculation

	public function test_calculate_price_refuses_an_unpublished_vehicle(): void
	{
		$response = $this->dispatch_calculate_price( $this->draft_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid vehicle ID.', $response['data']['message'] );
	}

	public function test_calculate_price_still_prices_a_published_vehicle(): void
	{
		$response = $this->dispatch_calculate_price( $this->published_id );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertGreaterThan( 0, (float) ( $response['data']['total_price'] ?? $response['data']['total'] ?? 0 ) );
	}

	// ----------------------------------------- surface 8: availability check

	public function test_check_availability_refuses_an_unpublished_vehicle(): void
	{
		$response = $this->dispatch_check_availability( $this->draft_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid vehicle ID.', $response['data']['message'] );
	}

	public function test_check_availability_still_reports_a_published_vehicle_as_available(): void
	{
		$response = $this->dispatch_check_availability( $this->published_id );

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertTrue( $response['data']['ok'] );
	}

	// ------------------------------------------------------------- fixtures

	private function seed_booking( int $vehicle_id, string $title ): int
	{
		$booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		update_post_meta( $booking_id, '_mhmrentiva_vehicle_id', $vehicle_id );
		update_post_meta( $booking_id, '_mhmrentiva_start_date', gmdate( 'Y-m-05' ) );
		update_post_meta( $booking_id, '_mhmrentiva_end_date', gmdate( 'Y-m-08' ) );
		update_post_meta( $booking_id, '_mhmrentiva_status', 'confirmed' );
		update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );

		return $booking_id;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function availability_days_for( int $vehicle_id ): array
	{
		$response = $this->dispatch_ajax( 'mhmrentiva_availability_unified', 'mhmrentiva_availability_nonce', $vehicle_id );
		$months   = $response['data']['availability_data'] ?? array();

		return $months ? (array) reset( $months )['days'] : array();
	}

	/**
	 * @param array<string, array<string, mixed>> $days
	 * @return array<string, mixed>|null
	 */
	private function first_occupied_day( array $days ): ?array
	{
		foreach ( $days as $day ) {
			if ( ! empty( $day['occupancy'] ) ) {
				return $day;
			}
		}

		return null;
	}

	/**
	 * Dispatch a `nopriv` handler exactly as an anonymous visitor would.
	 *
	 * The nonce is minted for the current (logged-out) user, which is precisely
	 * the point: these nonces are printed into public pages, so holding one is
	 * not evidence of anything. The gate under test must hold anyway.
	 *
	 * @return array<string, mixed>
	 */
	private function dispatch_ajax( string $action, string $nonce_action, int $vehicle_id ): array
	{
		return $this->dispatch_raw_ajax(
			$action,
			array(
				'action'         => $action,
				'nonce'          => wp_create_nonce( $nonce_action ),
				'vehicle_id'     => (string) $vehicle_id,
				'start_month'    => gmdate( 'Y-m' ),
				'months_to_show' => '1',
			)
		);
	}

	private function dispatch_calendar( int $vehicle_id ): array
	{
		return $this->dispatch_raw_ajax(
			'mhmrentiva_get_calendar',
			array(
				'action'     => 'mhmrentiva_get_calendar',
				'nonce'      => wp_create_nonce( 'mhmrentiva_calendar_nonce' ),
				'vehicle_id' => (string) $vehicle_id,
				'month'      => gmdate( 'n' ),
				'year'       => gmdate( 'Y' ),
			)
		);
	}

	private function dispatch_booking_form( int $vehicle_id ): array
	{
		return $this->dispatch_raw_ajax(
			'mhmrentiva_booking_form',
			array(
				'action'              => 'mhmrentiva_booking_form',
				'nonce'               => wp_create_nonce( 'mhmrentiva_booking_form_nonce' ),
				'vehicle_id'          => (string) $vehicle_id,
				'pickup_date'         => gmdate( 'Y-m-20' ),
				'pickup_time'         => '10:00',
				'dropoff_date'        => gmdate( 'Y-m-22' ),
				'dropoff_time'        => '10:00',
				'customer_first_name' => 'Jane',
				'customer_last_name'  => 'Doe',
				'customer_email'      => 'jane@example.com',
				'customer_phone'      => '5551112233',
			)
		);
	}

	private function dispatch_calculate_price( int $vehicle_id ): array
	{
		return $this->dispatch_raw_ajax(
			'mhmrentiva_calculate_price',
			array(
				'action'       => 'mhmrentiva_calculate_price',
				'nonce'        => wp_create_nonce( 'mhmrentiva_booking_form_nonce' ),
				'vehicle_id'   => (string) $vehicle_id,
				'pickup_date'  => gmdate( 'Y-m-20' ),
				'pickup_time'  => '10:00',
				'dropoff_date' => gmdate( 'Y-m-22' ),
				'dropoff_time' => '10:00',
			)
		);
	}

	private function dispatch_check_availability( int $vehicle_id ): array
	{
		return $this->dispatch_raw_ajax(
			'mhmrentiva_check_availability',
			array(
				'action'       => 'mhmrentiva_check_availability',
				'nonce'        => wp_create_nonce( 'mhmrentiva_booking_form_nonce' ),
				'vehicle_id'   => (string) $vehicle_id,
				'pickup_date'  => gmdate( 'Y-m-20' ),
				'pickup_time'  => '10:00',
				'dropoff_date' => gmdate( 'Y-m-22' ),
				'dropoff_time' => '10:00',
			)
		);
	}

	/**
	 * @param array<string, mixed> $post
	 * @return array<string, mixed>
	 */
	private function dispatch_raw_ajax( string $action, array $post ): array
	{
		$_POST    = $post;
		$_REQUEST = $_POST;

		/*
		 * Buffer bookkeeping, both directions.
		 *
		 * `_handleAjax()` opens ONE buffer, but the harness's die handler calls
		 * `ob_get_clean()` on every wp_die() -- and there are two here, because
		 * both the priv and nopriv registrations fire and the handlers' own
		 * `catch ( Exception )` swallows the first WPAjaxDieContinueException
		 * instead of letting it unwind. The net level therefore ends up one
		 * BELOW where it started, which PHPUnit flags as a risky test. Restore
		 * it whichever way it drifted.
		 */
		$ob_level = ob_get_level();

		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The payload, not the die, is what is under test.
			unset( $e );
		} finally {
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}
			while ( ob_get_level() < $ob_level ) {
				ob_start();
			}
		}

		$response             = $this->decode_first_json( (string) $this->_last_response );
		$this->_last_response = '';

		return $response;
	}

	/**
	 * Decode the FIRST JSON payload in the buffered AJAX response.
	 *
	 * `_handleAjax()` fires both `wp_ajax_{action}` and
	 * `wp_ajax_nopriv_{action}`, and these handlers are registered on both -- so
	 * the buffer ends up holding two concatenated payloads. The second is an
	 * artifact of the harness, not of production: `wp_send_json_*()` calls
	 * `wp_die()`, which really exits in a live request but here only throws
	 * `WPAjaxDieContinueException`, which the handlers' own `catch ( Exception )`
	 * then swallows into a generic error payload on the replay.
	 *
	 * A real caller receives the first payload, so that is what these tests read.
	 * Brace-depth scanning (string- and escape-aware) rather than a `}{` split,
	 * because booking titles and messages legitimately contain braces.
	 *
	 * @return array<string, mixed>
	 */
	private function decode_first_json( string $buffer ): array
	{
		$depth     = 0;
		$in_string = false;
		$escaped   = false;

		for ( $i = 0, $len = strlen( $buffer ); $i < $len; $i++ ) {
			$char = $buffer[ $i ];

			if ( $in_string ) {
				if ( $escaped ) {
					$escaped = false;
				} elseif ( '\\' === $char ) {
					$escaped = true;
				} elseif ( '"' === $char ) {
					$in_string = false;
				}
				continue;
			}

			if ( '"' === $char ) {
				$in_string = true;
			} elseif ( '{' === $char ) {
				$depth++;
			} elseif ( '}' === $char ) {
				$depth--;
				if ( 0 === $depth ) {
					$decoded = json_decode( substr( $buffer, 0, $i + 1 ), true );
					return is_array( $decoded ) ? $decoded : array( 'success' => false );
				}
			}
		}

		return array( 'success' => false );
	}
}
