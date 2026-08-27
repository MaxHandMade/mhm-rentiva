<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Payment\Core\MoneyAuthorization;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BookingDepositMetaBox extends AbstractMetaBox {


	protected static function get_post_type(): string {
		return 'mhmrentiva_booking';
	}

	protected static function get_meta_box_id(): string {
		return 'mhmrentiva_booking_deposit';
	}

	protected static function get_title(): string {
		return __( 'Deposit Management', 'mhm-rentiva' );
	}

	protected static function get_fields(): array {
		return array(
			'mhmrentiva_deposit_management' => array(
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
		if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $post_type === 'mhmrentiva_booking' ) {
			// Enqueue CSS
			wp_enqueue_style(
				'mhm-rentiva-deposit-management',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/deposit-management.css',
				array( 'mhm-rentiva-css-variables' ),
				MHMRENTIVA_VERSION
			);

			wp_enqueue_script(
				'mhm-rentiva-deposit-management',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/deposit-management.js',
				array( 'jquery' ),
				MHMRENTIVA_VERSION,
				true
			);

			// Localization
			wp_localize_script(
				'mhm-rentiva-deposit-management',
				'mhmDepositManagement',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'mhmrentiva_deposit_management_action' ),
					'strings' => array(
						'confirmRefund'                => __( 'Do you confirm this action?', 'mhm-rentiva' ),
						'confirmRemainingPayment'      => __( 'Confirm receipt of remaining payment?', 'mhm-rentiva' ),
						'confirmApprovePayment'        => __( 'Confirm receipt of deposit payment?', 'mhm-rentiva' ),
						'confirmCancelBooking'         => __( 'Are you sure you want to cancel this booking?', 'mhm-rentiva' ),
						'confirmCloseManualRefund'     => __( 'Confirm this refund was handed over and is complete?', 'mhm-rentiva' ),
						'confirmReviewCancelAndRefund' => __( 'Cancel this booking and start the refund?', 'mhm-rentiva' ),
						'confirmReviewDismiss'         => __( 'Confirm no refund is due for this booking?', 'mhm-rentiva' ),
						'dismiss'                      => __( 'Dismiss this notice', 'mhm-rentiva' ),
						'processing'                   => __( 'Processing...', 'mhm-rentiva' ),
						'success'                      => __( 'Operation successful!', 'mhm-rentiva' ),
						'error'                        => __( 'An error occurred!', 'mhm-rentiva' ),
						'copyLink'                     => __( 'Copy Link', 'mhm-rentiva' ),
						'linkCopied'                   => __( 'Link copied to clipboard!', 'mhm-rentiva' ),
					),
				)
			);
		}
	}

	/**
	 * Can a refund be started from THIS screen for this booking?
	 *
	 * One predicate, three callers: the button below, the link
	 * BookingRefundMetaBox points at it, and the rejection in
	 * DepositManagementAjax::process_refund(). Those three used to be three
	 * separate copies of the same question and they disagreed twice, both
	 * measured:
	 *
	 * - Refunds\Service writes payment_status = 'partially_refunded' whenever
	 *   a refund does not clear the whole balance (Service.php:295) while all
	 *   three copies demanded exactly 'paid'. A correct partial refund
	 *   therefore stranded the rest of the money permanently: no screen would
	 *   offer the second refund, and the refund box went on reporting the
	 *   balance as refundable while telling the operator to cancel a booking
	 *   that was already cancelled.
	 * - render_deposit_management() returns early -- before any button -- when
	 *   _mhmrentiva_payment_type is empty ("old system" notice). The refund
	 *   box's copy did not know that and linked to a button that was not on
	 *   the page.
	 *
	 * The conditions below are exactly, and only, the ones under which the
	 * button actually renders and the handler actually proceeds. The cheap
	 * meta reads come first so the PaymentState resolution -- which touches
	 * WooCommerce orders -- is skipped for the shapes that are already out.
	 */
	public static function can_refund_from_deposit_screen( int $booking_id ): bool {
		$payment_status = (string) get_post_meta( $booking_id, '_mhmrentiva_payment_status', true );

		if ( ! in_array( $payment_status, array( 'paid', 'partially_refunded' ), true ) ) {
			return false;
		}

		if ( Status::CANCELLED !== Status::get( $booking_id ) ) {
			return false;
		}

		if ( '' === (string) get_post_meta( $booking_id, '_mhmrentiva_payment_type', true ) ) {
			return false;
		}

		return PaymentState::forBooking( $booking_id )->refundable() > 0;
	}

	public static function render_deposit_management( \WP_Post $post ): void {
		// Nonce field
		wp_nonce_field( 'mhmrentiva_deposit_management_action', 'mhmrentiva_deposit_management_nonce' );

		// Fetch deposit details
		$payment_type          = get_post_meta( $post->ID, '_mhmrentiva_payment_type', true );
		$payment_method        = get_post_meta( $post->ID, '_mhmrentiva_payment_method', true );
		$deposit_amount        = floatval( get_post_meta( $post->ID, '_mhmrentiva_deposit_amount', true ) );
		$total_amount          = floatval( get_post_meta( $post->ID, '_mhmrentiva_total_price', true ) );
		$remaining_amount      = floatval( get_post_meta( $post->ID, '_mhmrentiva_remaining_amount', true ) );
		$deposit_type          = get_post_meta( $post->ID, '_mhmrentiva_deposit_type', true );
		$payment_display       = get_post_meta( $post->ID, '_mhmrentiva_payment_display', true );
		$cancellation_policy   = get_post_meta( $post->ID, '_mhmrentiva_cancellation_policy', true );
		$cancellation_deadline = get_post_meta( $post->ID, '_mhmrentiva_cancellation_deadline', true );
		$payment_deadline      = get_post_meta( $post->ID, '_mhmrentiva_payment_deadline', true );

		// Booking/payment status
		$booking_status = get_post_meta( $post->ID, '_mhmrentiva_status', true );
		$payment_status = get_post_meta( $post->ID, '_mhmrentiva_payment_status', true );

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
		$rental_days = (int) get_post_meta( $post->ID, '_mhmrentiva_rental_days', true );
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
			echo '<p><strong>' . esc_html__( 'Cancellation Deadline:', 'mhm-rentiva' ) . '</strong> ' . esc_html( get_date_from_gmt( $cancellation_deadline, 'd.m.Y H:i' ) ) . '</p>';

			$now      = time();
			$deadline = strtotime( $cancellation_deadline . ' UTC' );
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
		$payment_status = get_post_meta( $post_id, '_mhmrentiva_payment_status', true );
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
		$remaining_amount = floatval( get_post_meta( $post_id, '_mhmrentiva_remaining_amount', true ) );
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

		// EFT/Havale: Mark deposit payment as received
		if ( in_array( $payment_status, array( 'pending', 'unpaid', '' ), true ) &&
			in_array( $booking_status, array( 'pending', 'confirmed' ), true ) ) {
			echo '<button type="button" class="deposit-action-btn success" id="approve-payment" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
			echo '<span class="dashicons dashicons-yes-alt"></span>';
			echo esc_html__( 'Mark Payment Received', 'mhm-rentiva' );
			echo '</button>';
		}

		// Remaining balance button
		if ( $payment_type === 'deposit' && $remaining_amount > 0 && $payment_status === 'paid' ) {
			echo '<button type="button" class="deposit-action-btn primary" id="process-remaining-payment" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
			echo '<span class="dashicons dashicons-money-alt"></span>';
			echo esc_html__( 'Process Remaining Amount', 'mhm-rentiva' );
			echo '</button>';

			if ( DepositManagementAjax::can_send_remaining_payment_link( $post_id ) ) {
				echo '<button type="button" class="deposit-action-btn primary" id="send-remaining-payment-link" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
				echo '<span class="dashicons dashicons-email-alt"></span>';
				echo esc_html__( 'Send Payment Link', 'mhm-rentiva' );
				echo '</button>';
			} else {
				echo '<p class="description">' . esc_html__( 'The deposit was paid outside WooCommerce, so no payment link can be sent for the remaining balance; use "Process Remaining Amount" instead to record it as settled.', 'mhm-rentiva' ) . '</p>';
			}
		}

		// The cancel and refund buttons below pay money OUT and are gated on
		// the same actor question, asked once. The three buttons above them
		// (Mark Payment Received, Process Remaining Amount, Send Payment
		// Link) take money IN rather than paying it out and are deliberately
		// left ungated here -- this task's scope (Task 9, slice 5) is the two
		// buttons that offer an action MoneyAuthorization::mayMoveMoney()
		// would refuse.
		//
		// The actor question stays out of can_refund_from_deposit_screen() on
		// purpose: that method is a pure booking-state predicate with three
		// other callers (the refund box's link and the AJAX handler among
		// them), and folding an actor check into it would make it silently
		// actor-dependent for all of them. "One question, one home" applies
		// per question -- the state question's home is that method, the actor
		// question's home is MoneyAuthorization.
		$may_move_money = MoneyAuthorization::mayMoveMoney( $post_id, get_current_user_id(), 'admin_deposit' );

		// Cancel button
		if ( $may_move_money && in_array( $booking_status, array( 'pending', 'confirmed' ), true ) ) {
			echo '<button type="button" class="deposit-action-btn warning" id="cancel-booking" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
			echo '<span class="dashicons dashicons-no"></span>';
			echo esc_html__( 'Cancel Booking', 'mhm-rentiva' );
			echo '</button>';
		}

		// Refund button. The rule lives in can_refund_from_deposit_screen() so
		// this screen, the refund box's link and the AJAX handler cannot drift
		// apart again -- see that method for the two ways they already had.
		if ( $may_move_money && self::can_refund_from_deposit_screen( $post_id ) ) {
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
		$nonce = sanitize_text_field( wp_unslash( $_POST['mhmrentiva_deposit_management_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_deposit_management_action' ) ) {
			return;
		}

		// Capability check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// This override drops the parent's type check (AbstractMetaBox::save_meta tests
		// $post->post_type first), and register() here does not call parent::register(),
		// so nothing hooks it today -- it is unreachable. The check is here anyway: the
		// day someone wires it up, the M-1 defect would arrive with it, and an anchor
		// added after the wiring is an anchor added after the incident.
		if ( 'mhmrentiva_booking' !== $post->post_type ) {
			return;
		}

		// Autosave / revision guard
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Deposit actions are handled over AJAX; only meta can be stored here if needed.
	}

	private static function format_price( float $price ): string {
		// Canonical currency formatting (WC-aware symbol/position/separators).
		// Reading mhmrentiva_currency_position here pinned this to `right_space`
		// whenever that option was unset, which is its normal state.
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price( $price, 2 );
	}

	private static function get_payment_status_label( string $status ): string {
		$labels = array(
			'unpaid'               => __( 'Unpaid', 'mhm-rentiva' ),
			'pending'              => __( 'Pending', 'mhm-rentiva' ),
			'paid'                 => __( 'Paid', 'mhm-rentiva' ),
			'refunded'             => __( 'Refunded', 'mhm-rentiva' ),
			'failed'               => __( 'Failed', 'mhm-rentiva' ),
			'processing'           => __( 'Processing', 'mhm-rentiva' ),
			'partially_paid'       => __( 'Partially Paid', 'mhm-rentiva' ),
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
