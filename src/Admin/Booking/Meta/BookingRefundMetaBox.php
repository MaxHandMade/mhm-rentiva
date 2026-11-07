<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\MoneyAuthorization;
use MHMRentiva\Admin\Payment\Core\RefundStatus;

final class BookingRefundMetaBox {


	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
	}

	public static function add(): void {
		add_meta_box(
			'mhmrentiva_booking_refund',
			__( 'Refund', 'mhm-rentiva' ),
			array( self::class, 'render' ),
			'mhmrentiva_booking',
			'side',
			'high'
		);
	}

	public static function render( \WP_Post $post ): void {
		$bid = (int) $post->ID;

		// Before the early return, deliberately. The guard below zeroes
		// $remaining for every WooCommerce-order booking and for an offline
		// booking whose manual refund was already recorded -- which is exactly
		// the set that produces manual_pending, partial_failure and
		// needs_review. Rendered after it, this row would be invisible on
		// every booking that needs it.
		self::render_status_row( $bid );

		// Same reasoning, same placement, for the same reason
		// render_status_row() sits above the early return: a manual_pending
		// booking always hits $remaining = 0 below, either because it has a
		// WooCommerce order at all (the ternary's first branch zeroes
		// $remaining unconditionally, before refund status ever enters it),
		// or -- the pure-offline case -- because Refunds\Service already
		// recorded the manual amount into _mhmrentiva_refunded_amount.
		// That second write is gated on the refund's CHANNEL being offline
		// (Service.php:440), not on its MODE being manual (Service.php:436,
		// a different variable) -- a manual_pending booking backed by a real
		// WooCommerce order whose gateway simply cannot auto-refund never
		// takes that write at all, and does not need to: it is already
		// covered by the first branch. Either way, a control gated only on
		// status would never reach the page if it sat after the guard below.
		self::render_manual_close_control( $bid );

		// Same placement, same reason: needs_review is produced by
		// cancel_booking_with_orders() finding a paid WooCommerce order
		// (Task 4's K6 guard), which is exactly the shape that zeroes
		// $remaining below. A control gated only on status would never reach
		// the page if it sat after the early return.
		self::render_needs_review_controls( $bid );

		// The refund action itself (canvas v135: it lives in this box, not in
		// Payment Management). Same placement as the controls above and for a
		// sharper reason: the early return below fires for EVERY booking with a
		// WooCommerce order, and the gateway refund of a cancelled, paid
		// WooCommerce booking is exactly what this button triggers. Rendered
		// after the return, that refund would have no button anywhere.
		ob_start();
		BookingDepositMetaBox::render_refund_button( $bid );
		$refund_button = (string) ob_get_clean();
		if ( '' !== $refund_button ) {
			echo '<p class="mhm-refund-action">' . $refund_button . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render_refund_button().
		}

		$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $bid );

		// The offline summary below is for offline money only. A booking whose
		// money sits in a WooCommerce order shows no offline figures: its own
		// order screen is where WooCommerce keeps them.
		$remaining = array() === $state->orders() ? $state->refundableManual() : 0;
		$currency  = $state->currency() ?: (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_currency', 'USD' );

		if ( $remaining <= 0 ) {
			if ( $state->refundableAuto() > 0 ) {
				// This booking's money is genuinely refundable; "no refundable
				// payment found" would be false. With the button above the
				// fact is enough; without it (a paid booking not cancelled yet)
				// WooCommerce's own order screen is the route that works.
				echo '<p class="description">' . esc_html__( 'This booking has a refundable WooCommerce payment.', 'mhm-rentiva' );
				if ( '' === $refund_button ) {
					echo ' ' . esc_html__( 'It can be refunded from the WooCommerce order screen.', 'mhm-rentiva' );
				}
				echo '</p>';
			} else {
				echo '<p class="description">' . esc_html__( 'No refundable payment found for this booking.', 'mhm-rentiva' ) . '</p>';
			}
			return;
		}

		echo '<p><strong>' . esc_html__( 'Channel', 'mhm-rentiva' ) . ':</strong> ' . esc_html__( 'Offline', 'mhm-rentiva' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Paid', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $state->paid() ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Already refunded', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $state->refunded() ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Remaining refundable', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $remaining ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
	}

	private static function render_status_row( int $booking_id ): void {
		$status = RefundStatus::get( $booking_id );
		$labels = RefundStatus::labels();

		// The meta can hold a legacy or foreign value; never echo it raw.
		$label = $labels[ $status ] ?? __( 'Unrecognised refund status', 'mhm-rentiva' );

		echo '<p class="mhm-refund-status"><strong>'
			. esc_html__( 'Refund status:', 'mhm-rentiva' ) . '</strong> '
			. esc_html( $label ) . '</p>';
	}

	/**
	 * The control that lets an authorised actor attest a hand transfer.
	 *
	 * Kept separate from render_status_row() rather than folded into it:
	 * that method is deliberately a pure information line, visible to
	 * anyone who can open this box at all (Task 10's own docblock). This one
	 * is a money action -- gated on MoneyAuthorization::mayMoveMoney(), the
	 * same predicate Task 9 put on the deposit-screen's button and this
	 * box's own link, asked with the same $surface convention
	 * ('manual_close', matching DepositManagementAjax::close_manual_refund()'s
	 * own call) so the two halves of this feature cannot drift apart the way
	 * the button and the link once did (see RefundGateAgreementTest).
	 *
	 * The nonce this control's button needs is NOT a new wp_nonce_field
	 * here: deposit-management.js is already enqueued on this same booking
	 * edit screen by BookingDepositMetaBox::enqueue_scripts() and already
	 * carries mhmDepositManagement.nonce, created under this exact action
	 * ('mhmrentiva_deposit_management_action'). A second nonce field in this
	 * box would be redundant at best and a second, driftable source of
	 * truth at worst.
	 */
	private static function render_manual_close_control( int $booking_id ): void {
		if ( RefundStatus::MANUAL_PENDING !== RefundStatus::get( $booking_id ) ) {
			return;
		}

		if ( ! MoneyAuthorization::mayMoveMoney( $booking_id, get_current_user_id(), 'manual_close' ) ) {
			return;
		}

		echo '<p><label for="manual-refund-reference">' . esc_html__( 'Payment reference (optional)', 'mhm-rentiva' ) . '</label><br />'
			. '<input type="text" id="manual-refund-reference" class="widefat" /></p>';
		echo '<p><button type="button" class="button button-primary" id="close-manual-refund" data-booking-id="' . esc_attr( (string) $booking_id ) . '">'
			. esc_html__( 'Confirm hand transfer', 'mhm-rentiva' ) . '</button></p>';
	}

	/**
	 * Task 12 (slice 5): needs_review's two operator exits.
	 *
	 * Kept as its own method for the same reason render_manual_close_control()
	 * is: render_status_row() is deliberately a pure information line, visible
	 * to anyone who can open this box at all, and these two buttons are money
	 * decisions -- gated on MoneyAuthorization::mayMoveMoney(), asked with
	 * $surface = 'review_action' to match DepositManagementAjax::
	 * review_cancel_and_refund()/review_dismiss()'s own calls.
	 *
	 * Rendered independently of BookingDepositMetaBox::
	 * can_refund_from_deposit_screen(): that predicate requires the booking
	 * status to already be CANCELLED, and a needs_review booking is, by
	 * construction, one AutoCancel parked WITHOUT touching the booking status
	 * at all (cancel_booking_with_orders()'s docblock: "a park writes only
	 * _mhmrentiva_refund_status"). Gating these controls on that predicate
	 * too would hide them on exactly the bookings they exist for.
	 *
	 * The nonce these buttons need is the same mhmDepositManagement.nonce
	 * render_manual_close_control() relies on -- see that method's docblock
	 * for why no second wp_nonce_field() belongs here.
	 */
	private static function render_needs_review_controls( int $booking_id ): void {
		if ( RefundStatus::NEEDS_REVIEW !== RefundStatus::get( $booking_id ) ) {
			return;
		}

		if ( ! MoneyAuthorization::mayMoveMoney( $booking_id, get_current_user_id(), 'review_action' ) ) {
			return;
		}

		// Whole-branch review, F1: "Cancel and start the refund" delegates to
		// CancellationHandler::cancel_booking(), which refuses outright --
		// WP_Error( 'already_cancelled' ) at CancellationHandler.php:115 --
		// the moment this booking's OWN status is already CANCELLED. Both of
		// needs_review's real producers can leave a booking in exactly that
		// shape: the mixed-currency guard inside settle_refund() runs AFTER
		// COMMIT (cancel_booking()'s own docblock -- "past this line nothing
		// may be rolled back"), and AutoCancel::sync_orphan_wc_orders() only
		// ever parks a booking its own WP_Query already selected on
		// `_mhmrentiva_status = 'cancelled'`. Offering this button there
		// offers a dead action -- worse, on that same shape the deposit
		// screen offers nothing either (can_refund_from_deposit_screen()
		// demands refundable() > 0, which is 0 for exactly the
		// mixed-currency booking the first producer requires), so "No
		// refund is due" was the operator's only working button, and it
		// writes a terminal not_required onto a booking that demonstrably
		// still holds money. Gated and worded the same way render()'s own
		// WooCommerce-order sentence already is, a few lines above this
		// method's own render() caller: point at the path that actually
		// moves money instead of offering one that cannot. No new refund
		// path is invented here -- WooCommerce's own order screen already
		// does this, and remains how the money actually moves.
		if ( \MHMRentiva\Admin\Booking\Core\Status::CANCELLED === \MHMRentiva\Admin\Booking\Core\Status::get( $booking_id ) ) {
			echo '<p class="description">' . esc_html__( 'This booking is already cancelled. Refund its paid order from the WooCommerce order screen, then close this review below once the money has actually moved.', 'mhm-rentiva' ) . '</p>';
		} else {
			echo '<p><button type="button" class="button button-primary" id="review-cancel-and-refund" data-booking-id="' . esc_attr( (string) $booking_id ) . '">'
				. esc_html__( 'Cancel and start the refund', 'mhm-rentiva' ) . '</button></p>';
		}

		echo '<p><label for="refund-review-dismiss-reason">' . esc_html__( 'Why is no refund due?', 'mhm-rentiva' ) . '</label><br />'
			. '<textarea id="refund-review-dismiss-reason" class="widefat" rows="2"></textarea></p>';
		echo '<p><button type="button" class="button" id="review-dismiss" data-booking-id="' . esc_attr( (string) $booking_id ) . '">'
			. esc_html__( 'No refund is due', 'mhm-rentiva' ) . '</button></p>';
	}
}
