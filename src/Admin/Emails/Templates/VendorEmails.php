<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;

final class VendorEmails {

	public static function render(): void {
		echo '<h2>' . esc_html__( 'Vendor Notifications', 'mhm-rentiva' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Configure email notifications sent to vendors and admins for marketplace events.', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Available placeholders (all): {{vendor.name}}, {{site.name}}, {{site.url}}, {{panel.url}}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Vehicle emails also: {{vehicle.title}}, {{vehicle.url}}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Lifecycle emails also: {{lifecycle.days_remaining}}, {{lifecycle.expires_at}}, {{lifecycle.duration_days}}, {{lifecycle.cooldown_days}}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Payout emails also: {{payout.amount_formatted}}', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Note: Leave body empty to use the built-in Gold Standard template.', 'mhm-rentiva' ) . '</p>';

		$get_val = function ( string $opt_key ): string {
			return (string) get_option( $opt_key, '' );
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
					'value' => $get_val( 'mhm_rentiva_vendor_approved_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vendor_approved_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vendor_rejected_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vendor_rejected_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vendor_suspended_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_suspended_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vendor_suspended_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vendor_application_received_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_application_received_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vendor_application_received_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vendor_application_new_admin_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vendor_application_new_admin_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vendor_application_new_admin_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_approved_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_approved_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_rejected_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_rejected_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_submitted_admin_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_submitted_admin_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_submitted_admin_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_rereview_admin_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_rereview_admin_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_rereview_admin_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_activated_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_activated_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_activated_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_paused_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_paused_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_paused_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_resumed_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_resumed_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_resumed_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_expired_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_expired_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_expired_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_withdrawn_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_withdrawn_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_withdrawn_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_renewed_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_renewed_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_renewed_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_relisted_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_relisted_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_relisted_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_expiry_warning_first_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_expiry_warning_first_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_expiry_warning_first_body' ),
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
					'value' => $get_val( 'mhm_rentiva_vehicle_expiry_warning_second_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_vehicle_expiry_warning_second_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_vehicle_expiry_warning_second_body' ),
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
					'value' => $get_val( 'mhm_rentiva_payout_approved_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_payout_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_payout_approved_body' ),
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
					'value' => $get_val( 'mhm_rentiva_payout_rejected_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_payout_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_payout_rejected_body' ),
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
					'value' => $get_val( 'mhm_rentiva_iban_change_approved_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_iban_change_approved_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_iban_change_approved_body' ),
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
					'value' => $get_val( 'mhm_rentiva_iban_change_rejected_subject' ),
				),
				array(
					'type'  => 'textarea',
					'name'  => 'mhm_rentiva_iban_change_rejected_body',
					'label' => __( 'Content (HTML)', 'mhm-rentiva' ),
					'value' => $get_val( 'mhm_rentiva_iban_change_rejected_body' ),
					'rows'  => 12,
				),
			)
		);
	}
}
