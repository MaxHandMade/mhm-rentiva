<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Logs;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostType {

	public const TYPE = 'mhmrentiva_app_log';

	public static function register(): void {
		add_action( 'init', array( self::class, 'cpt' ) );
	}

	public static function cpt(): void {
		$labels = array(
			'name'               => __( 'Logs', 'mhm-rentiva' ),
			'singular_name'      => __( 'Log', 'mhm-rentiva' ),
			'menu_name'          => __( 'Logs', 'mhm-rentiva' ),
			'add_new'            => __( 'Add New', 'mhm-rentiva' ),
			'add_new_item'       => __( 'Add New Log', 'mhm-rentiva' ),
			'edit_item'          => __( 'Edit Log', 'mhm-rentiva' ),
			'new_item'           => __( 'New Log', 'mhm-rentiva' ),
			'view_item'          => __( 'View Log', 'mhm-rentiva' ),
			'search_items'       => __( 'Search Logs', 'mhm-rentiva' ),
			'not_found'          => __( 'No logs found.', 'mhm-rentiva' ),
			'not_found_in_trash' => __( 'No logs found in Trash.', 'mhm-rentiva' ),
		);

		register_post_type(
			self::TYPE,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false, // Manually added in Menu.php
				'supports'        => array( 'title', 'editor' ),
				// Same reasoning as the contact type: `edit.php` gates on the POST
				// TYPE's `edit_posts`, and inheriting `post` let an author or
				// contributor reach this screen by URL. Every row here carries the
				// IP address and browser user-agent of a logged request.
				'capability_type' => array( 'mhmrentiva_log', 'mhmrentiva_logs' ),
				'capabilities'    => array(
					'create_posts'           => 'do_not_allow',
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'edit_published_posts'   => 'manage_options',
					'read_private_posts'     => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'publish_posts'          => 'manage_options',
				),
				'map_meta_cap'    => true,
				'menu_position'   => null,
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
				// See the note on the contact type: Tools -> Export gates on the
				// `export` capability alone, and these rows carry the IP address and
				// user-agent of every logged request.
				'can_export'      => false,
			)
		);
	}
}
