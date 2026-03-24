<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Deposit;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Deposit Calculator Class
 *
 * This class manages vehicle deposit calculations.
 * Supports fixed amount and percentage deposit options.
 */
final class DepositCalculator
{


	/**
	 * Calculate deposit
	 *
	 * @param string $deposit_value Deposit value (strictly percentage, e.g., "10")
	 * @param float  $daily_price Daily price
	 * @param int    $rental_days Rental days
	 * @return array Calculation results
	 */
	public static function calculate_deposit(string $deposit_value, float $daily_price, int $rental_days = 1): array
	{
		if (empty($deposit_value) || $daily_price <= 0) {
			return array(
				'deposit_amount'   => 0,
				'deposit_type'     => 'none',
				'total_amount'     => $daily_price * $rental_days,
				'remaining_amount' => $daily_price * $rental_days,
				'daily_price'      => round($daily_price, 2),
			);
		}

		$deposit_type   = self::get_deposit_type($deposit_value);
		$deposit_amount = 0;

		if ($deposit_type === 'percentage') {
			$percentage     = self::extract_percentage($deposit_value);
			$total_amount   = $daily_price * $rental_days;
			$deposit_amount = ($total_amount * $percentage) / 100;
		}

		$total_amount     = $daily_price * $rental_days;
		$remaining_amount = max(0, $total_amount - $deposit_amount);

		return array(
			'deposit_amount'     => round($deposit_amount, 2),
			'deposit_type'       => $deposit_type,
			'total_amount'       => round($total_amount, 2),
			'remaining_amount'   => round($remaining_amount, 2),
			'deposit_percentage' => $deposit_type === 'percentage' ? self::extract_percentage($deposit_value) : 0,
			'daily_price'        => round($daily_price, 2),
		);
	}

	/**
	 * Determine deposit type
	 *
	 * @param string $deposit_value Deposit value
	 * @return string 'percentage', 'none'
	 */
	public static function get_deposit_type(string $deposit_value): string
	{
		$deposit_value = trim($deposit_value, " \t\n\r\0\x0B%");

		if (empty($deposit_value) || ! is_numeric($deposit_value)) {
			return 'none';
		}

		return 'percentage';
	}

	/**
	 * Extract percentage value
	 *
	 * @param string $deposit_value Deposit value
	 * @return float Percentage value
	 */
	public static function extract_percentage(string $deposit_value): float
	{
		$deposit_value = trim($deposit_value, " \t\n\r\0\x0B%");
		return floatval($deposit_value);
	}

	/**
	 * Extract fixed amount (Legacy/Removed)
	 *
	 * @param string $deposit_value Deposit value
	 * @return float 0
	 */
	public static function extract_fixed_amount(string $deposit_value): float
	{
		return 0;
	}

	/**
	 * Format deposit display
	 *
	 * @param string $deposit_value Deposit value
	 * @return string Formatted display
	 */
	public static function format_deposit_display(string $deposit_value): string
	{
		$type = self::get_deposit_type($deposit_value);

		if ($type === 'percentage') {
			$percentage = self::extract_percentage($deposit_value);
			return '%' . $percentage;
		}

		return '';
	}

	/**
	 * Get deposit description
	 *
	 * @param string $deposit_value Deposit value
	 * @return string Description
	 */
	public static function get_deposit_description(string $deposit_value): string
	{
		$type = self::get_deposit_type($deposit_value);

		if ($type === 'percentage') {
			$percentage = self::extract_percentage($deposit_value);
			/* translators: %.1f: percentage value */
			return sprintf(__('%.1f%% of total amount', 'mhm-rentiva'), $percentage);
		}

		return __('No deposit', 'mhm-rentiva');
	}

	/**
	 * Calculate deposit for all vehicles
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @param int $rental_days Rental days
	 * @return array Calculation results
	 */
	public static function calculate_vehicle_deposit(int $vehicle_id, int $rental_days = 1): array
	{
		$daily_price   = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true));
		$deposit_value = self::get_vehicle_deposit_value($vehicle_id);

		return self::calculate_deposit($deposit_value, $daily_price, $rental_days);
	}

	/**
	 * Get deposit options
	 *
	 * @return array Deposit options
	 */
	public static function get_deposit_options(): array
	{
		return array(
			'none'       => __('No Deposit', 'mhm-rentiva'),
			'percentage' => __('Percentage', 'mhm-rentiva'),
		);
	}

	/**
	 * Calculate deposit for booking
	 *
	 * @param int    $vehicle_id Vehicle ID
	 * @param int    $rental_days Rental days
	 * @param string $payment_type Payment type (deposit/full)
	 * @param array  $addons Addon service IDs
	 * @param int    $start_ts Start timestamp for weekend multiplier
	 * @return array Calculation results
	 */
	public static function calculate_booking_deposit(int $vehicle_id, int $rental_days = 1, string $payment_type = 'deposit', array $addons = array(), int $start_ts = 0): array
	{
		$daily_price   = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true));
		$deposit_value = self::get_vehicle_deposit_value($vehicle_id);

		if ($daily_price <= 0) {
			return array(
				'success'          => false,
				'error'            => __('Invalid daily price', 'mhm-rentiva'),
				'deposit_amount'   => 0,
				'total_amount'     => 0,
				'remaining_amount' => 0,
			);
		}

		// ⭐ Fix: Use Util::total_price for correct calculation (weekend multipliers etc.)
		$vehicle_total = \MHMRentiva\Admin\Booking\Helpers\Util::total_price($vehicle_id, $rental_days, $start_ts);

		$addon_total = 0;
		foreach ($addons as $addon_id) {
			$addon_price  = floatval(get_post_meta($addon_id, 'addon_price', true) ?: 0);
			$addon_total += $addon_price * $rental_days;
		}

		$total_amount = $vehicle_total + $addon_total;

		if ($payment_type === 'full') {
			return array(
				'success'          => true,
				'payment_type'     => 'full',
				'deposit_amount'   => $total_amount,
				'total_amount'     => $total_amount,
				'remaining_amount' => 0,
				'deposit_type'     => 'full_payment',
				'payment_display'  => __('Full Payment', 'mhm-rentiva'),
				'vehicle_total'    => $vehicle_total,
				'addon_total'      => $addon_total,
			);
		}

		$deposit_type   = self::get_deposit_type($deposit_value);
		$deposit_amount = 0;

		if ($deposit_type === 'percentage') {
			$percentage     = self::extract_percentage($deposit_value);
			$deposit_amount = ($total_amount * $percentage) / 100;
		}

		$remaining_amount = max(0, $total_amount - $deposit_amount);

		return array(
			'success'          => true,
			'payment_type'     => 'deposit',
			'deposit_amount'   => round($deposit_amount, 2),
			'total_amount'     => round($total_amount, 2), // Total amount (vehicle + addons)
			'remaining_amount' => round($remaining_amount, 2), // Total amount minus deposit
			'deposit_type'     => $deposit_type,
			'payment_display'  => $deposit_amount > 0 ?
				/* translators: 1: deposit amount; 2: currency symbol. */
				sprintf(__('Deposit: %1$s %2$s', 'mhm-rentiva'), number_format($deposit_amount, 2, ',', '.'), \MHMRentiva\Admin\Reports\Reports::get_currency_symbol()) :
				__('No Deposit', 'mhm-rentiva'),
			'vehicle_total'    => round($vehicle_total, 2),
			'addon_total'      => round($addon_total, 2),
		);
	}

	/**
	 * Get payment options
	 *
	 * @return array Payment options
	 */
	public static function get_payment_options(): array
	{
		return array(
			'deposit' => __('Deposit Payment', 'mhm-rentiva'),
			'full'    => __('Full Payment', 'mhm-rentiva'),
		);
	}

	/**
	 * Get payment methods
	 *
	 * @return array Payment methods
	 */
	public static function get_payment_methods(): array
	{
		// WooCommerce only - All payments go through WooCommerce
		if (! class_exists('WooCommerce')) {
			// WooCommerce is required
			return array();
		}

		return array(
			'woocommerce' => __('Pay via WooCommerce', 'mhm-rentiva'),
		);
	}

	/**
	 * Validate payment method
	 *
	 * @param string $payment_method Payment method
	 * @return bool Is valid
	 */
	public static function validate_payment_method(string $payment_method): bool
	{
		$valid_methods = array_keys(self::get_payment_methods());
		return in_array($payment_method, $valid_methods, true);
	}

	/**
	 * Validate payment type
	 *
	 * @param string $payment_type Payment type
	 * @return bool Is valid
	 */
	public static function validate_payment_type(string $payment_type): bool
	{
		$valid_types = array_keys(self::get_payment_options());
		return in_array($payment_type, $valid_types, true);
	}

	/**
	 * Calculate remaining amount
	 *
	 * @param float $total_amount Total amount
	 * @param float $paid_amount Paid amount
	 * @return float Remaining amount
	 */
	public static function get_remaining_amount(float $total_amount, float $paid_amount): float
	{
		return max(0, $total_amount - $paid_amount);
	}

	/**
	 * Get payment information for booking
	 *
	 * @param int $booking_id Booking ID
	 * @return array Payment information
	 */
	public static function get_booking_payment_info(int $booking_id): array
	{
		$payment_type     = get_post_meta($booking_id, '_mhm_payment_type', true);
		$payment_method   = get_post_meta($booking_id, '_mhm_payment_method', true);
		$deposit_amount   = floatval(get_post_meta($booking_id, '_mhm_deposit_amount', true));
		$total_amount     = floatval(get_post_meta($booking_id, '_mhm_total_price', true));
		$remaining_amount = floatval(get_post_meta($booking_id, '_mhm_remaining_amount', true));
		$payment_deadline = get_post_meta($booking_id, '_mhm_payment_deadline', true);

		return array(
			'payment_type'     => $payment_type,
			'payment_method'   => $payment_method,
			'deposit_amount'   => $deposit_amount,
			'total_amount'     => $total_amount,
			'remaining_amount' => $remaining_amount,
			'payment_deadline' => $payment_deadline,
			'is_paid'          => $remaining_amount <= 0,
			'is_deposit_paid'  => $deposit_amount > 0 && $payment_type === 'deposit',
		);
	}

	/**
	 * Get deposit value for a vehicle with universal default fallback
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Deposit value
	 */
	public static function get_vehicle_deposit_value(int $vehicle_id): string
	{
		$value = get_post_meta($vehicle_id, '_mhm_rentiva_deposit', true);

		// Universal Default: 10% (v4.9.0)
		if ($value === '' || $value === null || $value === '0' || $value === 0) {
			return '10';
		}

		return (string) $value;
	}
}
