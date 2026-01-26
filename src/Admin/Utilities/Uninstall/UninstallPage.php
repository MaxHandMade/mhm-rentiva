<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Uninstall;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uninstall Admin Page (AJAX Handlers)
 */
final class UninstallPage {

	public static function register(): void {
		add_action( 'wp_ajax_mhm_get_uninstall_stats', array( self::class, 'ajax_get_uninstall_stats' ) );
		add_action( 'wp_ajax_mhm_uninstall_plugin', array( self::class, 'ajax_uninstall_plugin' ) );
	}

	/**
	 * AJAX - Get uninstall statistics
	 */
	public static function ajax_get_uninstall_stats(): void {
		check_ajax_referer( 'mhm_uninstall', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Permission denied', 'mhm-rentiva' ) );
		}

		$stats = Uninstaller::get_uninstall_stats();

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX - Perform uninstall
	 */
	public static function ajax_uninstall_plugin(): void {
		check_ajax_referer( 'mhm_uninstall', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Permission denied', 'mhm-rentiva' ) );
		}

		$delete_backups = isset( $_POST['delete_backups'] ) && $_POST['delete_backups'] === '1';

		$result = Uninstaller::uninstall( $delete_backups );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] ?? esc_html__( 'Uninstall failed', 'mhm-rentiva' ) );
		}
	}
}
