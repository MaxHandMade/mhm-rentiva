<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Core\CurrencyHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The four add-on figures the KPI band shows.
 *
 * WHY THEY LIVE HERE RATHER THAN ON A SCREEN
 * ------------------------------------------
 * They are read by two surfaces now: the native edit.php band, which stays
 * reachable by URL, and the plugin's own add-ons page. Keeping the arithmetic
 * on one of those screens and copying it to the other is how a plugin ends up
 * telling an operator two different totals for the same thing depending on
 * which screen they opened.
 *
 * Nothing else in the tree computes these -- that was measured before the class
 * was written rather than assumed, so this is a move, not a second definition.
 * If a canonical add-on metric ever appears elsewhere (the way DashboardService
 * owns the booking figures), this is the one place that has to delegate.
 */
final class AddonStats {

	private const CACHE_KEY   = 'mhmrentiva_addon_stats';
	private const CACHE_GROUP = 'mhmrentiva';

	/**
	 * Drop the cached figures.
	 *
	 * Called from every path that can change them. A TTL was deliberately not
	 * used instead: the figures are read right after the operator changes
	 * something, so a band that waits for an expiry shows them their own edit
	 * failing to appear, which reads as a broken screen rather than a stale one.
	 */
	public static function flush(): void {
		wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
	}

	/**
	 * @return array{total_addons:int,active_addons:int,active_percentage:float|int,avg_price:string,total_value:string}
	 */
	public static function get(): array {
		$cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			/** @var array{total_addons:int,active_addons:int,active_percentage:float|int,avg_price:string,total_value:string} $cached */
			return $cached;
		}

		global $wpdb;

		$total_addons = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				AddonPostType::POST_TYPE,
				'publish'
			)
		);

		$active_addons = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = %s
				 AND pm.meta_key = 'mhmrentiva_addon_enabled' AND pm.meta_value = '1'",
				AddonPostType::POST_TYPE,
				'publish'
			)
		);

		$avg_price = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CAST(pm.meta_value AS DECIMAL(10,2)))
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = %s
				 AND pm.meta_key = 'mhmrentiva_addon_price'",
				AddonPostType::POST_TYPE,
				'publish'
			)
		);

		$total_value = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = %s
				 AND pm.meta_key = 'mhmrentiva_addon_price'",
				AddonPostType::POST_TYPE,
				'publish'
			)
		);

		$active_percentage = $total_addons > 0 ? round( ( $active_addons / $total_addons ) * 100 ) : 0;

		$stats = array(
			'total_addons'      => $total_addons,
			'active_addons'     => $active_addons,
			'active_percentage' => $active_percentage,
			'avg_price'         => CurrencyHelper::format_price( $avg_price, 2 ),
			'total_value'       => CurrencyHelper::format_price( $total_value, 2 ),
		);

		wp_cache_set( self::CACHE_KEY, $stats, self::CACHE_GROUP );

		return $stats;
	}
}
