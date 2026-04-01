<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

/**
 * Vendor Marketplace Settings Group
 *
 * Exposes vendor lifecycle, penalty, anti-gaming, and reliability score values
 * as configurable settings. Pro-only feature.
 *
 * @since 4.25.0
 */
final class VendorMarketplaceSettings {

	public const SECTION_LISTING     = 'mhm_rentiva_vendor_listing_section';
	public const SECTION_PENALTY     = 'mhm_rentiva_vendor_penalty_section';
	public const SECTION_ANTI_GAMING = 'mhm_rentiva_vendor_anti_gaming_section';
	public const SECTION_RELIABILITY = 'mhm_rentiva_vendor_reliability_section';
	public const SECTION_LISTING_FEE = 'mhm_rentiva_vendor_listing_fee_section';

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			// Listing & Duration
			'vendor_listing_duration_days'        => 90,
			'vendor_expiry_warning_first_days'    => 10,
			'vendor_expiry_warning_second_days'   => 3,
			'vendor_expiry_grace_days'            => 7,
			'vendor_withdrawal_cooldown_days'     => 7,
			'vendor_max_pauses_per_month'         => 2,
			'vendor_max_pause_duration_days'      => 30,

			// Penalty
			'vendor_penalty_tier2_rate'           => 10,
			'vendor_penalty_tier3_rate'           => 25,
			'vendor_penalty_rolling_window_months' => 12,

			// Anti-Gaming
			'vendor_anti_gaming_block_days'       => 30,

			// Reliability Score
			'vendor_score_cancel_penalty'         => 5,
			'vendor_score_withdrawal_penalty'     => 10,
			'vendor_score_pause_penalty'          => 2,
			'vendor_score_completion_bonus'       => 5,
			'vendor_score_max_completion_bonus'   => 20,

			// Listing Fee
			'mhm_rentiva_listing_fee_enabled'    => false,
			'mhm_rentiva_listing_fee_model'      => 'one_time',
			'mhm_rentiva_listing_fee_amount'     => 0.0,
		);
	}

	/**
	 * Render the vendor marketplace settings sections.
	 */
	public static function render_settings_section(): void {
		if ( ! \MHMRentiva\Admin\Licensing\Mode::canUseVendorMarketplace() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'These settings require a Pro license. Default values are used on Lite installations.', 'mhm-rentiva' ) . '</p></div>';
			return;
		}

		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_LISTING );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_PENALTY );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_ANTI_GAMING );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_RELIABILITY );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_LISTING_FEE );
		}
	}

	/**
	 * Register settings.
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		// Section 1: Listing & Duration Settings
		add_settings_section(
			self::SECTION_LISTING,
			__( 'Listing & Duration Settings', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure how long vehicle listings stay active and related timing rules.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_listing_duration_days',
			__( 'Listing Duration (Days)', 'mhm-rentiva' ),
			1,
			365,
			__( 'How many days a vehicle listing stays active before expiry.', 'mhm-rentiva' ),
			self::SECTION_LISTING
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_expiry_warning_first_days',
			__( 'First Expiry Warning (Days Before)', 'mhm-rentiva' ),
			1,
			60,
			__( 'Send first warning email this many days before listing expires.', 'mhm-rentiva' ),
			self::SECTION_LISTING
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_expiry_warning_second_days',
			__( 'Second Expiry Warning (Days Before)', 'mhm-rentiva' ),
			1,
			30,
			__( 'Send second warning email this many days before listing expires.', 'mhm-rentiva' ),
			self::SECTION_LISTING
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_expiry_grace_days',
			__( 'Grace Period After Expiry (Days)', 'mhm-rentiva' ),
			0,
			30,
			__( 'Days after expiry before expired listing is auto-withdrawn.', 'mhm-rentiva' ),
			self::SECTION_LISTING
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_withdrawal_cooldown_days',
			__( 'Withdrawal Cooldown (Days)', 'mhm-rentiva' ),
			0,
			90,
			__( 'Days a vendor must wait before relisting after withdrawal.', 'mhm-rentiva' ),
			self::SECTION_LISTING
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_max_pauses_per_month',
			__( 'Max Pauses Per Month', 'mhm-rentiva' ),
			1,
			10,
			__( 'Maximum number of times a vendor can pause a vehicle per calendar month.', 'mhm-rentiva' ),
			self::SECTION_LISTING
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_max_pause_duration_days',
			__( 'Max Pause Duration (Days)', 'mhm-rentiva' ),
			1,
			180,
			__( 'Maximum number of days a vehicle can remain paused.', 'mhm-rentiva' ),
			self::SECTION_LISTING
		);

		// Section 2: Withdrawal Penalty Settings
		add_settings_section(
			self::SECTION_PENALTY,
			__( 'Withdrawal Penalty Settings', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure progressive penalty rates applied when vendors withdraw vehicles.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_penalty_tier2_rate',
			__( '2nd Withdrawal Penalty (%)', 'mhm-rentiva' ),
			0,
			100,
			__( 'Penalty rate (% of monthly avg revenue) for the vendor\'s second withdrawal in 12 months.', 'mhm-rentiva' ),
			self::SECTION_PENALTY
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_penalty_tier3_rate',
			__( '3rd+ Withdrawal Penalty (%)', 'mhm-rentiva' ),
			0,
			100,
			__( 'Penalty rate for 3rd and subsequent withdrawals in the 12-month rolling window.', 'mhm-rentiva' ),
			self::SECTION_PENALTY
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_penalty_rolling_window_months',
			__( 'Rolling Window (Months)', 'mhm-rentiva' ),
			1,
			36,
			__( 'Number of months to look back when counting withdrawals for penalty calculation.', 'mhm-rentiva' ),
			self::SECTION_PENALTY
		);

		// Section 3: Anti-Gaming Protection
		add_settings_section(
			self::SECTION_ANTI_GAMING,
			__( 'Anti-Gaming Protection', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Prevent vendors from gaming the system by cancelling bookings to relist at higher prices.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_anti_gaming_block_days',
			__( 'Cancelled Booking Re-block Period (Days)', 'mhm-rentiva' ),
			1,
			180,
			__( 'After a vendor cancels a booking, these dates are blocked for new reservations for this many days.', 'mhm-rentiva' ),
			self::SECTION_ANTI_GAMING
		);

		// Section 4: Reliability Score Weights
		add_settings_section(
			self::SECTION_RELIABILITY,
			__( 'Reliability Score Weights', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure the point values used to calculate vendor reliability scores (base 100).', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_score_cancel_penalty',
			__( 'Points Deducted per Cancellation', 'mhm-rentiva' ),
			0,
			50,
			__( 'Points deducted from reliability score for each vendor-initiated cancellation (last 6 months).', 'mhm-rentiva' ),
			self::SECTION_RELIABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_score_withdrawal_penalty',
			__( 'Points Deducted per Withdrawal', 'mhm-rentiva' ),
			0,
			50,
			__( 'Points deducted per withdrawal in rolling window.', 'mhm-rentiva' ),
			self::SECTION_RELIABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_score_pause_penalty',
			__( 'Points Deducted per Pause', 'mhm-rentiva' ),
			0,
			20,
			__( 'Points deducted per pause in last 6 months.', 'mhm-rentiva' ),
			self::SECTION_RELIABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_score_completion_bonus',
			__( 'Points Added per Completed Booking', 'mhm-rentiva' ),
			0,
			20,
			__( 'Points added to reliability score per completed booking (last 6 months).', 'mhm-rentiva' ),
			self::SECTION_RELIABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'vendor_score_max_completion_bonus',
			__( 'Max Completion Bonus', 'mhm-rentiva' ),
			0,
			100,
			__( 'Maximum points that can be earned from completed bookings.', 'mhm-rentiva' ),
			self::SECTION_RELIABILITY
		);

		// Section 5: Listing Fee
		add_settings_section(
			self::SECTION_LISTING_FEE,
			__( 'Listing Fee', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure fees charged to vendors for listing vehicles on the marketplace.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhm_rentiva_listing_fee_enabled',
			__( 'Enable Listing Fee', 'mhm-rentiva' ),
			__( 'Charge vendors a fee for listing vehicles.', 'mhm-rentiva' ),
			self::SECTION_LISTING_FEE
		);

		SettingsHelper::select_field(
			$page_slug,
			'mhm_rentiva_listing_fee_model',
			__( 'Fee Model', 'mhm-rentiva' ),
			array(
				'one_time'   => __( 'One Time', 'mhm-rentiva' ),
				'per_period' => __( 'Per Period', 'mhm-rentiva' ),
			),
			__( 'Whether the fee is charged once or per listing period.', 'mhm-rentiva' ),
			self::SECTION_LISTING_FEE
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhm_rentiva_listing_fee_amount',
			__( 'Listing Fee Amount', 'mhm-rentiva' ),
			0,
			99999,
			__( 'The fee amount charged to vendors for listing a vehicle.', 'mhm-rentiva' ),
			self::SECTION_LISTING_FEE,
			0.01
		);
	}
}
