<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Refunds\RefundValidator;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The booking detail's cancellation section, as the rule applies it (PR D,
 * Task 4; R10, R11, K-D2).
 *
 * - `none`: nothing to cancel -- cancelled, refunded, completed, a no-show,
 *   or a rental already under way (its pickup, and so its deadline, passed);
 *   also when the deadline cannot be computed (no pickup date).
 * - `closed`: the deadline (CancellationHandler, the same instant the cancel
 *   request is refused at) has passed: no button, "contact us".
 * - `open`: the button and its dialog.
 *
 * The refund sentence is drawn only when the refund the cancellation would
 * start is real: RefundValidator accepts a full refund and
 * PaymentState::refundable() > 0 (the amount the handler refunds). No
 * partial or percentage policy exists, so none is promised. A refund that
 * cannot go back through a gateway (offline money, or an order whose
 * gateway cannot refund) is promised with "we will contact you".
 *
 * @since 6.4.0
 */
final class CancellationView {

	/** Statuses with nothing left to cancel. */
	private const NONE_STATUSES = array( Status::CANCELLED, Status::REFUNDED, Status::COMPLETED, Status::NO_SHOW, Status::IN_PROGRESS );

	/**
	 * @return array{state: string, deadline_text: ?string, refund_html: ?string, refund_manual: bool, can_cancel: bool, message_html: string, note: ?string, dialog_html: ?string}
	 */
	public static function for_booking(int $booking_id, int $user_id): array
	{
		$view = array(
			'state'         => 'none',
			'deadline_text' => null,
			'refund_html'   => null,
			'refund_manual' => false,
			'can_cancel'    => false,
			'message_html'  => '',
			'note'          => null,
			'dialog_html'   => null,
		);

		if (in_array(Status::get($booking_id), self::NONE_STATUSES, true)) {
			return $view;
		}

		$info = CancellationHandler::get_cancellation_info($booking_id);
		if (null === $info['deadline_ts']) {
			return $view;
		}

		// Same "<date> · <time>" the vehicle card and the progress use.
		$deadline              = BookingTimeline::moment_text( (int) $info['deadline_ts']);
		$view['deadline_text'] = $deadline;

		if (! $info['can_cancel']) {
			$view['state']        = 'closed';
			$view['message_html'] = esc_html__('The free cancellation period has ended. Contact us to cancel.', 'mhm-rentiva');
			return $view;
		}

		$view['state']      = 'open';
		$view['can_cancel'] = CancellationHandler::user_can_cancel($booking_id, $user_id);
		$view['note']       = __('After this time, contact us to cancel.', 'mhm-rentiva');

		$refund = self::refund($booking_id);
		$kses   = PriceDisplay::allowed_html();

		if (null === $refund) {
			/* translators: %s: cancellation deadline (date and time). */
			$view['message_html'] = sprintf(esc_html__('Free cancellation until %s.', 'mhm-rentiva'), esc_html($deadline));
			$view['dialog_html']  = esc_html__('You are within the free cancellation period.', 'mhm-rentiva');
			return $view;
		}

		$amount                = wp_kses($refund['html'], $kses);
		$view['refund_html']   = $amount;
		$view['refund_manual'] = $refund['manual'];

		if ($refund['manual']) {
			$view['message_html'] = sprintf(
				/* translators: %s: cancellation deadline (date and time). */
				esc_html__('Free cancellation until %s.', 'mhm-rentiva'),
				esc_html($deadline)
			) . ' ' . sprintf(
				/* translators: %s: amount the customer paid, to be refunded. */
				esc_html__('What you paid (%s) is refunded; we will contact you to arrange it.', 'mhm-rentiva'),
				$amount
			);
			$view['dialog_html'] = sprintf(
				/* translators: %s: amount the customer paid, to be refunded. */
				esc_html__('You are within the free cancellation period. What you paid (%s) is refunded; we will contact you to arrange it.', 'mhm-rentiva'),
				$amount
			);
			return $view;
		}

		$view['message_html'] = sprintf(
			/* translators: 1: cancellation deadline (date and time), 2: amount the customer paid, to be refunded. */
			esc_html__('Free cancellation until %1$s. What you paid (%2$s) is refunded.', 'mhm-rentiva'),
			esc_html($deadline),
			$amount
		);
		$view['dialog_html'] = sprintf(
			/* translators: %s: amount to be refunded. */
			esc_html__('You are within the free cancellation period. %s will be refunded.', 'mhm-rentiva'),
			$amount
		);

		return $view;
	}

	/**
	 * The refund a cancellation now would start, or null when there is none.
	 *
	 * @return array{html: string, manual: bool}|null
	 */
	private static function refund(int $booking_id): ?array
	{
		$check = RefundValidator::validateFullRefund($booking_id);
		$state = PaymentState::forBooking($booking_id);

		if (empty($check['valid']) || $state->refundable() <= 0) {
			return null;
		}

		// Manual as the refund service decides it: offline money, or any paid
		// order whose gateway cannot refund by itself.
		$manual = $state->refundableManual() > 0;
		if (! $manual && function_exists('wc_get_order')) {
			foreach ($state->orders() as $order_id) {
				$order = wc_get_order($order_id);
				if ($order instanceof \WC_Order && RefundValidator::MODE_MANUAL === RefundValidator::modeForOrder($order)) {
					$manual = true;
					break;
				}
			}
		}

		return array(
			// What was paid, as recorded (gross, its currency) -- not the
			// cart's tax display of it.
			'html'   => BookingTimeline::recorded_html($state->refundable(), $state->currency()),
			'manual' => $manual,
		);
	}
}
