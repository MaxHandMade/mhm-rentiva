<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Logs;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaBox {

	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function add(): void {
		add_meta_box(
			'mhmrentiva_log_context',
			__( 'Context', 'mhm-rentiva' ),
			array( self::class, 'render' ),
			PostType::TYPE,
			'normal',
			'default'
		);
	}

	public static function render( \WP_Post $post ): void {
		$raw    = (string) get_post_meta( $post->ID, '_mhmrentiva_log_context', true );
		$pretty = '—';
		if ( $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$pretty = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			} else {
				$pretty = $raw;
			}
		}
		// Legacy helper metas for backward compatibility.
		$gateway = strtoupper( (string) get_post_meta( $post->ID, '_mhmrentiva_log_gateway', true ) );
		$action  = (string) get_post_meta( $post->ID, '_mhmrentiva_log_action', true );
		$status  = (string) get_post_meta( $post->ID, '_mhmrentiva_log_status', true );
		$bid     = (int) get_post_meta( $post->ID, '_mhmrentiva_log_booking_id', true );

		$ak  = (int) get_post_meta( $post->ID, '_mhmrentiva_log_amount_kurus', true );
		$cur = (string) get_post_meta( $post->ID, '_mhmrentiva_log_currency', true );

		echo '<p style="margin:0 0 8px;">';
		if ( $gateway ) {
			echo '<strong>' . esc_html( $gateway ) . '</strong> ';
		}
		if ( $action ) {
			echo '<code>' . esc_html( $action ) . '</code> ';
		}
		if ( $status ) {
			echo '<span>[' . esc_html( $status ) . ']</span> ';
		}

		if ( $bid ) {
			$link = get_edit_post_link( $bid );
			if ( $link ) {
				echo '<span style="margin-left:8px;">' . esc_html__( 'Booking', 'mhm-rentiva' ) . ': <a href="' . esc_url( $link ) . '">#' . (int) $bid . '</a></span> ';
			}
		}
		if ( $ak > 0 ) {
			// The row's own currency, but the house placement — this used to pin
			// the symbol to the right regardless of woocommerce_currency_pos.
			$val = \MHMRentiva\Admin\Core\CurrencyHelper::format_price( (float) \MHMRentiva\Admin\Payment\Core\Money::toMajor( $ak ), \MHMRentiva\Admin\Payment\Core\Money::decimals(), $cur ?: null );
			echo '<span style="margin-left:8px;">' . esc_html__( 'Amount', 'mhm-rentiva' ) . ': ' . esc_html( $val ) . '</span>';
		}
		echo '</p>';

		echo '<p><button type="button" class="button" id="mhm-copy-context">' . esc_html__( 'Copy JSON', 'mhm-rentiva' ) . '</button></p>';
		echo '<textarea readonly id="mhm-context" class="large-text code mhm-json" rows="16" spellcheck="false">' . esc_textarea( $pretty ) . '</textarea>';
	}

	/**
	 * Enqueues scripts and styles for the metabox.
	 */
	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== PostType::TYPE || ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		$plugin_url = untrailingslashit( plugin_dir_url( MHMRENTIVA_PLUGIN_FILE ) );

		wp_enqueue_style(
			'mhm-rentiva-log-metabox-css',
			$plugin_url . '/assets/css/admin/log-metabox.css',
			array(),
			MHMRENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-log-metabox-js',
			$plugin_url . '/assets/js/admin/log-metabox.js',
			array( 'jquery' ),
			MHMRENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-log-metabox-js',
			'mhmLogMetabox',
			array(
				'copied' => __( 'Copied', 'mhm-rentiva' ),
				'copy'   => __( 'Copy JSON', 'mhm-rentiva' ),
			)
		);
	}
}
