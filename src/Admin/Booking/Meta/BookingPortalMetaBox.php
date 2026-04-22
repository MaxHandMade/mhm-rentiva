<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer Account Meta Box
 *
 * Shows customer account information linked to booking
 *
 * @since 4.0.0 - Token system removed, WP user system added
 */
final class BookingPortalMetaBox {


	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		add_action( 'wp_ajax_mhm_create_customer_account_manual', array( self::class, 'ajax_create_customer_account' ) );
	}

	public static function add(): void {
		add_meta_box(
			'mhm_booking_customer_account',
			__( 'Customer Account', 'mhm-rentiva' ),
			array( self::class, 'render' ),
			'vehicle_booking',
			'side',
			'default'
		);
	}

	public static function render( \WP_Post $post ): void {
		$booking_id       = (int) $post->ID;
		$customer_email   = get_post_meta( $booking_id, '_mhm_customer_email', true );
		$customer_name    = get_post_meta( $booking_id, '_mhm_customer_name', true );
		$customer_user_id = (int) get_post_meta( $booking_id, '_mhm_customer_user_id', true );

		echo '<div class="mhm-customer-account-info">';

		if ( $customer_user_id > 0 ) {
			$user = get_user_by( 'id', $customer_user_id );

			if ( $user ) {
				echo '<p><strong>' . esc_html__( 'Customer:', 'mhm-rentiva' ) . '</strong><br>';
				echo esc_html( $user->display_name ) . '</p>';

				echo '<p><strong>' . esc_html__( 'Email:', 'mhm-rentiva' ) . '</strong><br>';
				echo '<a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></p>';

				echo '<p><strong>' . esc_html__( 'Account Status:', 'mhm-rentiva' ) . '</strong><br>';
				echo '<span class="mhm-portal-status-active">';
				echo '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>';
				echo esc_html__( 'Active', 'mhm-rentiva' );
				echo '</span></p>';

				$account_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();

				echo '<p><a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $customer_user_id ) ) . '" class="button button-secondary mhm-portal-action-btn">';
				echo '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span> ';
				echo esc_html__( 'View User Profile', 'mhm-rentiva' ) . '</a></p>';

				echo '<p><a href="' . esc_url( $account_url ) . '" class="button button-primary mhm-portal-action-btn" target="_blank">';
				echo '<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> ';
				echo esc_html__( 'Customer Account Page', 'mhm-rentiva' ) . '</a></p>';

				echo '<hr class="mhm-portal-divider">';

				echo '<p class="mhm-portal-info-text">';
				echo '<span class="dashicons dashicons-info" aria-hidden="true"></span> ';
				echo esc_html__( 'Customer can login with their email and password to view this booking.', 'mhm-rentiva' );
				echo '</p>';
			} else {
				echo '<p class="mhm-portal-status-error">';
				echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
				echo esc_html__( 'User account not found', 'mhm-rentiva' );
				echo '</p>';
			}
		} else {
			echo '<p class="mhm-portal-status-warning">';
			echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
			echo esc_html__( 'No customer account linked', 'mhm-rentiva' );
			echo '</p>';

			if ( $customer_email ) {
				echo '<p><strong>' . esc_html__( 'Email:', 'mhm-rentiva' ) . '</strong><br>';
				echo esc_html( $customer_email ) . '</p>';

				$nonce = wp_create_nonce( 'mhm_create_customer_account' );

				echo '<p><button type="button" class="button button-primary mhm-portal-action-btn mhm-create-customer-account-btn"';
				echo ' data-booking-id="' . esc_attr( (string) $booking_id ) . '"';
				echo ' data-email="' . esc_attr( $customer_email ) . '"';
				echo ' data-name="' . esc_attr( $customer_name ) . '"';
				echo ' data-nonce="' . esc_attr( $nonce ) . '">';
				echo '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span> ';
				echo esc_html__( 'Create Customer Account', 'mhm-rentiva' ) . '</button></p>';
			}
		}

		echo '</div>';
	}

	public static function ajax_create_customer_account(): void {
		// Capability check
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mhm-rentiva' ) ) );
			return;
		}

		// Nonce check
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mhm_create_customer_account' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Get and validate input
		$booking_id = isset( $_POST['booking_id'] ) ? absint( wp_unslash( $_POST['booking_id'] ) ) : 0;
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( ! $booking_id || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking or email.', 'mhm-rentiva' ) ) );
			return;
		}

		// Verify booking exists and is the correct post type
		$booking = get_post( $booking_id );
		if ( ! $booking || 'vehicle_booking' !== $booking->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Check if user already exists with this email
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			update_post_meta( $booking_id, '_mhm_customer_user_id', $existing_user->ID );
			wp_send_json_success( array(
				'message' => __( 'Existing account linked to booking.', 'mhm-rentiva' ),
			) );
			return;
		}

		// Build username from email
		$username = sanitize_user( $email, true );

		// Create the WP user
		$user_id = wp_create_user( $username, wp_generate_password(), $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			return;
		}

		// Set display name, first/last name, and role
		$name_parts   = explode( ' ', trim( $name ), 2 );
		$first_name   = $name_parts[0];
		$last_name    = isset( $name_parts[1] ) ? $name_parts[1] : '';
		$display_name = trim( $name ) ?: $email;
		$role         = get_role( 'customer' ) ? 'customer' : 'subscriber';

		$update_result = wp_update_user( array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => $display_name,
			'role'         => $role,
		) );

		if ( is_wp_error( $update_result ) ) {
			wp_send_json_error( array( 'message' => $update_result->get_error_message() ) );
			return;
		}

		// Link the new user to the booking
		update_post_meta( $booking_id, '_mhm_customer_user_id', $user_id );

		wp_send_json_success( array(
			'message' => __( 'Customer account created and linked to booking.', 'mhm-rentiva' ),
		) );
	}
}
