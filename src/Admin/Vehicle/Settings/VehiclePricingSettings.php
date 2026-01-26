<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ VEHICLE PRICING SETTINGS - Configurable Pricing Settings
 *
 * Moves fixed pricing values to central settings
 */
final class VehiclePricingSettings
{

	const OPTION_NAME = 'mhm_rentiva_vehicle_pricing_settings';

	/**
	 * Default settings
	 */
	public static function get_default_settings(): array
	{
		return array(
			'seasonal_multipliers' => array(
				'spring' => array(
					'name'        => __('Spring', 'mhm-rentiva'),
					'months'      => array(3, 4, 5),
					'multiplier'  => 1.0,
					'description' => __('Standard pricing', 'mhm-rentiva'),
				),
				'summer' => array(
					'name'        => __('Summer', 'mhm-rentiva'),
					'months'      => array(6, 7, 8),
					'multiplier'  => 1.3,
					'description' => __('High season pricing', 'mhm-rentiva'),
				),
				'autumn' => array(
					'name'        => __('Autumn', 'mhm-rentiva'),
					'months'      => array(9, 10, 11),
					'multiplier'  => 1.1,
					'description' => __('Mid season pricing', 'mhm-rentiva'),
				),
				'winter' => array(
					'name'        => __('Winter', 'mhm-rentiva'),
					'months'      => array(12, 1, 2),
					'multiplier'  => 0.8,
					'description' => __('Low season pricing', 'mhm-rentiva'),
				),
			),

			'discount_options'     => array(
				'weekly'        => array(
					'name'             => __('Weekly Discount', 'mhm-rentiva'),
					'description'      => __('7 days or more rental', 'mhm-rentiva'),
					'min_days'         => 7,
					'discount_percent' => 10,
					'type'             => 'percentage',
					'enabled'          => true,
				),
				'monthly'       => array(
					'name'             => __('Monthly Discount', 'mhm-rentiva'),
					'description'      => __('Rental of 30 days or more', 'mhm-rentiva'),
					'min_days'         => 30,
					'discount_percent' => 20,
					'type'             => 'percentage',
					'enabled'          => true,
				),
				'early_booking' => array(
					'name'             => __('Early Booking', 'mhm-rentiva'),
					'description'      => __('Booking 30 days in advance', 'mhm-rentiva'),
					'advance_days'     => 30,
					'discount_percent' => 5,
					'type'             => 'percentage',
					'enabled'          => true,
				),
				'loyalty'       => array(
					'name'             => __('Loyalty Discount', 'mhm-rentiva'),
					'description'      => __('Regular customer discount', 'mhm-rentiva'),
					'discount_percent' => 15,
					'type'             => 'percentage',
					'enabled'          => false,
				),
			),

			'currency_settings'    => array(
				'default_currency' => 'USD',
			),

			'deposit_settings'     => array(
				'enable_deposit'          => true,
				'deposit_type'            => 'both', // 'fixed', 'percentage', 'both'
				'allow_no_deposit'        => true,
				'deposit_refund_policy'   => __('Deposit is non-refundable, deducted from total rental amount.', 'mhm-rentiva'),
				'deposit_payment_methods' => array('credit_card', 'cash', 'bank_transfer'),
				'show_deposit_in_listing' => true,
				'show_deposit_in_detail'  => true,
				'required_for_booking'    => false,
			),

			'general_settings'     => array(
				'min_rental_days'          => 1,
				'max_rental_days'          => 365,
				'default_rental_days'      => 3,
				'price_calculation_method' => 'daily', // daily, weekly, monthly
				'round_prices'             => true,
				'decimal_places'           => 2,
			),
		);
	}

	/**
	 * Get settings
	 */
	public static function get_settings(): array
	{
		return SettingsCore::get('vehicle_pricing', self::get_default_settings());
	}

	/**
	 * Get seasonal multiplier for specific date
	 */
	public static function get_seasonal_multiplier_for_date(string $date): float
	{
		$month = (int) gmdate('n', strtotime($date));
		return self::get_seasonal_multiplier_for_month($month);
	}

	/**
	 * Get seasonal multiplier for specific month
	 */
	public static function get_seasonal_multiplier_for_month(int $month): float
	{
		$seasonal_multipliers = self::get_seasonal_multipliers();

		foreach ($seasonal_multipliers as $season) {
			if (in_array($month, $season['months'])) {
				return $season['multiplier'];
			}
		}

		return 1.0;
	}

	/**
	 * Get season name for specific month
	 */
	public static function get_season_name_for_month(int $month): string
	{
		$seasonal_multipliers = self::get_seasonal_multipliers();

		foreach ($seasonal_multipliers as $key => $season) {
			if (in_array($month, $season['months'])) {
				return $key;
			}
		}

		return 'spring';
	}

	/**
	 * Discount calculation
	 */
	public static function calculate_discounts(int $days, string $start_date, float $price): array
	{
		$discounts        = array();
		$total_discount   = 0;
		$discount_options = self::get_enabled_discounts();

		foreach ($discount_options as $key => $discount) {
			$apply_discount  = false;
			$discount_amount = 0;

			switch ($key) {
				case 'weekly':
					if ($days >= $discount['min_days']) {
						$apply_discount  = true;
						$discount_amount = $price * ($discount['discount_percent'] / 100);
					}
					break;

				case 'monthly':
					if ($days >= $discount['min_days']) {
						$apply_discount  = true;
						$discount_amount = $price * ($discount['discount_percent'] / 100);
					}
					break;

				case 'early_booking':
					$advance_days = (new \DateTime($start_date))->diff(new \DateTime())->days;
					if ($advance_days >= $discount['advance_days']) {
						$apply_discount  = true;
						$discount_amount = $price * ($discount['discount_percent'] / 100);
					}
					break;

				case 'loyalty':
					$apply_discount  = true;
					$discount_amount = $price * ($discount['discount_percent'] / 100);
					break;
			}

			if ($apply_discount && $discount_amount > 0) {
				$discounts[$key] = array(
					'name'    => $discount['name'],
					'amount'  => $discount_amount,
					'percent' => $discount['discount_percent'],
				);
				$total_discount   += $discount_amount;
			}
		}

		return array(
			'discounts'      => $discounts,
			'total_discount' => $total_discount,
		);
	}

	/**
	 * Additional service price calculation (no longer used - AddonManager is used)
	 */
	public static function calculate_addon_prices(array $addons, int $days): float
	{
		return 0;
	}


	/**
	 * Sadece etkin indirimleri getir
	 */
	public static function get_enabled_discounts(): array
	{
		$discount_options = self::get_discount_options();
		return array_filter(
			$discount_options,
			function ($discount) {
				return $discount['enabled'] ?? false;
			}
		);
	}


	/**
	 * Get seasonal multipliers
	 */
	public static function get_seasonal_multipliers(): array
	{
		$settings = self::get_settings();
		return $settings['seasonal_multipliers'] ?? self::get_default_settings()['seasonal_multipliers'];
	}

	/**
	 * Get discount options
	 */
	public static function get_discount_options(): array
	{
		$settings = self::get_settings();
		return $settings['discount_options'] ?? self::get_default_settings()['discount_options'];
	}




	/**
	 * Get currency settings
	 */
	public static function get_currency_settings(): array
	{
		$settings = self::get_settings();
		return $settings['currency_settings'] ?? self::get_default_settings()['currency_settings'];
	}

	/**
	 * Get general settings
	 */
	public static function get_general_settings(): array
	{
		$settings = self::get_settings();
		return $settings['general_settings'] ?? self::get_default_settings()['general_settings'];
	}

	/**
	 * Get deposit settings
	 */
	public static function get_deposit_settings(): array
	{
		$settings = self::get_settings();
		return $settings['deposit_settings'] ?? self::get_default_settings()['deposit_settings'];
	}


	/**
	 * Save settings
	 */
	public static function save_settings(array $settings): bool
	{
		return SettingsCore::set('vehicle_pricing', $settings);
	}

	/**
	 * Clear settings
	 */
	public static function clear_settings(): bool
	{
		return SettingsCore::delete('vehicle_pricing');
	}

	/**
	 * Reset settings
	 */
	public static function reset_settings(): bool
	{
		return self::save_settings(self::get_default_settings());
	}

	/**
	 * Render settings section
	 */
	public static function render_settings_section(): void
	{
		echo '<h2>' . esc_html__('Vehicle Pricing Settings', 'mhm-rentiva') . '</h2>';
		echo '<p>' . esc_html__('Configure settings for vehicle pricing, seasonal multipliers, discounts, and additional services.', 'mhm-rentiva') . '</p>';

		echo '<table class="form-table">';
		echo '<tr><th scope="row">' . esc_html__('Seasonal Pricing', 'mhm-rentiva') . '</th><td>';

		$seasonal_multipliers = self::get_seasonal_multipliers();

		foreach ($seasonal_multipliers as $key => $season) {
			echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
			echo '<h4>' . esc_html($season['name']) . '</h4>';
			echo '<label for="season_' . esc_attr($key) . '_multiplier">' . esc_html__('Multiplier', 'mhm-rentiva') . '</label><br>';
			echo '<input type="number" id="season_' . esc_attr($key) . '_multiplier" name="mhm_rentiva_settings[vehicle_pricing][seasonal_multipliers][' . esc_attr($key) . '][multiplier]" value="' . esc_attr($season['multiplier']) . '" min="0.1" max="5.0" step="0.1" style="width: 100px;">';
			echo '<p class="description">' . esc_html($season['description']) . '</p>';
			echo '</div>';
		}

		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__('Discount Options', 'mhm-rentiva') . '</th><td>';

		$discount_options = self::get_discount_options();

		foreach ($discount_options as $key => $discount) {
			echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
			echo '<h4>' . esc_html($discount['name']) . '</h4>';
			echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][enabled]" value="1"' . checked($discount['enabled'], true, false) . '> ' . esc_html__('Active', 'mhm-rentiva') . '</label><br><br>';

			if (isset($discount['min_days'])) {
				echo '<label for="discount_' . esc_attr($key) . '_min_days">' . esc_html__('Minimum Days', 'mhm-rentiva') . '</label><br>';
				echo '<input type="number" id="discount_' . esc_attr($key) . '_min_days" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][min_days]" value="' . esc_attr($discount['min_days']) . '" min="1" style="width: 100px;"><br><br>';
			}

			if (isset($discount['advance_days'])) {
				echo '<label for="discount_' . esc_attr($key) . '_advance_days">' . esc_html__('Advance Booking (Days)', 'mhm-rentiva') . '</label><br>';
				echo '<input type="number" id="discount_' . esc_attr($key) . '_advance_days" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][advance_days]" value="' . esc_attr($discount['advance_days']) . '" min="1" style="width: 100px;"><br><br>';
			}

			echo '<label for="discount_' . esc_attr($key) . '_percent">' . esc_html__('Discount Percentage', 'mhm-rentiva') . '</label><br>';
			echo '<input type="number" id="discount_' . esc_attr($key) . '_percent" name="mhm_rentiva_settings[vehicle_pricing][discount_options][' . esc_attr($key) . '][discount_percent]" value="' . esc_attr($discount['discount_percent']) . '" min="1" max="100" style="width: 100px;">%';
			echo '<p class="description">' . esc_html($discount['description']) . '</p>';
			echo '</div>';
		}

		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__('Additional Services', 'mhm-rentiva') . '</th><td>';
		echo '<p class="description">' . esc_html__('Additional services are managed from the "Additional Services" menu. No default addon settings here.', 'mhm-rentiva') . '</p>';
		echo '<a href="' . esc_url(admin_url('edit.php?post_type=vehicle_addon')) . '" class="button">' . esc_html__('Manage Additional Services', 'mhm-rentiva') . '</a>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__('Currency Settings', 'mhm-rentiva') . '</th><td>';

		$current_currency = SettingsCore::get('mhm_rentiva_currency', 'USD');

		echo '<label for="default_currency">' . esc_html__('Default Currency', 'mhm-rentiva') . '</label><br>';
		echo '<select id="default_currency" name="mhm_rentiva_settings[mhm_rentiva_currency]" style="width: 150px;">';
		// Use centralized currency list from CurrencyHelper
		$currencies = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_list_for_dropdown();
		foreach ($currencies as $code => $name) {
			echo '<option value="' . esc_attr($code) . '"' . selected($current_currency, $code, false) . '>' . esc_html($name) . '</option>';
		}
		echo '</select><br><br>';

		echo '</td></tr>';

		// General Settings
		echo '<tr><th scope="row">' . esc_html__('General Settings', 'mhm-rentiva') . '</th><td>';

		$general_settings = self::get_general_settings();

		echo '<label for="min_rental_days">' . esc_html__('Minimum Rental Period (Days)', 'mhm-rentiva') . '</label><br>';
		echo '<input type="number" id="min_rental_days" name="mhm_rentiva_settings[vehicle_pricing][general_settings][min_rental_days]" value="' . esc_attr($general_settings['min_rental_days']) . '" min="1" style="width: 100px;"><br><br>';

		echo '<label for="max_rental_days">' . esc_html__('Maximum Rental Period (Days)', 'mhm-rentiva') . '</label><br>';
		echo '<input type="number" id="max_rental_days" name="mhm_rentiva_settings[vehicle_pricing][general_settings][max_rental_days]" value="' . esc_attr($general_settings['max_rental_days']) . '" min="1" max="365" style="width: 100px;"><br><br>';

		echo '<label for="decimal_places">' . esc_html__('Decimal Places', 'mhm-rentiva') . '</label><br>';
		echo '<input type="number" id="decimal_places" name="mhm_rentiva_settings[vehicle_pricing][general_settings][decimal_places]" value="' . esc_attr($general_settings['decimal_places']) . '" min="0" max="4" style="width: 100px;">';

		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__('Deposit Settings', 'mhm-rentiva') . '</th><td>';

		$deposit_settings = self::get_deposit_settings();

		echo '<div style="margin-bottom: 15px;">';
		echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][enable_deposit]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][enable_deposit]" value="1"' . checked($deposit_settings['enable_deposit'], true, false) . '> ' . esc_html__('Enable Deposit System', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Enable deposit collection feature for vehicle rentals.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom: 15px;">';
		echo '<label for="deposit_type">' . esc_html__('Deposit Type', 'mhm-rentiva') . '</label><br>';
		echo '<select id="deposit_type" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_type]" style="width: 200px;">';
		echo '<option value="fixed"' . selected($deposit_settings['deposit_type'], 'fixed', false) . '>' . esc_html__('Fixed Amount Only', 'mhm-rentiva') . '</option>';
		echo '<option value="percentage"' . selected($deposit_settings['deposit_type'], 'percentage', false) . '>' . esc_html__('Percentage Only', 'mhm-rentiva') . '</option>';
		echo '<option value="both"' . selected($deposit_settings['deposit_type'], 'both', false) . '>' . esc_html__('Both', 'mhm-rentiva') . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__('Determine which deposit types will be used.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom: 15px;">';
		echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][allow_no_deposit]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][allow_no_deposit]" value="1"' . checked($deposit_settings['allow_no_deposit'], true, false) . '> ' . esc_html__('Allow Rental Without Deposit', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Allow vehicle rental without deposit.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom: 15px;">';
		echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][required_for_booking]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][required_for_booking]" value="1"' . checked($deposit_settings['required_for_booking'], true, false) . '> ' . esc_html__('Required for Booking', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Make deposit payment mandatory to complete booking.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom: 15px;">';
		echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_listing]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_listing]" value="1"' . checked($deposit_settings['show_deposit_in_listing'], true, false) . '> ' . esc_html__('Show in Listing Page', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Display deposit information in vehicle list.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom: 15px;">';
		echo '<input type="hidden" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_detail]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][show_deposit_in_detail]" value="1"' . checked($deposit_settings['show_deposit_in_detail'], true, false) . '> ' . esc_html__('Show in Detail Page', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Display deposit information on vehicle detail page.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom: 15px;">';
		echo '<label for="deposit_refund_policy">' . esc_html__('Deposit Refund Policy', 'mhm-rentiva') . '</label><br>';
		echo '<textarea id="deposit_refund_policy" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_refund_policy]" rows="3" style="width: 100%;">' . esc_textarea($deposit_settings['deposit_refund_policy']) . '</textarea>';
		echo '<p class="description">' . esc_html__('Explain your deposit refund policy.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<div style="margin-bottom: 15px;">';
		echo '<label for="deposit_payment_methods">' . esc_html__('Accepted Payment Methods', 'mhm-rentiva') . '</label><br>';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_payment_methods][]" value="credit_card"' . checked(in_array('credit_card', $deposit_settings['deposit_payment_methods']), true, false) . '> ' . esc_html__('Credit Card', 'mhm-rentiva') . '</label><br>';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_payment_methods][]" value="cash"' . checked(in_array('cash', $deposit_settings['deposit_payment_methods']), true, false) . '> ' . esc_html__('Cash', 'mhm-rentiva') . '</label><br>';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[vehicle_pricing][deposit_settings][deposit_payment_methods][]" value="bank_transfer"' . checked(in_array('bank_transfer', $deposit_settings['deposit_payment_methods']), true, false) . '> ' . esc_html__('Bank Transfer', 'mhm-rentiva') . '</label>';
		echo '<p class="description">' . esc_html__('Select accepted payment methods for deposit payment.', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '</td></tr>';

		echo '</table>';
	}
}
