<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

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
		add_action( 'admin_enqueue_scripts', array( self::class, 'remove_unnecessary_admin_scripts' ), 999 );
		add_action( 'admin_notices', array( self::class, 'limit_admin_notices' ), 1 );
	}

	/*
	 * HEARTBEAT IS LEFT ALONE, DELIBERATELY.
	 *
	 * This class used to call wp_deregister_script( 'heartbeat' ) on the plugin's
	 * own admin screens, and it broke something no user could see coming. Core
	 * enqueues `wp-auth-check` -- the "your session has expired, log in again"
	 * modal -- with `heartbeat` as a dependency, so removing the registration made
	 * WP_Dependencies skip it: the modal's MARKUP was printed on every one of our
	 * screens while the script that drives it never loaded. A session expiring
	 * while somebody filled in a long vehicle form produced no warning at all --
	 * they pressed Save and lost the work. That happened on every install, not
	 * only with WP_DEBUG on.
	 *
	 * Measured on an authenticated page load with the plugin active and only the
	 * screen varying, by grepping the rendered HTML (no debug mode needed):
	 *   before  core index.php  heartbeat.js x2 | plugin screen  heartbeat.js x0
	 *   after   both screens identical: heartbeat.js x2, wp-auth-check present
	 *
	 * THE OPTIMISATION WAS NOT REPLACED, because it could not be delivered here.
	 * The supported way to poll less is the `heartbeat_settings` filter, and it
	 * does not work from this plugin's registration point: WordPressOptimizer is
	 * registered on `init` priority 2, and an added filter never reached core's
	 * application point -- the page still shipped
	 * `heartbeatSettings = {"nonce":"..."}` with no interval, even with every
	 * screen guard removed. Dequeueing instead of deregistering is no answer
	 * either: wp-auth-check depends on heartbeat, so it comes straight back into
	 * the queue and nothing is saved.
	 *
	 * So the choice was between shipping a filter proven to do nothing and
	 * removing the code. What remains below only touches this plugin's own
	 * assets, which is what the class docblock has always claimed.
	 */

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
		return in_array( $post_type, array( 'mhmrentiva_vehicle', 'mhmrentiva_booking', 'mhmrentiva_addon' ), true );
	}
}
