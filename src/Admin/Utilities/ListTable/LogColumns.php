<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\ListTable;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Logs\PostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogColumns {


	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Filter params of the payment-log list screen, registered on WordPress's
	 * `query_vars` whitelist so get_text() can read them with get_query_var()
	 * instead of reaching into $_GET.
	 *
	 * Same mechanism BookingColumns/VehicleColumns use -- the fix WP.org accepted
	 * here. These are display parameters of a bookmarkable admin URL: they
	 * change no state, so nonce-gating them would break shareable filtered links,
	 * and an annotation over a raw superglobal read is not a resolution.
	 * `post_type` and `orderby`, also read here, are core public query vars.
	 *
	 * @var array<int, string>
	 */
	private const PUBLIC_QUERY_VARS = array(
		'mhmrentiva_log_gateway',
		'mhmrentiva_log_status',
	);

	/**
	 * `query_vars` filter callback.
	 *
	 * @param array<int, string> $vars Registered public query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		return array_values( array_unique( array_merge( $vars, self::PUBLIC_QUERY_VARS ) ) );
	}

	public static function register(): void {
		add_filter( 'query_vars', array( self::class, 'register_query_vars' ) );
		add_filter( 'manage_edit-' . PostType::TYPE . '_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . PostType::TYPE . '_posts_custom_column', array( self::class, 'render' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( self::class, 'filters' ) );
		add_action( 'pre_get_posts', array( self::class, 'handle_filters' ) );
		add_filter( 'manage_edit-' . PostType::TYPE . '_sortable_columns', array( self::class, 'sortable' ) );
	}

	public static function columns( array $cols ): array {
		$new = array();
		// Keep checkbox and title
		foreach ( $cols as $k => $v ) {
			if ( in_array( $k, array( 'cb', 'title', 'date' ), true ) ) {
				$new[ $k ] = $v;
			}
		}
		$new['gateway'] = __( 'Payment Method', 'mhm-rentiva' );
		$new['action']  = __( 'Action', 'mhm-rentiva' );
		$new['status']  = __( 'Status', 'mhm-rentiva' );
		$new['booking'] = __( 'Booking', 'mhm-rentiva' );
		$new['amount']  = __( 'Amount', 'mhm-rentiva' );
		$new['message'] = __( 'Message', 'mhm-rentiva' );
		$new['date']    = $cols['date'] ?? __( 'Date', 'mhm-rentiva' );
		return $new;
	}

	public static function render( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'gateway':
				echo esc_html( strtoupper( (string) get_post_meta( $post_id, '_mhmrentiva_log_gateway', true ) ) );
				break;
			case 'action':
				echo esc_html( (string) get_post_meta( $post_id, '_mhmrentiva_log_action', true ) );
				break;
			case 'status':
				$s     = (string) get_post_meta( $post_id, '_mhmrentiva_log_status', true );
				$label = $s ? ucfirst( $s ) : '';
				echo esc_html( $label );
				break;
			case 'booking':
				$bid = (int) get_post_meta( $post_id, '_mhmrentiva_log_booking_id', true );
				if ( $bid ) {
					$link = get_edit_post_link( $bid, '' );
					echo '<a href="' . esc_url( $link ) . '">#' . (int) $bid . '</a>';
				} else {
					echo '—';
				}
				break;
			case 'amount':
				$ak  = (int) get_post_meta( $post_id, '_mhmrentiva_log_amount_kurus', true );
				$cur = (string) get_post_meta( $post_id, '_mhmrentiva_log_currency', true );
				if ( $ak > 0 ) {
					$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol( $cur ?: null );
					echo esc_html( number_format_i18n( $ak / 100, 2 ) . ' ' . $currency_symbol );
				} else {
					echo '—';
				}
				break;
			case 'message':
				$msg = self::sanitize_text_field_safe( get_post_field( 'post_content', $post_id ) );
				if ( $msg !== '' ) {
					echo esc_html( wp_strip_all_tags( $msg ) );
				} else {
					echo '—';
				}
				break;
		}
	}

	public static function filters(): void {
		global $typenow;
		if ( $typenow !== PostType::TYPE ) {
			return;
		}
		$selGateway = self::get_text( 'mhmrentiva_log_gateway' );
		$selStatus  = self::get_text( 'mhmrentiva_log_status' );

		echo '<label for="mhmrentiva_log_gateway" class="screen-reader-text">' . esc_html__( 'Gateway', 'mhm-rentiva' ) . '</label>';
		echo '<select name="mhmrentiva_log_gateway" id="mhmrentiva_log_gateway">';
		echo '<option value="">' . esc_html__( 'All payment methods', 'mhm-rentiva' ) . '</option>';
		$allowedGateways = function_exists( 'WC' ) ? array_keys( WC()->payment_gateways()->payment_gateways() ) : array();
		foreach ( $allowedGateways as $gw ) {
			echo '<option value="' . esc_attr( $gw ) . '"' . selected( $selGateway, $gw, false ) . '>' . esc_html( strtoupper( $gw ) ) . '</option>';
		}
		echo '</select> ';

		echo '<label for="mhmrentiva_log_status" class="screen-reader-text">' . esc_html__( 'Status', 'mhm-rentiva' ) . '</label>';
		echo '<select name="mhmrentiva_log_status" id="mhmrentiva_log_status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'mhm-rentiva' ) . '</option>';
		foreach ( array( 'success', 'error' ) as $st ) {
			echo '<option value="' . esc_attr( $st ) . '"' . selected( $selStatus, $st, false ) . '>' . esc_html( ucfirst( $st ) ) . '</option>';
		}
		echo '</select>';
	}

	public static function handle_filters( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->post_type !== PostType::TYPE ) {
			return;
		}
		$pt = self::get_text( 'post_type' );
		if ( $pt && $pt !== PostType::TYPE ) {
			return;
		}

		$metaQuery = (array) $q->get( 'meta_query' );
		if ( empty( $metaQuery ) ) {
			$metaQuery = array( 'relation' => 'AND' );
		}

		$gw = self::get_text( 'mhmrentiva_log_gateway' );
		if ( $gw !== '' ) {
			$metaQuery[] = array(
				'key'     => '_mhmrentiva_log_gateway',
				'value'   => $gw,
				'compare' => '=',
			);
		}
		$st = self::get_text( 'mhmrentiva_log_status' );
		if ( $st !== '' ) {
			$metaQuery[] = array(
				'key'     => '_mhmrentiva_log_status',
				'value'   => $st,
				'compare' => '=',
			);
		}
		$q->set( 'meta_query', $metaQuery );

		$orderby = self::get_text( 'orderby' );
		if ( $orderby === 'amount' ) {
			$q->set( 'meta_key', '_mhmrentiva_log_amount_kurus' );
			$q->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Read a filter param off the `query_vars` whitelist (see PUBLIC_QUERY_VARS).
	 *
	 * WP::parse_request() preserves arrays for registered query vars, so a
	 * `?mhmrentiva_log_status[]=x` request would hand an array to a reader typed as
	 * string; the is_array() guard keeps that from raising a live
	 * "Array to string conversion" warning.
	 */
	private static function get_text( string $key, string $default = '' ): string {
		$value = get_query_var( $key, null );
		if ( null === $value || is_array( $value ) ) {
			return $default;
		}

		return self::sanitize_text_field_safe( wp_unslash( (string) $value ) );
	}

	public static function sortable( array $cols ): array {
		$cols['amount'] = 'amount';
		return $cols;
	}
}
