<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\ShortcodePages\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;

/**
 * REST endpoints for the ShortcodePages admin page.
 *
 * Routes:
 *   GET    /mhm-rentiva/v1/shortcode-pages                       → list all shortcodes with page status
 *   POST   /mhm-rentiva/v1/shortcode-pages/{slug}/create         → create WP page for slug
 *   DELETE /mhm-rentiva/v1/shortcode-pages/{slug}                → trash WP page for slug
 *   POST   /mhm-rentiva/v1/shortcode-pages/clear-cache           → clear ShortcodeUrlManager cache
 *   GET    /mhm-rentiva/v1/shortcode-pages/debug                 → scan pages for shortcode usage
 *   POST   /mhm-rentiva/v1/shortcode-pages/reset                 → delete all auto-created pages
 *
 * Slug regex (?P<slug>[a-z]+_[a-z_]+) requires at least one underscore,
 * which prevents collision with fixed routes (debug, reset, clear-cache).
 */
final class ShortcodePagesController {

	private const REST_NAMESPACE = 'mhm-rentiva/v1';
	private const BASE           = '/shortcode-pages';
	private const SLUG_REGEX     = '(?P<slug>[a-z]+_[a-z_]+)';

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		$ns   = self::REST_NAMESPACE;
		$base = self::BASE;
		$perm = array( self::class, 'check_permission' );

		register_rest_route(
			$ns,
			$base,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_list' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			$ns,
			$base . '/clear-cache',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'clear_cache' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			$ns,
			$base . '/debug',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'debug_search' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			$ns,
			$base . '/reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'reset_all' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			$ns,
			$base . '/' . self::SLUG_REGEX . '/create',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'create_page' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			$ns,
			$base . '/' . self::SLUG_REGEX,
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'delete_page' ),
				'permission_callback' => $perm,
			)
		);
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function get_list( \WP_REST_Request $request ): \WP_REST_Response {
		$actions      = new ShortcodePageActions();
		$config       = $actions->get_config(); // ksorted
		$shortcodes   = array();
		$active_count = 0;

		foreach ( $config as $slug => $info ) {
			$page_id = ShortcodeUrlManager::get_page_id( $slug );
			$status  = null !== $page_id ? 'active' : 'missing';

			if ( 'active' === $status ) {
				++$active_count;
			}

			$shortcodes[] = array(
				'slug'        => $slug,
				'label'       => $info['title'],
				'description' => $info['description'],
				'page_id'     => $page_id,
				'page_title'  => $page_id ? get_the_title( $page_id ) : null,
				'page_url'    => $page_id ? get_permalink( $page_id ) : null,
				'edit_url'    => $page_id ? admin_url( "post.php?post={$page_id}&action=edit" ) : null,
				'status'      => $status,
			);
		}

		$total = count( $shortcodes );

		return new \WP_REST_Response(
			array(
				'shortcodes' => $shortcodes,
				'stats'      => array(
					'total'   => $total,
					'active'  => $active_count,
					'missing' => $total - $active_count,
				),
			)
		);
	}

	public static function create_page( \WP_REST_Request $request ): \WP_REST_Response {
		$slug    = (string) $request->get_param( 'slug' );
		$actions = new ShortcodePageActions();
		$config  = $actions->get_config();

		if ( ! isset( $config[ $slug ] ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'invalid_slug',
					'message' => 'Unknown shortcode slug.',
				),
				400
			);
		}

		if ( null !== ShortcodeUrlManager::get_page_id( $slug ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'page_exists',
					'message' => 'Page already exists for this shortcode.',
				),
				409
			);
		}

		$page_id = $actions->create_page( $slug );

		if ( ! $page_id ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'create_failed',
					'message' => 'Failed to create page.',
				),
				500
			);
		}

		ShortcodeUrlManager::clear_cache();

		return new \WP_REST_Response(
			array(
				'page_id'    => $page_id,
				'page_title' => get_the_title( $page_id ),
				'page_url'   => get_permalink( $page_id ),
				'edit_url'   => admin_url( "post.php?post={$page_id}&action=edit" ),
				'status'     => 'active',
			)
		);
	}

	public static function delete_page( \WP_REST_Request $request ): \WP_REST_Response {
		$slug    = (string) $request->get_param( 'slug' );
		$actions = new ShortcodePageActions();
		$config  = $actions->get_config();

		if ( ! isset( $config[ $slug ] ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'invalid_slug',
					'message' => 'Unknown shortcode slug.',
				),
				400
			);
		}

		$page_id = ShortcodeUrlManager::get_page_id( $slug );
		if ( null === $page_id ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'page_not_found',
					'message' => 'No page found for this shortcode.',
				),
				404
			);
		}

		if ( ! $actions->delete_page( $page_id ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'delete_failed',
					'message' => 'Failed to delete page.',
				),
				500
			);
		}

		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	public static function clear_cache( \WP_REST_Request $request ): \WP_REST_Response {
		ShortcodeUrlManager::clear_cache();
		return new \WP_REST_Response( array( 'cleared' => true ) );
	}

	public static function debug_search( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( ShortcodePageActions::debug_search() );
	}

	public static function reset_all( \WP_REST_Request $request ): \WP_REST_Response {
		$actions       = new ShortcodePageActions();
		$deleted_count = $actions->reset_pages();
		return new \WP_REST_Response( array( 'deleted_count' => $deleted_count ) );
	}
}
