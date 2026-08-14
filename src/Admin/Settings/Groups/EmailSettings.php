<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Settings Group
 *
 * Manages email configuration, branding, and template defaults.
 * Optimized for Gold Standard HTML templates and high-performance delivery.
 */
final class EmailSettings {



	public const SECTION_ID            = 'mhmrentiva_email_section';
	public const SECTION_NOTIFICATIONS = 'mhmrentiva_email_notifications_section';

	/**
	 * Get default settings for email, fully materialised.
	 *
	 * Kept as the public contract because three callers hand this array
	 * straight to `update_option()` / `register_setting( 'default' => ... )`
	 * (SettingsService::reset_defaults(), Settings::render(), RESTSettings) and
	 * a Closure cannot be serialized. Anything on an early code path must go
	 * through {@see self::deferred_default_settings()} instead.
	 */
	public static function get_default_settings(): array {
		return SettingsCore::resolve_defaults( self::deferred_default_settings() );
	}

	/**
	 * The same defaults, with every translated one still DEFERRED.
	 *
	 * This group owns all ~54 translated strings in the settings defaults, and
	 * SettingsCore merges every group's defaults to answer a read of ANY single
	 * key -- including `mhmrentiva_log_level`, read from
	 * AdvancedLogger::should_skip_log() while the upgrade migration runs on
	 * `plugins_loaded`. Building a translated string there is before `init`,
	 * which is exactly what WordPress 6.7+ reports as
	 * "Translation loading for the mhm-rentiva domain was triggered too early".
	 *
	 * Each Closure is the recipe for one default; SettingsCore calls it only
	 * when that key is actually read, by which time `init` has long passed.
	 * The `__()` call sites themselves are untouched, so `makepot` still
	 * extracts every msgid from this file exactly as before.
	 *
	 * @return array<string, mixed> Translatable values are Closures.
	 */
	public static function deferred_default_settings(): array {
		return array(
			'mhmrentiva_email_from_name'                => get_bloginfo( 'name' ),
			'mhmrentiva_email_from_address'             => get_option( 'admin_email' ),
			'mhmrentiva_email_reply_to'                 => get_option( 'admin_email' ),
			'mhmrentiva_email_send_enabled'             => '1',
			'mhmrentiva_email_test_mode'                => '0',
			'mhmrentiva_email_test_address'             => get_option( 'admin_email' ),
			'mhmrentiva_email_template_path'            => 'mhm-rentiva/emails/',
			'mhmrentiva_email_auto_send'                => '1',
			'mhmrentiva_email_log_enabled'              => '1',
			'mhmrentiva_email_log_retention_days'       => 30,

			// Branding
			'mhmrentiva_email_base_color'               => '#1e88e5',
			'mhmrentiva_email_header_image'             => '',
			// The site's own name, and nothing else. This is the footer of mail
			// this plugin sends to customers -- a user-facing surface -- and the
			// default used to read "%s - Powered by MHM Rentiva". WordPress.org
			// guideline 10 allows a credit on a user-facing surface only where
			// the site administrator has opted in by a deliberate action, and a
			// shipped default is the opposite of that: it appears in every
			// customer's inbox on a fresh install without anyone choosing it.
			// Found by the T8 review at this line.
			//
			// The field itself is untouched and still free text, so an
			// administrator who wants to credit the plugin can write whatever
			// they like there. The plugin just stops writing it for them.
			'mhmrentiva_email_footer_text'              => static fn(): string => (string) get_bloginfo( 'name' ),

			// Customer Booking Confirmation
			'mhmrentiva_booking_created_subject'        => static fn(): string => __( 'Booking Confirmed: #{booking_id}', 'mhm-rentiva' ),
			'mhmrentiva_booking_created_body'           => static fn(): string => self::get_default_customer_confirmation_body(),

			// Booking Status Change (Customer)
			'mhmrentiva_booking_status_subject'         => static fn(): string => __( 'Booking #{booking_id} status updated', 'mhm-rentiva' ),
			'mhmrentiva_booking_status_body'            => static fn(): string => self::get_default_booking_status_body(),

			// Admin Booking Alert
			'mhmrentiva_booking_admin_subject'          => static fn(): string => __( 'New Booking Alert: #{booking_id} - {site_name}', 'mhm-rentiva' ),
			'mhmrentiva_booking_admin_body'             => static fn(): string => self::get_default_admin_notification_body(),

			// Auto Cancel Email
			'mhmrentiva_auto_cancel_email_subject'      => static fn(): string => __( 'Booking Cancelled (Payment Timeout): #{booking_id}', 'mhm-rentiva' ),
			'mhmrentiva_auto_cancel_email_content'      => static fn(): string => self::get_default_auto_cancel_body(),

			// Booking Reminder
			'mhmrentiva_booking_reminder_subject'       => static fn(): string => __( 'Reminder: Your Booking #{booking_id} Starts Soon', 'mhm-rentiva' ),
			'mhmrentiva_booking_reminder_body'          => static fn(): string => self::get_default_booking_reminder_body(),

			// Refund Emails
			'mhmrentiva_refund_customer_subject'        => static fn(): string => __( 'Refund Processed for Booking #{booking_id}', 'mhm-rentiva' ),
			'mhmrentiva_refund_customer_body'           => static fn(): string => self::get_default_refund_customer_body(),
			'mhmrentiva_refund_admin_subject'           => static fn(): string => __( 'Refund Alert: Booking #{booking_id}', 'mhm-rentiva' ),
			'mhmrentiva_refund_admin_body'              => static fn(): string => self::get_default_refund_admin_body(),

			// Customer Notification Toggles
			'mhmrentiva_customer_welcome_email'         => '1',
			'mhmrentiva_customer_booking_notifications' => '1',
		);
	}

	/**
	 * GOLD STANDARD TEMPLATE: Customer Confirmation
	 */
	public static function get_default_customer_confirmation_body(): string {
		return '
<p>' . __( 'Dear {contact_name},', 'mhm-rentiva' ) . '</p>
<p>' . __( 'Your booking #{booking_id} has been successfully created. Below are your reservation details:', 'mhm-rentiva' ) . '</p>

<table style="width: 100%; border-collapse: collapse; margin: 25px 0; background: #f9f9f9; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
    <tr style="background: #f1f1f1;"><th colspan="2" style="padding: 12px 15px; text-align: left; border-bottom: 2px solid #ddd;">' . __( 'Reservation Details', 'mhm-rentiva' ) . '</th></tr>
    <tr><td style="padding: 10px 15px; border-bottom: 1px solid #eee; color: #666;">' . __( 'Reservation No:', 'mhm-rentiva' ) . '</td><td style="padding: 10px 15px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">#{booking_id}</td></tr>
    <tr><td style="padding: 10px 15px; border-bottom: 1px solid #eee; color: #666;">' . __( 'Vehicle:', 'mhm-rentiva' ) . '</td><td style="padding: 10px 15px; border-bottom: 1px solid #eee; text-align: right;">{vehicle_title}</td></tr>
    <tr><td style="padding: 10px 15px; border-bottom: 1px solid #eee; color: #666;">' . __( 'Pickup Date:', 'mhm-rentiva' ) . '</td><td style="padding: 10px 15px; border-bottom: 1px solid #eee; text-align: right;">{pickup_date}</td></tr>
    <tr><td style="padding: 10px 15px; border-bottom: 1px solid #eee; color: #666;">' . __( 'Return Date:', 'mhm-rentiva' ) . '</td><td style="padding: 10px 15px; border-bottom: 1px solid #eee; text-align: right;">{dropoff_date}</td></tr>
    <tr style="background: #fff8e1;"><td style="padding: 12px 15px; color: #333; font-weight: bold;">' . __( 'Total Amount:', 'mhm-rentiva' ) . '</td><td style="padding: 12px 15px; text-align: right; font-size: 18px; color: #d32f2f; font-weight: bold;">{total_price}</td></tr>
</table>

<div style="background: #e8f5e9; padding: 15px; border-radius: 6px; border-left: 4px solid #2e7d32; margin-bottom: 20px;">
    <p style="margin: 0; color: #2e7d32; font-weight: bold;">' . __( 'Thank you for choosing us!', 'mhm-rentiva' ) . '</p>
    <p style="margin: 5px 0 0 0; font-size: 13px;">' . __( 'Our team will review your application and contact you shortly if any further action is required.', 'mhm-rentiva' ) . '</p>
</div>';
	}

	/**
	 * GOLD STANDARD TEMPLATE: Booking Status Change
	 */
	public static function get_default_booking_status_body(): string {
		return '
<p>' . __( 'Dear {contact_name},', 'mhm-rentiva' ) . '</p>
<p>' . __( 'The status of your booking #{booking_id} has been updated:', 'mhm-rentiva' ) . '</p>

<div style="background: #ffffff; border: 2px solid #efefef; border-radius: 12px; padding: 25px; margin: 20px 0; text-align: center;">
    <span style="display: block; font-size: 14px; text-transform: uppercase; color: #888; margin-bottom: 5px;">' . __( 'New Status', 'mhm-rentiva' ) . '</span>
    <span style="display: block; font-size: 24px; font-weight: bold; color: #1e88e5; background: #e3f2fd; padding: 10px 20px; border-radius: 50px; display: inline-block;">{status}</span>
</div>

<p>' . __( 'You can track your reservation and view all details in your customer dashboard.', 'mhm-rentiva' ) . '</p>
<p style="text-align: center; margin-top: 30px;">
    <a href="{site_url}" style="background-color: #1e88e5; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">' . __( 'My Account', 'mhm-rentiva' ) . '</a>
</p>';
	}

	/**
	 * GOLD STANDARD TEMPLATE: Admin Notification
	 */
	public static function get_default_admin_notification_body(): string {
		return '
<div style="background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; border-left: 6px solid #ffc107; margin-bottom: 25px;">
    <h3 style="margin-top: 0;">' . __( 'New Booking Received!', 'mhm-rentiva' ) . '</h3>
    <p>' . __( 'A new reservation request has been submitted on your website. Please log in to the admin panel to process the request.', 'mhm-rentiva' ) . '</p>
</div>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr><td style="width: 40%; color: #888; padding: 8px 0;">' . __( 'Booking ID:', 'mhm-rentiva' ) . '</td><td style="font-weight: bold; padding: 8px 0;">#{booking_id}</td></tr>
    <tr><td style="color: #888; padding: 8px 0;">' . __( 'Customer:', 'mhm-rentiva' ) . '</td><td style="font-weight: bold; padding: 8px 0;">{contact_name} ({contact_email})</td></tr>
    <tr><td style="color: #888; padding: 8px 0;">' . __( 'Vehicle:', 'mhm-rentiva' ) . '</td><td style="padding: 8px 0;">{vehicle_title}</td></tr>
    <tr><td style="color: #888; padding: 8px 0;">' . __( 'Dates:', 'mhm-rentiva' ) . '</td><td style="padding: 8px 0;">{pickup_date} - {dropoff_date}</td></tr>
    <tr><td style="color: #888; padding: 8px 0;">' . __( 'Revenue:', 'mhm-rentiva' ) . '</td><td style="color: #28a745; font-weight: bold; padding: 8px 0;">{total_price}</td></tr>
</table>';
	}

	/**
	 * GOLD STANDARD TEMPLATE: Auto Cancel
	 */
	public static function get_default_auto_cancel_body(): string {
		return '
<p>' . __( 'Dear {contact_name},', 'mhm-rentiva' ) . '</p>
<p style="color: #d32f2f; font-weight: bold;">' . __( 'Your booking #{booking_id} has been automatically cancelled.', 'mhm-rentiva' ) . '</p>
<p>' . __( 'We did not receive the required payment within the allotted time frame, so the reservation has been released.', 'mhm-rentiva' ) . '</p>

<div style="background: #fafafa; border: 1px dashed #ddd; padding: 15px; margin: 20px 0; font-size: 14px; color: #666;">
    ' . __( 'If you believe this is an error or wish to make a new reservation, please visit our website.', 'mhm-rentiva' ) . '
</div>';
	}

	/**
	 * GOLD STANDARD TEMPLATE: Welcome Email
	 */
	public static function get_default_welcome_email_body(): string {
		return '
<div style="text-align: center; padding: 20px 0;">
    <h2 style="color: #1e88e5;">' . __( 'Welcome to {site_name}!', 'mhm-rentiva' ) . '</h2>
    <p style="font-size: 16px; color: #555;">' . __( 'We are happy to have you as part of our rental community.', 'mhm-rentiva' ) . '</p>
</div>
<p>' . __( 'Through your customer dashboard, you can:', 'mhm-rentiva' ) . '</p>
<ul style="color: #666;">
    <li>' . __( 'Manage and track your reservations', 'mhm-rentiva' ) . '</li>
    <li>' . __( 'Download payment invoices', 'mhm-rentiva' ) . '</li>
    <li>' . __( 'Communicate directly with our support team', 'mhm-rentiva' ) . '</li>
    <li>' . __( 'Save your profile for faster future bookings', 'mhm-rentiva' ) . '</li>
</ul>';
	}

	/**
	 * GOLD STANDARD TEMPLATE: Reminder
	 */
	public static function get_default_booking_reminder_body(): string {
		return '
<p>' . __( 'Dear {contact_name},', 'mhm-rentiva' ) . '</p>
<p>' . __( 'This is a friendly reminder that your upcoming rental starts very soon!', 'mhm-rentiva' ) . '</p>

<div style="background: #fff9c4; padding: 20px; border-radius: 8px; border: 1px solid #fbc02d; margin: 20px 0;">
    <h4 style="margin-top: 0; color: #f57f17;">' . __( 'Pickup Details', 'mhm-rentiva' ) . '</h4>
    <p><strong>' . __( 'Vehicle:', 'mhm-rentiva' ) . '</strong> {vehicle_title}</p>
    <p><strong>' . __( 'Pickup Time:', 'mhm-rentiva' ) . '</strong> {pickup_date}</p>
</div>

<p>' . __( 'Please make sure to have your identity documents and driver\'s license ready. We look forward to seeing you!', 'mhm-rentiva' ) . '</p>';
	}

	/**
	 * GOLD STANDARD TEMPLATE: Cancelled (Manual)
	 */
	public static function get_default_booking_cancelled_body(): string {
		return '<p>' . __( 'Dear {contact_name},', 'mhm-rentiva' ) . '</p><p>' . __( 'Your booking #{booking_id} has been cancelled.', 'mhm-rentiva' ) . '</p><table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #f8f9fa; border-radius: 8px;"><tr><td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . __( 'Booking ID:', 'mhm-rentiva' ) . '</strong></td><td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#{booking_id}</td></tr><tr><td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . __( 'Vehicle:', 'mhm-rentiva' ) . '</strong></td><td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{vehicle_title}</td></tr><tr><td style="padding: 10px 15px; color: #555;"><strong>' . __( 'Status:', 'mhm-rentiva' ) . '</strong></td><td style="padding: 10px 15px; text-align: right; color: #dc3545; font-weight: bold;">' . __( 'Cancelled', 'mhm-rentiva' ) . '</td></tr></table>';
	}

	/**
	 * Refund & Message Defaults (Simplified but professional)
	 */
	public static function get_default_refund_customer_body(): string {
		return '<p>' . __( 'Dear {contact_name},', 'mhm-rentiva' ) . '</p><p>' . __( 'A refund of {amount} has been processed for your booking #{booking_id}.', 'mhm-rentiva' ) . '</p><div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #28a745;"><strong>' . __( 'Refund Amount:', 'mhm-rentiva' ) . '</strong> {amount}</div>';
	}

	public static function get_default_refund_admin_body(): string {
		return '<p><strong>' . __( 'Refund Notification', 'mhm-rentiva' ) . '</strong></p><p>' . __( 'A refund of {amount} has been processed for booking #{booking_id}.', 'mhm-rentiva' ) . '</p>';
	}

	public static function get_default_admin_status_change_body(): string {
		return '<p><strong>' . __( 'Booking Status Update', 'mhm-rentiva' ) . '</strong></p><p>' . __( 'The status of booking #{booking_id} has been changed to {new_status}.', 'mhm-rentiva' ) . '</p>';
	}

	/**
	 * Register email settings sections and basic fields
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		add_settings_section(
			self::SECTION_ID,
			__( 'Email Configuration', 'mhm-rentiva' ),
			array( self::class, 'render_section_description' ),
			$page_slug
		);

		SettingsHelper::text_field( $page_slug, 'mhmrentiva_email_from_name', __( 'Sender Name', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::email_field( $page_slug, 'mhmrentiva_email_from_address', __( 'Sender Email', 'mhm-rentiva' ), '', self::SECTION_ID );
		SettingsHelper::email_field( $page_slug, 'mhmrentiva_email_reply_to', __( 'Reply-To Address', 'mhm-rentiva' ), '', self::SECTION_ID );

		add_settings_field(
			'mhmrentiva_email_base_color',
			__( 'Base Color', 'mhm-rentiva' ),
			array( self::class, 'render_color_field' ),
			$page_slug,
			self::SECTION_ID
		);

		SettingsHelper::url_field( $page_slug, 'mhmrentiva_email_header_image', __( 'Header Image (Logo URL)', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::textarea_field( $page_slug, 'mhmrentiva_email_footer_text', __( 'Footer Text', 'mhm-rentiva' ), 3, '', self::SECTION_ID );

		SettingsHelper::checkbox_field( $page_slug, 'mhmrentiva_email_send_enabled', __( 'Enable Outgoing Emails', 'mhm-rentiva' ), __( 'Allow system to send automated transaction emails.', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::checkbox_field( $page_slug, 'mhmrentiva_email_test_mode', __( 'Production Sandbox (Test Mode)', 'mhm-rentiva' ), __( 'Redirect all outgoing mails to the test address below.', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::email_field( $page_slug, 'mhmrentiva_email_test_address', __( 'Test Email Address', 'mhm-rentiva' ), '', self::SECTION_ID );

		SettingsHelper::text_field( $page_slug, 'mhmrentiva_email_template_path', __( 'Template Override Path', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::checkbox_field( $page_slug, 'mhmrentiva_email_auto_send', __( 'Automatic Background Sending', 'mhm-rentiva' ), '', self::SECTION_ID );
		SettingsHelper::checkbox_field( $page_slug, 'mhmrentiva_email_log_enabled', __( 'Enable Communication Logs', 'mhm-rentiva' ), '', self::SECTION_ID );
		SettingsHelper::number_field( $page_slug, 'mhmrentiva_email_log_retention_days', __( 'Log Retention (Days)', 'mhm-rentiva' ), 1, 365, '', self::SECTION_ID );

		// Customer Notification Toggles section
		add_settings_section(
			self::SECTION_NOTIFICATIONS,
			__( 'Customer Notification Toggles', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Control which automated emails are sent to customers.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::checkbox_field( $page_slug, 'mhmrentiva_customer_welcome_email', __( 'Send Welcome Email', 'mhm-rentiva' ), __( 'Send a one-time welcome email to customers on their first booking.', 'mhm-rentiva' ), self::SECTION_NOTIFICATIONS );
		SettingsHelper::checkbox_field( $page_slug, 'mhmrentiva_customer_booking_notifications', __( 'Send Booking Notifications', 'mhm-rentiva' ), __( 'Send booking confirmation, reminder, and cancellation emails to customers.', 'mhm-rentiva' ), self::SECTION_NOTIFICATIONS );
	}

	public static function render_section_description(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'WooCommerce Detected:', 'mhm-rentiva' ) . '</strong> ' . esc_html__( 'Transactional emails are managed by WooCommerce. These settings apply to plugin-specific internal alerts.', 'mhm-rentiva' ) . '</p></div>';
		}

		if ( current_user_can( 'manage_options' ) ) {
			$nonce = wp_create_nonce( 'mhmrentiva_send_test_email' );
			echo '<div style="margin: 15px 0;"><button type="button" class="button button-secondary mhm-send-test-email" data-nonce="' . esc_attr( $nonce ) . '"><span class="dashicons dashicons-email-alt" style="vertical-align: middle;"></span> ' . esc_html__( 'Send Test Connection Email', 'mhm-rentiva' ) . '</button></div>';
		}
	}

	public static function render_color_field(): void {
		$val = esc_attr( (string) SettingsCore::get( 'mhmrentiva_email_base_color', '#1e88e5' ) );
		printf(
			'<input type="color" name="mhmrentiva_settings[mhmrentiva_email_base_color]" value="%s" style="height:38px; width:70px; padding:2px; border:1px solid #ccc; border-radius:4px;"/>',
			esc_attr( (string) $val )
		);
	}

	// Static Accessors (Cached/High Performance)
	public static function get_from_name(): string {
		return (string) SettingsCore::get( 'mhmrentiva_email_from_name', get_bloginfo( 'name' ) );
	}
	public static function get_from_address(): string {
		return (string) SettingsCore::get( 'mhmrentiva_email_from_address', get_option( 'admin_email' ) );
	}
	public static function get_base_color(): string {
		return (string) SettingsCore::get( 'mhmrentiva_email_base_color', '#1e88e5' );
	}
	public static function get_header_image(): string {
		return (string) SettingsCore::get( 'mhmrentiva_email_header_image', '' );
	}
	public static function get_footer_text(): string {
		return (string) SettingsCore::get( 'mhmrentiva_email_footer_text', '' );
	}
	public static function is_send_enabled(): bool {
		return SettingsCore::get( 'mhmrentiva_email_send_enabled', '1' ) === '1';
	}
	public static function is_test_mode(): bool {
		return SettingsCore::get( 'mhmrentiva_email_test_mode', '0' ) === '1';
	}
	public static function get_test_address(): string {
		return (string) SettingsCore::get( 'mhmrentiva_email_test_address', get_option( 'admin_email' ) );
	}
	public static function get_template_path(): string {
		return (string) SettingsCore::get( 'mhmrentiva_email_template_path', 'mhm-rentiva/emails/' );
	}
	public static function is_auto_send_enabled(): bool {
		return SettingsCore::get( 'mhmrentiva_email_auto_send', '1' ) === '1';
	}
	public static function is_log_enabled(): bool {
		return SettingsCore::get( 'mhmrentiva_email_log_enabled', '1' ) === '1';
	}
	public static function get_log_retention_days(): int {
		return (int) SettingsCore::get( 'mhmrentiva_email_log_retention_days', 30 );
	}
}
