<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BookingRefundMetaBox {


	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
	}

	public static function add(): void {
		add_meta_box(
			'mhm_booking_refund',
			__( 'Refund', 'mhm-rentiva' ),
			array( self::class, 'render' ),
			'vehicle_booking',
			'side',
			'high'
		);
	}

	public static function render( \WP_Post $post ): void {
		$bid       = (int) $post->ID;
		$gateway   = (string) get_post_meta( $bid, '_mhm_payment_gateway', true );
		$payStatus = (string) get_post_meta( $bid, '_mhm_payment_status', true );
		$paidKurus = (int) get_post_meta( $bid, '_mhm_payment_amount', true );
		$currency  = (string) get_post_meta( $bid, '_mhm_payment_currency', true ) ?: \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency', 'USD' );
		$refunded  = (int) get_post_meta( $bid, '_mhm_refunded_amount', true );
		$remaining = max( 0, $paidKurus - $refunded );

		// Only show when refundable
		if ( $gateway !== 'offline' || $paidKurus <= 0 ) {
			echo '<p class="description">' . esc_html__( 'No refundable payment found for this booking.', 'mhm-rentiva' ) . '</p>';
			return;
		}
		if ( ! in_array( $payStatus, array( 'paid', 'partially_refunded' ), true ) ) {
			echo '<p class="description">' . esc_html__( 'Booking is not in a refundable state.', 'mhm-rentiva' ) . '</p>';
			return;
		}
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$nonce  = wp_create_nonce( 'mhm_rentiva_refund_booking' );
		$defAmt = $remaining > 0 ? $remaining : 0;
		echo '<p><strong>' . esc_html__( 'Gateway', 'mhm-rentiva' ) . ':</strong> ' . esc_html( strtoupper( $gateway ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Paid', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( $paidKurus / 100, 2 ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Already refunded', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( $refunded / 100, 2 ) . ' ' . strtoupper( $currency ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Remaining refundable', 'mhm-rentiva' ) . ':</strong> ' . esc_html( number_format_i18n( $remaining / 100, 2 ) . ' ' . strtoupper( $currency ) ) . '</p>';
		if ( $remaining <= 0 ) {
			echo '<p class="description">' . esc_html__( 'Nothing left to refund.', 'mhm-rentiva' ) . '</p>';
			return;
		}
		echo '<form action="' . esc_url( $action ) . '" method="post">';
		echo '<input type="hidden" name="action" value="mhm_rentiva_refund_booking" />';
		echo '<input type="hidden" name="booking_id" value="' . (int) $bid . '" />';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';

		echo '<p><label>' . esc_html__( 'Refund amount', 'mhm-rentiva' ) . '</label><br />';
		echo '<input type="number" name="amount_visible" min="0" step="0.01" value="' . esc_attr( number_format( $defAmt / 100, 2, '.', '' ) ) . '" class="small-text" /> ' . esc_html( strtoupper( $currency ) ) . '</p>';
		echo '<input type="hidden" name="amount_kurus" id="mhm_amount_kurus" value="' . (int) $defAmt . '" />';
		echo '<p><label>' . esc_html__( 'Reason (optional)', 'mhm-rentiva' ) . '</label><br />';
		echo '<select name="reason">';
		foreach ( array( 'customer_request', 'vehicle_unavailable', 'duplicate', 'fraud_suspected', 'other' ) as $r ) {
			echo '<option value="' . esc_attr( $r ) . '">' . esc_html( ucwords( str_replace( '_', ' ', $r ) ) ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><button type="submit" class="button button-primary" onclick="return confirm(\'' . esc_js( __( 'Proceed with refund?', 'mhm-rentiva' ) ) . '\')">' . esc_html__( 'Refund', 'mhm-rentiva' ) . '</button></p>';
		echo '</form>';
		echo '<script>
        (function(){
          var v=document.querySelector(\'input[name="amount_visible"]\');
          var h=document.getElementById("mhm_amount_kurus");
          if(v&&h){
            v.addEventListener("input", function(){
              var x = Math.round(parseFloat(v.value || "0") * 100);
              if(!isFinite(x) || x<0) x=0;
              h.value = String(x);
            });
          }
        })();
        </script>';
	}
}
