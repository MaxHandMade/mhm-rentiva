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
		$bid   = (int) $post->ID;
		$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( $bid );

		// This box is the offline surface. A booking whose money sits in a
		// WooCommerce order is refunded from the WooCommerce order screen or
		// from the deposit-management screen; a third button here would give the
		// operator two paths with different rules over the same money.
		$remaining = array() === $state->orders() ? $state->refundableManual() : 0;
		$currency  = $state->currency() ?: (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_currency', 'USD' );

		if ( $remaining <= 0 ) {
			echo '<p class="description">' . esc_html__( 'No refundable payment found for this booking.', 'mhm-rentiva' ) . '</p>';
			return;
		}

		$action = esc_url( admin_url( 'admin-post.php' ) );
		$nonce  = wp_create_nonce( 'mhmrentiva_refund_booking' );
		$defAmt = $remaining;

		echo '<p><strong>' . esc_html__( 'Channel', 'mhm-rentiva' ) . ':</strong> ' . esc_html__( 'Offline', 'mhm-rentiva' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Paid', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $state->paid() ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Already refunded', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $state->refunded() ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Remaining refundable', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( (float) Money::toMajor( $remaining ), Money::decimals() ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<form action="' . esc_url( $action ) . '" method="post">';
		echo '<input type="hidden" name="action" value="mhmrentiva_refund_booking" />';
		echo '<input type="hidden" name="booking_id" value="' . (int) $bid . '" />';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';

		$decimals = Money::decimals();
		$step     = $decimals > 0 ? '0.' . str_repeat( '0', $decimals - 1 ) . '1' : '1';
		echo '<p><label>' . esc_html__( 'Refund amount', 'mhm-rentiva' ) . '</label><br />';
		echo '<input type="number" name="amount_visible" min="0" step="' . esc_attr( $step ) . '" value="' . esc_attr( Money::toMajor( (int) $defAmt ) ) . '" class="small-text" /> ' . esc_html( strtoupper( $currency ) ) . '</p>';
		echo '<input type="hidden" name="amount_kurus" id="mhmrentiva_amount_kurus" value="' . (int) $defAmt . '" />';
		echo '<p><label>' . esc_html__( 'Reason (optional)', 'mhm-rentiva' ) . '</label><br />';
		echo '<select name="reason">';
		foreach ( array( 'customer_request', 'vehicle_unavailable', 'duplicate', 'fraud_suspected', 'other' ) as $r ) {
			echo '<option value="' . esc_attr( $r ) . '">' . esc_html( ucwords( str_replace( '_', ' ', $r ) ) ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><button type="submit" class="button button-primary mhm-refund-submit-btn">' . esc_html__( 'Refund', 'mhm-rentiva' ) . '</button></p>';
		echo '</form>';
	}
}
