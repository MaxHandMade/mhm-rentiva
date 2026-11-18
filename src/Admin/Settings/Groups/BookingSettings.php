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
 * Booking Management Settings
 *
 * Only includes actively used settings.
 * Refactored for modularity and high performance.
 *
 * @since 4.0.0
 */
final class BookingSettings {

	public const SECTION_BASIC        = 'mhmrentiva_booking_basic_section';
	public const SECTION_TIME         = 'mhmrentiva_booking_time_section';
	public const SECTION_NOTIFICATION = 'mhmrentiva_booking_notification_section';
	public const SECTION_AGREEMENT    = 'mhmrentiva_booking_agreement_section';

	/**
	 * Get default settings, fully materialised.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return SettingsCore::resolve_defaults( self::deferred_default_settings() );
	}

	/**
	 * The same defaults with the translated one (the agreement template) still
	 * deferred, so merging the defaults map does not call __() before `init`
	 * (see SettingsCore::defaults_map()).
	 *
	 * @return array
	 */
	public static function deferred_default_settings(): array {
		return array(
			'mhmrentiva_booking_cancellation_deadline_hours' => 24,
			'mhmrentiva_booking_payment_deadline_minutes' => 30,
			'mhmrentiva_booking_auto_cancel_enabled'      => '1',
			'mhmrentiva_booking_send_confirmation_emails' => '1',
			'mhmrentiva_booking_send_reminder_emails'     => '1',
			'mhmrentiva_booking_admin_notifications'      => '1',
			'mhmrentiva_send_auto_cancel_email'           => '0',
			'mhmrentiva_default_rental_days'              => 1,
			'mhmrentiva_booking_buffer_time'              => 60,
			'mhmrentiva_booking_security_deposit_note'    => '',
			'mhmrentiva_booking_fuel_policy_note'         => '',
			// Off by default: a site that updates keeps its booking form as it was.
			'mhmrentiva_rental_agreement_enabled'         => '0',
			'mhmrentiva_rental_agreement_text'            => static fn (): string => self::rental_agreement_template(),
		);
	}

	/**
	 * The rental agreement as the booking form uses it: whether it is required,
	 * and its text (an empty stored text resolves to the template).
	 *
	 * @return array{enabled: bool, text: string}
	 */
	public static function rental_agreement(): array {
		return array(
			'enabled' => '1' === (string) SettingsCore::get( 'mhmrentiva_rental_agreement_enabled', '0' ),
			'text'    => trim( (string) SettingsCore::get( 'mhmrentiva_rental_agreement_text', '' ) ),
		);
	}

	/**
	 * The built-in rental agreement: what customers see while the Agreement
	 * Text field is empty. A neutral starting point between the renter and
	 * the rental business -- it names neither, fixes no amounts, and the
	 * field description asks the operator to review it. Written in the light
	 * formatting AgreementText renders; no title line, the form section
	 * carries the "Rental agreement" heading.
	 */
	public static function rental_agreement_template(): string {
		$sections = array(
			__( 'This agreement sets out the terms between you, the renter who books a vehicle on this website, and the business that rents the vehicle out.', 'mhm-rentiva' ),
			'## ' . __( '1. Driver and documents', 'mhm-rentiva' ),
			'- ' . __( 'Only the drivers named in the booking, each holding a valid driving licence, may drive the vehicle.', 'mhm-rentiva' ),
			'- ' . __( 'The driving licence and an identity document are shown at pickup; additional drivers show theirs too.', 'mhm-rentiva' ),
			'## ' . __( '2. Pickup and return', 'mhm-rentiva' ),
			'- ' . __( 'The vehicle is picked up and returned at the place and time in the booking.', 'mhm-rentiva' ),
			'- ' . __( 'A late return may be charged as extra rental days under the business’s rates.', 'mhm-rentiva' ),
			'## ' . __( '3. Fuel and use of the vehicle', 'mhm-rentiva' ),
			'- ' . __( 'The vehicle is returned with the fuel level it was picked up with; missing fuel is charged.', 'mhm-rentiva' ),
			'- ' . __( 'The vehicle may not be used for racing, towing, commercial transport or unauthorised travel abroad, and may not be sublet.', 'mhm-rentiva' ),
			'## ' . __( '4. Damage, accidents and insurance', 'mhm-rentiva' ),
			'- ' . __( 'After an accident or damage, the authorities and the business are told at once and the required report is made.', 'mhm-rentiva' ),
			'- ' . __( 'The renter is liable for damage the insurance does not cover and for the insurance excess.', 'mhm-rentiva' ),
			'## ' . __( '5. Fines and charges', 'mhm-rentiva' ),
			__( 'Traffic fines, bridge and motorway tolls and parking charges incurred during the rental are paid by the renter.', 'mhm-rentiva' ),
			'## ' . __( '6. Payment, deposit and cancellation', 'mhm-rentiva' ),
			'- ' . __( 'The deposit and the remaining amount are paid as shown in the booking.', 'mhm-rentiva' ),
			'- ' . __( 'Cancellations and refunds follow the cancellation policy published on this website.', 'mhm-rentiva' ),
			'## ' . __( '7. Personal data', 'mhm-rentiva' ),
			__( 'Your personal data is processed only to carry out the rental and under the data protection law that applies.', 'mhm-rentiva' ),
		);

		// One line per entry, a blank line before each heading: consecutive
		// list items stay one list.
		return (string) preg_replace( '/\n(?=#)/', "\n\n", implode( "\n", $sections ) );
	}

	/**
	 * Render the booking settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_BASIC );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_TIME );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_NOTIFICATION );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_AGREEMENT );
		}
	}

	/**
	 * Register settings
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		// 1. Basic Booking Settings Section
		add_settings_section(
			self::SECTION_BASIC,
			__( 'Basic Booking Settings', 'mhm-rentiva' ),
			fn() => print( '<p class="description">' . esc_html__( 'General booking configuration and data retention settings.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_default_rental_days',
			__( 'Default Rental Days', 'mhm-rentiva' ),
			1,
			365,
			__( 'Default rental duration in booking form (days).', 'mhm-rentiva' ),
			self::SECTION_BASIC
		);

		// Rental conditions on the vehicle page; an empty note is not shown.
		SettingsHelper::textarea_field(
			$page_slug,
			'mhmrentiva_booking_security_deposit_note',
			__( 'Security deposit note', 'mhm-rentiva' ),
			3,
			__( 'Shown under Rental conditions on the vehicle page, e.g. "5,000 TL card hold at pickup, released on return". Leave empty to hide.', 'mhm-rentiva' ),
			self::SECTION_BASIC
		);

		SettingsHelper::textarea_field(
			$page_slug,
			'mhmrentiva_booking_fuel_policy_note',
			__( 'Fuel policy note', 'mhm-rentiva' ),
			3,
			__( 'Shown under Rental conditions on the vehicle page, e.g. "Full to full; missing fuel is charged". Leave empty to hide.', 'mhm-rentiva' ),
			self::SECTION_BASIC
		);

		// 2. Time Management Settings Section
		add_settings_section(
			self::SECTION_TIME,
			__( 'Time Management Settings', 'mhm-rentiva' ),
			fn() => print( '<p class="description">' . esc_html__( 'Configure timing rules for bookings, cancellations and payments.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_booking_cancellation_deadline_hours',
			__( 'Cancellation Deadline (Hours)', 'mhm-rentiva' ),
			1,
			168,
			__( 'Hours before booking start that customers can cancel.', 'mhm-rentiva' ),
			self::SECTION_TIME
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_booking_payment_deadline_minutes',
			__( 'Payment Deadline (Minutes)', 'mhm-rentiva' ),
			0,
			1440,
			__( 'Minutes allowed for payment completion. Set to 0 to disable deadline.', 'mhm-rentiva' ),
			self::SECTION_TIME
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_booking_auto_cancel_enabled',
			__( 'Enable Auto Cancel', 'mhm-rentiva' ),
			__( 'Automatically cancel bookings that exceed payment deadline', 'mhm-rentiva' ),
			self::SECTION_TIME
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_booking_buffer_time',
			__( 'Buffer Time (Minutes)', 'mhm-rentiva' ),
			0,
			1440,
			__( 'Minimum time gap required between bookings for cleaning and preparation.', 'mhm-rentiva' ),
			self::SECTION_TIME
		);

		// 3. Notification Settings Section
		add_settings_section(
			self::SECTION_NOTIFICATION,
			__( 'Notification Settings', 'mhm-rentiva' ),
			fn() => print( '<p class="description">' . esc_html__( 'Email notification settings for bookings.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_booking_send_confirmation_emails',
			__( 'Send Confirmation Emails', 'mhm-rentiva' ),
			__( 'Send confirmation emails to customers when bookings are confirmed', 'mhm-rentiva' ),
			self::SECTION_NOTIFICATION
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_booking_send_reminder_emails',
			__( 'Send Reminder Emails', 'mhm-rentiva' ),
			__( 'Send reminder emails to customers before booking start time', 'mhm-rentiva' ),
			self::SECTION_NOTIFICATION
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_booking_admin_notifications',
			__( 'Admin Notifications', 'mhm-rentiva' ),
			__( 'Send email notifications to admin when new bookings are created', 'mhm-rentiva' ),
			self::SECTION_NOTIFICATION
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_send_auto_cancel_email',
			__( 'Send Auto Cancel Email', 'mhm-rentiva' ),
			__( 'Send notification to customer when booking is auto-cancelled due to timeout.', 'mhm-rentiva' ),
			self::SECTION_NOTIFICATION
		);

		// 4. Rental agreement on the booking form
		add_settings_section(
			self::SECTION_AGREEMENT,
			__( 'Rental agreement', 'mhm-rentiva' ),
			fn() => print( '<p class="description">' . esc_html__( 'An agreement the customer reads and accepts on the booking form before booking.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_rental_agreement_enabled',
			__( 'Require rental agreement', 'mhm-rentiva' ),
			__( 'Customers must accept the agreement before booking. The time and the version of the text they accepted are saved with the booking.', 'mhm-rentiva' ),
			self::SECTION_AGREEMENT
		);

		SettingsHelper::textarea_field(
			$page_slug,
			'mhmrentiva_rental_agreement_text',
			__( 'Agreement text', 'mhm-rentiva' ),
			12,
			__( 'Leave empty to use the built-in template, which is a starting point, not legal advice: review it and adapt it to your business and local law. Formatting: # Heading, **bold**, - list item, --- line.', 'mhm-rentiva' ),
			self::SECTION_AGREEMENT
		);
	}
}
