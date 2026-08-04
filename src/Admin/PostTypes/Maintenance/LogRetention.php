<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (!defined('ABSPATH')) {
    exit;
}


use MHMRentiva\Admin\PostTypes\Logs\PostType;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogRetention {

	public const EVENT = 'mhmrentiva_log_purge_event';

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
		// Both values live in the `mhmrentiva_settings` array, which is where the
		// settings screen writes them. This read used to go to a STANDALONE option
		// of the same name that nothing has ever written, so it always fell back to
		// thirty days and never consulted the toggle -- and `purge()` force-deletes,
		// so an administrator who switched cleanup off, or raised retention to keep
		// an audit trail, still lost everything older than thirty days, daily, while
		// the screen showed their setting intact. The sibling cron and the e-mail
		// log purge both read through SettingsCore already.
		if ( ! \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_log_cleanup_enabled', '1' ) ) {
			return;
		}

		$days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_log_retention_days', 30 );
		if ( $days <= 0 ) {
			return; // keep forever, do nothing
		}
		// Limit per run (filterable)
		$limit   = (int) apply_filters( 'mhmrentiva_log_purge_limit', 200 );
		$deleted = self::purge( $days, $limit );
		do_action( 'mhmrentiva_logs_purged', $deleted, $days );
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
