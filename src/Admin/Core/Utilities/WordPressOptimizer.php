<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin/frontend asset housekeeping.
 *
 * Scope is deliberately narrow: only dequeues this plugin's own unused
 * admin script/style bundles and trims admin notices on this plugin's own
 * screens. It does not alter site-wide WordPress behaviour (XML-RPC,
 * pingbacks, feeds, oEmbed, etc.) -- a car-rental plugin has no functional
 * need to touch any of that.
 */
final class WordPressOptimizer {

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'disable_heartbeat' ), 1 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'remove_unnecessary_admin_scripts' ), 999 );
		add_action( 'admin_notices', array( self::class, 'limit_admin_notices' ), 1 );
	}

	/**
	 * Disable Heartbeat
	 */
	public static function disable_heartbeat(): void {
		if ( ! self::is_plugin_admin_screen() ) {
			return;
		}

		global $pagenow;

		// Only enable Heartbeat on required pages
		$allowed_pages = array( 'post.php', 'post-new.php', 'edit.php' );
		if ( ! in_array( $pagenow, $allowed_pages, true ) ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	/**
	 * Remove unnecessary admin scripts
	 */
	public static function remove_unnecessary_admin_scripts(): void {
		if ( ! self::is_plugin_admin_screen() ) {
			return;
		}

		// Only remove optional helpers, keep critical core assets
		wp_dequeue_script( 'wp-pointer' );
		wp_dequeue_style( 'wp-pointer' );
	}

	/**
	 * Limit admin notices
	 */
	public static function limit_admin_notices(): void {
		if ( ! self::is_plugin_admin_screen() ) {
			return;
		}

		// Remove unnecessary WordPress admin notices
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag', 10 );
		remove_action( 'admin_notices', 'wp_php_version_notice', 10 );
		remove_action( 'admin_notices', 'wp_update_php_notice', 10 );
		remove_action( 'admin_notices', 'wp_update_php_annotation', 10 );
	}
	private static function is_plugin_admin_screen(): bool {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		if ( ! empty( $screen->id ) && strpos( $screen->id, 'mhm-rentiva' ) !== false ) {
			return true;
		}

		$post_type = $screen->post_type ?? null;
		return in_array( $post_type, array( 'vehicle', 'vehicle_booking', 'vehicle_addon' ), true );
	}
}
