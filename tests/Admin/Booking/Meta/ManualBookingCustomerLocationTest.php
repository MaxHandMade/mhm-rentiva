<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * A new customer's optional country and state (spec K6) are checked against
 * WooCommerce on the server and saved as the user's billing_country /
 * billing_state. Anything that does not belong -- an unknown country, a state
 * of another country, a state for a country WooCommerce gives none -- is
 * dropped, never stored.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox
 */
final class ManualBookingCustomerLocationTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Location Vehicle',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

		// Filters, not update_option(): booking creation runs DDL, which commits
		// the test transaction, so an option written here would outlive the test.
		add_filter('pre_option_mhmrentiva_settings', static fn () => array());
		add_filter('pre_option_woocommerce_default_country', static fn () => 'TR:TR34');
		add_filter('pre_option_woocommerce_allowed_countries', static fn () => 'all');

		ManualBookingMetaBox::register();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	/**
	 * @param array<string, string> $extra
	 * @return array<string, mixed>
	 */
	private function create(array $extra): array
	{
		$_POST = $extra + array(
			'nonce'                   => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'              => (string) $this->vehicle_id,
			'customer_id'             => 'new_customer',
			'new_customer_first_name' => 'Mehmet',
			'new_customer_last_name'  => 'Demir',
			'new_customer_email'      => 'loc-' . wp_generate_password(8, false) . '@example.com',
			'new_customer_phone'      => '05320000000',
			'pickup_date'             => '2099-06-01',
			'pickup_time'             => '10:00',
			'dropoff_date'            => '2099-06-03',
			'dropoff_time'            => '10:00',
			'payment_type'            => 'full',
			'status'                  => 'confirmed',
		);

		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}

		$raw                  = (string) $this->_last_response;
		$this->_last_response = '';
		$response             = json_decode($raw, true);
		$this->assertTrue($response['success'] ?? false, $raw);

		return $response['data'];
	}

	private function customer_of(array $data): int
	{
		return (int) get_post_meta((int) $data['booking_id'], '_mhmrentiva_customer_user_id', true);
	}

	public function test_a_listed_state_is_saved_with_its_country(): void
	{
		$user = $this->customer_of($this->create(array( 'new_customer_country' => 'TR', 'new_customer_state' => 'TR34' )));

		$this->assertGreaterThan(0, $user);
		$this->assertSame('TR', get_user_meta($user, 'billing_country', true));
		$this->assertSame('TR34', get_user_meta($user, 'billing_state', true));
	}

	public function test_free_text_state_for_a_country_without_a_list(): void
	{
		$user = $this->customer_of($this->create(array( 'new_customer_country' => 'GB', 'new_customer_state' => 'Greater London' )));

		$this->assertSame('GB', get_user_meta($user, 'billing_country', true));
		$this->assertSame('Greater London', get_user_meta($user, 'billing_state', true));
	}

	public function test_no_state_for_a_country_woocommerce_gives_none(): void
	{
		$user = $this->customer_of($this->create(array( 'new_customer_country' => 'FR', 'new_customer_state' => 'IDF' )));

		$this->assertSame('FR', get_user_meta($user, 'billing_country', true));
		$this->assertFalse(metadata_exists('user', $user, 'billing_state'));
	}

	public function test_a_state_of_another_country_is_dropped(): void
	{
		$user = $this->customer_of($this->create(array( 'new_customer_country' => 'DE', 'new_customer_state' => 'TR34' )));

		$this->assertSame('DE', get_user_meta($user, 'billing_country', true));
		$this->assertFalse(metadata_exists('user', $user, 'billing_state'));
	}

	public function test_an_unknown_country_takes_its_state_with_it(): void
	{
		$user = $this->customer_of($this->create(array( 'new_customer_country' => 'XX', 'new_customer_state' => 'TR34' )));

		$this->assertFalse(metadata_exists('user', $user, 'billing_country'));
		$this->assertFalse(metadata_exists('user', $user, 'billing_state'));
	}

	public function test_both_empty_still_creates_the_booking(): void
	{
		$data = $this->create(array( 'new_customer_country' => '', 'new_customer_state' => '' ));
		$user = $this->customer_of($data);

		$this->assertGreaterThan(0, (int) $data['booking_id']);
		$this->assertFalse(metadata_exists('user', $user, 'billing_country'));
		$this->assertFalse(metadata_exists('user', $user, 'billing_state'));
	}

	public function test_an_existing_customer_is_left_alone(): void
	{
		$existing = self::factory()->user->create(array( 'role' => 'customer' ));
		update_user_meta($existing, 'billing_country', 'DE');

		$this->create(array(
			'customer_id'          => (string) $existing,
			'new_customer_country' => 'TR',
			'new_customer_state'   => 'TR34',
		));

		$this->assertSame('DE', get_user_meta($existing, 'billing_country', true));
		$this->assertFalse(metadata_exists('user', $existing, 'billing_state'));
	}
}
