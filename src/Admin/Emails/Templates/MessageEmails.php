<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MessageEmails {

	public static function render(): void {
		echo '<h2>' . esc_html__( 'Message Notifications', 'mhm-rentiva' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Configure email notifications for the messaging system.', 'mhm-rentiva' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Available placeholders: {booking_id}, {customer_name}, {contact_email}, {message_body}, {reply_body}, {site_name}', 'mhm-rentiva' ) . '</p>';

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

		// New Message (Admin)
		$admin_fields = array(
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_message_received_admin_subject',
				'label' => esc_html__( 'Subject (Admin)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_message_received_admin_subject', fn() => __( 'New Message from {contact_name}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_message_received_admin_body',
				'label' => esc_html__( 'Content (Admin HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_message_received_admin_body', array( EmailSettings::class, 'get_default_message_admin_body' ), 'message_body' ),
				'rows'  => 10,
			),
		);

		EmailFormRenderer::render_form(
			__( 'New Message Notification (Admin)', 'mhm-rentiva' ),
			__( 'Email sent to admin when a customer sends a new message.', 'mhm-rentiva' ),
			$admin_fields
		);

		// Reply Message (Customer)
		$customer_fields = array(
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_message_replied_customer_subject',
				'label' => esc_html__( 'Subject (Customer)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_message_replied_customer_subject', fn() => __( 'New Reply for Booking #{booking_id}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_message_replied_customer_body',
				'label' => esc_html__( 'Content (Customer HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_message_replied_customer_body', array( EmailSettings::class, 'get_default_message_customer_body' ), 'reply_body' ),
				'rows'  => 10,
			),
		);

		EmailFormRenderer::render_form(
			__( 'Message Reply Notification (Customer)', 'mhm-rentiva' ),
			__( 'Email sent to customer when admin replies to a message.', 'mhm-rentiva' ),
			$customer_fields
		);

		// Auto Reply (Customer)
		$auto_reply_fields = array(
			array(
				'type'  => 'text',
				'name'  => 'mhm_rentiva_message_auto_reply_subject',
				'label' => esc_html__( 'Subject (Auto Reply)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_message_auto_reply_subject', fn() => __( 'We received your message - {site_name}', 'mhm-rentiva' ) ),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'mhm_rentiva_message_auto_reply_body',
				'label' => esc_html__( 'Content (Auto Reply HTML)', 'mhm-rentiva' ),
				'value' => $get_val( 'mhm_rentiva_message_auto_reply_body', array( EmailSettings::class, 'get_default_message_auto_reply_body' ) ),
				'rows'  => 10,
			),
		);

		EmailFormRenderer::render_form(
			__( 'Message Auto Reply (Customer)', 'mhm-rentiva' ),
			__( 'Email confirmation sent to customer when they submit a new message.', 'mhm-rentiva' ),
			$auto_reply_fields
		);
	}
}
