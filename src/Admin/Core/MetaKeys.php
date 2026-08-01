<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class MetaKeys
 *
 * Centralized management of all post meta keys used in the plugin.
 * This prevents typos and makes refactoring easier.
 *
 * @package MHMRentiva\Admin\Core
 * @since 4.5.0
 */
final class MetaKeys {



	// Vehicle Meta Keys
	public const VEHICLE_LICENSE_PLATE = '_mhmrentiva_license_plate';
	public const VEHICLE_PRICE_PER_DAY = '_mhmrentiva_price_per_day';
	public const VEHICLE_SEATS         = '_mhmrentiva_seats';
	public const VEHICLE_TRANSMISSION  = '_mhmrentiva_transmission';
	public const VEHICLE_FUEL_TYPE     = '_mhmrentiva_fuel_type';
	public const VEHICLE_STATUS        = '_mhmrentiva_vehicle_status';
	/** @deprecated 3.0.0 Use VEHICLE_STATUS instead */
	public const VEHICLE_AVAILABILITY     = '_mhmrentiva_vehicle_availability'; // Legacy
	public const VEHICLE_CATEGORY         = '_mhmrentiva_category'; // Legacy field (prefer taxonomy)
	public const VEHICLE_FEATURES_LIST    = '_mhmrentiva_features'; // Serialized list
	public const VEHICLE_DEPOSIT          = '_mhmrentiva_deposit';
	public const VEHICLE_RATING_AVERAGE   = '_mhmrentiva_rating_average';
	public const VEHICLE_RATING_COUNT     = '_mhmrentiva_rating_count';
	public const VEHICLE_CONFIDENCE_SCORE = '_mhmrentiva_confidence_score';
	public const VEHICLE_BRAND            = '_mhmrentiva_brand';
	public const VEHICLE_MODEL            = '_mhmrentiva_model';
	public const VEHICLE_YEAR             = '_mhmrentiva_year';
	public const VEHICLE_MILEAGE          = '_mhmrentiva_mileage';
	public const VEHICLE_FEATURED         = '_mhmrentiva_featured';
	public const VEHICLE_LOCATION_ID      = '_mhmrentiva_location_id';
	public const VENDOR_LOCATION_ID       = '_mhmrentiva_vendor_location_id';

	// Vendor Profile (v4.37.0)
	public const VENDOR_SLUG         = '_rentiva_vendor_slug';
	public const VENDOR_SLUG_HISTORY = '_rentiva_vendor_slug_history';
	public const VENDOR_AVATAR_ID    = '_rentiva_vendor_avatar_id';

	// Canonical vendor base-city user meta. Written by VendorOnboardingController on
	// approval and by VendorProfileSettingsSave on self-edit; read by the vendor vehicle
	// form and the admin transfer meta box. Single source of truth to avoid key drift.
	public const VENDOR_CITY = '_rentiva_vendor_city';

	// Vehicle Lifecycle Meta Keys
	public const VEHICLE_LIFECYCLE_STATUS    = '_mhmrentiva_vehicle_lifecycle_status';
	public const VEHICLE_LISTING_STARTED_AT  = '_mhmrentiva_vehicle_listing_started_at';
	public const VEHICLE_LISTING_EXPIRES_AT  = '_mhmrentiva_vehicle_listing_expires_at';
	public const VEHICLE_LISTING_RENEWED_AT  = '_mhmrentiva_vehicle_listing_renewed_at';
	public const VEHICLE_LISTING_RENEWAL_CNT = '_mhmrentiva_vehicle_listing_renewal_count';
	public const VEHICLE_PAUSED_AT           = '_mhmrentiva_vehicle_paused_at';
	public const VEHICLE_WITHDRAWN_AT        = '_mhmrentiva_vehicle_withdrawn_at';
	public const VEHICLE_COOLDOWN_ENDS_AT    = '_mhmrentiva_vehicle_cooldown_ends_at';
	/** Penalty amount computed at withdraw time but deferred while an appeal is open. */
	public const VEHICLE_DEFERRED_PENALTY = '_mhmrentiva_vehicle_deferred_penalty';
	/** Ledger transaction_uuid of the most recent applied withdrawal penalty (for appeal/reversal). */
	public const VEHICLE_PENALTY_UUID = '_mhmrentiva_vehicle_penalty_uuid';
	/** Set to '1' when a withdrawal appeal is upheld: the withdrawal is excused and no longer counts against the vendor's reliability score or penalty tier. */
	public const VEHICLE_WITHDRAWAL_EXCUSED = '_mhmrentiva_vehicle_withdrawal_excused';
	public const VEHICLE_BLOCKED_DATES      = '_mhmrentiva_vehicle_blocked_dates';

	// Vendor Reliability Meta Keys
	public const VENDOR_RELIABILITY_SCORE      = '_rentiva_vendor_reliability_score';
	public const VENDOR_RELIABILITY_UPDATED_AT = '_rentiva_vendor_reliability_updated_at';
	public const VENDOR_SCORE_HISTORY          = '_rentiva_vendor_score_history';

	// Booking Meta Keys
	public const BOOKING_STATUS              = '_mhmrentiva_status';
	public const BOOKING_START_TS            = '_mhmrentiva_start_ts';
	public const BOOKING_END_TS              = '_mhmrentiva_end_ts';
	public const BOOKING_VEHICLE_ID          = '_mhmrentiva_vehicle_id';
	public const BOOKING_TOTAL_PRICE         = '_mhmrentiva_total_price';
	public const BOOKING_CUSTOMER_EMAIL      = '_mhmrentiva_customer_email';
	public const BOOKING_CUSTOMER_FIRST_NAME = '_mhmrentiva_customer_first_name';
	public const BOOKING_CUSTOMER_LAST_NAME  = '_mhmrentiva_customer_last_name';
	public const BOOKING_CUSTOMER_PHONE      = '_mhmrentiva_customer_phone';
	public const BOOKING_CONTACT_EMAIL       = '_mhmrentiva_contact_email'; // Legacy/Contact Form
	public const BOOKING_CONTACT_NAME        = '_mhmrentiva_contact_name';  // Legacy/Contact Form
	public const BOOKING_PICKUP_DATE         = '_mhmrentiva_pickup_date';
	public const BOOKING_RETURN_DATE         = '_mhmrentiva_return_date';
	public const BOOKING_DROPOFF_DATE        = '_mhmrentiva_dropoff_date'; // Legacy/Alternative
	public const BOOKING_PICKUP_TIME         = '_mhmrentiva_start_time';
	public const BOOKING_RETURN_TIME         = '_mhmrentiva_end_time';
	public const BOOKING_END_DATE            = '_mhmrentiva_end_date'; // Legacy/Alternative
	public const BOOKING_PAYMENT_TYPE        = '_mhmrentiva_payment_type';
	public const BOOKING_DEPOSIT_AMOUNT      = '_mhmrentiva_deposit_amount';
	public const BOOKING_REMAINING_AMOUNT    = '_mhmrentiva_remaining_amount';
	public const BOOKING_PAYMENT_METHOD      = '_mhmrentiva_payment_method';
	public const BOOKING_PAYMENT_STATUS      = '_mhmrentiva_payment_status';
	public const BOOKING_PAYMENT_GATEWAY     = '_mhmrentiva_payment_gateway';
	public const BOOKING_SELECTED_ADDONS     = '_mhmrentiva_selected_addons';
	public const BOOKING_WC_ORDER_ID         = '_mhmrentiva_wc_order_id';

	// User Meta Keys
	// Add user meta keys here if needed in the future

	/**
	 * Private constructor to prevent instantiation
	 */
	private function __construct() {}
}
