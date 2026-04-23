<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;
use MHMRentiva\Admin\Emails\Core\Templates;

final class VendorEmails {

	public static function render(): void {
		echo '<h2>' . esc_html__( 'Vendor Notifications', 'mhm-rentiva' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Configure email notifications sent to vendors and admins for marketplace events.', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Available placeholders (all): {{vendor.name}}, {{site.name}}, {{site.url}}, {{panel.url}}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Vehicle emails also: {{vehicle.title}}, {{vehicle.url}}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Lifecycle emails also: {{lifecycle.days_remaining}}, {{lifecycle.expires_at}}, {{lifecycle.duration_days}}, {{lifecycle.cooldown_days}}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Payout emails also: {{payout.amount_formatted}}', 'mhm-rentiva' ) . '</p>';

		$registry = Templates::registry();

		/**
		 * Get saved subject, falling back to registry default when not yet customised.
		 */
		$get_subject = function ( string $opt_key, string $template_key ) use ( $registry ): string {
			$val = (string) get_option( $opt_key, '' );
			if ( $val !== '' ) {
				return $val;
			}
			return (string) ( $registry[ $template_key ]['subject'] ?? '' );
		};

		/**
		 * Get saved body, falling back to the rendered template file when not yet customised.
		 * Same pattern as BookingNotifications — shows actual content so admin can see and edit.
		 */
		$get_body = function ( string $opt_key, string $template_key ): string {
			$val = (string) get_option( $opt_key, '' );
			if ( trim( $val ) !== '' ) {
				return $val;
			}
			return self::render_template_default( $template_key );
		};

		// -----------------------------------------------------------------------
		// Section 1: Vendor Account
		// -----------------------------------------------------------------------
		echo '<h3>' . esc_html__( 'Vendor Account', 'mhm-rentiva' ) . '</h3>';

		EmailFormRenderer::render_form(
			__( 'Vendor Approved', 'mhm-rentiva' ),
			__( 'Sent to vendor when application is approved.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vendor_approved_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vendor_approved_subject', 'vendor_approved' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vendor_approved_body', 'vendor_approved' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vendor Rejected', 'mhm-rentiva' ),
			__( 'Sent to vendor when application is rejected.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vendor_rejected_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vendor_rejected_subject', 'vendor_rejected' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vendor_rejected_body', 'vendor_rejected' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vendor Suspended', 'mhm-rentiva' ),
			__( 'Sent to vendor when account is suspended.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vendor_suspended_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vendor_suspended_subject', 'vendor_suspended' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_suspended_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vendor_suspended_body', 'vendor_suspended' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Application Received', 'mhm-rentiva' ),
			__( 'Confirmation sent to applicant when vendor application is received.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vendor_application_received_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vendor_application_received_subject', 'vendor_application_received' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_application_received_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vendor_application_received_body', 'vendor_application_received' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'New Application (Admin)', 'mhm-rentiva' ),
			__( 'Admin notification when a new vendor application is submitted.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vendor_application_new_admin_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vendor_application_new_admin_subject', 'vendor_application_new_admin' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_application_new_admin_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vendor_application_new_admin_body', 'vendor_application_new_admin' ),
					'rows'  => 12,
				),
			)
		);

		// -----------------------------------------------------------------------
		// Section: Booking Notifications (sent to vendor)
		// -----------------------------------------------------------------------
		echo '<h3>' . esc_html__( 'Booking Notifications', 'mhm-rentiva' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Booking emails use these placeholders: {{customer.name}}, {{customer.email}}, {{customer.phone}}, {{vehicle.title}}, {{booking.order_id}}, {{booking.pickup_date}}, {{booking.return_date}}, {{booking.total_price}}, {{booking.service_type}}.', 'mhm-rentiva' ) . '</p>';

		EmailFormRenderer::render_form(
			__( 'New Reservation (Vendor)', 'mhm-rentiva' ),
			__( 'Sent to the vendor when a customer reserves one of their vehicles.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_booking_created_vendor_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_booking_created_vendor_subject', 'booking_created_vendor' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_booking_created_vendor_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_booking_created_vendor_body', 'booking_created_vendor' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Reservation Status Changed (Vendor)', 'mhm-rentiva' ),
			__( 'Sent to the vendor when a reservation status changes (e.g. confirmed, cancelled).', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_booking_status_vendor_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_booking_status_vendor_subject', 'booking_status_changed_vendor' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_booking_status_vendor_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_booking_status_vendor_body', 'booking_status_changed_vendor' ),
					'rows'  => 12,
				),
			)
		);

		// -----------------------------------------------------------------------
		// Section 2: Vehicle Review
		// -----------------------------------------------------------------------
		echo '<h3>' . esc_html__( 'Vehicle Review', 'mhm-rentiva' ) . '</h3>';

		EmailFormRenderer::render_form(
			__( 'Vehicle Approved', 'mhm-rentiva' ),
			__( 'Sent to vendor when a vehicle listing is approved.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_approved_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_approved_subject', 'vehicle_approved' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_approved_body', 'vehicle_approved' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vehicle Rejected', 'mhm-rentiva' ),
			__( 'Sent to vendor when a vehicle listing is rejected.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_rejected_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_rejected_subject', 'vehicle_rejected' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_rejected_body', 'vehicle_rejected' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vehicle Submitted (Admin)', 'mhm-rentiva' ),
			__( 'Admin notification when a vehicle is submitted for review.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_submitted_admin_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_submitted_admin_subject', 'vehicle_submitted_admin' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_submitted_admin_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_submitted_admin_body', 'vehicle_submitted_admin' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vehicle Re-review (Admin)', 'mhm-rentiva' ),
			__( 'Admin notification when a vehicle is resubmitted for re-review.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_rereview_admin_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_rereview_admin_subject', 'vehicle_rereview_admin' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_rereview_admin_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_rereview_admin_body', 'vehicle_rereview_admin' ),
					'rows'  => 12,
				),
			)
		);

		// -----------------------------------------------------------------------
		// Section 3: Vehicle Lifecycle
		// -----------------------------------------------------------------------
		echo '<h3>' . esc_html__( 'Vehicle Lifecycle', 'mhm-rentiva' ) . '</h3>';

		EmailFormRenderer::render_form(
			__( 'Vehicle Activated', 'mhm-rentiva' ),
			__( 'Sent to vendor when a listing goes live.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_activated_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_activated_subject', 'vehicle_activated' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_activated_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_activated_body', 'vehicle_activated' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vehicle Paused', 'mhm-rentiva' ),
			__( 'Sent to vendor when they pause a listing.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_paused_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_paused_subject', 'vehicle_paused' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_paused_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_paused_body', 'vehicle_paused' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vehicle Resumed', 'mhm-rentiva' ),
			__( 'Sent to vendor when they resume a paused listing.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_resumed_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_resumed_subject', 'vehicle_resumed' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_resumed_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_resumed_body', 'vehicle_resumed' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Listing Expired', 'mhm-rentiva' ),
			__( 'Sent to vendor when a 90-day listing expires.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_expired_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_expired_subject', 'vehicle_expired' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_expired_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_expired_body', 'vehicle_expired' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vehicle Withdrawn', 'mhm-rentiva' ),
			__( 'Sent to vendor when a listing is withdrawn.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_withdrawn_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_withdrawn_subject', 'vehicle_withdrawn' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_withdrawn_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_withdrawn_body', 'vehicle_withdrawn' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Listing Renewed', 'mhm-rentiva' ),
			__( 'Sent to vendor when they renew a listing.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_renewed_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_renewed_subject', 'vehicle_renewed' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_renewed_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_renewed_body', 'vehicle_renewed' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Vehicle Relisted', 'mhm-rentiva' ),
			__( 'Sent to vendor when a withdrawn vehicle is resubmitted.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_relisted_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_relisted_subject', 'vehicle_relisted' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_relisted_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_relisted_body', 'vehicle_relisted' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Expiry Warning (First)', 'mhm-rentiva' ),
			__( 'First warning sent to vendor before listing expires.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_expiry_warning_first_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_expiry_warning_first_subject', 'vehicle_expiry_warning_first' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_expiry_warning_first_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_expiry_warning_first_body', 'vehicle_expiry_warning_first' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Expiry Warning (Second)', 'mhm-rentiva' ),
			__( 'Urgent warning sent to vendor before listing expires.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_vehicle_expiry_warning_second_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_vehicle_expiry_warning_second_subject', 'vehicle_expiry_warning_second' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_expiry_warning_second_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_vehicle_expiry_warning_second_body', 'vehicle_expiry_warning_second' ),
					'rows'  => 12,
				),
			)
		);

		// -----------------------------------------------------------------------
		// Section 4: Financial Emails
		// -----------------------------------------------------------------------
		echo '<h3>' . esc_html__( 'Financial Emails', 'mhm-rentiva' ) . '</h3>';

		EmailFormRenderer::render_form(
			__( 'Payout Approved', 'mhm-rentiva' ),
			__( 'Sent to vendor when a payout is approved.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_payout_approved_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_payout_approved_subject', 'payout_approved' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_payout_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_payout_approved_body', 'payout_approved' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'Payout Rejected', 'mhm-rentiva' ),
			__( 'Sent to vendor when a payout is rejected.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_payout_rejected_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_payout_rejected_subject', 'payout_rejected' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_payout_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_payout_rejected_body', 'payout_rejected' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'IBAN Change Approved', 'mhm-rentiva' ),
			__( 'Sent to vendor when an IBAN change request is approved.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_iban_change_approved_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_iban_change_approved_subject', 'iban_change_approved' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_iban_change_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_iban_change_approved_body', 'iban_change_approved' ),
					'rows'  => 12,
				),
			)
		);

		EmailFormRenderer::render_form(
			__( 'IBAN Change Rejected', 'mhm-rentiva' ),
			__( 'Sent to vendor when an IBAN change request is rejected.', 'mhm-rentiva' ),
			array(
				array(
					'type'  => 'text',
					'name'  => 'mhm_rentiva_iban_change_rejected_subject',
					'label' => __( 'Subject', 'mhm-rentiva' ),
					'value' => $get_subject( 'mhm_rentiva_iban_change_rejected_subject', 'iban_change_rejected' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_iban_change_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_body( 'mhm_rentiva_iban_change_rejected_body', 'iban_change_rejected' ),
					'rows'  => 12,
				),
			)
		);
	}

	/**
	 * Render the template file with mock data and return the HTML fragment.
	 * Used as a fallback when no custom body has been saved in the database.
	 */
	private static function render_template_default( string $template_key ): string {
		$registry = Templates::registry();
		$slug     = $registry[ $template_key ]['file'] ?? str_replace( '_', '-', $template_key );
		$path     = Templates::locate_template( $slug );
		if ( ! $path ) {
			return '';
		}
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
		$data = self::mock_context();
		ob_start();
		include $path;
		return (string) ob_get_clean();
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
	}

	/**
	 * Mock context for template preview in admin — uses visible placeholder strings.
	 */
	private static function mock_context(): array {
		return array(
			'vendor'        => array(
				'name'  => '{{vendor.name}}',
				'email' => '{{vendor.email}}',
			),
			'site'          => array(
				'name' => '{{site.name}}',
				'url'  => '{{site.url}}',
			),
			'panel'         => array( 'url' => '{{panel.url}}' ),
			'vehicle'       => array(
				'title'     => '{{vehicle.title}}',
				'url'       => '{{vehicle.url}}',
				'admin_url' => '{{vehicle.admin_url}}',
			),
			'lifecycle'     => array(
				'days_remaining' => '{{lifecycle.days_remaining}}',
				'expires_at'     => '{{lifecycle.expires_at}}',
				'duration_days'  => '{{lifecycle.duration_days}}',
				'cooldown_days'  => '{{lifecycle.cooldown_days}}',
			),
			'payout'        => array( 'amount_formatted' => '{{payout.amount_formatted}}' ),
			'application'   => array( 'id' => '{{application.id}}' ),
			'customer'      => array(
				'name'  => '{{customer.name}}',
				'email' => '{{customer.email}}',
				'phone' => '{{customer.phone}}',
			),
			'booking'       => array(
				'id'             => '{{booking.id}}',
				'order_id'       => '{{booking.order_id}}',
				'service_type'   => '{{booking.service_type}}',
				'pickup_date'    => '{{booking.pickup_date}}',
				'return_date'    => '{{booking.return_date}}',
				'total_price'    => '{{booking.total_price}}',
				'payment_status' => '{{booking.payment_status}}',
			),
			'status_change' => array(
				'old_status'       => '{{status_change.old_status}}',
				'new_status'       => '{{status_change.new_status}}',
				'old_status_label' => '{{status_change.old_status_label}}',
				'new_status_label' => '{{status_change.new_status_label}}',
			),
		);
	}
}
