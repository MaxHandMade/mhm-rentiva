<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing\LiteOverflow;

use MHMRentiva\Admin\Licensing\Mode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded reconcile query over the custom routes table.

/**
 * Idempotent decision of which catalog items are hidden in Lite. Pro clears
 * the registry (full restore); Lite keeps the oldest `limit` publicly-visible
 * items per type and hides the newest excess.
 */
final class OverflowReconciler {

	/**
	 * Pure rule: IDs ordered oldest-first, keep the first $limit, hide the rest.
	 *
	 * @param int[] $orderedIds Oldest-first.
	 * @return int[] Hidden (newest-excess) IDs.
	 */
	public static function computeHidden( array $orderedIds, int $limit ): array {
		if ( $limit <= 0 ) {
			return array_values( array_map( 'intval', $orderedIds ) );
		}
		return array_values( array_map( 'intval', array_slice( $orderedIds, $limit ) ) );
	}

	/**
	 * @param bool|null $isProOverride Null in production (uses Mode::isPro()).
	 * @return array<string,int[]> The written hidden map.
	 */
	public static function reconcile( ?bool $isProOverride = null ): array {
		$is_pro = ( null === $isProOverride ) ? Mode::isPro() : $isProOverride;

		if ( $is_pro ) {
			OverflowRegistry::clearAll();
			return OverflowRegistry::all();
		}

		$caps = array(
			'vehicle'       => (int) apply_filters( 'mhm_rentiva_lite_max_vehicles', 5 ),
			'vehicle_addon' => (int) apply_filters( 'mhm_rentiva_lite_max_addons', 4 ),
			'route'         => (int) apply_filters( 'mhm_rentiva_lite_max_transfer_routes', 3 ),
		);

		OverflowRegistry::set( 'vehicle', self::computeHidden( self::visible_post_ids( 'vehicle' ), $caps['vehicle'] ) );
		OverflowRegistry::set( 'vehicle_addon', self::computeHidden( self::visible_post_ids( 'vehicle_addon' ), $caps['vehicle_addon'] ) );
		OverflowRegistry::set( 'route', self::computeHidden( self::visible_route_ids(), $caps['route'] ) );

		return OverflowRegistry::all();
	}

	/** @return int[] Published post IDs, oldest-first. */
	private static function visible_post_ids( string $post_type ): array {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/** @return int[] Route IDs, oldest-first. */
	private static function visible_route_ids(): array {
		global $wpdb;
		$table = self::routes_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier resolved from known prefix; no user input.
		$ids = $wpdb->get_col( "SELECT id FROM `{$table}` ORDER BY created_at ASC, id ASC" );
		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	private static function routes_table(): string {
		global $wpdb;
		$new = $wpdb->prefix . 'rentiva_transfer_routes';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) );
		return ( $exists === $new ) ? $new : $wpdb->prefix . 'mhm_rentiva_transfer_routes';
	}
}
