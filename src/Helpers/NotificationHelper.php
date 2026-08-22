<?php
declare(strict_types=1);

namespace MHMRentiva\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;



/**
 * Notification Helper Class
 *
 * Handles generic email notifications for the system.
 * Designed to be extensible for future notification types (Reminders, Confirmations, etc.)
 *
 * @since 4.5.4
 */
class NotificationHelper {

	/**
	 * Send Auto Cancellation Email
	 *
	 * @param int $booking_id
	 * @return bool
	 */
	public static function send_auto_cancel_email(int $booking_id): bool
	{
		// 1. Check if the feature is enabled
		$enabled = SettingsCore::get('mhmrentiva_send_auto_cancel_email', '0');
		if ($enabled !== '1') {
			return false;
		}

		// 2. Get Booking & Customer Data
		// 3. Send Email using Mailer (Modern System)
		// This will automatically wrap the content with the layout and use the template registry
		if (class_exists('\\MHMRentiva\\Admin\\Emails\\Core\\Mailer')) {
			return \MHMRentiva\Admin\Emails\Core\Mailer::sendBookingEmail('auto_cancel', $booking_id, 'customer');
		}

		// Fallback for safety (should not happen if autoloader works)
		return false;
	}

	/**
	 * Send Refund Needs-Review Email
	 *
	 * AutoCancel found a paid order inside its cancel set and, per K6 (no
	 * unattended path moves money), parked the booking instead of cancelling
	 * it. Unlike send_auto_cancel_email() this is not behind a settings
	 * toggle -- a paid order left alone by a sweep is exactly the situation
	 * that needs a human, every time it happens, not only when an admin has
	 * opted in.
	 *
	 * @param int $booking_id
	 * @return bool
	 */
	public static function send_refund_needs_review_email(int $booking_id): bool
	{
		$admin_email = get_option('admin_email');
		if (! filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$state    = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking_id);
		$amount   = \MHMRentiva\Admin\Payment\Core\Money::toMajor($state->paid());
		$currency = $state->currency() ?: 'TRY';
		// Both `?:` branches are live, because AutoCancel::run() has two
		// production callers: the WP-Cron hook (AutoCancel.php:54), where
		// there is no logged-in user, and VehicleColumns::maybe_run_autocancel()
		// (VehicleColumns.php:1460, reached from :1417), a 60s-throttled
		// fallback that runs inside an admin request with a logged-in user.
		// The cron caller always takes the right-hand side: get_edit_post_link()
		// bails at `if ( ! current_user_can( 'edit_post', $post->ID ) ) return null;`
		// (core link-template.php:1473-1475) -- before its own filter, so no
		// filter can rescue it. The admin caller usually takes the left.
		//
		// The fallback is byte-identical to what core would have built: the
		// booking CPT leaves WP_Post_Type::$_edit_link at its 'post.php?post=%d'
		// default and core appends '&action=edit' for a non-display context.
		// It concedes no access either: AbstractPostType::get_capabilities_args()
		// (:290-303) maps edit_posts AND edit_post to manage_options with no
		// map_meta_cap, so the deep link and the list screen demand the very
		// same capability -- which is why this is the tree's deep-link pattern
		// (BookingRefundMetaBox.php:83) and not a link to the booking list.
		$link = get_edit_post_link($booking_id, '') ?: admin_url('post.php?post=' . $booking_id . '&action=edit');

		$subject = __('A cancelled reservation still holds paid money', 'mhm-rentiva');

		$body_parts = array(
			sprintf(
				/* translators: %d: booking id */
				__('Auto-cancel found booking #%d already holding a paid order and left it alone -- it did not cancel the booking or its order.', 'mhm-rentiva'),
				$booking_id
			),
			'',
			sprintf(
				/* translators: 1: amount held, 2: currency code */
				__('Amount held: %1$s %2$s', 'mhm-rentiva'),
				$amount,
				$currency
			),
			sprintf(
				/* translators: %s: link to the booking */
				__('Review the booking: %s', 'mhm-rentiva'),
				$link
			),
		);

		return wp_mail($admin_email, $subject, implode("\n", $body_parts));
	}

	/**
	 * Send "order cancelled but the booking still owes money" email.
	 *
	 * The cancelled/failed branch found a sibling order still holding paid
	 * money and left the booking alone -- cancelling the collection
	 * instrument is not cancelling the debt. Unlike send_auto_cancel_email()
	 * this is not behind a settings toggle, for the same reason
	 * send_refund_needs_review_email() is not: a live booking that just lost
	 * one of its payment orders is exactly the situation that needs a human,
	 * every time it happens.
	 *
	 * @param int $booking_id
	 * @param int $order_id
	 * @return bool
	 */
	public static function send_order_cancelled_on_live_booking_email(int $booking_id, int $order_id): bool
	{
		$admin_email = get_option('admin_email');
		if (! filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$link = get_edit_post_link($booking_id, '') ?: admin_url('post.php?post=' . $booking_id . '&action=edit');

		$subject = __('An order was cancelled but the booking still owes money', 'mhm-rentiva');

		$body_parts = array(
			sprintf(
				/* translators: 1: order id, 2: booking id */
				__('Order #%1$d was cancelled or failed, but booking #%2$d still has an unpaid balance -- the order was only the collection instrument, not the debt.', 'mhm-rentiva'),
				$order_id,
				$booking_id
			),
			'',
			__('A new payment link should be issued for the remaining balance.', 'mhm-rentiva'),
			sprintf(
				/* translators: %s: link to the booking */
				__('Review the booking: %s', 'mhm-rentiva'),
				$link
			),
		);

		return wp_mail($admin_email, $subject, implode("\n", $body_parts));
	}
}
