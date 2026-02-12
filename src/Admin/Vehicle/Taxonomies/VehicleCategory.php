<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VehicleCategory {



	public const TAXONOMY = 'vehicle_category';

	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_now' ) );
	}

	public static function register_now(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( \MHMRentiva\Admin\Vehicle\PostType\Vehicle::POST_TYPE ),
			array(
				'labels'                => self::labels(),
				'public'                => true,
				'show_ui'               => true,
				'show_in_rest'          => true,
				'hierarchical'          => true,
				'rewrite'               => array( 'slug' => 'vehicle-category' ),
				'show_admin_column'     => true,
				'show_in_nav_menus'     => true,
				'show_tagcloud'         => false,
				'meta_box_cb'           => 'post_categories_meta_box',
				'rest_base'             => 'vehicle-categories',
				'rest_controller_class' => 'WP_REST_Terms_Controller',
			)
		);
	}

	private static function labels(): array {
		return array(
			'name'                       => __( 'Vehicle Categories', 'mhm-rentiva' ),
			'singular_name'              => __( 'Vehicle Category', 'mhm-rentiva' ),
			'menu_name'                  => __( 'Categories', 'mhm-rentiva' ),
			'all_items'                  => __( 'All Categories', 'mhm-rentiva' ),
			'edit_item'                  => __( 'Edit Category', 'mhm-rentiva' ),
			'view_item'                  => __( 'View Category', 'mhm-rentiva' ),
			'update_item'                => __( 'Update Category', 'mhm-rentiva' ),
			'add_new_item'               => __( 'Add New Category', 'mhm-rentiva' ),
			'new_item_name'              => __( 'New Category Name', 'mhm-rentiva' ),
			'parent_item'                => __( 'Parent Category', 'mhm-rentiva' ),
			'parent_item_colon'          => __( 'Parent Category:', 'mhm-rentiva' ),
			'search_items'               => __( 'Search Categories', 'mhm-rentiva' ),
			'popular_items'              => __( 'Popular Categories', 'mhm-rentiva' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'mhm-rentiva' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'mhm-rentiva' ),
			'choose_from_most_used'      => __( 'Choose from most used', 'mhm-rentiva' ),
			'not_found'                  => __( 'No categories found', 'mhm-rentiva' ),
			'no_terms'                   => __( 'No categories', 'mhm-rentiva' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'mhm-rentiva' ),
			'items_list'                 => __( 'Categories list', 'mhm-rentiva' ),
		);
	}


	/**
	 * Add custom meta fields support
	 */
	public static function add_meta_support(): void {
		add_action(
			'init',
			function () {
				// Security: Check if function exists and user has permission
				if ( ! function_exists( 'register_meta' ) || ! current_user_can( 'manage_categories' ) ) {
					return;
				}

				try {
					register_meta(
						'term',
						'category_color',
						array(
							'type'              => 'string',
							'description'       => __( 'Category color for visual identification', 'mhm-rentiva' ),
							'single'            => true,
							'show_in_rest'      => true,
							'sanitize_callback' => 'sanitize_hex_color',
						)
					);

					register_meta(
						'term',
						'category_icon',
						array(
							'type'              => 'string',
							'description'       => __( 'Category icon class or URL', 'mhm-rentiva' ),
							'single'            => true,
							'show_in_rest'      => true,
							'sanitize_callback' => 'sanitize_text_field',
						)
					);
				} catch ( \Exception $e ) {
					// Log error but don't break the site
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error( 'Failed to register taxonomy meta fields - ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * Add admin filters and columns
	 */
	public static function add_admin_features(): void {
		add_action( 'restrict_manage_posts', array( self::class, 'add_category_filter' ) );
		add_filter( 'manage_edit-vehicle_columns', array( self::class, 'add_category_column' ) );
		add_action( 'manage_vehicle_posts_custom_column', array( self::class, 'display_category_column' ), 10, 2 );

		// Security: Add nonce field for admin actions
		add_action( 'admin_init', array( self::class, 'add_admin_nonce' ) );
	}

	/**
	 * Add nonce field for admin security
	 */
	public static function add_admin_nonce(): void {
		if ( current_user_can( 'edit_posts' ) ) {
			wp_nonce_field( 'mhm_vehicle_category_admin', 'mhm_vehicle_category_nonce' );
		}
	}

	/**
	 * Add category filter dropdown
	 */
	public static function add_category_filter(): void {
		global $typenow;

		// Security: Check user capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( $typenow === 'vehicle' ) {
			$taxonomy = get_taxonomy( self::TAXONOMY );
			if ( $taxonomy ) {
				// Security: Sanitize selected value
				$selected = get_query_var( self::TAXONOMY );
				$selected = $selected ? self::sanitize_text_field_safe( $selected ) : 0;

				wp_dropdown_categories(
					array(
						/* translators: %1$s placeholder. */
						'show_option_all' => sprintf( __( 'All %1$s', 'mhm-rentiva' ), $taxonomy->labels->name ),
						'taxonomy'        => self::TAXONOMY,
						'name'            => self::TAXONOMY,
						'orderby'         => 'name',
						'selected'        => $selected,
						'show_count'      => true,
						'hide_empty'      => false,
					)
				);
			}
		}
	}

	/**
	 * Add category column to vehicle list
	 */
	public static function add_category_column( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( $key === 'title' ) {
				$new_columns[ self::TAXONOMY ] = __( 'Categories', 'mhm-rentiva' );
			}
		}
		return $new_columns;
	}

	/**
	 * Display category column content
	 */
	public static function display_category_column( string $column, int $post_id ): void {
		// Security: Check user capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( $column === self::TAXONOMY ) {
			// Security: Validate post ID
			if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
				echo '<span class="na">' . esc_html__( 'Invalid post', 'mhm-rentiva' ) . '</span>';
				return;
			}

			$terms = get_the_terms( $post_id, self::TAXONOMY );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$term_links = array();
				foreach ( $terms as $term ) {
					// Security: Validate term object
					if ( ! is_object( $term ) || ! isset( $term->slug, $term->name ) ) {
						continue;
					}

					$term_links[] = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( add_query_arg( self::TAXONOMY, self::sanitize_text_field_safe( $term->slug ), admin_url( 'edit.php?post_type=vehicle' ) ) ),
						esc_html( $term->name )
					);
				}
				echo wp_kses_post( implode( ', ', $term_links ) );
			} else {
				echo '<span class="na">' . esc_html__( 'No categories', 'mhm-rentiva' ) . '</span>';
			}
		}
	}
}
