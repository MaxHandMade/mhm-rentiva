<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The booking edit screen's box order (canvas Yon-Rezervasyon-Duzenle v135).
 *
 * Core's do_meta_boxes() reads get_user_option( "meta-box-order_$page" ) and
 * moves every listed id into the 'sorted' bucket in list order
 * (wp-admin/includes/template.php). This fills that option only while the
 * user has none: someone who dragged the boxes keeps their own order. Ids
 * that are not on a screen (the new-booking screen shares this page id) are
 * skipped by core.
 */
final class BookingEditLayout {

	private const ORDER = array(
		'side'   => 'submitdiv,mhmrentiva_booking_customer_account,mhmrentiva_receipt_box,mhmrentiva_booking_refund,mhmrentiva_customer_email_box',
		'normal' => 'mhmrentiva_booking_edit,mhmrentiva_booking_deposit',
	);

	public static function register(): void {
		add_filter('get_user_option_meta-box-order_mhmrentiva_booking', array( self::class, 'default_order' ));
	}

	/**
	 * @param mixed $value The user's saved order, false/empty when there is none.
	 * @return mixed
	 */
	public static function default_order($value) {
		return empty($value) ? self::ORDER : $value;
	}
}
