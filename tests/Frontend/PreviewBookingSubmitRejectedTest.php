<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use WP_Ajax_UnitTestCase;

/**
 * Slice 3B-2a (Codex P1): booking-form.js re-enables the submit button that the
 * preview markup disables, and submitForm() posts
 * `mhmRentivaBookingForm.nonce`. In a preview that value is '' (see
 * PreviewScriptNonceTest), so the server must refuse the request before it
 * reaches the cart/checkout flow. This pins the refusal for every endpoint the
 * script calls with that nonce.
 */
final class PreviewBookingSubmitRejectedTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private const ENDPOINTS = array(
		'mhmrentiva_booking_form'        => 'ajax_booking_form',
		'mhmrentiva_calculate_price'     => 'ajax_calculate_price',
		'mhmrentiva_check_availability'  => 'ajax_check_availability',
	);

	public function setUp(): void
	{
		parent::setUp();
		foreach (self::ENDPOINTS as $action => $method) {
			if (false === has_action('wp_ajax_' . $action)) {
				add_action('wp_ajax_' . $action, array( BookingForm::class, $method ));
			}
		}
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function endpoints(): array
	{
		$rows = array();
		foreach (array_keys(self::ENDPOINTS) as $action) {
			$rows[ $action ] = array( $action );
		}
		return $rows;
	}

	/**
	 * @dataProvider endpoints
	 */
	public function test_empty_nonce_is_refused(string $action): void
	{
		global $wpdb;
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		$before  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mhmrentiva_booking'");

		$response = $this->post($action, '', $vehicle);

		$this->assertFalse($response['success'] ?? null, "$action accepted an empty nonce.");
		$this->assertStringContainsString('Security check failed', (string) wp_json_encode($response['data'] ?? ''));
		$this->assertSame($before, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mhmrentiva_booking'"));
	}

	/**
	 * Control: with the real nonce the request gets past the nonce check, so the
	 * refusal above is the nonce's doing.
	 *
	 * @dataProvider endpoints
	 */
	public function test_real_nonce_passes_the_check(string $action): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));

		$response = $this->post($action, wp_create_nonce('mhmrentiva_booking_form_nonce'), $vehicle);

		$this->assertStringNotContainsString('Security check failed', (string) wp_json_encode($response['data'] ?? ''));
	}

	/**
	 * @return array<string,mixed>
	 */
	private function post(string $action, string $nonce, int $vehicle): array
	{
		$this->_last_response = '';
		$_POST                = array(
			'action'      => $action,
			'nonce'       => $nonce,
			'vehicle_id'  => (string) $vehicle,
			'pickup_date' => gmdate('Y-m-d', strtotime('+10 days')),
			'return_date' => gmdate('Y-m-d', strtotime('+12 days')),
		);

		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			// Expected: wp_send_json_*() always wp_die()s.
			unset($e);
		} catch (\WPAjaxDieStopException $e) {
			unset($e);
		}

		$decoded = json_decode((string) $this->_last_response, true);
		return is_array($decoded) ? $decoded : array();
	}
}
