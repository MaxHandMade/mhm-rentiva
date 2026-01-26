<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\PostType;

use MHMRentiva\Admin\Core\PostTypes\AbstractPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Booking extends AbstractPostType {

	protected static function get_post_type(): string {
		return 'vehicle_booking';
	}

	protected static function get_singular_name(): string {
		return __( 'Booking', 'mhm-rentiva' );
	}

	protected static function get_plural_name(): string {
		return __( 'Bookings', 'mhm-rentiva' );
	}

	protected static function get_menu_icon(): string {
		return 'dashicons-calendar-alt';
	}

	protected static function get_custom_args(): array {
		return array_merge(
			self::get_admin_only_args(),
			self::get_capabilities_args(),
			array(
				'show_in_menu' => false, // Manual menu defined in Menu.php, automatic menu removed
			)
		);
	}
}
