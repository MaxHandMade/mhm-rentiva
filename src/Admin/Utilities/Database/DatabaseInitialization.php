<?php declare(strict_types=1);

/**
 * MHM Rentiva Plugin - Database Initialization
 *
 * This file contains the code that creates the database when the plugin is first installed.
 * Ensures meta keys are created correctly.
 *
 * @package MHM_Rentiva
 * @since 1.0.0
 */

namespace MHMRentiva\Admin\Utilities\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Initialization Class
 *
 * Creates the database structure when the plugin is first installed
 */
final class DatabaseInitialization
{
    
    /**
     * Runs on plugin activation
     */
    public static function on_activation(): void {
        // Register meta keys
        self::register_meta_keys();
        
        // Create database tables
        self::create_database_tables();
        
        // Create default settings
        self::create_default_settings();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
    }
    
    /**
     * Runs on plugin deactivation
     */
    public static function on_deactivation(): void {
        // Clear cron jobs
        self::clear_cron_jobs();
    }
    
    /**
     * Register all meta keys
     */
    public static function register_meta_keys(): void {
        // Vehicle meta keys
        self::register_vehicle_meta_keys();
        
        // Booking meta keys
        self::register_booking_meta_keys();
        
        // Customer meta keys
        self::register_customer_meta_keys();
        
        // Payment meta keys
        self::register_payment_meta_keys();
        
        // Receipt meta keys
        self::register_receipt_meta_keys();
        
        // System meta keys
        self::register_system_meta_keys();
    }
    
    /**
     * Register vehicle meta keys
     */
    private static function register_vehicle_meta_keys(): void {
        // Standard meta key - VEHICLE AVAILABILITY STATUS
        register_post_meta('vehicle', '_mhm_vehicle_availability', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => 'Vehicle availability status (active/inactive)'
        ]);
        
        // Vehicle status (backup)
        register_post_meta('vehicle', '_mhm_vehicle_status', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => 'Vehicle status (active/inactive)'
        ]);
        
        // Price information
        register_post_meta('vehicle', '_mhm_rentiva_price_per_day', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Daily rental price', 'mhm-rentiva')
        ]);
        
        // Vehicle information
        register_post_meta('vehicle', '_mhm_rentiva_brand', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Vehicle brand', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_model', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Vehicle model', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_year', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Vehicle year', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_color', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Vehicle color', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_seats', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Number of seats', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_doors', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Number of doors', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_transmission', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Transmission type (manual/auto)', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_fuel_type', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Fuel type (petrol/diesel/hybrid/electric)', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_engine_size', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'floatval',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Engine displacement', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_mileage', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Mileage', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_license_plate', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('License plate', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_deposit', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Deposit percentage', 'mhm-rentiva')
        ]);
        
        // Array meta keys
        register_post_meta('vehicle', '_mhm_rentiva_features', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Vehicle features', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_equipment', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Vehicle equipment', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_gallery_images', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_gallery_images'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Vehicle gallery images', 'mhm-rentiva')
        ]);
        
        // Rating meta key'ler
        register_post_meta('vehicle', '_mhm_rentiva_rating_average', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'floatval',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Average rating (0-5)', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_rentiva_rating_count', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Rating count', 'mhm-rentiva')
        ]);
    }
    
    /**
     * Register booking meta keys
     */
    private static function register_booking_meta_keys(): void {
        // Booking basic information
        register_post_meta('vehicle_booking', '_mhm_vehicle_id', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('ID of the vehicle being booked', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_status', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Booking status', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_booking_type', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Booking type', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_created_via', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Booking creation method', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_created_by', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('User ID who created the booking', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_booking_created', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Booking creation date', 'mhm-rentiva')
        ]);
        
        // Date information
        register_post_meta('vehicle_booking', '_mhm_start_date', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Start date', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_start_time', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Start time', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_start_ts', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Start timestamp', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_end_date', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('End date', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_end_time', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('End time', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_end_ts', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('End timestamp', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_pickup_date', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Pickup date', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_pickup_time', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Pickup time', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_dropoff_date', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Drop-off date', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_dropoff_time', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Drop-off time', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_rental_days', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Number of rental days', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_guests', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Number of guests', 'mhm-rentiva')
        ]);
    }
    
    /**
     * Register customer meta keys
     */
    private static function register_customer_meta_keys(): void {
        register_post_meta('vehicle_booking', '_mhm_customer_user_id', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Customer user ID', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_customer_name', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Customer full name', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_customer_first_name', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Customer first name', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_customer_last_name', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Customer last name', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_customer_email', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_email',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Customer email', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_customer_phone', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Customer phone', 'mhm-rentiva')
        ]);
    }
    
    /**
     * Register payment meta keys
     */
    private static function register_payment_meta_keys(): void {
        register_post_meta('vehicle_booking', '_mhm_payment_method', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Payment method', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_payment_gateway', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Payment gateway', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_payment_type', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Payment type', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_payment_status', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Payment status', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_payment_deadline', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Payment deadline', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_payment_display', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Payment display text', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_total_price', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'floatval',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Total price', 'mhm-rentiva')
        ]);

        register_post_meta('vehicle_booking', '_mhm_deposit_amount', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'floatval',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Deposit amount', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_deposit_type', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Deposit type', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_remaining_amount', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'floatval',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Remaining amount', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_selected_addons', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Selected additional services', 'mhm-rentiva')
        ]);
    }
    
    /**
     * Register receipt meta keys
     */
    private static function register_receipt_meta_keys(): void {
        register_post_meta('vehicle_booking', '_mhm_receipt_status', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Receipt status', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_receipt_attachment_id', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Receipt file ID', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_receipt_uploaded_at', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Receipt upload date', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_receipt_uploaded_by', [
            'type' => 'number',
            'single' => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('User ID who uploaded receipt', 'mhm-rentiva')
        ]);
    }
    
    /**
     * Register system meta keys
     */
    private static function register_system_meta_keys(): void {
        register_post_meta('vehicle', '_mhm_shortcode', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Shortcode information', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_auto_created', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Automatically created', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_booking_history', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Booking history', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_booking_logs', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Booking logs', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_cancellation_deadline', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Cancellation deadline', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_cancellation_policy', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Cancellation policy', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_booking_reference', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Booking reference number', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle_booking', '_mhm_special_notes', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Special notes or customer requests', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_removed_details', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Removed details', 'mhm-rentiva')
        ]);
        
        register_post_meta('vehicle', '_mhm_custom_details', [
            'type' => 'array',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_array_meta'],
            'auth_callback' => '__return_true',
            'show_in_rest' => true,
            'description' => __('Custom details', 'mhm-rentiva')
        ]);
    }
    
    /**
     * Create database tables
     */
    private static function create_database_tables(): void {
        global $wpdb;
        
        // Custom tables can be created here
        // Currently using WordPress post meta
    }
    
    /**
     * Create default settings
     */
    private static function create_default_settings(): void {
        // Plugin settings
        $default_settings = [
            'mhm_rentiva_auto_cancel_enabled' => '1',
            'mhm_rentiva_auto_cancel_minutes' => 30,
            'mhm_rentiva_currency' => 'USD',
            'mhm_rentiva_date_format' => 'd.m.Y',
            'mhm_rentiva_time_format' => 'H:i'
        ];
        
        foreach ($default_settings as $key => $value) {
            if (!get_option($key)) {
                add_option($key, $value);
            }
        }

        // Secure Token Key
        if (!get_option('mhm_rentiva_secret_key')) {
            add_option('mhm_rentiva_secret_key', wp_generate_password(64, true, true));
        }
    }
    
    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs(): void {
        // Ensure custom schedules are registered before scheduling
        if (class_exists('\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel')) {
            add_filter('cron_schedules', ['\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel', 'schedules'], 1);
        }
        
        // Auto cancel cron job
        if (!wp_next_scheduled('mhm_rentiva_auto_cancel_event')) {
            // Verify schedule exists before scheduling
            $schedules = wp_get_schedules();
            if (isset($schedules['mhm_rentiva_5min'])) {
                $result = wp_schedule_event(time(), 'mhm_rentiva_5min', 'mhm_rentiva_auto_cancel_event');
                if ($result === false) {
                    error_log('DatabaseInitialization: Failed to schedule auto cancel event');
                }
            } else {
                error_log('DatabaseInitialization: Custom schedule mhm_rentiva_5min not available during activation');
            }
        }
    }
    
    /**
     * Clear cron jobs
     */
    private static function clear_cron_jobs(): void {
        wp_clear_scheduled_hook('mhm_rentiva_auto_cancel_event');
    }
    
    /**
     * Sanitize array meta values
     */
    public static function sanitize_array_meta($value) {
        if (!is_array($value)) {
            return [];
        }
        
        return array_map('sanitize_text_field', $value);
    }
    
    /**
     * Sanitize gallery images array
     */
    public static function sanitize_gallery_images($value) {
        if (!is_array($value)) {
            return [];
        }
        
        return array_map('absint', $value);
    }
}
