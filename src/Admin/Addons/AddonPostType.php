<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Post Type Class.
 *
 * @package MHMRentiva\Admin\Addons
 */





use MHMRentiva\Admin\Core\PostTypes\AbstractPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and manages the additional service post type.
 */
final class AddonPostType extends AbstractPostType {


	public const POST_TYPE = 'vehicle_addon';

	/**
	 * Get post type name.
	 *
	 * @return string Post type.
	 */
	protected static function get_post_type(): string {
		return self::POST_TYPE;
	}

	/**
	 * Get singular name.
	 *
	 * @return string Singular name.
	 */
	protected static function get_singular_name(): string {
		return __( 'Additional Service', 'mhm-rentiva' );
	}

	/**
	 * Get plural name.
	 *
	 * @return string Plural name.
	 */
	protected static function get_plural_name(): string {
		return __( 'Additional Services', 'mhm-rentiva' );
	}

	/**
	 * Get menu icon.
	 *
	 * @return string Dashicon name.
	 */
	protected static function get_menu_icon(): string {
		return 'dashicons-plus-alt';
	}

	/**
	 * Get custom registration arguments.
	 *
	 * @return array Arguments.
	 */
	protected static function get_custom_args(): array {
		return array_merge(
			self::get_admin_only_args(),
			array(
				'supports' => self::get_supports_array( array( 'editor', 'excerpt', 'thumbnail', 'page-attributes' ) ),
			)
		);
	}

	/**
	 * Register taxonomies for this post type.
	 */
	public static function register_taxonomies(): void {
		// Addon categories taxonomy (optional).
		$labels = self::get_taxonomy_labels( __( 'Addon Category', 'mhm-rentiva' ), __( 'Addon Categories', 'mhm-rentiva' ) );
		$args   = array_merge(
			self::get_default_taxonomy_args(),
			array( 'labels' => $labels )
		);

		register_taxonomy( 'addon_category', array( self::POST_TYPE ), $args );
	}
}
