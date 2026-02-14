<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode Test Handler
 *
 * Provides AJAX handlers for testing shortcodes
 */
final class ShortcodeTestHandler {

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
	 * Register AJAX handlers
	 */
	public static function register(): void {
		add_action( 'wp_ajax_test_shortcode', array( self::class, 'handle_test_shortcode' ) );
	}

	/**
	 * Test shortcode AJAX handler
	 */
	public static function handle_test_shortcode(): void {
		// Security check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'test_shortcode' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
			return;
		}

		// Capability check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission for this action.', 'mhm-rentiva' ) );
			return;
		}

		$shortcode = isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['shortcode'] ) ) : '';
		$example   = isset( $_POST['example'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['example'] ) ) : '';

		if ( empty( $shortcode ) ) {
			wp_send_json_error( __( 'Shortcode not specified.', 'mhm-rentiva' ) );
			return;
		}

		try {
			// Check if shortcode is registered
			if ( ! shortcode_exists( $shortcode ) ) {
				/* translators: Dynamic value. */
				wp_send_json_error( sprintf( __( 'Shortcode "%s" is not registered.', 'mhm-rentiva' ), $shortcode ) );
				return;
			}

			// Test shortcode
			$output = do_shortcode( $example );

			// Check if output is empty
			if ( empty( trim( $output ) ) ) {
				wp_send_json_error( __( 'Shortcode output is empty.', 'mhm-rentiva' ) );
				return;
			}

			// Safely sanitize output
			$safe_output = wp_kses_post( $output );

			wp_send_json_success( $safe_output );
		} catch ( Exception $e ) {
			wp_send_json_error( __( 'Shortcode test error: ', 'mhm-rentiva' ) . $e->getMessage() );
		}
	}

	/**
	 * Get all registered shortcodes
	 */
	public static function get_registered_shortcodes(): array {
		global $shortcode_tags;

		$rentiva_shortcodes = array();

		foreach ( $shortcode_tags as $tag => $callback ) {
			if ( strpos( $tag, 'rentiva_' ) === 0 ) {
				$rentiva_shortcodes[ $tag ] = array(
					'tag'         => $tag,
					'callback'    => $callback,
					'is_callable' => is_callable( $callback ),
				);
			}
		}

		return $rentiva_shortcodes;
	}

	/**
	 * Test all shortcodes at once
	 */
	public static function test_all_shortcodes(): array {
		$results    = array();
		$shortcodes = self::get_registered_shortcodes();

		foreach ( $shortcodes as $tag => $info ) {
			$results[ $tag ] = array(
				'registered' => true,
				'callable'   => $info['is_callable'],
				'tested'     => false,
				'output'     => '',
				'error'      => '',
			);

			try {
				// Run a simple test shortcode
				$test_output               = do_shortcode( "[{$tag}]" );
				$results[ $tag ]['tested'] = true;
				$results[ $tag ]['output'] = ! empty( trim( $test_output ) );
			} catch ( Exception $e ) {
				$results[ $tag ]['error'] = $e->getMessage();
			}
		}

		return $results;
	}

	/**
	 * Generate test report
	 */
	public static function generate_test_report(): array {
		$shortcodes   = self::get_registered_shortcodes();
		$test_results = self::test_all_shortcodes();

		$total      = count( $shortcodes );
		$registered = count( array_filter( $shortcodes, fn( $s ) => $s['is_callable'] ) );
		$tested     = count( array_filter( $test_results, fn( $r ) => $r['tested'] ) );
		$working    = count( array_filter( $test_results, fn( $r ) => $r['tested'] && $r['output'] ) );

		return array(
			'summary'      => array(
				'total'        => $total,
				'registered'   => $registered,
				'tested'       => $tested,
				'working'      => $working,
				'success_rate' => $total > 0 ? round( ( $working / $total ) * 100, 2 ) : 0,
			),
			'shortcodes'   => $shortcodes,
			'test_results' => $test_results,
			'timestamp'    => current_time( 'mysql' ),
		);
	}
}
