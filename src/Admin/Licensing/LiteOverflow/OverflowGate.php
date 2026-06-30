<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing\LiteOverflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires Lite catalog-overflow enforcement: hides over-limit catalog items from
 * the public frontend, keeps the reconcile state fresh, and never touches admin
 * queries or the admin REST SPA.
 */
final class OverflowGate {

	private const RECONCILE_THROTTLE = 'mhm_rentiva_overflow_reconciled';

	public static function register(): void {
		add_action( 'pre_get_posts', array( self::class, 'exclude_hidden_from_frontend' ) );

		// State-driven reconcile (no Pro->Lite transition hook exists; isActive()
		// is computed live). Throttled so it is cheap on every admin request.
		add_action( 'admin_init', array( self::class, 'maybe_reconcile' ) );
		OverflowAdminBadge::register();
	}

	public static function exclude_hidden_from_frontend( \WP_Query $q ): void {
		// admin-ajax.php sets is_admin()=true even for front-end (nopriv) AJAX,
		// so gate on a REAL admin screen request, not on AJAX. REST stays exempt
		// (admin React SPA). This keeps wp-admin list tables unfiltered while
		// still filtering front-end AJAX catalog queries.
		if ( ( is_admin() && ! wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$post_type = $q->get( 'post_type' );
		$types     = is_array( $post_type ) ? $post_type : array( $post_type );
		$hidden    = array();
		if ( in_array( 'vehicle', $types, true ) ) {
			$hidden = array_merge( $hidden, OverflowRegistry::get( 'vehicle' ) );
		}
		if ( in_array( 'vehicle_addon', $types, true ) ) {
			$hidden = array_merge( $hidden, OverflowRegistry::get( 'vehicle_addon' ) );
		}
		if ( empty( $hidden ) ) {
			return;
		}

		$existing = (array) $q->get( 'post__not_in' );
		$q->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $hidden ) ) ) );
	}

	public static function maybe_reconcile(): void {
		if ( get_transient( self::RECONCILE_THROTTLE ) ) {
			return;
		}
		set_transient( self::RECONCILE_THROTTLE, 1, 15 * MINUTE_IN_SECONDS );
		OverflowReconciler::reconcile();
	}
}
