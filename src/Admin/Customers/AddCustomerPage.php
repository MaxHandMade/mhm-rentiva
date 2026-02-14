<?php

/**
 * Add Customer Page Class.
 *
 * @package MHMRentiva\Admin\Customers
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Licensing\Restrictions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the display and processing of adding a new customer.
 */
final class AddCustomerPage {


	/**
	 * Register actions and hooks.
	 */
	public static function register(): void {
		// Hooks for AJAX operations.
		add_action( 'wp_ajax_mhm_rentiva_add_customer', array( self::class, 'ajax_add_customer' ) );
	}

	/**
	 * Render the add customer page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Customer limit check for Lite version.
		if ( ! Mode::isPro() ) {
			$current = Restrictions::customerCount();
			$max     = Mode::maxCustomers();

			if ( $current >= $max ) {
				echo '<div class="wrap mhm-rentiva-wrap">';
				echo '<h1>' . esc_html__( 'Add New Customer', 'mhm-rentiva' ) . '</h1>';
				echo '<p>' . wp_kses_post(
					sprintf(
						/* translators: %d: maximum number of customers. */
						__( 'You can add up to %d customers in Lite version. Enter your license key to upgrade to Pro.', 'mhm-rentiva' ),
						(int) $max
					)
				) . '</p>';
				echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=mhm-rentiva-license' ) ) . '" class="button button-primary">' . esc_html__( 'Upgrade to Pro', 'mhm-rentiva' ) . '</a></p>';
				echo '</div>';
				return;
			}
		}

		// Form processing.
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhm_rentiva_add_customer_nonce'] ?? '' ) ), 'mhm_rentiva_add_customer' ) ) {
			$customer_name    = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
			$customer_email   = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
			$customer_phone   = sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) );
			$customer_address = sanitize_textarea_field( wp_unslash( $_POST['customer_address'] ?? '' ) );

			if ( empty( $customer_name ) || empty( $customer_email ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Customer name and email fields are required.', 'mhm-rentiva' ) . '</p></div>';
			} else {
				// Generate username from customer name
				$base_username = trim( strtolower( $customer_name ) );
				$base_username = sanitize_user( $base_username, true );

				// If username is empty or invalid, use email prefix as fallback
				if ( empty( $base_username ) || ! validate_username( $base_username ) ) {
					$email_parts   = explode( '@', $customer_email );
					$base_username = sanitize_user( $email_parts[0], true );
				}

				// Ensure username is unique
				$username = $base_username;
				$counter  = 1;
				while ( username_exists( $username ) ) {
					$username = $base_username . $counter;
					++$counter;
				}

				// Save customer information (as WordPress user)
				$user_id = wp_create_user( $username, wp_generate_password(), $customer_email );

				if ( ! is_wp_error( $user_id ) ) {
					// Determine safe default role
					$default_role = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_customer_default_role', 'customer' );
					if ( ! get_role( $default_role ) ) {
						$default_role = 'customer';
					}

					// Update user information
					wp_update_user(
						array(
							'ID'           => $user_id,
							'display_name' => $customer_name,
							'first_name'   => $customer_name,
							'role'         => $default_role,
						)
					);

					// Ensure role is set even if wp_update_user ignores role
					$wp_user_obj = new \WP_User( $user_id );
					if ( ! in_array( $default_role, (array) $wp_user_obj->roles, true ) ) {
						$wp_user_obj->set_role( $default_role );
					}

					// Add meta information
					update_user_meta( $user_id, 'mhm_rentiva_phone', $customer_phone );
					update_user_meta( $user_id, 'mhm_rentiva_address', $customer_address );

					// Clear cache
					\MHMRentiva\Admin\Customers\CustomersOptimizer::clear_cache();

					echo '<div class="notice notice-success mhm-auto-hide-notice"><p>' . esc_html__( 'Customer added successfully!', 'mhm-rentiva' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Error occurred while adding customer: ', 'mhm-rentiva' ) . esc_html( $user_id->get_error_message() ) . '</p></div>';
				}
			}
		}

		echo '<div class="wrap mhm-rentiva-wrap">';
		echo '<h1>' . esc_html__( 'Add New Customer', 'mhm-rentiva' ) . '</h1>';

		echo '<form method="post" action="">';
		wp_nonce_field( 'mhm_rentiva_add_customer', 'mhm_rentiva_add_customer_nonce' );

		echo '<table class="form-table">';
		echo '<tbody>';

		$posted_customer_name    = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$posted_customer_email   = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$posted_customer_phone   = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';
		$posted_customer_address = isset( $_POST['customer_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['customer_address'] ) ) : '';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_name">' . esc_html__( 'Customer Name', 'mhm-rentiva' ) . ' <span class="description">(required)</span></label></th>';
		echo '<td><input name="customer_name" type="text" id="customer_name" value="' . esc_attr( $posted_customer_name ) . '" class="regular-text" required /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_email">' . esc_html__( 'Email', 'mhm-rentiva' ) . ' <span class="description">(required)</span></label></th>';
		echo '<td><input name="customer_email" type="email" id="customer_email" value="' . esc_attr( $posted_customer_email ) . '" class="regular-text" required /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_phone">' . esc_html__( 'Phone', 'mhm-rentiva' ) . '</label></th>';
		echo '<td><input name="customer_phone" type="tel" id="customer_phone" value="' . esc_attr( $posted_customer_phone ) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_address">' . esc_html__( 'Address', 'mhm-rentiva' ) . '</label></th>';
		echo '<td><textarea name="customer_address" id="customer_address" rows="3" cols="50" class="large-text">' . esc_textarea( $posted_customer_address ) . '</textarea></td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

		echo '<p class="submit">';
		echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr__( 'Add Customer', 'mhm-rentiva' ) . '">';
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=mhm-rentiva-customers' ) ) . '" class="button">' . esc_html__( 'Cancel', 'mhm-rentiva' ) . '</a>';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Add customer via AJAX
	 *
	 * @return void
	 */
	public static function ajax_add_customer(): void {
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_rentiva_add_customer' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'mhm-rentiva' ) );
		}

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		// Lite sürümde müşteri limiti kontrolü
		if ( ! Mode::isPro() ) {
			$current = Restrictions::customerCount();
			$max     = Mode::maxCustomers();

			if ( $current >= $max ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %d: maximum number of customers. */
							__( 'You can add up to %d customers in Lite version. Enter your license key to upgrade to Pro.', 'mhm-rentiva' ),
							(int) $max
						),
					)
				);
			}
		}

		$customer_name    = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
		$customer_email   = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
		$customer_phone   = sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) );
		$customer_address = sanitize_textarea_field( wp_unslash( $_POST['customer_address'] ?? '' ) );

		if ( empty( $customer_name ) || empty( $customer_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Customer name and email fields are required.', 'mhm-rentiva' ) ) );
			return;
		}

		// Generate username from customer name
		$base_username = trim( strtolower( $customer_name ) );
		$base_username = sanitize_user( $base_username, true );

		// If username is empty or invalid, use email prefix as fallback
		if ( empty( $base_username ) || ! validate_username( $base_username ) ) {
			$email_parts   = explode( '@', $customer_email );
			$base_username = sanitize_user( $email_parts[0], true );
		}

		// Ensure username is unique
		$username = $base_username;
		$counter  = 1;
		while ( username_exists( $username ) ) {
			$username = $base_username . $counter;
			++$counter;
		}

		// Save customer information (as WordPress user)
		$user_id = wp_create_user( $username, wp_generate_password(), $customer_email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error occurred while adding customer: ', 'mhm-rentiva' ) . esc_html( $user_id->get_error_message() ) ) );
			return;
		}

		// Determine safe default role
		$default_role = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_customer_default_role', 'customer' );
		if ( ! get_role( $default_role ) ) {
			$default_role = 'customer';
		}

		// Update user information
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $customer_name,
				'first_name'   => $customer_name,
				'role'         => $default_role,
			)
		);

		// Ensure role is set even if wp_update_user ignores role
		$wp_user_obj = new \WP_User( $user_id );
		if ( ! in_array( $default_role, (array) $wp_user_obj->roles, true ) ) {
			$wp_user_obj->set_role( $default_role );
		}

		// Add meta information
		update_user_meta( $user_id, 'mhm_rentiva_phone', $customer_phone );
		update_user_meta( $user_id, 'mhm_rentiva_address', $customer_address );

		// Clear cache
		\MHMRentiva\Admin\Customers\CustomersOptimizer::clear_cache();

		wp_send_json_success( array( 'message' => __( 'Customer added successfully.', 'mhm-rentiva' ) ) );
	}
}
