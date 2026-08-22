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

		// Same reasoning, same placement: a manual_pending booking's
		// refundableManual() is already 0 by the time this box renders
		// (Refunds\Service records the manual amount into
		// _mhmrentiva_refunded_amount the moment manual_pending is set), so a
		// control gated on that status would never reach the page if it sat
		// after the guard below.
		self::render_manual_close_control( $bid );

		$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $bid );

		// This box is the offline surface. A booking whose money sits in a
		// WooCommerce order is refunded from the WooCommerce order screen or
		// from the deposit-management screen; a third button here would give the
		// operator two paths with different rules over the same money.
		$remaining = array() === $state->orders() ? $state->refundableManual() : 0;
		$currency  = $state->currency() ?: (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_currency', 'USD' );

		if ( $remaining <= 0 ) {
			if ( $state->refundableAuto() > 0 ) {
				// This booking's money is genuinely refundable -- just not
				// through THIS box, which is deliberately offline-only (see
				// the comment above). Saying "no refundable payment found"
				// here would be false: point the operator at the path that
				// actually works instead.
				echo '<p class="description">' . esc_html__( 'This booking has a refundable WooCommerce payment. Refund it from the WooCommerce order screen or the deposit-management screen.', 'mhm-rentiva' ) . '</p>';
			} else {
				echo '<p class="description">' . esc_html__( 'No refundable payment found for this booking.', 'mhm-rentiva' ) . '</p>';
			}
			return;
		}

		echo '<p><strong>' . esc_html__( 'Channel', 'mhm-rentiva' ) . ':</strong> ' . esc_html__( 'Offline', 'mhm-rentiva' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Paid', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $state->paid() ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Already refunded', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $state->refunded() ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Remaining refundable', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $remaining ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';

		// A metabox renders INSIDE WordPress's own #post form. A nested
		// <form> here (the box used to print one) is invalid HTML, so the
		// browser's parser discards the <form> tag and adopts its fields
		// into WordPress's own form -- the refund button then submits
		// editpost, not a refund, and duplicates the `action` field WP
		// itself relies on. Link to the box that already has a working,
		// non-nested refund trigger (wp_ajax_mhmrentiva_deposit_process_refund
		// via assets/js/admin/deposit-management.js) instead of rendering a
		// second, broken one here. See BookingRefundMetaBoxRendersTest's
		// "no form, no name=action" assertion for the regression this guards.
		//
		// That trigger is not always there to link to, and this box used to
		// keep its own copy of the rule that decides. The copy was wrong twice
		// -- it demanded exactly 'paid' (so a partially refunded booking lost
		// its route the moment a correct partial refund ran) and it did not
		// know the deposit box returns early, before any button, on a booking
		// with no _mhmrentiva_payment_type. Ask the screen that owns the
		// button instead of mirroring its rule.
		//
		// Task 9 (slice 5) added a second condition: this link and the button
		// it points at answer the same question, so a refused actor must not
		// see either. Fix round 1 (F1) closed the drift a first pass left
		// open -- the button alone asked MoneyAuthorization, so an actor who
		// failed it saw the button disappear but the link stay, disagreeing
		// with the very button it points at. Same predicate, same $surface
		// as the button ('admin_deposit') so the two never drift again.
		if ( MoneyAuthorization::mayMoveMoney( $bid, get_current_user_id(), 'admin_deposit' ) && BookingDepositMetaBox::can_refund_from_deposit_screen( $bid ) ) {
			$deposit_url = admin_url( 'post.php?post=' . $bid . '&action=edit' ) . '#mhmrentiva_booking_deposit';
			echo '<p><a class="button button-primary" href="' . esc_url( $deposit_url ) . '">' . esc_html__( 'Process this refund from the deposit-management screen.', 'mhm-rentiva' ) . '</a></p>';
		} else {
			// Deliberately does NOT name a missing condition. The predicate has
			// four of them and the sentence has to stay true for every false
			// case; the old wording named cancellation alone, which is already
			// false for a cancelled booking that is merely partially refunded.
			echo '<p class="description">' . esc_html__( 'This amount is refundable; refunds for this booking are recorded from the deposit-management screen.', 'mhm-rentiva' ) . '</p>';
		}
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
}
