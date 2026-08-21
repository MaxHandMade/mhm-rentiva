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
		// get_edit_post_link() returns null when current_user_can( 'edit_post',
		// $id ) is false, and the only production caller of this method is
		// AutoCancel, which runs from WP-Cron -- no logged-in user, so the
		// link was empty on the one path that actually sends this email. The
		// `?:` fallback to the booking list is the tree's existing answer to
		// exactly this (Actions.php:67), chosen over BookingRefundMetaBox's
		// hand-built admin_url( 'post.php?post=...&action=edit' ) because that
		// one always emits a deep link, including for a recipient who cannot
		// open it; this keeps the deep link whenever WordPress agrees to give
		// one and degrades to a screen the recipient can always reach.
		$link = get_edit_post_link($booking_id, '') ?: admin_url('edit.php?post_type=mhmrentiva_booking');

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
}
