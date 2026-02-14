<?php declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Public/legacy hook names kept stable for compatibility.

use MHMRentiva\Admin\PostTypes\Logs\PostType;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogRetention {

	public const EVENT = 'mhm_rentiva_log_purge_event';

	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_schedule' ) );
		add_action( self::EVENT, array( self::class, 'run' ) );
	}

	public static function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			// Use WP core daily schedule
			wp_schedule_event( time() + 3600, 'daily', self::EVENT );
		}
	}

	public static function run(): void {
		$days = (int) get_option( 'mhm_rentiva_log_retention_days', 90 );
		if ( $days <= 0 ) {
			return; // keep forever, do nothing
		}
		// Limit per run (filterable)
		$limit   = (int) apply_filters( 'mhm_rentiva_log_purge_limit', 200 );
		$deleted = self::purge( $days, $limit );
		do_action( 'mhm_rentiva_logs_purged', $deleted, $days );
	}

	/**
	 * Purge logs older than X days.
	 *
	 * @param int $days
	 * @param int $limit
	 * @return int number of deleted posts
	 */
	public static function purge( int $days, int $limit = 500 ): int {
		if ( $days <= 0 ) {
			return 0;
		}
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 1000 ) {
			$limit = 1000;
		}

		$thresholdTs = time() - ( $days * 86400 );
		$threshold   = gmdate( 'Y-m-d H:i:s', $thresholdTs );

		$q = new WP_Query(
			array(
				'post_type'      => PostType::TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column'    => 'post_date_gmt',
						'before'    => $threshold,
						'inclusive' => true,
					),
				),
			)
		);

		$count = 0;
		if ( $q->have_posts() ) {
			foreach ( $q->posts as $pid ) {
				wp_delete_post( (int) $pid, true );
				++$count;
			}
		}
		wp_reset_postdata();
		return $count;
	}
}