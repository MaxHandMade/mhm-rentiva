<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract PostType Base Class
 *
 * Central base class for WordPress PostType classes.
 * Eliminates common functions and structural repetition.
 *
 * @abstract
 */
abstract class AbstractPostType {


	/**
	 * Abstract methods - Must be implemented in subclasses
	 */
	abstract protected static function get_post_type(): string;
	abstract protected static function get_singular_name(): string;
	abstract protected static function get_plural_name(): string;
	abstract protected static function get_menu_icon(): string;

	/**
	 * Register post type
	 */
	public static function register(): void {
		add_action( 'init', array( static::class, 'register_post_type' ) );
		add_filter( 'enter_title_here', array( static::class, 'change_title_placeholder' ), 10, 2 );
		add_filter( 'post_updated_messages', array( static::class, 'post_updated_messages' ) );

		// Taxonomy registration (if any)
		if ( method_exists( static::class, 'register_taxonomies' ) ) {
			add_action( 'init', array( static::class, 'register_taxonomies' ) );
		}
	}

	/**
	 * Save post type
	 */
	public static function register_post_type(): void {
		$args = array_merge(
			static::get_default_args(),
			static::get_custom_args()
		);

		register_post_type( static::get_post_type(), $args );
	}

	/**
	 * Default args
	 */
	protected static function get_default_args(): array {
		return array(
			'labels'          => static::get_labels(),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false, // It will be displayed in the submenu
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'hierarchical'    => false,
			'menu_position'   => null,
			'menu_icon'       => static::get_menu_icon(),
		);
	}

	/**
	 * Custom args (can be overridden)
	 */
	protected static function get_custom_args(): array {
		return array();
	}

	/**
	 * Create Labels
	 */
	protected static function get_labels(): array {
		$singular = static::get_singular_name();
		$plural   = static::get_plural_name();

		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			'add_new'               => __( 'Add New', 'mhm-rentiva' ),
			/* translators: %s placeholder. */
			'add_new_item'          => sprintf( __( 'Add New %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'edit_item'             => sprintf( __( 'Edit %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'new_item'              => sprintf( __( 'New %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'view_item'             => sprintf( __( 'View %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'search_items'          => sprintf( __( 'Search %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'not_found'             => sprintf( __( 'No %s found', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'not_found_in_trash'    => sprintf( __( 'No %s found in Trash', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'all_items'             => sprintf( __( 'All %s', 'mhm-rentiva' ), $plural ),
			/* translators: %s placeholder. */
			'archives'              => sprintf( __( '%s Archive', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'attributes'            => sprintf( __( '%s Attributes', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'insert_into_item'      => sprintf( __( 'Insert into %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'uploaded_to_this_item' => sprintf( __( 'Uploaded to this %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'featured_image'        => sprintf( __( '%s Image', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'set_featured_image'    => sprintf( __( 'Set %s image', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'remove_featured_image' => sprintf( __( 'Remove %s image', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'use_featured_image'    => sprintf( __( 'Use as %s image', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'filter_items_list'     => sprintf( __( 'Filter %s list', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'items_list_navigation' => sprintf( __( '%s list navigation', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'items_list'            => sprintf( __( '%s list', 'mhm-rentiva' ), $singular ),
		);
	}

	/**
	 * Change title placeholder
	 */
	public static function change_title_placeholder( string $title, \WP_Post $post ): string {
		if ( $post->post_type === static::get_post_type() ) {
			/* translators: %s placeholder. */
			return sprintf( __( 'Enter %s name...', 'mhm-rentiva' ), static::get_singular_name() );
		}
		return $title;
	}

	/**
	 * Post updated messages
	 */
	public static function post_updated_messages( array $messages ): array {
		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		if ( $post_type === static::get_post_type() ) {
			$singular = static::get_singular_name();

			$revision_id = 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core-managed revision query arg in edit screen context.
			if ( isset( $_GET['revision'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only revision id used for admin message text.
				$revision_id = (int) sanitize_text_field( wp_unslash( $_GET['revision'] ) );
			}

			$messages[ static::get_post_type() ] = array(
				0  => '', // Unused. Messages start at index 1.
				/* translators: %s: Post type singular name */
				1  => sprintf( __( '%s updated.', 'mhm-rentiva' ), $singular ),
				2  => __( 'Custom field updated.', 'mhm-rentiva' ),
				3  => __( 'Custom field deleted.', 'mhm-rentiva' ),
				/* translators: %s: Post type singular name */
				4  => sprintf( __( '%s updated.', 'mhm-rentiva' ), $singular ),
				/* translators: 1: Post type singular name, 2: Revision date and time */
				5  => $revision_id > 0 ? sprintf( __( '%1$s restored to revision from %2$s.', 'mhm-rentiva' ), $singular, wp_post_revision_title( $revision_id, false ) ) : false,
				/* translators: %s: Post type singular name */
				6  => sprintf( __( '%s published.', 'mhm-rentiva' ), $singular ),
				/* translators: %s: Post type singular name */
				7  => sprintf( __( '%s saved as draft.', 'mhm-rentiva' ), $singular ),
				/* translators: %s: Post type singular name */
				8  => sprintf( __( '%s submitted.', 'mhm-rentiva' ), $singular ),
				9  => sprintf(
					/* translators: 1: Post type singular name, 2: Scheduled date */
					__( '%1$s scheduled for: %2$s.', 'mhm-rentiva' ),
					$singular,
					/* translators: %s: date format. */
					'<strong>' . wp_date( __( 'M j, Y @ G:i', 'mhm-rentiva' ), strtotime( $post->post_date ) ) . '</strong>'
				),
				/* translators: %s: Post type singular name */
				10 => sprintf( __( '%s draft updated.', 'mhm-rentiva' ), $singular ),
			);
		}

		return $messages;
	}

	/**
	 * Get post type args (for backward compatibility)
	 */
	public static function get_post_type_args(): array {
		return array_merge(
			static::get_default_args(),
			static::get_custom_args()
		);
	}

	/**
	 * Create taxonomy labels (can be overridden)
	 */
	protected static function get_taxonomy_labels( string $singular, string $plural ): array {
		return array(
			'name'              => $plural,
			'singular_name'     => $singular,
			/* translators: %s placeholder. */
			'search_items'      => sprintf( __( 'Search %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'all_items'         => sprintf( __( 'All %s', 'mhm-rentiva' ), $plural ),
			/* translators: %s placeholder. */
			'parent_item'       => sprintf( __( 'Parent %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s placeholder. */
			'parent_item_colon' => sprintf( __( 'Parent %s:', 'mhm-rentiva' ), $singular ),
			/* translators: %s: Post type singular name */
			'edit_item'         => sprintf( __( 'Edit %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s: Post type singular name */
			'update_item'       => sprintf( __( 'Update %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s: Post type singular name */
			'add_new_item'      => sprintf( __( 'Add New %s', 'mhm-rentiva' ), $singular ),
			/* translators: %s: Post type singular name */
			'new_item_name'     => sprintf( __( 'New %s Name', 'mhm-rentiva' ), $singular ),
			'menu_name'         => $plural,
		);
	}

	/**
	 * Default taxonomy args
	 */
	protected static function get_default_taxonomy_args(): array {
		return array(
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => false,
			'public'            => false,
			'show_in_rest'      => false,
		);
	}

	/**
	 * Helper: Args for public post type
	 */
	protected static function get_public_args(): array {
		return array(
			'public'              => true,
			'publicly_queryable'  => true,
			'show_in_menu'        => false,
			'exclude_from_search' => false,
			'query_var'           => true,
			'rewrite'             => array( 'slug' => static::get_post_type() ),
			'has_archive'         => true,
		);
	}

	/**
	 * Helper: Args for admin only post type
	 */
	protected static function get_admin_only_args(): array {
		return array(
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false, // Set to false for WordPress menu issue
			'query_var'          => false,
			'rewrite'            => false,
			'has_archive'        => false,
		);
	}

	/**
	 * Helper: Capabilities args
	 */
	protected static function get_capabilities_args(): array {
		return array(
			'capabilities' => array(
				'create_posts'        => 'manage_options',
				'edit_posts'          => 'manage_options',
				'edit_others_posts'   => 'manage_options',
				'delete_posts'        => 'manage_options',
				'delete_others_posts' => 'manage_options',
				'read_private_posts'  => 'manage_options',
				'edit_post'           => 'manage_options',
				'delete_post'         => 'manage_options',
				'read_post'           => 'manage_options',
			),
		);
	}

	/**
	 * Helper: Supports array
	 */
	protected static function get_supports_array( array $supports = array() ): array {
		$default_supports = array( 'title' );
		return array_merge( $default_supports, $supports );
	}
}
