<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GDPR Manager
 *
 * Handles GDPR compliance features for customer data
 *
 * @since 4.0.0
 */
final class GDPRManager {


	/**
	 * Initialize GDPR management
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'check_gdpr_compliance' ) );
		add_action( 'wp_ajax_mhm_rentiva_data_export', array( self::class, 'ajax_data_export' ) );
		add_action( 'wp_ajax_mhm_rentiva_data_deletion', array( self::class, 'ajax_data_deletion' ) );
		add_action( 'wp_ajax_mhm_rentiva_consent_withdrawal', array( self::class, 'ajax_consent_withdrawal' ) );
	}

	/**
	 * Check if GDPR compliance is enabled
	 */
	public static function is_gdpr_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_customer_gdpr_compliance', '1' ) === '1';
	}

	/**
	 * Check GDPR compliance requirements
	 */
	public static function check_gdpr_compliance(): void {
		if ( ! self::is_gdpr_enabled() ) {
			return;
		}

		// Add GDPR compliance hooks
		add_action( 'wp_footer', array( self::class, 'add_gdpr_notice' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_gdpr_scripts' ) );

		// Add data consent requirement check
		add_action( 'init', array( self::class, 'check_data_consent_requirement' ) );
	}

	/**
	 * Check if data consent is required
	 */
	public static function is_data_consent_required(): bool {
		return self::is_gdpr_enabled() && SettingsCore::get( 'mhm_rentiva_customer_data_consent', '0' ) === '1';
	}

	/**
	 * Check data consent requirement
	 */
	public static function check_data_consent_requirement(): void {
		if ( ! self::is_data_consent_required() ) {
			return;
		}

		// Add consent check to user registration
		add_action( 'user_register', array( self::class, 'handle_user_registration_consent' ) );

		// Add consent check to booking process
		add_action( 'mhm_rentiva_before_booking_creation', array( self::class, 'check_booking_consent' ) );
	}

	/**
	 * Handle user registration consent
	 */
	public static function handle_user_registration_consent( int $user_id ): void {
		if ( ! self::is_data_consent_required() ) {
			return;
		}

		// Check if consent was given during registration.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- user_register runs after core registration flow; nonce validation belongs to the registration form handler.
		if ( isset( $_POST['data_consent'] ) && $_POST['data_consent'] === '1' ) {
			update_user_meta( $user_id, 'mhm_data_consent_given', '1' );
			update_user_meta( $user_id, 'mhm_data_consent_date', current_time( 'mysql' ) );
		} else {
			// If consent is required but not given, mark user as pending consent
			update_user_meta( $user_id, 'mhm_data_consent_given', '0' );
		}
	}

	/**
	 * Check booking consent
	 */
	public static function check_booking_consent(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! self::is_data_consent_required() ) {
			return;
		}

		$user_id       = get_current_user_id();
		$consent_given = get_user_meta( $user_id, 'mhm_data_consent_given', true );

		if ( $consent_given !== '1' ) {
			wp_die( esc_html__( 'You must provide consent for data processing before making a booking.', 'mhm-rentiva' ) );
		}
	}

	/**
	 * Add GDPR compliance notice
	 */
	public static function add_gdpr_notice(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id       = get_current_user_id();
		$consent_given = get_user_meta( $user_id, 'mhm_gdpr_consent_given', true );

		if ( $consent_given !== '1' ) {
			?>
			<div id="mhm-gdpr-notice" class="mhm-gdpr-notice" style="position: fixed; bottom: 20px; right: 20px; background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 9999; max-width: 400px;">
				<h4><?php esc_html_e( 'Privacy Notice', 'mhm-rentiva' ); ?></h4>
				<p><?php esc_html_e( 'We need your consent to process your personal data in accordance with GDPR regulations.', 'mhm-rentiva' ); ?></p>
				<div class="gdpr-actions">
					<button type="button" id="gdpr-accept" class="button button-primary"><?php esc_html_e( 'Accept', 'mhm-rentiva' ); ?></button>
					<button type="button" id="gdpr-decline" class="button"><?php esc_html_e( 'Decline', 'mhm-rentiva' ); ?></button>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue GDPR scripts
	 */
	public static function enqueue_gdpr_scripts(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script( 'mhm-gdpr', plugin_dir_url( __FILE__ ) . '../assets/js/gdpr.js', array( 'jquery' ), '1.0.0', true );
		wp_localize_script(
			'mhm-gdpr',
			'mhm_gdpr',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mhm_gdpr_nonce' ),
			)
		);
	}

	/**
	 * Export user data
	 */
	public static function export_user_data( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$data = array(
			'personal_info'             => array(
				'user_id'      => $user->ID,
				'username'     => $user->user_login,
				'email'        => $user->user_email,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'display_name' => $user->display_name,
				'registered'   => $user->user_registered,
				'last_login'   => get_user_meta( $user_id, 'last_login', true ),
			),
			'meta_data'                 => get_user_meta( $user_id ),
			'bookings'                  => self::get_user_bookings( $user_id ),
			'favorites'                 => get_user_meta( $user_id, 'mhm_favorite_vehicles', true ),
			'communication_preferences' => array(
				'welcome_email'         => get_user_meta( $user_id, 'mhm_welcome_email', true ),
				'booking_notifications' => get_user_meta( $user_id, 'mhm_booking_notifications', true ),
				'marketing_emails'      => get_user_meta( $user_id, 'mhm_marketing_emails', true ),
			),
			'consent_records'           => array(
				'gdpr_consent'   => get_user_meta( $user_id, 'mhm_gdpr_consent_given', true ),
				'consent_date'   => get_user_meta( $user_id, 'mhm_gdpr_consent_date', true ),
				'terms_accepted' => get_user_meta( $user_id, 'terms_accepted', true ),
				'terms_date'     => get_user_meta( $user_id, 'terms_accepted_date', true ),
			),
		);

		return $data;
	}

	/**
	 * Get user bookings with optimized query
	 */
	private static function get_user_bookings( int $user_id ): array {
		global $wpdb;

		// Use direct query for better performance
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_modified
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'vehicle_booking'
            AND pm.meta_key = '_mhm_customer_user_id'
            AND pm.meta_value = %d
            ORDER BY p.post_date DESC
        ",
				$user_id
			)
		);

		$booking_data = array();
		foreach ( $bookings as $booking ) {
			$booking_data[] = array(
				'id'       => (int) $booking->ID,
				'title'    => $booking->post_title,
				'status'   => $booking->post_status,
				'created'  => $booking->post_date,
				'modified' => $booking->post_modified,
				'meta'     => get_post_meta( $booking->ID ),
			);
		}

		return $booking_data;
	}

	/**
	 * Anonymize user data
	 */
	public static function anonymize_user_data( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Generate anonymous username and email
		$anonymous_id    = 'anonymous_' . wp_generate_password( 12, false );
		$anonymous_email = $anonymous_id . '@anonymized.local';

		// Update user data
		wp_update_user(
			array(
				'ID'           => $user_id,
				'user_login'   => $anonymous_id,
				'user_email'   => $anonymous_email,
				'first_name'   => 'Anonymous',
				'last_name'    => 'User',
				'display_name' => 'Anonymous User',
			)
		);

		// Anonymize meta data
		$meta_keys_to_anonymize = array(
			'phone',
			'address',
			'city',
			'postal_code',
			'country',
		);

		foreach ( $meta_keys_to_anonymize as $meta_key ) {
			update_user_meta( $user_id, $meta_key, '[ANONYMIZED]' );
		}

		// Mark as anonymized
		update_user_meta( $user_id, 'mhm_data_anonymized', '1' );
		update_user_meta( $user_id, 'mhm_anonymization_date', current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Delete user data completely
	 */
	public static function delete_user_data( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Delete user bookings
		$bookings = get_posts(
			array(
				'post_type'      => 'vehicle_booking',
				'meta_key'       => '_mhm_customer_user_id',
				'meta_value'     => $user_id,
				'posts_per_page' => -1,
			)
		);

		foreach ( $bookings as $booking ) {
			wp_delete_post( $booking->ID, true );
		}

		// Delete user meta
		$meta_keys = $GLOBALS['wpdb']->get_col(
			$GLOBALS['wpdb']->prepare(
				"SELECT meta_key FROM {$GLOBALS['wpdb']->usermeta} WHERE user_id = %d",
				$user_id
			)
		);

		foreach ( $meta_keys as $meta_key ) {
			delete_user_meta( $user_id, $meta_key );
		}

		// Delete user account
		wp_delete_user( $user_id );

		return true;
	}

	/**
	 * AJAX handler for data export
	 */
	public static function ajax_data_export(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Not logged in.', 'mhm-rentiva' ) ) );
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_gdpr_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		$user_id = get_current_user_id();
		$data    = self::export_user_data( $user_id );

		// Return JSON data directly for JavaScript blob creation
		wp_send_json_success( json_encode( $data, JSON_PRETTY_PRINT ) );
	}

	/**
	 * AJAX handler for data deletion
	 */
	public static function ajax_data_deletion(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Not logged in.', 'mhm-rentiva' ) ) );
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_gdpr_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		$user_id = get_current_user_id();

		if ( self::delete_user_data( $user_id ) ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Data deletion completed.', 'mhm-rentiva' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete data.', 'mhm-rentiva' ) ) );
		}
	}

	/**
	 * AJAX handler for consent withdrawal
	 */
	public static function ajax_consent_withdrawal(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Not logged in.', 'mhm-rentiva' ) ) );
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_gdpr_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		$user_id = get_current_user_id();

		// Withdraw consent
		delete_user_meta( $user_id, 'mhm_gdpr_consent_given' );
		update_user_meta( $user_id, 'mhm_gdpr_consent_withdrawn', '1' );
		update_user_meta( $user_id, 'mhm_gdpr_consent_withdrawal_date', current_time( 'mysql' ) );

		wp_send_json_success( array( 'message' => esc_html__( 'Consent withdrawn successfully.', 'mhm-rentiva' ) ) );
	}

	/**
	 * Check if user has given consent
	 */
	public static function has_user_consent( int $user_id ): bool {
		return get_user_meta( $user_id, 'mhm_gdpr_consent_given', true ) === '1';
	}

	/**
	 * Record user consent
	 */
	public static function record_user_consent( int $user_id ): void {
		update_user_meta( $user_id, 'mhm_gdpr_consent_given', '1' );
		update_user_meta( $user_id, 'mhm_gdpr_consent_date', current_time( 'mysql' ) );
		delete_user_meta( $user_id, 'mhm_gdpr_consent_withdrawn' );
	}
}
