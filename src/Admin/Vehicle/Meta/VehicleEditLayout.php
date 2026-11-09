<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The vehicle edit screen's box order (canvas Yon-Arac-Duzenle v144).
 *
 * Core's do_meta_boxes() reads get_user_option( "meta-box-order_$page" ),
 * moves every listed id into the 'sorted' bucket in list order and draws
 * 'high' before 'sorted' (wp-admin/includes/template.php). This fills that
 * option only while the user has none: someone who dragged the boxes keeps
 * their own order. Ids that are not on a screen are skipped by core.
 *
 * Boxes an extension adds join through mhmrentiva_vehicle_edit_box_order,
 * applied to this default only -- never to a saved preference. Lite names no
 * extension box here.
 */
final class VehicleEditLayout {

	private const ORDER = array(
		'side'   => 'submitdiv,mhmrentiva_vehicle_featured,mhmrentiva_vehicle_categorydiv,postimagediv',
		'normal' => 'mhmrentiva_vehicle_gallery,mhmrentiva_vehicle_details,mhmrentiva_blocked_dates',
	);

	public static function register(): void {
		add_filter('get_user_option_meta-box-order_mhmrentiva_vehicle', array( self::class, 'default_order' ));
	}

	/**
	 * @param mixed $value The user's saved order, false/empty when there is none.
	 * @return mixed
	 */
	public static function default_order($value) {
		if (! empty($value)) {
			return $value;
		}

		/**
		 * The default box order of the vehicle edit screen, for a user who has
		 * not arranged the boxes. Comma-separated meta box ids per column.
		 *
		 * @param array{side: string, normal: string} $order
		 */
		$order = apply_filters('mhmrentiva_vehicle_edit_box_order', self::ORDER);

		return is_array($order) ? $order : self::ORDER;
	}
}
