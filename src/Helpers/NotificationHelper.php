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

		// Whole-branch review, F2: paid() is zeroed for a mixed-currency
		// booking (Task 15) -- summing across currencies is exactly the
		// meaningless number isMixedCurrency() exists to refuse. This
		// e-mail's decision to fire (K6: a paid order was found) is made
		// per-order (AutoCancel::is_paid()) with no currency-matching
		// involved, so a booking whose two paid orders sit in different
		// currencies reaches this same e-mail -- and printing that zero
		// stated the opposite of the truth this e-mail exists to report: it
		// said no money was held on a booking flagged BECAUSE it holds paid
		// money. State what is true instead of a figure, the same choice
		// send_refund_mixed_currency_review_email() made when it was added.
		if ($state->isMixedCurrency()) {
			$amount_line = __('This booking\'s paid orders are in more than one currency, so the amount held cannot be totalled automatically.', 'mhm-rentiva');
		} else {
			$amount_line = sprintf(
				/* translators: 1: amount held, 2: currency code */
				__('Amount held: %1$s %2$s', 'mhm-rentiva'),
				\MHMRentiva\Admin\Payment\Core\Money::toMajor($state->paid()),
				$currency
			);
		}

		$body_parts = array(
			sprintf(
				/* translators: %d: booking id */
				__('Auto-cancel found booking #%d already holding a paid order and left it alone -- it did not cancel the booking or its order.', 'mhm-rentiva'),
				$booking_id
			),
			'',
			$amount_line,
			sprintf(
				/* translators: %s: link to the booking */
				__('Review the booking: %s', 'mhm-rentiva'),
				$link
			),
		);

		return wp_mail($admin_email, $subject, implode("\n", $body_parts));
	}

	/**
	 * Send "a committed cancellation had a post-commit problem" email.
	 *
	 * CancellationHandler::cancel_booking() commits the cancellation itself
	 * (the booking IS cancelled, the customer HAS been told) and only then
	 * runs the cancellation e-mail, the refund and the public
	 * mhmrentiva_booking_cancelled hook. If any of those three throws, the
	 * booking stays cancelled -- there is nothing left to roll back -- but a
	 * refund that was supposed to happen may not have, and nothing else in
	 * that request will ever revisit it. Unlike send_auto_cancel_email() this
	 * is not behind a settings toggle, for the same reason
	 * send_refund_needs_review_email() is not: a post-commit throwable is
	 * exactly the situation that needs a human, every time it happens.
	 *
	 * @param int $booking_id
	 * @return bool
	 */
	public static function send_refund_failed_email(int $booking_id): bool
	{
		$admin_email = get_option('admin_email');
		if (! filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$state    = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking_id);
		$currency = $state->currency() ?: 'TRY';
		// Same get_edit_post_link() ?: admin_url(...) fallback as
		// send_refund_needs_review_email(), for the same reason: the
		// booking CPT maps both edit_posts and edit_post to manage_options
		// with no map_meta_cap (AbstractPostType.php:290-303), so an admin
		// caller's deep link and the fallback URL this method would build by
		// hand require the exact same capability.
		$link = get_edit_post_link($booking_id, '') ?: admin_url('post.php?post=' . $booking_id . '&action=edit');

		$subject = __('A cancelled booking had a problem completing its refund', 'mhm-rentiva');

		// Whole-branch review, F2: refundable() is zeroed for a
		// mixed-currency booking (Task 15). CancellationHandler.php's own
		// `$recovery_state->isMixedCurrency()` disjunct (:403) routes this
		// exact shape to THIS e-mail deliberately -- its own comment says so
		// -- BECAUSE money is owed and unknowable, not despite it. Printing
		// the zero said the opposite of the truth this e-mail exists to
		// report: "0.00" on an e-mail whose only reason to exist is that
		// money IS still owed. State what is true instead of a figure, the
		// same choice send_refund_mixed_currency_review_email() made when it
		// was added.
		if ($state->isMixedCurrency()) {
			$amount_line = __('This booking\'s paid orders are in more than one currency, so the amount still owed cannot be totalled automatically.', 'mhm-rentiva');
		} else {
			$amount_line = sprintf(
				/* translators: 1: amount still owed to the customer, 2: currency code */
				__('Amount still owed to the customer: %1$s %2$s', 'mhm-rentiva'),
				\MHMRentiva\Admin\Payment\Core\Money::toMajor($state->refundable()),
				$currency
			);
		}

		$body_parts = array(
			sprintf(
				/* translators: %d: booking id */
				__('Booking #%d was cancelled, but something went wrong afterward and the refund may not have completed.', 'mhm-rentiva'),
				$booking_id
			),
			'',
			$amount_line,
			sprintf(
				/* translators: %s: link to the booking */
				__('Review the booking: %s', 'mhm-rentiva'),
				$link
			),
		);

		return wp_mail($admin_email, $subject, implode("\n", $body_parts));
	}

	/**
	 * Send "order cancelled, but a sibling order on this booking is still
	 * paid" email.
	 *
	 * The cancelled/failed branch found a sibling order still holding paid
	 * money and left the booking alone -- cancelling the collection
	 * instrument is not cancelling the debt. Unlike send_auto_cancel_email()
	 * this is not behind a settings toggle, for the same reason
	 * send_refund_needs_review_email() is not: a live booking that just lost
	 * one of its payment orders is exactly the situation that needs a human,
	 * every time it happens.
	 *
	 * Deliberately silent on WHICH order died and which is still paid: a
	 * deposit booking's dying order can be either leg, and a message that
	 * assumes "the remaining balance" is wrong exactly when the deposit
	 * order is the one that died -- the operator would be sent to issue a
	 * new payment link for a balance that is actually zero. The true
	 * statement in both directions is only that one order died while
	 * another is still paid, and that the booking's balance needs a look.
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

		$subject = __('An order was cancelled while another order on the same booking is still paid', 'mhm-rentiva');

		$body_parts = array(
			sprintf(
				/* translators: 1: order id, 2: booking id */
				__('Order #%1$d on booking #%2$d was cancelled or failed. Another order on this booking still holds paid money -- the order that died was not necessarily the debt.', 'mhm-rentiva'),
				$order_id,
				$booking_id
			),
			'',
			__("Review this booking's payment orders and balance before treating it as settled or cancelled.", 'mhm-rentiva'),
			sprintf(
				/* translators: %s: link to the booking */
				__('Review the booking: %s', 'mhm-rentiva'),
				$link
			),
		);

		return wp_mail($admin_email, $subject, implode("\n", $body_parts));
	}

	/**
	 * Send "this cancelled booking's payment cannot be totalled across
	 * currencies, a human must settle it" email.
	 *
	 * CancellationHandler::settle_refund() found paid orders in more than one
	 * currency (PaymentState::isMixedCurrency()) and parked the booking in
	 * needs_review instead of computing or moving a refund -- summing across
	 * currencies would be a number with no meaning, labelled with whichever
	 * currency happened to be seen first.
	 *
	 * Deliberately its own method rather than a reuse of
	 * send_refund_needs_review_email(): that helper's wording is written for
	 * AutoCancel's surface and both of its factual claims are false here --
	 * "left it alone, did not cancel the booking" is wrong (this booking IS
	 * cancelled; the commit already happened before settle_refund() ever
	 * runs), and "AutoCancel found" is wrong (auto-cancel was never
	 * involved). Also prints NO amount, unlike that sibling: the whole point
	 * of isMixedCurrency() is that no single figure is safe to quote here.
	 *
	 * @param int $booking_id
	 * @return bool
	 */
	public static function send_refund_mixed_currency_review_email(int $booking_id): bool
	{
		$admin_email = get_option('admin_email');
		if (! filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		// Same get_edit_post_link() ?: admin_url(...) fallback as every other
		// refund notification, for the same reason (AbstractPostType.php
		// :290-303 maps edit_posts and edit_post to the same capability).
		$link = get_edit_post_link($booking_id, '') ?: admin_url('post.php?post=' . $booking_id . '&action=edit');

		$subject = __('A cancelled booking needs a manual refund across currencies', 'mhm-rentiva');

		$body_parts = array(
			sprintf(
				/* translators: %d: booking id */
				__('Booking #%d has been cancelled. Its paid orders are in more than one currency, so the plugin cannot total or move a refund for it automatically.', 'mhm-rentiva'),
				$booking_id
			),
			'',
			__("Please review this booking's paid orders and settle any refund by hand.", 'mhm-rentiva'),
			sprintf(
				/* translators: %s: link to the booking */
				__('Review the booking: %s', 'mhm-rentiva'),
				$link
			),
		);

		return wp_mail($admin_email, $subject, implode("\n", $body_parts));
	}
}
