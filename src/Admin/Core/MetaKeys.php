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
final class MetaKeys
{


	// Vehicle Meta Keys
	public const VEHICLE_LICENSE_PLATE    = '_mhm_rentiva_license_plate';
	public const VEHICLE_PRICE_PER_DAY    = '_mhm_rentiva_price_per_day';
	public const VEHICLE_SEATS            = '_mhm_rentiva_seats';
	public const VEHICLE_TRANSMISSION     = '_mhm_rentiva_transmission';
	public const VEHICLE_FUEL_TYPE        = '_mhm_rentiva_fuel_type';
	public const VEHICLE_STATUS           = '_mhm_vehicle_status';
	/** @deprecated 3.0.0 Use VEHICLE_STATUS instead */
	public const VEHICLE_AVAILABILITY     = '_mhm_vehicle_availability'; // Legacy
	public const VEHICLE_CATEGORY         = '_mhm_rentiva_category'; // Legacy field (prefer taxonomy)
	public const VEHICLE_FEATURES_LIST    = '_mhm_rentiva_features'; // Serialized list
	public const VEHICLE_DEPOSIT          = '_mhm_rentiva_deposit';
	public const VEHICLE_RATING_AVERAGE   = '_mhm_rentiva_rating_average';
	public const VEHICLE_RATING_COUNT     = '_mhm_rentiva_rating_count';
	public const VEHICLE_CONFIDENCE_SCORE = '_mhm_rentiva_confidence_score';
	public const VEHICLE_BRAND            = '_mhm_rentiva_brand';
	public const VEHICLE_MODEL            = '_mhm_rentiva_model';
	public const VEHICLE_YEAR             = '_mhm_rentiva_year';
	public const VEHICLE_MILEAGE          = '_mhm_rentiva_mileage';
	public const VEHICLE_FEATURED         = '_mhm_rentiva_featured';
	public const VEHICLE_LOCATION_ID      = '_mhm_rentiva_location_id';
	public const VENDOR_LOCATION_ID       = '_mhm_rentiva_vendor_location_id';

	// Vehicle Lifecycle Meta Keys
	public const VEHICLE_LIFECYCLE_STATUS    = '_mhm_vehicle_lifecycle_status';
	public const VEHICLE_LISTING_STARTED_AT  = '_mhm_vehicle_listing_started_at';
	public const VEHICLE_LISTING_EXPIRES_AT  = '_mhm_vehicle_listing_expires_at';
	public const VEHICLE_LISTING_RENEWED_AT  = '_mhm_vehicle_listing_renewed_at';
	public const VEHICLE_LISTING_RENEWAL_CNT = '_mhm_vehicle_listing_renewal_count';
	public const VEHICLE_PAUSED_AT           = '_mhm_vehicle_paused_at';
	public const VEHICLE_WITHDRAWN_AT        = '_mhm_vehicle_withdrawn_at';
	public const VEHICLE_COOLDOWN_ENDS_AT    = '_mhm_vehicle_cooldown_ends_at';
	public const VEHICLE_BLOCKED_DATES       = '_mhm_vehicle_blocked_dates';

	// Vendor Reliability Meta Keys
	public const VENDOR_RELIABILITY_SCORE      = '_rentiva_vendor_reliability_score';
	public const VENDOR_RELIABILITY_UPDATED_AT = '_rentiva_vendor_reliability_updated_at';

	// Booking Meta Keys
	public const BOOKING_STATUS              = '_mhm_status';
	public const BOOKING_START_TS            = '_mhm_start_ts';
	public const BOOKING_END_TS              = '_mhm_end_ts';
	public const BOOKING_VEHICLE_ID          = '_mhm_vehicle_id';
	public const BOOKING_TOTAL_PRICE         = '_mhm_total_price';
	public const BOOKING_CUSTOMER_EMAIL      = '_mhm_customer_email';
	public const BOOKING_CUSTOMER_FIRST_NAME = '_mhm_customer_first_name';
	public const BOOKING_CUSTOMER_LAST_NAME  = '_mhm_customer_last_name';
	public const BOOKING_CUSTOMER_PHONE      = '_mhm_customer_phone';
	public const BOOKING_CONTACT_EMAIL       = '_mhm_contact_email'; // Legacy/Contact Form
	public const BOOKING_CONTACT_NAME        = '_mhm_contact_name';  // Legacy/Contact Form
	public const BOOKING_PICKUP_DATE         = '_mhm_pickup_date';
	public const BOOKING_RETURN_DATE         = '_mhm_return_date';
	public const BOOKING_DROPOFF_DATE        = '_mhm_dropoff_date'; // Legacy/Alternative
	public const BOOKING_PICKUP_TIME         = '_mhm_start_time';
	public const BOOKING_RETURN_TIME         = '_mhm_end_time';
	public const BOOKING_END_DATE            = '_mhm_end_date'; // Legacy/Alternative
	public const BOOKING_PAYMENT_TYPE        = '_mhm_payment_type';
	public const BOOKING_DEPOSIT_AMOUNT      = '_mhm_deposit_amount';
	public const BOOKING_REMAINING_AMOUNT    = '_mhm_remaining_amount';
	public const BOOKING_PAYMENT_METHOD      = '_mhm_payment_method';
	public const BOOKING_PAYMENT_STATUS      = '_mhm_payment_status';
	public const BOOKING_PAYMENT_GATEWAY     = '_mhm_payment_gateway';
	public const BOOKING_SELECTED_ADDONS     = '_mhm_selected_addons';
	public const BOOKING_WC_ORDER_ID         = '_mhm_wc_order_id';

	// User Meta Keys
	// Add user meta keys here if needed in the future

	/**
	 * Private constructor to prevent instantiation
	 */
	private function __construct() {}
}
