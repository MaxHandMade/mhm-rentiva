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
final class DepositCalculator {

	public const CONTEXT_RENTAL   = 'rental';
	public const CONTEXT_TRANSFER = 'transfer';

	public const TYPE_FULL_PAYMENT = 'full_payment';
	public const TYPE_PERCENTAGE   = 'percentage';

	/**
	 * Global deposit settings per flow. The transfer defaults are the add-on's
	 * (TransferSettings::get_default_settings()), so a site without it charges
	 * transfers in full, as it always has.
	 */
	private const GLOBAL_SETTINGS = array(
		self::CONTEXT_RENTAL   => array(
			'type'         => 'mhmrentiva_rental_deposit_type',
			'rate'         => 'mhmrentiva_rental_deposit_rate',
			'default_type' => self::TYPE_PERCENTAGE,
			'default_rate' => 10,
		),
		self::CONTEXT_TRANSFER => array(
			'type'         => 'mhmrentiva_transfer_deposit_type',
			'rate'         => 'mhmrentiva_transfer_deposit_rate',
			'default_type' => self::TYPE_FULL_PAYMENT,
			'default_rate' => 20,
		),
	);

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
			$deposit_amount = ( $total_amount * $percentage ) / 100;
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
			return \MHMRentiva\Admin\Core\CurrencyHelper::format_percentage(self::extract_percentage($deposit_value));
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
		$daily_price   = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);
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
		// Same price source RentalPricing reads, so the guard and the amount agree.
		$daily_price = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);
		$rate        = self::resolve_rate($vehicle_id, self::CONTEXT_RENTAL);

		// No deposit applies: whatever the caller asked for, a "deposit" of 0
		// would let the booking through unpaid, so the whole total is due.
		if ($rate <= 0) {
			$payment_type = 'full';
		}

		if ($daily_price <= 0) {
			return array(
				'success'          => false,
				'error'            => __('Invalid daily price', 'mhm-rentiva'),
				'deposit_amount'   => 0,
				'total_amount'     => 0,
				'remaining_amount' => 0,
			);
		}

		// RentalPricing is the one computation the quote, the cart and the
		// stored booking total all share (season, weekend, add-ons).
		$quote         = \MHMRentiva\Admin\Booking\Pricing\RentalPricing::quote_for_days($vehicle_id, $rental_days, $start_ts, $addons);
		$vehicle_total = $quote->rental_amount;
		$addon_total   = $quote->addon_total;

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

		$deposit_type     = self::TYPE_PERCENTAGE;
		$deposit_amount   = ( $total_amount * $rate ) / 100;
		$remaining_amount = max(0, $total_amount - $deposit_amount);

		return array(
			'success'          => true,
			'payment_type'     => 'deposit',
			'deposit_amount'   => round($deposit_amount, 2),
			'total_amount'     => round($total_amount, 2), // Total amount (vehicle + addons)
			'remaining_amount' => round($remaining_amount, 2), // Total amount minus deposit
			'deposit_type'     => $deposit_type,
			'payment_display'  => $deposit_amount > 0 ?
				/* translators: %s: formatted deposit amount including the currency symbol. */
				sprintf(__('Deposit: %s', 'mhm-rentiva'), \MHMRentiva\Admin\Core\CurrencyHelper::format_price($deposit_amount, 2)) :
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
		$payment_type     = get_post_meta($booking_id, '_mhmrentiva_payment_type', true);
		$payment_method   = get_post_meta($booking_id, '_mhmrentiva_payment_method', true);
		$deposit_amount   = floatval(get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true));
		$total_amount     = floatval(get_post_meta($booking_id, '_mhmrentiva_total_price', true));
		$remaining_amount = floatval(get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true));
		$payment_deadline = get_post_meta($booking_id, '_mhmrentiva_payment_deadline', true);

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
	 * The vehicle's deposit rate as a string, for callers that still take one.
	 *
	 * Kept public for backward compatibility. It used to turn an empty or '0'
	 * field into a hard-coded 10%; it now reports the rate resolve_rate()
	 * settles on for the rental flow, and '' when that rate is 0 (full payment).
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Deposit percentage, or '' for no deposit
	 */
	public static function get_vehicle_deposit_value(int $vehicle_id): string
	{
		$rate = self::resolve_rate($vehicle_id, self::CONTEXT_RENTAL);

		return $rate > 0 ? (string) $rate : '';
	}

	/**
	 * The deposit percentage a booking of this vehicle pays up front.
	 *
	 * The vehicle's own `_mhmrentiva_deposit` overrides when it holds a number
	 * in (0, 100], in every flow. Otherwise the flow's global setting applies:
	 * the rental settings on the Payment tab, or the transfer settings. A
	 * "Full payment" setting -- and any flow this method does not know --
	 * resolves to 0, which means no deposit: the whole total is charged.
	 *
	 * @param int    $vehicle_id Vehicle ID.
	 * @param string $context    'rental' or 'transfer'.
	 * @return float Percentage in [0, 100]; 0 = full payment required.
	 */
	public static function resolve_rate(int $vehicle_id, string $context = self::CONTEXT_RENTAL): float
	{
		if (! isset(self::GLOBAL_SETTINGS[ $context ])) {
			return 0.0;
		}

		$own = self::vehicle_override_rate($vehicle_id);
		if (null !== $own) {
			return $own;
		}

		return self::global_rate($context);
	}

	/**
	 * The flow's site-wide deposit percentage, ignoring any vehicle override.
	 *
	 * @param string $context 'rental' or 'transfer'.
	 * @return float Percentage in [0, 100]; 0 = full payment required.
	 */
	public static function global_rate(string $context = self::CONTEXT_RENTAL): float
	{
		if (! isset(self::GLOBAL_SETTINGS[ $context ])) {
			return 0.0;
		}

		$keys = self::GLOBAL_SETTINGS[ $context ];
		$type = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get($keys['type'], $keys['default_type']);
		if (self::TYPE_PERCENTAGE !== $type) {
			return 0.0;
		}

		$rate = \MHMRentiva\Admin\Settings\Core\SettingsCore::get($keys['rate'], $keys['default_rate']);
		if (! is_numeric($rate)) {
			$rate = $keys['default_rate'];
		}

		return max(0.0, min(100.0, (float) $rate));
	}

	/**
	 * Normalise a vehicle deposit field value before it is stored.
	 *
	 * Empty, zero, negative and non-numeric input all mean "use the global
	 * setting" and are stored as ''. Anything else is clamped to 1-100.
	 *
	 * @param mixed $value Raw field value.
	 * @return string '' or a percentage between 1 and 100.
	 */
	public static function normalize_vehicle_rate(mixed $value): string
	{
		$clean = is_scalar($value) ? str_replace(array( '%', ' ' ), '', (string) $value) : '';
		if ('' === $clean || ! is_numeric($clean) || (float) $clean <= 0) {
			return '';
		}

		return (string) max(1.0, min(100.0, (float) $clean));
	}

	/**
	 * The vehicle's own rate, or null when its field does not override.
	 */
	private static function vehicle_override_rate(int $vehicle_id): ?float
	{
		$value = get_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_DEPOSIT, true);
		$value = is_scalar($value) ? trim(str_replace('%', '', (string) $value)) : '';

		if ('' === $value || ! is_numeric($value)) {
			return null;
		}

		$rate = (float) $value;

		return ( $rate > 0 && $rate <= 100 ) ? $rate : null;
	}
}
