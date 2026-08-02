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

		// Additional lightweight optimizations for Rentiva screens
		self::apply_lightweight_optimizations();
	}

	/**
	 * Apply additional lightweight optimizations for Rentiva admin screens.
	 */
	private static function apply_lightweight_optimizations(): void {
		// Remove unnecessary admin notices that might clutter the interface
		remove_action( 'admin_notices', 'wp_print_media_templates' );

		// 🔴 NOT ON A POST EDITOR. This used to dequeue 'heartbeat' and 'autosave'
		// unconditionally, and is_vehicle_editor_screen() below deliberately
		// matches the mhmrentiva_vehicle / _booking / _addon post types -- so the
		// screens it hit hardest were exactly the long forms where losing work
		// costs the most.
		//
		// Core registers 'autosave' WITH 'heartbeat' as a dependency
		// (script-loader.php: $scripts->add( 'autosave', ..., array( 'heartbeat' ) ),
		// and 'wp-auth-check' the same way), so removing heartbeat took autosave
		// and the post-lock with it and logged _doing_it_wrong notices that are
		// visible to anyone running WP_DEBUG.
		//
		// The trade was never close: one poll every 15-60 seconds against a user's
		// unsaved work on a vehicle form, plus the "somebody else is editing this"
		// warning that stops two people overwriting each other.
		//
		// The exemption list mirrors WordPressOptimizer::disable_heartbeat() rather
		// than being a special case here, so the two optimisers now answer the
		// question the same way.
		if ( self::is_post_editing_screen() ) {
			return;
		}

		// Disable some unnecessary admin scripts on Rentiva screens
		wp_dequeue_script( 'heartbeat' );
		wp_dequeue_script( 'autosave' );
	}

	/**
	 * Is this one of the core screens where heartbeat carries real work?
	 *
	 * The post editors run autosave and the post lock, and edit.php uses heartbeat
	 * for the lock indicator in the list table. These are the same three screens
	 * WordPressOptimizer::disable_heartbeat() already exempts.
	 */
	private static function is_post_editing_screen(): bool {
		global $pagenow;

		return in_array( $pagenow, array( 'post.php', 'post-new.php', 'edit.php' ), true );
	}

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
