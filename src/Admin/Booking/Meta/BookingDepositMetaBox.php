<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BookingDepositMetaBox extends AbstractMetaBox {


	protected static function get_post_type(): string {
		return 'vehicle_booking';
	}

	protected static function get_meta_box_id(): string {
		return 'mhm_rentiva_booking_deposit';
	}

	protected static function get_title(): string {
		return __( 'Deposit Management', 'mhm-rentiva' );
	}

	protected static function get_fields(): array {
		return array(
			'mhm_rentiva_deposit_management' => array(
				'title'    => __( 'Deposit Management', 'mhm-rentiva' ),
				'context'  => 'normal',
				'priority' => 'high',
				'template' => 'render_deposit_management',
			),
		);
	}

	/**
	 * Register meta box hooks.
	 */
	public static function register(): void {
		// Show meta box only for existing bookings
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_boxes' ) );

		// Load required assets
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );

		// AJAX handlers
		add_action( 'wp_ajax_mhm_rentiva_process_deposit_payment', array( self::class, 'ajax_process_deposit_payment' ) );
		add_action( 'wp_ajax_mhm_rentiva_process_full_payment', array( self::class, 'ajax_process_full_payment' ) );
		add_action( 'wp_ajax_mhm_rentiva_process_refund', array( self::class, 'ajax_process_refund' ) );
	}

	/**
	 * Add meta box – only on existing bookings.
	 */
	public static function add_meta_boxes(): void {
		global $post, $pagenow;

		// Only display on existing bookings (not while creating a new booking)
		if ( ! $post || ! $post->ID || $pagenow === 'post-new.php' ) {
			return;
		}

		add_meta_box(
			self::get_meta_box_id(),
			self::get_title(),
			array( self::class, 'render_deposit_management' ),
			self::get_post_type(),
			'normal',
			'high'
		);
	}

	public static function enqueue_scripts( string $hook ): void {
		global $post_type;

		// Load assets only on booking edit screen
		if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $post_type === 'vehicle_booking' ) {
			// Enqueue CSS
			wp_enqueue_style(
				'mhm-deposit-management',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/deposit-management.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			wp_enqueue_script(
				'mhm-deposit-management',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/deposit-management.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			// Localization
			wp_localize_script(
				'mhm-deposit-management',
				'mhmDepositManagement',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'mhm_deposit_management_action' ),
					'strings' => array(
						'confirmRefund' => __( 'Do you confirm this action?', 'mhm-rentiva' ),
						'processing'    => __( 'Processing...', 'mhm-rentiva' ),
						'success'       => __( 'Operation successful!', 'mhm-rentiva' ),
						'error'         => __( 'An error occurred!', 'mhm-rentiva' ),
					),
				)
			);
		}
	}

	public static function render_deposit_management( \WP_Post $post ): void {
		// Nonce field
		wp_nonce_field( 'mhm_rentiva_deposit_management_action', 'mhm_rentiva_deposit_management_nonce' );

		// Fetch deposit details
		$payment_type          = get_post_meta( $post->ID, '_mhm_payment_type', true );
		$payment_method        = get_post_meta( $post->ID, '_mhm_payment_method', true );
		$deposit_amount        = floatval( get_post_meta( $post->ID, '_mhm_deposit_amount', true ) );
		$total_amount          = floatval( get_post_meta( $post->ID, '_mhm_total_price', true ) );
		$remaining_amount      = floatval( get_post_meta( $post->ID, '_mhm_remaining_amount', true ) );
		$deposit_type          = get_post_meta( $post->ID, '_mhm_deposit_type', true );
		$payment_display       = get_post_meta( $post->ID, '_mhm_payment_display', true );
		$cancellation_policy   = get_post_meta( $post->ID, '_mhm_cancellation_policy', true );
		$cancellation_deadline = get_post_meta( $post->ID, '_mhm_cancellation_deadline', true );
		$payment_deadline      = get_post_meta( $post->ID, '_mhm_payment_deadline', true );

		// Booking/payment status
		$booking_status = get_post_meta( $post->ID, '_mhm_status', true );
		$payment_status = get_post_meta( $post->ID, '_mhm_payment_status', true );

		echo '<div class="deposit-management-metabox">';

		// Check if the booking was created with the deposit system
		if ( ! $payment_type ) {
			echo '<div class="notice notice-info">';
			echo '<p>' . esc_html__( 'This booking was created with the old system. Deposit system information is not available.', 'mhm-rentiva' ) . '</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		// Deposit details grid
		echo '<div class="deposit-info-grid">';

		// Payment type
		echo '<div class="deposit-info-item">';
		echo '<div class="deposit-info-label">' . esc_html__( 'Payment Type', 'mhm-rentiva' ) . '</div>';
		echo '<div class="deposit-info-value">';
		if ( $payment_type === 'deposit' ) {
			echo '<span class="payment-type-badge deposit">' . esc_html__( 'Deposit', 'mhm-rentiva' ) . '</span>';
		} else {
			echo '<span class="payment-type-badge full">' . esc_html__( 'Full Payment', 'mhm-rentiva' ) . '</span>';
		}
		echo '</div>';
		echo '</div>';

		// Payment method
		echo '<div class="deposit-info-item">';
		echo '<div class="deposit-info-label">' . esc_html__( 'Payment Method', 'mhm-rentiva' ) . '</div>';
		echo '<div class="deposit-info-value">';
		if ( $payment_method === 'online' ) {
			echo '<span class="payment-method-badge online">' . esc_html__( 'Online', 'mhm-rentiva' ) . '</span>';
		} else {
			echo '<span class="payment-method-badge offline">' . esc_html__( 'Offline', 'mhm-rentiva' ) . '</span>';
		}
		echo '</div>';
		echo '</div>';

		// Total amount
		echo '<div class="deposit-info-item">';
		echo '<div class="deposit-info-label">' . esc_html__( 'Total Amount', 'mhm-rentiva' ) . '</div>';
		echo '<div class="deposit-info-value" data-field="total-amount">' . esc_html( self::format_price( $total_amount ) ) . '</div>';
		echo '</div>';

		// Deposit amount
		if ( $payment_type === 'deposit' ) {
			echo '<div class="deposit-info-item">';
			echo '<div class="deposit-info-label">' . esc_html__( 'Deposit Amount', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-info-value" data-field="deposit-amount">' . esc_html( self::format_price( $deposit_amount ) ) . '</div>';
			echo '</div>';

			// Remaining amount
			echo '<div class="deposit-info-item">';
			echo '<div class="deposit-info-label">' . esc_html__( 'Remaining Amount', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-info-value" data-field="remaining-amount">' . esc_html( self::format_price( $remaining_amount ) ) . '</div>';
			echo '</div>';
		}

		// Rental days
		$rental_days = (int) get_post_meta( $post->ID, '_mhm_rental_days', true );
		if ( $rental_days > 0 ) {
			echo '<div class="deposit-info-item">';
			echo '<div class="deposit-info-label">' . esc_html__( 'Rental Days', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-info-value" data-field="rental-days" data-suffix="' . esc_attr__( 'days', 'mhm-rentiva' ) . '">' . esc_html( (string) $rental_days ) . ' ' . esc_html__( 'days', 'mhm-rentiva' ) . '</div>';
			echo '</div>';
		}

		// Deposit type
		if ( $deposit_type ) {
			echo '<div class="deposit-info-item">';
			echo '<div class="deposit-info-label">' . esc_html__( 'Deposit Type', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-info-value">' . esc_html( self::get_deposit_type_label( $deposit_type ) ) . '</div>';
			echo '</div>';
		}

		echo '</div>';

		// Payment status
		echo '<div class="payment-status-section">';
		echo '<h4>' . esc_html__( 'Payment Status', 'mhm-rentiva' ) . '</h4>';
		echo '<div class="payment-status-indicator ' . esc_attr( $payment_status ?: 'unpaid' ) . '">';
		echo esc_html( self::get_payment_status_label( $payment_status ?: 'unpaid' ) );
		echo '</div>';
		echo '</div>';

		// Cancellation policy
		if ( $cancellation_policy && $cancellation_deadline ) {
			echo '<div class="cancellation-policy-section">';
			echo '<h4>' . esc_html__( 'Cancellation Policy', 'mhm-rentiva' ) . '</h4>';
			echo '<p><strong>' . esc_html__( 'Cancellation Deadline:', 'mhm-rentiva' ) . '</strong> ' . esc_html( gmdate( 'd.m.Y H:i', strtotime( $cancellation_deadline ) ) ) . '</p>';

			$now      = time();
			$deadline = strtotime( $cancellation_deadline );
			if ( $now < $deadline ) {
				echo '<p class="cancellation-available">' . esc_html__( 'This booking can be cancelled.', 'mhm-rentiva' ) . '</p>';
			} else {
				echo '<p class="cancellation-expired">' . esc_html__( 'Cancellation period expired.', 'mhm-rentiva' ) . '</p>';
			}
			echo '</div>';
		}

		// Payment deadline
		if ( $payment_deadline ) { // ⭐ Show deadline for all payment methods (WooCommerce)
			echo '<div class="payment-deadline-section">';
			echo '<h4>' . esc_html__( 'Payment Deadline', 'mhm-rentiva' ) . '</h4>';
			echo '<p><strong>' . esc_html__( 'Deadline:', 'mhm-rentiva' ) . '</strong> ' . esc_html( gmdate( 'd.m.Y H:i', strtotime( $payment_deadline ) ) ) . '</p>';

			$now      = time();
			$deadline = strtotime( $payment_deadline );
			if ( $now > $deadline && $payment_status !== 'paid' ) {
				echo '<p class="payment-expired">' . esc_html__( 'Payment period expired. Booking can be cancelled.', 'mhm-rentiva' ) . '</p>';
			}
			echo '</div>';
		}

		// Deposit timeline
		self::render_deposit_timeline( $post->ID );

		// Deposit actions
		self::render_deposit_actions( $post->ID, $payment_type, $remaining_amount, $payment_status, $booking_status );

		echo '</div>';
	}

	private static function render_deposit_timeline( int $post_id ): void {
		echo '<div class="deposit-timeline-section">';
		echo '<h4>' . esc_html__( 'Deposit History', 'mhm-rentiva' ) . '</h4>';
		echo '<div class="deposit-timeline">';

		// Booking created
		$post = get_post( $post_id );
		if ( $post ) {
			echo '<div class="deposit-timeline-item completed">';
			echo '<div class="deposit-timeline-content">';
			echo '<div class="deposit-timeline-title">' . esc_html__( 'Booking Created', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-timeline-description">' . esc_html__( 'Booking successfully created and deposit information saved.', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-timeline-date">' . esc_html( gmdate( 'd.m.Y H:i', strtotime( $post->post_date ) ) ) . '</div>';
			echo '</div>';
			echo '</div>';
		}

		// Payment state
		$payment_status = get_post_meta( $post_id, '_mhm_payment_status', true );
		if ( $payment_status === 'paid' ) {
			echo '<div class="deposit-timeline-item completed">';
			echo '<div class="deposit-timeline-content">';
			echo '<div class="deposit-timeline-title">' . esc_html__( 'Payment Received', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-timeline-description">' . esc_html__( 'Deposit payment successfully received.', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-timeline-date">' . esc_html( gmdate( 'd.m.Y H:i' ) ) . '</div>';
			echo '</div>';
			echo '</div>';
		} else {
			echo '<div class="deposit-timeline-item pending">';
			echo '<div class="deposit-timeline-content">';
			echo '<div class="deposit-timeline-title">' . esc_html__( 'Payment Pending', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-timeline-description">' . esc_html__( 'Deposit payment not yet received.', 'mhm-rentiva' ) . '</div>';
			echo '</div>';
			echo '</div>';
		}

		// Remaining balance payment
		$remaining_amount = floatval( get_post_meta( $post_id, '_mhm_remaining_amount', true ) );
		if ( $remaining_amount > 0 ) {
			echo '<div class="deposit-timeline-item pending">';
			echo '<div class="deposit-timeline-content">';
			echo '<div class="deposit-timeline-title">' . esc_html__( 'Remaining Amount Payment', 'mhm-rentiva' ) . '</div>';
			/* translators: %s: formatted price */
			echo '<div class="deposit-timeline-description">' . esc_html( sprintf( __( 'Remaining amount: %s', 'mhm-rentiva' ), self::format_price( $remaining_amount ) ) ) . '</div>';
			echo '</div>';
			echo '</div>';
		} else {
			echo '<div class="deposit-timeline-item completed">';
			echo '<div class="deposit-timeline-content">';
			echo '<div class="deposit-timeline-title">' . esc_html__( 'All Payments Completed', 'mhm-rentiva' ) . '</div>';
			echo '<div class="deposit-timeline-description">' . esc_html__( 'Deposit and remaining amount payments completed.', 'mhm-rentiva' ) . '</div>';
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
		echo '</div>';
	}

	private static function render_deposit_actions( int $post_id, string $payment_type, float $remaining_amount, string $payment_status, string $booking_status ): void {
		echo '<div class="deposit-actions-section">';
		echo '<div class="deposit-actions">';

		// Remaining balance button
		if ( $payment_type === 'deposit' && $remaining_amount > 0 && $payment_status === 'paid' ) {
			echo '<button type="button" class="deposit-action-btn primary" id="process-remaining-payment" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
			echo '<span class="dashicons dashicons-money-alt"></span>';
			echo esc_html__( 'Process Remaining Amount', 'mhm-rentiva' );
			echo '</button>';
		}

		// Cancel button
		if ( in_array( $booking_status, array( 'pending', 'confirmed' ), true ) ) {
			echo '<button type="button" class="deposit-action-btn warning" id="cancel-booking" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
			echo '<span class="dashicons dashicons-no"></span>';
			echo esc_html__( 'Cancel Booking', 'mhm-rentiva' );
			echo '</button>';
		}

		// Refund button
		if ( $payment_status === 'paid' && in_array( $booking_status, array( 'cancelled' ), true ) ) {
			echo '<button type="button" class="deposit-action-btn danger" id="process-refund" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
			echo '<span class="dashicons dashicons-undo"></span>';
			echo esc_html__( 'Process Refund', 'mhm-rentiva' );
			echo '</button>';
		}

		echo '</div>';
		echo '</div>';
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		// Nonce check
		if (
			! isset( $_POST['mhm_rentiva_deposit_management_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhm_rentiva_deposit_management_nonce'] ) ), 'mhm_rentiva_deposit_management_action' )
		) {
			return;
		}

		// Capability check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Autosave / revision guard
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Deposit actions are handled over AJAX; only meta can be stored here if needed.
	}

	private static function format_price( float $price ): string {
		$currency = Settings::get( 'currency', 'USD' );
		$position = Settings::get( 'currency_position', 'right_space' );
		$amount   = number_format_i18n( $price, 2 );
		$symbol   = $currency;

		switch ( $position ) {
			case 'left':
				return $symbol . $amount;
			case 'right':
				return $amount . $symbol;
			case 'left_space':
				return $symbol . ' ' . $amount;
			case 'right_space':
			default:
				return $amount . ' ' . $symbol;
		}
	}

	private static function get_payment_status_label( string $status ): string {
		$labels = array(
			'unpaid'               => __( 'Unpaid', 'mhm-rentiva' ),
			'paid'                 => __( 'Paid', 'mhm-rentiva' ),
			'refunded'             => __( 'Refunded', 'mhm-rentiva' ),
			'failed'               => __( 'Failed', 'mhm-rentiva' ),
			'pending_verification' => __( 'Pending Verification', 'mhm-rentiva' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Get translated deposit type label
	 */
	private static function get_deposit_type_label( string $deposit_type ): string {
		$labels = array(
			'full_payment' => __( 'Full Payment', 'mhm-rentiva' ),
			'percentage'   => __( 'Percentage', 'mhm-rentiva' ),
			'fixed'        => __( 'Fixed Amount', 'mhm-rentiva' ),
			'both'         => __( 'Both', 'mhm-rentiva' ),
			'none'         => __( 'None', 'mhm-rentiva' ),
		);

		return $labels[ $deposit_type ] ?? ucfirst( str_replace( '_', ' ', $deposit_type ) );
	}
}
