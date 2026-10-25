<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_Ajax_UnitTestCase;

/**
 * Aşama 2 PR B: the vehicle page's two endpoints answer with the same
 * partials the page renders (months; booking card total, breakdown, link).
 *
 * @group ajax
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails
 */
final class VehicleDetailsRvsAjaxTest extends WP_Ajax_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Hyundai Tucson',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize(array(
			'current_active_tab'                    => 'vehicle',
			'mhmrentiva_vehicle_base_price'         => '1.0',
			'mhmrentiva_vehicle_weekend_multiplier' => '1.0',
		)));
		$settings                                   = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_type'] = 'percentage';
		$settings['mhmrentiva_rental_deposit_rate'] = 10;
		update_option('mhmrentiva_settings', $settings);
		delete_transient('mhmrentiva_rate_limit_vehicle_quote');
	}

	public function tearDown(): void
	{
		$_POST = array();
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function call(string $action, array $post): array
	{
		$_POST = array_merge(array( 'nonce' => wp_create_nonce('mhmrentiva_vd_nonce') ), $post);
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}
		$decoded              = json_decode( (string) $this->_last_response, true);
		$this->_last_response = '';
		return is_array($decoded) ? $decoded : array();
	}

	private function quote(array $extra = array()): array
	{
		return $this->call('mhmrentiva_vd_quote', array_merge(array(
			'vehicle_id'  => (string) $this->vehicle_id,
			'pickup_date' => '2027-07-12',
			'pickup_time' => '10:00',
			'return_date' => '2027-07-15',
		), $extra));
	}

	public function test_quote_endpoint_returns_same_breakdown_as_page(): void
	{
		$response = $this->quote();
		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));

		$view = VehicleDetailsView::build($this->vehicle_id, array(), array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:00', 'return_date' => '2027-07-15' ));
		$expected = (string) Templates::render('partials/rvs/breakdown', array( 'breakdown' => $view['booking']['breakdown'], 'collapsible' => true ), true);

		$this->assertSame($expected, $response['data']['breakdown_html']);
		$this->assertSame(3, $response['data']['days']);
		$this->assertTrue($response['data']['available']);
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-07-12', $response['data']['continue_url']);
		$this->assertStringContainsString('data-rvs-calendar', $response['data']['months_html']);
		$this->assertSame($view['booking']['breakdown']['total']['html'], $response['data']['total_html']);
	}

	public function test_quote_endpoint_reports_unavailable_window(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2027-07-13');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2027-07-14');
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');

		$data = $this->quote()['data'] ?? array();

		$this->assertFalse($data['available']);
		$this->assertNotSame('', $data['message']);
	}

	public function test_months_endpoint_returns_two_months_from_the_given_one(): void
	{
		$response = $this->call('mhmrentiva_vd_months', array(
			'vehicle_id' => (string) $this->vehicle_id,
			'year'       => '2027',
			'month'      => '12',
		));

		$this->assertTrue($response['success'] ?? false);
		$this->assertSame(2, substr_count($response['data']['html'], 'data-rvs-calendar'));
		$this->assertStringContainsString('data-year="2027" data-month="12"', $response['data']['html']);
		$this->assertStringContainsString('data-year="2028" data-month="1"', $response['data']['html']);
	}

	public function test_months_endpoint_rejects_unpublished_vehicle(): void
	{
		wp_update_post(array( 'ID' => $this->vehicle_id, 'post_status' => 'draft' ));

		$this->assertFalse($this->call('mhmrentiva_vd_months', array( 'vehicle_id' => (string) $this->vehicle_id, 'year' => '2027', 'month' => '7' ))['success'] ?? true);
		$this->assertFalse($this->quote()['success'] ?? true);
	}

	public function test_password_protected_vehicle_rejected(): void
	{
		wp_update_post(array( 'ID' => $this->vehicle_id, 'post_password' => 'secret' ));

		$this->assertFalse($this->call('mhmrentiva_vd_months', array( 'vehicle_id' => (string) $this->vehicle_id, 'year' => '2027', 'month' => '7' ))['success'] ?? true);
		$this->assertFalse($this->quote()['success'] ?? true);
	}

	public function test_bad_nonce_rejected(): void
	{
		$response = $this->call('mhmrentiva_vd_quote', array( 'nonce' => 'bad', 'vehicle_id' => (string) $this->vehicle_id ));
		$this->assertFalse($response['success'] ?? true);
	}
}
