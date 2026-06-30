<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing\LiteOverflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks Lite-overflow-hidden catalog posts in the admin list tables with a
 * "Lite limit — hidden" post state, so the operator sees exactly what the
 * public site cannot.
 */
final class OverflowAdminBadge {

	public static function register(): void {
		add_filter( 'display_post_states', array( self::class, 'post_states' ), 10, 2 );
	}

	/**
	 * @param array<string,string> $states
	 * @return array<string,string>
	 */
	public static function post_states( array $states, \WP_Post $post ): array {
		$type = $post->post_type;
		if ( ( 'vehicle' === $type || 'vehicle_addon' === $type )
			&& OverflowRegistry::isHidden( $type, (int) $post->ID )
		) {
			$states['mhm_lite_overflow'] = __( '🔒 Lite limit — hidden', 'mhm-rentiva' );
		}
		return $states;
	}
}
