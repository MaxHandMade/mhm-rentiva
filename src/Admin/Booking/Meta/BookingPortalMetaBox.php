<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

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
		$booking_id = (int) $post->ID;

		// Get customer information
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
				echo '<span style="color: #4caf50;">✅ ' . esc_html__( 'Active', 'mhm-rentiva' ) . '</span></p>';

				// Show My Account link
				$account_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();

				echo '<p><a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $customer_user_id ) ) . '" class="button button-secondary" style="width: 100%; margin-bottom: 8px;">';
				echo '👤 ' . esc_html__( 'View User Profile', 'mhm-rentiva' ) . '</a></p>';

				echo '<p><a href="' . esc_url( $account_url ) . '" class="button button-primary" style="width: 100%;" target="_blank">';
				echo '📊 ' . esc_html__( 'Customer Account Page', 'mhm-rentiva' ) . '</a></p>';

				echo '<hr style="margin: 15px 0;">';

				echo '<p style="font-size: 12px; color: #666;">';
				echo '💡 ' . esc_html__( 'Customer can login with their email and password to view this booking.', 'mhm-rentiva' );
				echo '</p>';
			} else {
				echo '<p style="color: #f44336;">⚠️ ' . esc_html__( 'User account not found', 'mhm-rentiva' ) . '</p>';
			}
		} else {
			// No user account
			echo '<p style="color: #ff9800;">⚠️ ' . esc_html__( 'No customer account linked', 'mhm-rentiva' ) . '</p>';

			if ( $customer_email ) {
				echo '<p><strong>' . esc_html__( 'Email:', 'mhm-rentiva' ) . '</strong><br>';
				echo esc_html( $customer_email ) . '</p>';

				// Manual account creation button
				echo '<p><button type="button" class="button button-primary" style="width: 100%;" onclick="mhmCreateCustomerAccount(' . (int) $booking_id . ', \'' . esc_js( $customer_email ) . '\', \'' . esc_js( $customer_name ) . '\')">';
				echo '➕ ' . esc_html__( 'Create Customer Account', 'mhm-rentiva' ) . '</button></p>';

				echo '<script>
                function mhmCreateCustomerAccount(bookingId, email, name) {
                    if (!confirm("' . esc_js( __( 'Create a WordPress account for this customer?', 'mhm-rentiva' ) ) . '")) {
                        return;
                    }
                    
                    jQuery.post(ajaxurl, {
                        action: "mhm_create_customer_account_manual",
                        booking_id: bookingId,
                        email: email,
                        name: name,
                        nonce: "' . esc_attr( wp_create_nonce( 'mhm_create_customer_account' ) ) . '"
                    }, function(response) {
                        if (response.success) {
                            alert("' . esc_js( __( 'Account created successfully!', 'mhm-rentiva' ) ) . '");
                            location.reload();
                        } else {
                            alert(response.data.message || "' . esc_js( __( 'Error creating account', 'mhm-rentiva' ) ) . '");
                        }
                    });
                }
                </script>';
			}
		}

		echo '</div>';
	}
}
