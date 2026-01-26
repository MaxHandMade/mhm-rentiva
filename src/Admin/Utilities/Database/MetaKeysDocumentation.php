<?php

declare(strict_types=1);

/**
 * MHM Rentiva Plugin - Meta Keys Documentation
 *
 * This file contains documentation for all meta keys used in the plugin.
 * Add new meta keys according to this documentation.
 *
 * @package MHM_Rentiva
 * @since 1.0.0
 */

namespace MHMRentiva\Admin\Utilities\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta Keys Documentation Class
 *
 * Standard documentation for all meta keys used in the plugin
 */
final class MetaKeysDocumentation {



	/**
	 * List of meta keys by category
	 */
	public static function get_meta_keys_documentation(): array {
		return array(

			// ========================================
			// VEHICLE META KEYS
			// ========================================
			'vehicle'  => array(
				'description' => 'Meta keys used for vehicle information',
				'keys'        => array(
					'_mhm_vehicle_availability'   => array(
						'type'        => 'string',
						'values'      => array( 'active', 'inactive' ),
						'description' => 'Vehicle availability status (STANDARD)',
						'required'    => true,
						'usage'       => 'Vehicle listing, booking control',
					),
					'_mhm_vehicle_status'         => array(
						'type'        => 'string',
						'values'      => array( 'active', 'inactive' ),
						'description' => 'Vehicle status (backup)',
						'required'    => false,
						'usage'       => 'Vehicle management',
					),
					'_mhm_rentiva_availability'   => array(
						'type'        => 'string',
						'values'      => array( 'active', 'inactive' ),
						'description' => 'Vehicle availability status (OLD FORMAT - TO BE REMOVED)',
						'required'    => false,
						'usage'       => 'Backward compatibility',
						'deprecated'  => true,
					),
					'_mhm_rentiva_price_per_day'  => array(
						'type'        => 'number',
						'values'      => 'numeric',
						'description' => 'Daily rental price',
						'required'    => true,
						'usage'       => 'Price calculation',
					),
					'_mhm_rentiva_brand'          => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Vehicle brand',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_model'          => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Vehicle model',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_year'           => array(
						'type'        => 'number',
						'values'      => 'year (4 digits)',
						'description' => 'Vehicle year',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_color'          => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Vehicle color',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_seats'          => array(
						'type'        => 'number',
						'values'      => 'integer',
						'description' => 'Number of seats',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_doors'          => array(
						'type'        => 'number',
						'values'      => 'integer',
						'description' => 'Number of doors',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_transmission'   => array(
						'type'        => 'string',
						'values'      => array( 'manual', 'auto' ),
						'description' => 'Transmission type',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_fuel_type'      => array(
						'type'        => 'string',
						'values'      => array( 'petrol', 'diesel', 'hybrid', 'electric' ),
						'description' => 'Fuel type',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_engine_size'    => array(
						'type'        => 'number',
						'values'      => 'decimal',
						'description' => 'Engine displacement',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_mileage'        => array(
						'type'        => 'number',
						'values'      => 'integer',
						'description' => 'Mileage',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_license_plate'  => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'License plate',
						'required'    => true,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_deposit'        => array(
						'type'        => 'number',
						'values'      => 'percentage',
						'description' => 'Deposit percentage',
						'required'    => true,
						'usage'       => 'Price calculation',
					),
					'_mhm_rentiva_features'       => array(
						'type'        => 'array',
						'values'      => 'serialized array',
						'description' => 'Vehicle features',
						'required'    => false,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_equipment'      => array(
						'type'        => 'array',
						'values'      => 'serialized array',
						'description' => 'Vehicle equipment',
						'required'    => false,
						'usage'       => 'Vehicle information',
					),
					'_mhm_rentiva_gallery_images' => array(
						'type'        => 'array',
						'values'      => 'JSON array',
						'description' => 'Vehicle gallery images',
						'required'    => false,
						'usage'       => 'Vehicle gallery',
					),
					'_mhm_rentiva_rating_average' => array(
						'type'        => 'number',
						'values'      => 'decimal (0-5)',
						'description' => 'Average rating',
						'required'    => false,
						'usage'       => 'Vehicle rating',
					),
					'_mhm_rentiva_rating_count'   => array(
						'type'        => 'number',
						'values'      => 'integer',
						'description' => 'Rating count',
						'required'    => false,
						'usage'       => 'Vehicle rating',
					),
				),
			),

			// ========================================
			// BOOKING META KEYS
			// ========================================
			'booking'  => array(
				'description' => 'Meta keys used for booking information',
				'keys'        => array(
					'_mhm_vehicle_id'      => array(
						'type'        => 'number',
						'values'      => 'post_id',
						'description' => 'ID of the vehicle being booked',
						'required'    => true,
						'usage'       => 'Booking-vehicle relationship',
					),
					'_mhm_status'          => array(
						'type'        => 'string',
						'values'      => array( 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled' ),
						'description' => 'Booking status',
						'required'    => true,
						'usage'       => 'Booking management',
					),
					'_mhm_booking_type'    => array(
						'type'        => 'string',
						'values'      => array( 'online', 'manual' ),
						'description' => 'Booking type',
						'required'    => true,
						'usage'       => 'Booking management',
					),
					'_mhm_created_via'     => array(
						'type'        => 'string',
						'values'      => array( 'frontend', 'admin', 'api' ),
						'description' => 'Booking creation method',
						'required'    => true,
						'usage'       => 'Booking management',
					),
					'_mhm_created_by'      => array(
						'type'        => 'number',
						'values'      => 'user_id',
						'description' => 'User ID who created the booking',
						'required'    => true,
						'usage'       => 'Booking management',
					),
					'_mhm_booking_created' => array(
						'type'        => 'string',
						'values'      => 'datetime',
						'description' => 'Booking creation date',
						'required'    => true,
						'usage'       => 'Booking management',
					),
					'_mhm_start_date'      => array(
						'type'        => 'string',
						'values'      => 'date (Y-m-d)',
						'description' => 'Start date',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_start_time'      => array(
						'type'        => 'string',
						'values'      => 'time (H:i)',
						'description' => 'Start time',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_start_ts'        => array(
						'type'        => 'number',
						'values'      => 'timestamp',
						'description' => 'Start timestamp',
						'required'    => true,
						'usage'       => 'Booking dates (for queries)',
					),
					'_mhm_end_date'        => array(
						'type'        => 'string',
						'values'      => 'date (Y-m-d)',
						'description' => 'End date',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_end_time'        => array(
						'type'        => 'string',
						'values'      => 'time (H:i)',
						'description' => 'End time',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_end_ts'          => array(
						'type'        => 'number',
						'values'      => 'timestamp',
						'description' => 'End timestamp',
						'required'    => true,
						'usage'       => 'Booking dates (for queries)',
					),
					'_mhm_pickup_date'     => array(
						'type'        => 'string',
						'values'      => 'date (Y-m-d)',
						'description' => 'Pickup date',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_pickup_time'     => array(
						'type'        => 'string',
						'values'      => 'time (H:i)',
						'description' => 'Pickup time',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_dropoff_date'    => array(
						'type'        => 'string',
						'values'      => 'date (Y-m-d)',
						'description' => 'Drop-off date',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_dropoff_time'    => array(
						'type'        => 'string',
						'values'      => 'time (H:i)',
						'description' => 'Drop-off time',
						'required'    => true,
						'usage'       => 'Booking dates',
					),
					'_mhm_rental_days'     => array(
						'type'        => 'number',
						'values'      => 'integer',
						'description' => 'Number of rental days',
						'required'    => true,
						'usage'       => 'Price calculation',
					),
					'_mhm_guests'          => array(
						'type'        => 'number',
						'values'      => 'integer',
						'description' => 'Number of guests',
						'required'    => true,
						'usage'       => 'Booking information',
					),
				),
			),

			// ========================================
			// CUSTOMER META KEYS
			// ========================================
			'customer' => array(
				'description' => 'Meta keys used for customer information',
				'keys'        => array(
					'_mhm_customer_user_id'    => array(
						'type'        => 'number',
						'values'      => 'user_id',
						'description' => 'Customer user ID',
						'required'    => false,
						'usage'       => 'Customer relationship',
					),
					'_mhm_customer_name'       => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Customer full name',
						'required'    => true,
						'usage'       => 'Customer information',
					),
					'_mhm_customer_first_name' => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Customer first name',
						'required'    => true,
						'usage'       => 'Customer information',
					),
					'_mhm_customer_last_name'  => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Customer last name',
						'required'    => true,
						'usage'       => 'Customer information',
					),
					'_mhm_customer_email'      => array(
						'type'        => 'string',
						'values'      => 'email',
						'description' => 'Customer email',
						'required'    => true,
						'usage'       => 'Customer information',
					),
					'_mhm_customer_phone'      => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Customer phone',
						'required'    => true,
						'usage'       => 'Customer information',
					),
				),
			),

			// ========================================
			// PAYMENT META KEYS
			// ========================================
			'payment'  => array(
				'description' => 'Meta keys used for payment information',
				'keys'        => array(
					'_mhm_payment_method'   => array(
						'type'        => 'string',
						'values'      => array( 'woocommerce' ),
						'description' => 'Payment method (WooCommerce only)',
						'required'    => true,
						'usage'       => 'Payment management',
					),
					'_mhm_payment_gateway'  => array(
						'type'        => 'string',
						'values'      => array( 'woocommerce' ),
						'description' => 'Payment gateway (WooCommerce only)',
						'required'    => true,
						'usage'       => 'Payment management',
					),
					'_mhm_payment_type'     => array(
						'type'        => 'string',
						'values'      => array( 'full', 'deposit' ),
						'description' => 'Payment type',
						'required'    => true,
						'usage'       => 'Payment management',
					),
					'_mhm_payment_status'   => array(
						'type'        => 'string',
						'values'      => array( 'pending', 'completed', 'failed', 'refunded', 'pending_verification' ),
						'description' => 'Payment status',
						'required'    => true,
						'usage'       => 'Payment management',
					),
					'_mhm_payment_deadline' => array(
						'type'        => 'string',
						'values'      => 'datetime',
						'description' => 'Payment deadline',
						'required'    => false,
						'usage'       => 'Payment deadline control (for auto-cancellation)',
					),
					'_mhm_payment_display'  => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Payment display text',
						'required'    => false,
						'usage'       => 'Payment management',
					),
					'_mhm_total_price'      => array(
						'type'        => 'number',
						'values'      => 'decimal',
						'description' => 'Total price',
						'required'    => true,
						'usage'       => 'Price calculation',
					),
					'_mhm_deposit_amount'   => array(
						'type'        => 'number',
						'values'      => 'decimal',
						'description' => 'Deposit amount',
						'required'    => true,
						'usage'       => 'Price calculation',
					),
					'_mhm_deposit_type'     => array(
						'type'        => 'string',
						'values'      => array( 'percentage', 'fixed' ),
						'description' => 'Deposit type',
						'required'    => true,
						'usage'       => 'Price calculation',
					),
					'_mhm_remaining_amount' => array(
						'type'        => 'number',
						'values'      => 'decimal',
						'description' => 'Remaining amount',
						'required'    => true,
						'usage'       => 'Price calculation',
					),
					'_mhm_selected_addons'  => array(
						'type'        => 'array',
						'values'      => 'serialized array',
						'description' => 'Selected additional services',
						'required'    => false,
						'usage'       => 'Price calculation',
					),
				),
			),

			// ========================================
			// RECEIPT META KEYS
			// ========================================
			'receipt'  => array(
				'description' => 'Meta keys used for receipt information',
				'keys'        => array(
					'_mhm_receipt_status'        => array(
						'type'        => 'string',
						'values'      => array( 'submitted', 'approved', 'rejected' ),
						'description' => 'Receipt status',
						'required'    => false,
						'usage'       => 'Receipt management',
					),
					'_mhm_receipt_attachment_id' => array(
						'type'        => 'number',
						'values'      => 'attachment_id',
						'description' => 'Receipt file ID',
						'required'    => false,
						'usage'       => 'Receipt management',
					),
					'_mhm_receipt_uploaded_at'   => array(
						'type'        => 'string',
						'values'      => 'datetime',
						'description' => 'Receipt upload date',
						'required'    => false,
						'usage'       => 'Receipt management',
					),
					'_mhm_receipt_uploaded_by'   => array(
						'type'        => 'number',
						'values'      => 'user_id',
						'description' => 'User ID who uploaded receipt',
						'required'    => false,
						'usage'       => 'Receipt management',
					),
				),
			),

			// ========================================
			// SYSTEM META KEYS
			// ========================================
			'system'   => array(
				'description' => 'Meta keys used for system information',
				'keys'        => array(
					'_mhm_shortcode'             => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Shortcode information',
						'required'    => false,
						'usage'       => 'System management',
					),
					'_mhm_auto_created'          => array(
						'type'        => 'string',
						'values'      => 'boolean',
						'description' => 'Automatically created',
						'required'    => false,
						'usage'       => 'System management',
					),
					'_mhm_booking_history'       => array(
						'type'        => 'array',
						'values'      => 'serialized array',
						'description' => 'Booking history',
						'required'    => false,
						'usage'       => 'System management',
					),
					'_mhm_booking_logs'          => array(
						'type'        => 'array',
						'values'      => 'serialized array',
						'description' => 'Booking logs',
						'required'    => false,
						'usage'       => 'System management',
					),
					'_mhm_cancellation_deadline' => array(
						'type'        => 'string',
						'values'      => 'datetime',
						'description' => 'Cancellation deadline',
						'required'    => false,
						'usage'       => 'Cancellation management',
					),
					'_mhm_cancellation_policy'   => array(
						'type'        => 'string',
						'values'      => 'text',
						'description' => 'Cancellation policy',
						'required'    => false,
						'usage'       => 'Cancellation management',
					),
					'_mhm_removed_details'       => array(
						'type'        => 'array',
						'values'      => 'serialized array',
						'description' => 'Removed details',
						'required'    => false,
						'usage'       => 'System management',
					),
					'_mhm_custom_details'        => array(
						'type'        => 'array',
						'values'      => 'serialized array',
						'description' => 'Custom details',
						'required'    => false,
						'usage'       => 'System management',
					),
				),
			),
		);
	}

	/**
	 * Generate HTML documentation for meta keys
	 */
	public static function generate_html_documentation(): string {
		$meta_keys = self::get_meta_keys_documentation();

		$html = '<!DOCTYPE html>
<html lang="' . esc_attr( get_locale() ) . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html__( 'MHM Rentiva - Meta Keys Documentation', 'mhm-rentiva' ) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 15px; }
        h3 { color: #7f8c8d; margin-top: 20px; }
        .meta-key { background: #ecf0f1; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3498db; }
        .meta-key-name { font-weight: bold; color: #2c3e50; font-size: 16px; }
        .meta-key-info { margin-top: 8px; }
        .meta-key-info span { display: inline-block; margin-right: 15px; padding: 3px 8px; background: #3498db; color: white; border-radius: 3px; font-size: 12px; }
        .meta-key-description { margin-top: 8px; color: #7f8c8d; }
        .deprecated { background: #e74c3c; color: white; }
        .required { background: #e67e22; }
        .optional { background: #95a5a6; }
        .warning { background: #f39c12; color: white; padding: 10px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>' . esc_html__( '🚗 MHM Rentiva Plugin - Meta Keys Documentation', 'mhm-rentiva' ) . '</h1>

        <div class="warning">
            <strong>' . esc_html__( '⚠️ IMPORTANT:', 'mhm-rentiva' ) . '</strong> ' .
			esc_html__( 'This documentation contains the standard list of all meta keys used in the plugin. Add new meta keys according to this documentation. Do not create inconsistent meta keys!', 'mhm-rentiva' ) . '
        </div>

        <p><strong>' . esc_html__( 'Last Update:', 'mhm-rentiva' ) . '</strong> ' . esc_html( current_time( 'd.m.Y H:i:s' ) ) . '</p>
        <p><strong>' . esc_html__( 'Total Meta Key Count:', 'mhm-rentiva' ) . '</strong> ' . esc_html( self::count_total_meta_keys() ) . '</p>';

		foreach ( $meta_keys as $category => $data ) {
			$html .= '<h2>' . esc_html( ucfirst( $category ) . ' Meta Keys' ) . '</h2>';
			$html .= '<p><em>' . esc_html( $data['description'] ) . '</em></p>';

			foreach ( $data['keys'] as $key => $info ) {
				$deprecated_class = isset( $info['deprecated'] ) && $info['deprecated'] ? 'deprecated' : '';
				$required_class   = $info['required'] ? 'required' : 'optional';

				$html .= '<div class="meta-key ' . esc_attr( $deprecated_class ) . '">
                    <div class="meta-key-name">' . esc_html( $key ) . '</div>
                    <div class="meta-key-info">
                        <span class="' . esc_attr( $required_class ) . '">' . esc_html( $info['required'] ? esc_html__( 'Required', 'mhm-rentiva' ) : esc_html__( 'Optional', 'mhm-rentiva' ) ) . '</span>
                        <span>' . esc_html( $info['type'] ) . '</span>
                        <span>' . esc_html( is_array( $info['values'] ) ? implode( ', ', $info['values'] ) : $info['values'] ) . '</span>
                    </div>
                    <div class="meta-key-description">' . esc_html( $info['description'] ) . '</div>
                    <div class="meta-key-description"><strong>' . esc_html__( 'Usage:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $info['usage'] ) . '</div>
                </div>';
			}
		}

		$html .= '
        <h2>' . esc_html__( '📋 Meta Key Usage Rules', 'mhm-rentiva' ) . '</h2>
        <div class="meta-key">
            <h3>' . esc_html__( '✅ What Should Be Done:', 'mhm-rentiva' ) . '</h3>
            <ul>
                <li>' . esc_html__( 'Add new meta keys according to this documentation', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Create meta key names consistently', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Always add required meta keys', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Define meta key types correctly (string, number, array)', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Define value ranges clearly', 'mhm-rentiva' ) . '</li>
            </ul>
        </div>

        <div class="meta-key deprecated">
            <h3>' . esc_html__( '❌ What Should Not Be Done:', 'mhm-rentiva' ) . '</h3>
            <ul>
                <li>' . esc_html__( 'Do not use old meta keys', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Do not create inconsistent meta key names', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Do not add unnecessary meta keys', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Do not mix meta key types', 'mhm-rentiva' ) . '</li>
                <li>' . esc_html__( 'Do not leave value ranges undefined', 'mhm-rentiva' ) . '</li>
            </ul>
        </div>

        <h2>' . esc_html__( '🔧 Developer Notes', 'mhm-rentiva' ) . '</h2>
        <div class="meta-key">
            <p><strong>' . esc_html__( 'Standard Meta Key Format:', 'mhm-rentiva' ) . '</strong> <code>_mhm_[category]_[field_name]</code></p>
            <p><strong>' . esc_html__( 'Example:', 'mhm-rentiva' ) . '</strong> <code>_mhm_vehicle_availability</code>, <code>_mhm_booking_status</code></p>
            <p><strong>' . esc_html__( 'Categories:', 'mhm-rentiva' ) . '</strong> vehicle, booking, customer, payment, receipt, system</p>
        </div>
    </div>
</body>
</html>';

		return $html;
	}

	/**
	 * Calculate total meta key count
	 */
	private static function count_total_meta_keys(): int {
		$meta_keys = self::get_meta_keys_documentation();
		$count     = 0;
		foreach ( $meta_keys as $category => $data ) {
			$count += count( $data['keys'] );
		}
		return $count;
	}
}
