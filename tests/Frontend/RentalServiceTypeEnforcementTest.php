<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\REST\Availability;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use ReflectionMethod;
use WP_Ajax_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Server-side half of the service-type fix: a "transfer only" vehicle
 * (`_mhmrentiva_vehicle_service_type = transfer`) must not be rentable through
 * ANY rental entry point, whatever the browser sends.
 *
 * Hiding it from listings (VehicleServiceTypeVisibilityTest) is not a control:
 * every endpoint below is nopriv or reachable with a hand-crafted id.
 *
 * The transfer checkout -- the add-on's TransferCartIntegration -- goes through
 * the SAME WooCommerceBridge cart and create_booking_from_data(), marked with
 * `booking_type => 'transfer'`. The rental guard must leave that flow alone, so
 * each bridge test has a transfer-flagged positive control.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_booking_form
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_check_availability
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::check_availability
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::get_alternative_vehicles
 * @covers \MHMRentiva\Admin\REST\Availability::check
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_cart_availability
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_checkout_availability
 */
final class RentalServiceTypeEnforcementTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceFixtures;

	private const REFUSAL = 'This vehicle is not available for rental. Please select another vehicle.';

	private int $transfer_id;
	private int $both_id;

	/** @var string */
	protected $_last_response;

	public function setUp(): void
	{
		parent::setUp();

		BookingForm::register();

		$this->transfer_id = $this->make_vehicle('Transfer Only Vito', 'transfer');
		$this->both_id     = $this->make_vehicle('Both Minivan', 'both');

		wp_set_current_user(0);
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		}
		parent::tearDown();
	}

	// ------------------------------------------------- booking form AJAX

	public function test_booking_form_refuses_a_transfer_only_vehicle(): void
	{
		$response = $this->dispatch('mhmrentiva_booking_form', $this->transfer_id, $this->customer_fields());

		$this->assertFalse($response['success']);
		$this->assertSame(self::REFUSAL, $response['data']['message']);
	}

	public function test_booking_form_still_processes_a_both_type_vehicle(): void
	{
		$response = $this->dispatch('mhmrentiva_booking_form', $this->both_id, $this->customer_fields());

		$this->assertTrue($response['success'], wp_json_encode($response));
	}

	public function test_price_calculation_refuses_a_transfer_only_vehicle(): void
	{
		$response = $this->dispatch('mhmrentiva_calculate_price', $this->transfer_id);

		$this->assertFalse($response['success']);
		$this->assertSame(self::REFUSAL, $response['data']['message']);
	}

	public function test_price_calculation_still_prices_a_both_type_vehicle(): void
	{
		$response = $this->dispatch('mhmrentiva_calculate_price', $this->both_id);

		$this->assertTrue($response['success'], wp_json_encode($response));
	}

	public function test_availability_check_does_not_call_a_transfer_only_vehicle_available(): void
	{
		$response = $this->dispatch('mhmrentiva_check_availability', $this->transfer_id);

		$this->assertFalse($response['success'], wp_json_encode($response));
		$this->assertFalse((bool) ( $response['data']['ok'] ?? true ));
	}

	public function test_availability_check_still_reports_a_both_type_vehicle_as_available(): void
	{
		$response = $this->dispatch('mhmrentiva_check_availability', $this->both_id);

		$this->assertTrue($response['success'], wp_json_encode($response));
		$this->assertTrue($response['data']['ok']);
	}

	// ------------------------------------- shared availability (REST + admin)

	public function test_shared_availability_check_refuses_a_transfer_only_vehicle(): void
	{
		$result = Util::check_availability($this->transfer_id, $this->date(10), '10:00', $this->date(12), '10:00');

		$this->assertFalse($result['ok']);
		$this->assertSame('service_unavailable', $result['code']);
		$this->assertSame(self::REFUSAL, $result['message']);

		$this->assertTrue(
			Util::check_availability($this->both_id, $this->date(10), '10:00', $this->date(12), '10:00')['ok'],
			'Positive control: a both-type vehicle is available.'
		);
	}

	public function test_alternative_suggestions_never_offer_a_transfer_only_vehicle(): void
	{
		$alternatives = Util::get_alternative_vehicles($this->both_id, $this->date(10), '10:00', $this->date(12), '10:00', 20);
		$ids          = array_map('intval', wp_list_pluck($alternatives, 'id'));

		$untyped = $this->make_vehicle('Untyped Sedan', null);
		$with    = array_map('intval', wp_list_pluck(Util::get_alternative_vehicles($this->both_id, $this->date(10), '10:00', $this->date(12), '10:00', 20), 'id'));

		$this->assertNotContains($this->transfer_id, $ids);
		$this->assertContains($untyped, $with, 'Positive control: an untyped vehicle is still suggested.');
	}

	public function test_rest_availability_refuses_a_transfer_only_vehicle(): void
	{
		$refused = $this->rest_availability($this->transfer_id);
		$allowed = $this->rest_availability($this->both_id);

		$this->assertSame(400, $refused->get_status());
		$this->assertSame('service_unavailable', $refused->get_data()['code']);
		$this->assertSame(200, $allowed->get_status(), wp_json_encode($allowed->get_data()));
	}

	// ----------------------------------------------- WooCommerce bridge

	public function test_booking_creation_refuses_a_rental_of_a_transfer_only_vehicle(): void
	{
		$before = $this->booking_count();

		$this->assertNull($this->create_booking($this->booking_data($this->transfer_id)));
		$this->assertSame($before, $this->booking_count(), 'No booking row may be written.');
	}

	public function test_booking_creation_still_accepts_a_transfer_booking_of_a_transfer_only_vehicle(): void
	{
		$data                 = $this->booking_data($this->transfer_id);
		$data['booking_type'] = 'transfer';

		$booking_id = $this->create_booking($data);

		$this->assertIsInt($booking_id, 'The add-on transfer checkout uses this same method and must keep working.');
		$this->assertGreaterThan(0, $booking_id);
	}

	public function test_a_transfer_origin_also_marks_a_transfer_booking(): void
	{
		// Mailer's second transfer marker; a cart item carrying it is a transfer.
		$data                       = $this->booking_data($this->transfer_id);
		$data['transfer_origin_id'] = 7;

		$this->assertIsInt($this->create_booking($data));
	}

	public function test_booking_creation_still_accepts_a_rental_of_a_both_type_vehicle(): void
	{
		$this->assertIsInt($this->create_booking($this->booking_data($this->both_id)));
	}

	public function test_checkout_validation_blocks_a_rental_of_a_transfer_only_vehicle(): void
	{
		$this->require_woocommerce();
		$this->ensure_booking_product();
		WC()->cart->empty_cart();
		wc_clear_notices();

		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->booking_data($this->transfer_id), 100.0));

		WooCommerceBridge::validate_checkout_availability();

		$this->assertSame(1, wc_notice_count('error'), 'Checkout must be stopped before payment.');
	}

	public function test_checkout_validation_lets_a_transfer_booking_through(): void
	{
		$this->require_woocommerce();
		$this->ensure_booking_product();
		WC()->cart->empty_cart();
		wc_clear_notices();

		$data                 = $this->booking_data($this->transfer_id);
		$data['booking_type'] = 'transfer';
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($data, 100.0));

		WooCommerceBridge::validate_checkout_availability();

		$this->assertSame(0, wc_notice_count('error'));
	}

	public function test_cart_validation_removes_a_rental_of_a_transfer_only_vehicle(): void
	{
		$this->require_woocommerce();
		$this->ensure_booking_product();
		WC()->cart->empty_cart();
		wc_clear_notices();

		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->booking_data($this->transfer_id), 100.0));
		$transfer_data                 = $this->booking_data($this->transfer_id);
		$transfer_data['booking_type'] = 'transfer';
		$transfer_data['pickup_date']  = $this->date(20);
		$transfer_data['dropoff_date'] = $this->date(20);
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($transfer_data, 100.0));

		WooCommerceBridge::validate_cart_availability();

		$remaining = array_values(WC()->cart->get_cart());
		$this->assertCount(1, $remaining, 'Only the rental item is removed.');
		$this->assertSame('transfer', $remaining[0]['mhmrentiva_booking_data']['booking_type'] ?? '');
		$this->assertSame(1, wc_notice_count('error'));
	}

	// ------------------------------------------------------------- fixtures

	private function make_vehicle(string $title, ?string $service): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta($id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');
		if (null !== $service) {
			update_post_meta($id, '_mhmrentiva_vehicle_service_type', $service);
		}

		return $id;
	}

	private function date(int $days_ahead): string
	{
		return gmdate('Y-m-d', strtotime('+' . $days_ahead . ' days'));
	}

	/**
	 * @return array<string, string>
	 */
	private function customer_fields(): array
	{
		return array(
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Doe',
			'customer_email'      => 'jane@example.com',
			'customer_phone'      => '5551112233',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function booking_data(int $vehicle_id): array
	{
		return array(
			'vehicle_id'            => $vehicle_id,
			'pickup_date'           => $this->date(5),
			'pickup_time'           => '10:00',
			'dropoff_date'          => $this->date(7),
			'dropoff_time'          => '10:00',
			'guests'                => 1,
			'customer_user_id'      => 0,
			'customer_name'         => 'Jane Customer',
			'customer_first_name'   => 'Jane',
			'customer_last_name'    => 'Customer',
			'customer_email'        => 'jane@example.com',
			'customer_phone'        => '5551234567',
			'payment_type'          => 'full',
			'payment_method'        => 'woocommerce',
			'payment_gateway'       => '',
			'deposit_amount'        => 100,
			'remaining_amount'      => 0,
			'deposit_type'          => '',
			'payment_display'       => 'Full',
			'total_price'           => 100,
			'rental_days'           => 2,
			'selected_addons'       => array(),
			'cancellation_policy'   => 'flexible',
			'cancellation_deadline' => gmdate('Y-m-d H:i:s', strtotime('+24 hours')),
			'payment_deadline'      => gmdate('Y-m-d H:i:s', strtotime('+48 hours')),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function create_booking(array $data): ?int
	{
		$method = new ReflectionMethod(WooCommerceBridge::class, 'create_booking_from_data');
		$method->setAccessible(true);

		return $method->invoke(null, $data, 0);
	}

	private function booking_count(): int
	{
		return count(
			get_posts(
				array(
					'post_type'      => 'mhmrentiva_booking',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
	}

	private function rest_availability(int $vehicle_id): \WP_REST_Response
	{
		global $wp_rest_server;
		add_action('rest_api_init', array( Availability::class, 'register_routes' ));
		$wp_rest_server = new WP_REST_Server();
		do_action('rest_api_init', $wp_rest_server);
		remove_action('rest_api_init', array( Availability::class, 'register_routes' ));

		$request = new WP_REST_Request('GET', '/mhm-rentiva/v1/availability');
		$request->set_param('vehicle_id', $vehicle_id);
		$request->set_param('pickup_date', $this->date(10));
		$request->set_param('pickup_time', '10:00');
		$request->set_param('dropoff_date', $this->date(12));
		$request->set_param('dropoff_time', '10:00');

		return Availability::check($request);
	}

	/**
	 * @param array<string, string> $extra
	 * @return array<string, mixed>
	 */
	private function dispatch(string $action, int $vehicle_id, array $extra = array()): array
	{
		$_POST    = array_merge(
			array(
				'action'       => $action,
				'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
				'vehicle_id'   => (string) $vehicle_id,
				'pickup_date'  => $this->date(10),
				'pickup_time'  => '10:00',
				'dropoff_date' => $this->date(12),
				'dropoff_time' => '10:00',
			),
			$extra
		);
		$_REQUEST = $_POST;

		// Same buffer bookkeeping as PublicSurfaceUnpublishedDisclosureTest:
		// priv + nopriv both fire, so the level drifts one way or the other.
		$ob_level = ob_get_level();

		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The payload, not the die, is what is under test.
			unset($e);
		} finally {
			while (ob_get_level() > $ob_level) {
				ob_end_clean();
			}
			while (ob_get_level() < $ob_level) {
				ob_start();
			}
		}

		$buffer               = (string) $this->_last_response;
		$this->_last_response = '';

		return $this->decode_first_json($buffer);
	}

	/**
	 * First JSON document in the buffer (the second is a harness replay).
	 *
	 * @return array<string, mixed>
	 */
	private function decode_first_json(string $buffer): array
	{
		$depth     = 0;
		$in_string = false;
		$escaped   = false;

		for ($i = 0, $len = strlen($buffer); $i < $len; $i++) {
			$char = $buffer[ $i ];

			if ($in_string) {
				if ($escaped) {
					$escaped = false;
				} elseif ('\\' === $char) {
					$escaped = true;
				} elseif ('"' === $char) {
					$in_string = false;
				}
				continue;
			}

			if ('"' === $char) {
				$in_string = true;
			} elseif ('{' === $char) {
				$depth++;
			} elseif ('}' === $char) {
				$depth--;
				if (0 === $depth) {
					$decoded = json_decode(substr($buffer, 0, $i + 1), true);
					return is_array($decoded) ? $decoded : array( 'success' => false );
				}
			}
		}

		return array( 'success' => false );
	}
}
