<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Core\CurrencyHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * "Rezervasyon durumu" (canvas Yon-Rezervasyon-Duzenle v135): the core publish
 * box, re-registered with the booking summary and the cancel button on top.
 *
 * The box stays core's `submitdiv` so `#submitpost`, the `name="save"` Update
 * button, Move to Trash, button locking, autosave, the edit lock and the
 * Screen Options exception keep working unchanged; only its title and callback
 * change. Re-registered on the type hook with the same id and 'high' -- core
 * drops its 'core' entry and adds ours (wp-admin/includes/template.php,
 * add_meta_box()); remove_meta_box() is NOT called (it would leave a false
 * entry that blocks the re-add). The rows that mean nothing for a booking
 * (status, visibility, publish date, preview) are hidden by CSS; their inputs
 * stay in the form, so edit_post() keeps the current values.
 */
final class BookingStatusBox {

	public static function register(): void {
		add_action('add_meta_boxes_mhmrentiva_booking', array( self::class, 'add' ));
	}

	public static function add(\WP_Post $post): void {
		// The new-booking screen removes submitdiv on purpose (ManualBookingMetaBox).
		if ('auto-draft' === $post->post_status) {
			return;
		}

		add_meta_box(
			'submitdiv',
			__('Booking Status', 'mhm-rentiva'),
			array( self::class, 'render' ),
			'mhmrentiva_booking',
			'side',
			'high'
		);
	}

	/**
	 * @param array<string, mixed> $args The box arguments core passes; handed on as they are.
	 */
	public static function render(\WP_Post $post, array $args = array()): void {
		$vehicle_id = (int) get_post_meta($post->ID, '_mhmrentiva_vehicle_id', true);
		$days       = (int) get_post_meta($post->ID, '_mhmrentiva_rental_days', true);
		$total      = (float) get_post_meta($post->ID, '_mhmrentiva_total_price', true);

		echo '<div class="mhm-booking-status-summary">';
		echo '<strong>' . esc_html__('Booking summary', 'mhm-rentiva') . '</strong>';
		if ($vehicle_id > 0) {
			echo '<span><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong> ' . esc_html(get_the_title($vehicle_id)) . '</span>';
		}
		echo '<span><strong>' . esc_html__('Days:', 'mhm-rentiva') . '</strong> ' . esc_html(number_format_i18n($days)) . '</span>';
		echo '<span><strong>' . esc_html__('Total:', 'mhm-rentiva') . '</strong> ' . esc_html(CurrencyHelper::format_price($total, 2)) . '</span>';
		BookingDepositMetaBox::render_cancel_button($post->ID);
		echo '</div>';

		post_submit_meta_box($post, $args);
	}
}
