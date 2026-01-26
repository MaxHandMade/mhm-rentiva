<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\PostType;

use MHMRentiva\Admin\Core\PostTypes\AbstractPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Vehicle extends AbstractPostType {

	public const POST_TYPE = 'vehicle';

	protected static function get_post_type(): string {
		return self::POST_TYPE;
	}

	protected static function get_singular_name(): string {
		return __( 'Vehicle', 'mhm-rentiva' );
	}

	protected static function get_plural_name(): string {
		return __( 'Vehicles', 'mhm-rentiva' );
	}

	protected static function get_menu_icon(): string {
		return 'dashicons-car';
	}

	protected static function get_custom_args(): array {
		return array(
			'public'              => true,
			'publicly_queryable'  => true,
			'show_in_menu'        => false,
			'exclude_from_search' => false,
			'query_var'           => 'vehicle',
			'has_archive'         => false,
			'supports'            => self::get_supports_array( array( 'editor', 'thumbnail', 'excerpt', 'comments' ) ),
			'rewrite'             => false,
			'map_meta_cap'        => true,
		);
	}
}
