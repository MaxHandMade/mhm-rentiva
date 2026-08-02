<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Performance;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies lightweight optimizations only on Rentiva admin screens.
 * Previous version's aggressive interventions (Gutenberg/REST blocking etc.)
 * have been removed; thus the default WordPress flow continues without disruption.
 */
final class AdminOptimizer {

	public static function register(): void {
		// Only register if we're in admin area
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( self::class, 'maybe_optimize_vehicle_editor' ), 20 );
	}

	/**
	 * Remove unnecessary admin pointers on vehicle/booking editing screens.
	 */
	public static function maybe_optimize_vehicle_editor(): void {
		if ( ! self::is_vehicle_editor_screen() ) {
			return;
		}

		// Light optimization: only disable pointer documents
		wp_dequeue_script( 'wp-pointer' );
		wp_dequeue_style( 'wp-pointer' );
	}

	/*
	 * apply_lightweight_optimizations() WAS REMOVED, because every line of it was
	 * a no-op that read like an optimisation.
	 *
	 *   - wp_dequeue_script( 'heartbeat' ): wp-auth-check declares heartbeat as a
	 *     dependency, so dequeueing pulls it straight back. Measured on a plugin
	 *     settings screen with the dequeue in place: heartbeat.js still emitted.
	 *   - wp_dequeue_script( 'autosave' ): core only enqueues autosave on the post
	 *     editors, and those screens were exempted. Measured on a plugin settings
	 *     screen: autosave.js count 0 before the dequeue could matter.
	 *   - remove_action( 'admin_notices', 'wp_print_media_templates' ): core does
	 *     not hook that function to admin_notices at all, so it removed nothing.
	 *
	 * The heartbeat and autosave dequeues did do real harm before the post-editor
	 * exemption was added -- see the sibling note in WordPressOptimizer, where the
	 * same subsystem broke the session-expiry modal plugin-wide. What is left in
	 * this class is the one measurable thing it did: dropping wp-pointer, which
	 * core does enqueue in admin and which this plugin's screens do not use.
	 */

	private static function is_vehicle_editor_screen(): bool {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Check if it's a Rentiva admin screen
		if ( ! empty( $screen->id ) && strpos( $screen->id, 'mhm-rentiva' ) !== false ) {
			return true;
		}

		// Check if it's a Rentiva post type editor
		$post_type = $screen->post_type ?? null;
		return in_array( $post_type, array( 'mhmrentiva_vehicle', 'mhmrentiva_booking', 'mhmrentiva_addon' ), true );
	}
}
