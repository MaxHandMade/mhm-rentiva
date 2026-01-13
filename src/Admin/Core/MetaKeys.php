<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
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
    public const VEHICLE_LICENSE_PLATE = '_mhm_rentiva_license_plate';
    public const VEHICLE_PRICE_PER_DAY = '_mhm_rentiva_price_per_day';
    public const VEHICLE_SEATS = '_mhm_rentiva_seats';
    public const VEHICLE_TRANSMISSION = '_mhm_rentiva_transmission';
    public const VEHICLE_FUEL_TYPE = '_mhm_rentiva_fuel_type';
    public const VEHICLE_STATUS = '_mhm_vehicle_status';
    public const VEHICLE_AVAILABILITY = '_mhm_vehicle_availability'; // Legacy

    // Booking Meta Keys
    public const BOOKING_STATUS = '_mhm_status';
    public const BOOKING_START_TS = '_mhm_start_ts';
    public const BOOKING_END_TS = '_mhm_end_ts';
    public const BOOKING_VEHICLE_ID = '_mhm_vehicle_id';
    public const BOOKING_TOTAL_PRICE = '_mhm_total_price';
    public const BOOKING_CONTACT_EMAIL = '_mhm_contact_email';
    public const BOOKING_CONTACT_NAME = '_mhm_contact_name';
    public const BOOKING_PICKUP_DATE = '_mhm_pickup_date';
    public const BOOKING_RETURN_DATE = '_mhm_return_date';
    public const BOOKING_DROPOFF_DATE = '_mhm_dropoff_date'; // Legacy/Alternative
    public const BOOKING_END_DATE = '_mhm_end_date'; // Legacy/Alternative

    // User Meta Keys
    // Add user meta keys here if needed in the future

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {}
}
