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
		return __( 'Payment Management', 'mhm-rentiva' );
	}

	protected static function get_fields(): array {
		return array(
			'mhmrentiva_deposit_management' => array(
				'title'    => __( 'Payment Management', 'mhm-rentiva' ),
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

		// No save_post hook, on purpose: the box has no form fields (every
		// action is an AJAX button carrying mhmDepositManagement.nonce), and
		// parent::register() would wire AbstractMetaBox::save_meta() to box
		// definitions from get_fields() that are not fields.

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
		if ( ! self::uses_deposit_system( $post->ID ) ) {
			echo '<div class="notice notice-info">';
			echo '<p>' . esc_html__( 'This booking was created with the old system. Deposit system information is not available.', 'mhm-rentiva' ) . '</p>';
			echo '</div>';
			// The history is not a payment section: it stays on an old booking too.
			BookingMeta::render_history_section( $post );
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
			echo '<div class="deposit-info-value">' . esc_html( self::deposit_type_text( $post->ID ) ) . '</div>';
			echo '</div>';
		}

		echo '</div>';

		// Payment status
		echo '<div class="payment-status-section">';
		echo '<h4>' . esc_html__( 'Payment Status', 'mhm-rentiva' ) . '</h4>';
		// Shown from the derived payment view (PaymentView); the raw status
		// above still drives the actions below.
		$payment_view = \MHMRentiva\Admin\Booking\Core\PaymentView::status( $post->ID );
		echo '<div class="payment-status-indicator ' . esc_attr( $payment_view ) . '">';
		echo esc_html( \MHMRentiva\Admin\Booking\Core\PaymentView::label( $payment_view ) );
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
			echo '<p><strong>' . esc_html__( 'Deadline:', 'mhm-rentiva' ) . '</strong> ' . esc_html( get_date_from_gmt( (string) $payment_deadline, 'd.m.Y H:i' ) ) . '</p>';

			$now      = time();
			$deadline = strtotime( $payment_deadline . ' UTC' );
			if ( $now > $deadline && $payment_status !== 'paid' ) {
				echo '<p class="payment-expired">' . esc_html__( 'Payment period expired. Booking can be cancelled.', 'mhm-rentiva' ) . '</p>';
			}
			echo '</div>';
		}

		// Deposit timeline
		self::render_deposit_timeline( $post->ID );

		// Booking history, under the payment history (canvas v135).
		BookingMeta::render_history_section( $post );

		// Deposit actions
		self::render_deposit_actions( $post->ID, $payment_type, $remaining_amount, $payment_status, $booking_status );

		echo '</div>';
	}

	private static function render_deposit_timeline( int $post_id ): void {
		echo '<div class="deposit-timeline-section">';
		echo '<h4>' . esc_html__( 'Payment History', 'mhm-rentiva' ) . '</h4>';
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

		// The two buttons that pay money OUT live in their own boxes (canvas
		// v135): cancel in Booking Status (render_cancel_button()), refund in the
		// Refund box (render_refund_button()). Both keep their ids and markup.

		echo '</div>';
		echo '</div>';
	}

	/**
	 * The cancel button, in the Booking Status box (canvas v135). The same rule
	 * that drew it here: the booking has a payment type (render_deposit_management()
	 * returns before any button otherwise), the actor may move money on it, and
	 * it is pending or confirmed. Markup and id unchanged: deposit-management.js
	 * binds #cancel-booking on the document and reads only data-booking-id.
	 */
	public static function render_cancel_button( int $post_id ): void {
		if ( ! self::uses_deposit_system( $post_id ) ) {
			return;
		}
		if ( ! MoneyAuthorization::mayMoveMoney( $post_id, get_current_user_id(), 'admin_deposit' ) ) {
			return;
		}
		if ( ! in_array( (string) get_post_meta( $post_id, '_mhmrentiva_status', true ), array( 'pending', 'confirmed' ), true ) ) {
			return;
		}

		echo '<button type="button" class="deposit-action-btn warning" id="cancel-booking" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
		echo '<span class="dashicons dashicons-no"></span>';
		echo esc_html__( 'Cancel Booking', 'mhm-rentiva' );
		echo '</button>';
		// What the button does, as CancellationHandler::cancel_booking() does it:
		// the cancellation e-mail goes out and the refund of what was paid starts.
		echo '<p class="description mhm-cancel-hint">' . esc_html__( 'Cancelling starts the refund of the amount paid and sends the customer a notification.', 'mhm-rentiva' ) . ' ' . esc_html__( 'Choosing Cancelled in the status list and pressing Update does the same.', 'mhm-rentiva' ) . '</p>';
	}

	/**
	 * The refund button, in the Refund box (canvas v135). Printed exactly when it
	 * was printed here before: the actor may move money on the booking
	 * (MoneyAuthorization, surface 'admin_deposit') and the booking state allows a
	 * refund (can_refund_from_deposit_screen(), which also requires a payment
	 * type -- the old early return of render_deposit_management()). Markup and id
	 * unchanged: deposit-management.js binds #process-refund on the document and
	 * reads only data-booking-id.
	 *
	 * The actor question stays out of can_refund_from_deposit_screen() on
	 * purpose: that predicate is pure booking state with other callers (the AJAX
	 * handler among them); folding the actor in would make it silently
	 * actor-dependent for all of them.
	 */
	public static function render_refund_button( int $post_id ): void {
		if ( ! self::refund_button_shows( $post_id ) ) {
			return;
		}

		echo '<button type="button" class="deposit-action-btn danger" id="process-refund" data-booking-id="' . esc_attr( (string) $post_id ) . '">';
		echo '<span class="dashicons dashicons-undo"></span>';
		echo esc_html__( 'Process Refund', 'mhm-rentiva' );
		echo '</button>';
	}

	/**
	 * Whether render_refund_button() prints the button for the current actor --
	 * so a box can word its text around it without buffering the markup.
	 */
	public static function refund_button_shows( int $post_id ): bool {
		return self::uses_deposit_system( $post_id )
			&& MoneyAuthorization::mayMoveMoney( $post_id, get_current_user_id(), 'admin_deposit' )
			&& self::can_refund_from_deposit_screen( $post_id );
	}

	/**
	 * Whether the booking was created with the deposit system -- the one rule
	 * the Payment Management box, the cancel button and the refund button share.
	 *
	 * PHP truthiness, exactly as the box's early return always read it: an empty
	 * value AND '0' mean "old system". Before PR 3b-1 the two buttons lived after
	 * that return; moved out, they must keep asking the same question (Codex
	 * audit 3b-1: a '0' payment type grew both buttons).
	 */
	public static function uses_deposit_system( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, '_mhmrentiva_payment_type', true );
	}

	private static function format_price( float $price ): string {
		// Canonical currency formatting (WC-aware symbol/position/separators).
		// Reading mhmrentiva_currency_position here pinned this to `right_space`
		// whenever that option was unset, which is its normal state.
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price( $price, 2 );
	}

	/**
	 * Get translated deposit type label
	 */
	/**
	 * The deposit type as the Payment Management box shows it. A percentage
	 * deposit names its rate ("Percentage · 10%"); only the type is stored, so
	 * the rate is derived from the deposit and the total.
	 */
	public static function deposit_type_text( int $post_id ): string {
		$type  = (string) get_post_meta( $post_id, '_mhmrentiva_deposit_type', true );
		$label = self::get_deposit_type_label( $type );
		if ( 'percentage' !== $type ) {
			return $label;
		}
		$deposit = (float) get_post_meta( $post_id, '_mhmrentiva_deposit_amount', true );
		$total   = (float) get_post_meta( $post_id, '_mhmrentiva_total_price', true );
		if ( $total <= 0.0 || $deposit <= 0.0 ) {
			return $label;
		}
		$rate = round( $deposit / $total * 100, 1 );

		return sprintf(
			/* translators: 1: deposit type label ("Percentage"), 2: the deposit rate, e.g. 10 or 12.5 -- place the percent sign as your language does. */
			_x( '%1$s · %2$s%%', 'deposit type with its rate', 'mhm-rentiva' ),
			$label,
			number_format_i18n( $rate, floor( $rate ) === $rate ? 0 : 1 )
		);
	}

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
