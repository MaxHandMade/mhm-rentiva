<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * AJAX endpoints for vendor self-service vehicle lifecycle actions.
 *
 * Actions: pause, resume, withdraw, renew, relist.
 * All require authenticated vendor with ownership of the vehicle.
 *
 * @since 4.24.0
 */
final class VehicleLifecycleAjaxController
{
	private const NONCE_ACTION = 'mhm_rentiva_vehicle_lifecycle';

	/**
	 * Register AJAX handlers.
	 */
	public static function register(): void
	{
		add_action('wp_ajax_mhm_vehicle_lifecycle_pause', array(self::class, 'handle_pause'));
		add_action('wp_ajax_mhm_vehicle_lifecycle_resume', array(self::class, 'handle_resume'));
		add_action('wp_ajax_mhm_vehicle_lifecycle_withdraw', array(self::class, 'handle_withdraw'));
		add_action('wp_ajax_mhm_vehicle_lifecycle_renew', array(self::class, 'handle_renew'));
		add_action('wp_ajax_mhm_vehicle_lifecycle_relist', array(self::class, 'handle_relist'));
	}

	/**
	 * Handle pause action.
	 */
	public static function handle_pause(): void
	{
		$vehicle_id = self::validate_request();
		if ($vehicle_id === 0) {
			return;
		}

		$result = VehicleLifecycleManager::pause($vehicle_id, get_current_user_id());
		self::send_result($result, __('Vehicle paused successfully.', 'mhm-rentiva'));
	}

	/**
	 * Handle resume action.
	 */
	public static function handle_resume(): void
	{
		$vehicle_id = self::validate_request();
		if ($vehicle_id === 0) {
			return;
		}

		$result = VehicleLifecycleManager::resume($vehicle_id, get_current_user_id());
		self::send_result($result, __('Vehicle resumed successfully.', 'mhm-rentiva'));
	}

	/**
	 * Handle withdraw action.
	 */
	public static function handle_withdraw(): void
	{
		$vehicle_id = self::validate_request();
		if ($vehicle_id === 0) {
			return;
		}

		$vendor_id = get_current_user_id();

		// Preview penalty before withdrawal so we can include it in the response.
		$penalty = PenaltyCalculator::calculate_withdrawal_penalty($vehicle_id, $vendor_id);

		$result = VehicleLifecycleManager::withdraw($vehicle_id, $vendor_id);

		if (is_wp_error($result)) {
			self::send_result($result, '');
			return;
		}

		$message = __('Vehicle withdrawn successfully.', 'mhm-rentiva');
		if ($penalty > 0.0) {
			$message = sprintf(
				/* translators: %s: formatted penalty amount */
				__('Vehicle withdrawn. A penalty of %s has been applied.', 'mhm-rentiva'),
				number_format($penalty, 2)
			);
		}

		wp_send_json_success(array(
			'message' => $message,
			'status'  => VehicleLifecycleStatus::WITHDRAWN,
			'penalty' => $penalty,
		));
		exit;
	}

	/**
	 * Handle renew action.
	 */
	public static function handle_renew(): void
	{
		$vehicle_id = self::validate_request();
		if ($vehicle_id === 0) {
			return;
		}

		// --- Listing Fee Payment Gate ---
		if ( ListingFeeManager::requires_payment( 'renew' ) ) {
			$checkout_url = ListingFeeManager::add_to_cart( $vehicle_id, 'renew' );
			wp_send_json_success( array(
				'requires_payment' => true,
				'checkout_url'     => $checkout_url,
				'message'          => __( 'Redirecting to payment...', 'mhm-rentiva' ),
			) );
			return;
		}

		$result = VehicleLifecycleManager::renew($vehicle_id, get_current_user_id());
		self::send_result($result, __('Listing renewed for another 90 days.', 'mhm-rentiva'));
	}

	/**
	 * Handle relist action.
	 */
	public static function handle_relist(): void
	{
		$vehicle_id = self::validate_request();
		if ($vehicle_id === 0) {
			return;
		}

		// --- Listing Fee Payment Gate ---
		if ( ListingFeeManager::requires_payment( 'relist' ) ) {
			$checkout_url = ListingFeeManager::add_to_cart( $vehicle_id, 'relist' );
			wp_send_json_success( array(
				'requires_payment' => true,
				'checkout_url'     => $checkout_url,
				'message'          => __( 'Redirecting to payment...', 'mhm-rentiva' ),
			) );
			return;
		}

		$result = VehicleLifecycleManager::relist($vehicle_id, get_current_user_id());
		self::send_result($result, __('Vehicle submitted for review.', 'mhm-rentiva'));
	}

	/**
	 * Validate the AJAX request: nonce, capability, vehicle_id.
	 *
	 * @return int Vehicle ID (0 on failure — response already sent).
	 */
	private static function validate_request(): int
	{
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		if (! current_user_can('rentiva_vendor')) {
			wp_send_json_error(array('message' => __('Unauthorized.', 'mhm-rentiva')), 403);
			exit;
		}

		$vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
		if ($vehicle_id < 1) {
			wp_send_json_error(array('message' => __('Invalid vehicle ID.', 'mhm-rentiva')));
			exit;
		}

		return $vehicle_id;
	}

	/**
	 * Send a standard JSON response for a lifecycle result.
	 *
	 * @param true|\WP_Error $result  Result from VehicleLifecycleManager.
	 * @param string         $success_message Message on success.
	 */
	private static function send_result($result, string $success_message): void
	{
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message(), 'code' => $result->get_error_code()));
			exit;
		}

		wp_send_json_success(array('message' => $success_message));
		exit;
	}
}
