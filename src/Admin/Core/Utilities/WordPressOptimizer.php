<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress general performance optimization
 */
final class WordPressOptimizer {

	public static function register(): void {
		// PERFORMANCE: Disable unnecessary WordPress features
		add_action( 'init', array( self::class, 'disable_unnecessary_features' ), 1 );

		add_action( 'admin_enqueue_scripts', array( self::class, 'disable_heartbeat' ), 1 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'remove_unnecessary_admin_scripts' ), 999 );
		add_action( 'admin_notices', array( self::class, 'limit_admin_notices' ), 1 );
	}

	/**
	 * Disable unnecessary WordPress features
	 */
	public static function disable_unnecessary_features(): void {
		// Gutenberg editor active (on all post types)
		// add_filter('use_block_editor_for_post_type', '__return_true', 10, 2);

		// Prevent REST API errors (disabled because required for Gutenberg)
		// add_filter('rest_pre_dispatch', [self::class, 'filter_rest_requests'], 10, 3);

		// Disable unnecessary WordPress emojis
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		// Disable unnecessary WordPress embeds
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );

		// Disable unnecessary WordPress feeds
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );

		// Disable unnecessary WordPress meta tags
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'start_post_rel_link' );
		remove_action( 'wp_head', 'index_rel_link' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link' );

		// Disable unnecessary WordPress pingbacks
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

		// Disable unnecessary WordPress XML-RPC
		add_filter( 'xmlrpc_enabled', '__return_false' );

		// Disable unnecessary WordPress pings
		remove_action( 'do_pings', 'do_all_pings' );
		remove_action( 'publish_post', '_publish_post_hook', 5 );
	}

	/**
	 * Optimize admin page performance
	 */
	public static function optimize_admin_performance(): void {
		// Disable unnecessary admin scripts
		wp_dequeue_script( 'heartbeat' );
		wp_dequeue_script( 'autosave' );
		wp_dequeue_script( 'wp-pointer' );
		wp_dequeue_script( 'thickbox' );
		wp_dequeue_script( 'media-upload' );
		wp_dequeue_script( 'jquery-ui-tabs' );
		wp_dequeue_script( 'jquery-ui-dialog' );
		wp_dequeue_script( 'jquery-ui-widget' );
		wp_dequeue_script( 'jquery-ui-mouse' );
		wp_dequeue_script( 'jquery-ui-draggable' );
		wp_dequeue_script( 'jquery-ui-droppable' );
		wp_dequeue_script( 'jquery-ui-sortable' );
		wp_dequeue_script( 'jquery-ui-resizable' );
		wp_dequeue_script( 'jquery-ui-selectable' );

		// Disable unnecessary admin CSS
		wp_dequeue_style( 'thickbox' );
		wp_dequeue_style( 'wp-pointer' );
		wp_dequeue_style( 'media-upload' );
		wp_dequeue_style( 'jquery-ui-tabs' );
		wp_dequeue_style( 'jquery-ui-dialog' );
		wp_dequeue_style( 'jquery-ui-widget' );
		wp_dequeue_style( 'jquery-ui-mouse' );
		wp_dequeue_style( 'jquery-ui-draggable' );
		wp_dequeue_style( 'jquery-ui-droppable' );
		wp_dequeue_style( 'jquery-ui-sortable' );
		wp_dequeue_style( 'jquery-ui-resizable' );
		wp_dequeue_style( 'jquery-ui-selectable' );

		// Disable unnecessary WordPress admin bar
		if ( ! current_user_can( 'administrator' ) ) {
			show_admin_bar( false );
		}

		// Limit unnecessary WordPress admin notices
		add_action( 'admin_notices', array( self::class, 'limit_admin_notices' ), 1 );
	}

	/**
	 * Optimize database queries
	 */
	public static function optimize_database_queries( array $clauses, \WP_Query $query ): array {
		// Limit unnecessary meta queries
		if ( isset( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
			// Limit the number of meta queries
			if ( count( $query->query_vars['meta_query'] ) > 5 ) {
				$query->query_vars['meta_query'] = array_slice( $query->query_vars['meta_query'], 0, 5 );
			}
		}

		// Prevent unnecessary JOINs
		if ( strpos( $clauses['join'], 'LEFT JOIN' ) !== false ) {
			// Clean up unnecessary JOINs
			$clauses['join'] = preg_replace( '/LEFT JOIN.*?ON.*?(?=LEFT JOIN|$)/s', '', $clauses['join'] );
		}

		return $clauses;
	}

	/**
	 * Clean up unnecessary cron jobs
	 */
	public static function cleanup_unnecessary_cron_jobs(): void {
		// Clean up unnecessary WordPress cron jobs
		wp_clear_scheduled_hook( 'wp_scheduled_delete' );
		wp_clear_scheduled_hook( 'wp_scheduled_auto_draft_delete' );
		wp_clear_scheduled_hook( 'wp_scheduled_revision_delete' );

		// Clean up unnecessary WordPress pings
		wp_clear_scheduled_hook( 'do_pings' );

		// Clean up unnecessary WordPress updates
		wp_clear_scheduled_hook( 'wp_update_plugins' );
		wp_clear_scheduled_hook( 'wp_update_themes' );
		wp_clear_scheduled_hook( 'wp_update_core' );

		// Clean up unnecessary WordPress comments
		wp_clear_scheduled_hook( 'wp_scheduled_comment_cleanup' );
	}

	/**
	 * Optimize memory usage
	 */
	public static function optimize_memory_usage(): void {
		// Increase memory limit
		if ( function_exists( 'ini_set' ) ) {
			ini_set( 'memory_limit', '256M' );
		}

		// Clean up unnecessary WordPress cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clean up unnecessary WordPress transients
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_value = ''" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()" );
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
	 * Remove unnecessary scripts
	 */
	public static function remove_unnecessary_scripts(): void {
		// Remove unnecessary jQuery scripts
		wp_dequeue_script( 'jquery-ui-core' );
		wp_dequeue_script( 'jquery-ui-widget' );
		wp_dequeue_script( 'jquery-ui-mouse' );
		wp_dequeue_script( 'jquery-ui-draggable' );
		wp_dequeue_script( 'jquery-ui-droppable' );
		wp_dequeue_script( 'jquery-ui-sortable' );
		wp_dequeue_script( 'jquery-ui-resizable' );
		wp_dequeue_script( 'jquery-ui-selectable' );

		// Remove unnecessary WordPress scripts
		wp_dequeue_script( 'wp-embed' );
		wp_dequeue_script( 'wp-emoji' );
		wp_dequeue_script( 'comment-reply' );

		// Remove unnecessary WordPress CSS
		wp_dequeue_style( 'wp-embed' );
		wp_dequeue_style( 'wp-emoji' );
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-blocks-style' );
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

	/**
	 * Filter REST API requests and prevent unnecessary errors
	 */
	public static function filter_rest_requests( $result, $server, $request ): mixed {
		$route = $request->get_route();

		// Prevent Gutenberg editor's unnecessary REST API requests
		$blocked_routes = array(
			'/wp/v2/settings',
			'/wp/v2/templates',
			'/wp/v2/global-styles',
			// Don't block Vehicle post type - required for Gutenberg
		);

		foreach ( $blocked_routes as $blocked_route ) {
			if ( strpos( $route, $blocked_route ) === 0 ) {
				// Return empty response instead of 404 error
				return new \WP_REST_Response( array( 'message' => 'Endpoint not available' ), 200 );
			}
		}

		return $result;
	}
}
