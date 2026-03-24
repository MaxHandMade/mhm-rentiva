<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BookingNotifications {

	public static function register(): void {
		// BookingNotifications class only uses render method, no register needed
	}

	public static function render(): void {
		echo '<h2>' . esc_html__( 'Booking Notifications', 'mhm-rentiva' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Configure email notifications for booking creation and status changes.', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Available placeholders: {booking_id}, {vehicle_title}, {pickup_date}, {dropoff_date}, {total_price}, {contact_name}, {contact_email}, {status}, {site_name}, {site_url}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Allowed HTML tags: a, b, strong, em, i, u, p, br, span, ul, ol, li, h1, h2, h3, table, tr, td, th, img.', 'mhm-rentiva' ) . '</p>';

		/**
		 * Helper to get option with fallback to Gold Standard
		 */
		$get_val = function ( string $key, callable $default_callback, string $quality_check = '' ): string {
			$val = (string) get_option( $key, '' );
			if ( trim( $val ) === '' ) {
				return (string) $default_callback();
			}
			if ( $quality_check !== '' && strpos( $val, $quality_check ) === false ) {
				return (string) $default_callback();
			}
			return $val;
		};

		// New Booking Email
		$booking_created_fields = array(
			array(
				'type'  => 'checkbox',
				'name'  => 'mhm_rentiva_booking_created_enabled',
				'label' => __( 'Enabled', 'mhm-rentiva' ),
				'value' => get_option( 'mhm_rentiva_booking_created_enabled', '1' ),
			),
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_booking_created_subject',
				'label' => __( 'Subject', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_created_subject', fn() => __( 'Booking Confirmed: #{booking_id}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_booking_created_body',
				'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_created_body', array( EmailSettings::class, 'get_default_customer_confirmation_body' ), '<table' ),
				'rows'  => 12,
			),
		);

		EmailFormRenderer::render_form(
			__( 'New Booking Email', 'mhm-rentiva' ),
			__( 'Email sent to customer when a new booking is created.', 'mhm-rentiva' ),
			$booking_created_fields
		);

		// Booking Status Change Email
		$booking_status_fields = array(
			array(
				'type'  => 'checkbox',
				'name'  => 'mhm_rentiva_booking_status_enabled',
				'label' => __( 'Enabled', 'mhm-rentiva' ),
				'value' => get_option( 'mhm_rentiva_booking_status_enabled', '1' ),
			),
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_booking_status_subject',
				'label' => __( 'Subject', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_status_subject', fn() => __( 'Booking #{booking_id} status updated', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_booking_status_body',
				'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_status_body', array( EmailSettings::class, 'get_default_booking_status_body' ), 'border-radius' ),
				'rows'  => 12,
			),
		);

		EmailFormRenderer::render_form(
			__( 'Booking Status Change Email', 'mhm-rentiva' ),
			__( 'Email sent when booking status changes.', 'mhm-rentiva' ),
			$booking_status_fields
		);

		// Admin Notification Email
		$booking_admin_fields = array(
			array(
				'type'  => 'checkbox',
				'name'  => 'mhm_rentiva_booking_admin_enabled',
				'label' => __( 'Enabled', 'mhm-rentiva' ),
				'value' => get_option( 'mhm_rentiva_booking_admin_enabled', '1' ),
			),
			array(
				'type'        => 'email',
				'name'        => 'mhm_rentiva_booking_admin_to',
				'label'       => __( 'Admin Email', 'mhm-rentiva' ),
				'value'       => get_option( 'mhm_rentiva_booking_admin_to', get_option( 'admin_email' ) ),
				'description' => __( 'Recipient for administrative alerts.', 'mhm-rentiva' ),
			),
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_booking_admin_subject',
				'label' => __( 'Subject', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_admin_subject', fn() => __( 'New Booking Alert: #{booking_id} - {site_name}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_booking_admin_body',
				'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_admin_body', array( EmailSettings::class, 'get_default_admin_notification_body' ), 'background:' ),
				'rows'  => 12,
			),
		);

		EmailFormRenderer::render_form(
			__( 'Admin Notification Email', 'mhm-rentiva' ),
			__( 'Alert sent to administrator for every new reservation.', 'mhm-rentiva' ),
			$booking_admin_fields
		);

		// Auto Cancel Email
		$auto_cancel_fields = array(
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_auto_cancel_email_subject',
				'label' => __( 'Subject', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_auto_cancel_email_subject', fn() => __( 'Booking Cancelled (Payment Timeout): #{booking_id}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_auto_cancel_email_content',
				'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_auto_cancel_email_content', array( EmailSettings::class, 'get_default_auto_cancel_body' ), 'color:' ),
				'rows'  => 10,
			),
		);

		EmailFormRenderer::render_form(
			__( 'Auto Cancel Email', 'mhm-rentiva' ),
			__( 'Sent when payment period expires.', 'mhm-rentiva' ),
			$auto_cancel_fields
		);

		// Booking Cancelled Email (Manual)
		$cancelled_fields = array(
			array(
				'type'  => 'checkbox',
				'name'  => 'mhm_rentiva_booking_cancelled_enabled',
				'label' => __( 'Enabled', 'mhm-rentiva' ),
				'value' => get_option( 'mhm_rentiva_booking_cancelled_enabled', '1' ),
			),
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_booking_cancelled_subject',
				'label' => __( 'Subject', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_cancelled_subject', fn() => __( 'Booking Cancelled: #{booking_id}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_booking_cancelled_body',
				'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_cancelled_body', array( EmailSettings::class, 'get_default_booking_cancelled_body' ), '<table' ),
				'rows'  => 12,
			),
		);

		EmailFormRenderer::render_form(
			__( 'Manual Cancellation Email', 'mhm-rentiva' ),
			__( 'Sent when a booking is manually cancelled by staff.', 'mhm-rentiva' ),
			$cancelled_fields
		);

		// Booking Reminder
		$reminder_fields = array(
			array(
				'type'  => 'checkbox',
				'name'  => 'mhm_rentiva_booking_reminder_enabled',
				'label' => __( 'Enabled', 'mhm-rentiva' ),
				'value' => get_option( 'mhm_rentiva_booking_reminder_enabled', '1' ),
			),
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_booking_reminder_subject',
				'label' => __( 'Subject', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_reminder_subject', fn() => __( 'Reminder: Your Booking #{booking_id} Starts Soon', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_booking_reminder_body',
				'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_booking_reminder_body', array( EmailSettings::class, 'get_default_booking_reminder_body' ), 'background:' ),
				'rows'  => 12,
			),
		);
		EmailFormRenderer::render_form(
			__( 'Pickup Reminder Email', 'mhm-rentiva' ),
			__( 'Friendly reminder sent to customer shortly before pickup.', 'mhm-rentiva' ),
			$reminder_fields
		);

		// Welcome Email
		$welcome_fields = array(
			array(
				'type'  => 'checkbox',
				'name'  => 'mhm_rentiva_welcome_email_enabled',
				'label' => __( 'Enabled', 'mhm-rentiva' ),
				'value' => get_option( 'mhm_rentiva_welcome_email_enabled', '1' ),
			),
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_welcome_email_subject',
				'label' => __( 'Subject', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_welcome_email_subject', fn() => __( 'Welcome to {site_name}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_welcome_email_body',
				'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_welcome_email_body', array( EmailSettings::class, 'get_default_welcome_email_body' ), 'community' ),
				'rows'  => 12,
			),
		);
		EmailFormRenderer::render_form(
			__( 'Customer Welcome Email', 'mhm-rentiva' ),
			__( 'Onboarding email sent after the first successful booking.', 'mhm-rentiva' ),
			$welcome_fields
		);
	}
}
