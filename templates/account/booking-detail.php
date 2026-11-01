<?php

declare(strict_types=1);
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Booking Detail Template (PR D, Task 4; drawing Rezervasyon-Detayi).
 *
 * Reads the booking, builds the view values and hands them to
 * templates/partials/rvs/account-booking-detail.php. On a WooCommerce endpoint
 * this template writes the `rvs-` shell; inside an account shortcode that
 * already wrote it ($rvs_shell) there is no second one. A vendor's view of the
 * booking is Pro's template (AccountRenderer picks it), not this one.
 *
 * @var array $data AccountRenderer::get_booking_detail_data()['data'].
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\LocationContext;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountPaymentSummary;
use MHMRentiva\Admin\Frontend\Account\BookingSummaryPage;
use MHMRentiva\Admin\Frontend\Account\BookingTimeline;
use MHMRentiva\Admin\Frontend\Account\CancellationView;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsBreakdown;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsStatus;
use MHMRentiva\Admin\Payment\Core\PaymentState;

$booking       = $data['booking'] ?? null;
$booking_id    = $booking ? (int) $booking->ID : 0;
$navigation    = $data['navigation'] ?? array();
$is_integrated = $data['is_integrated'] ?? false;

if (! $booking) {
	return;
}

$booking_status = Status::get($booking_id);
$number         = (string) mhmrentiva_get_display_id($booking_id);

// Vehicle.
$vehicle_id    = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
$vehicle       = $vehicle_id ? get_post($vehicle_id) : null;
$vehicle_image = $vehicle_id ? get_the_post_thumbnail_url($vehicle_id, 'medium_large') : false;
if (! $vehicle_image) {
	$vehicle_image = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_placeholder_image_url();
}
// The vehicle's pickup point today: no location is stored on the booking (m17).
$location = $vehicle_id ? LocationContext::for_vehicle($vehicle_id) : null;
$planned  = BookingTimeline::planned($booking_id);

// Money.
$payment_state    = PaymentState::forBooking($booking_id);
$money_received   = $payment_state->paid() > 0 || array() !== $payment_state->orders();
$payment_type     = (string) get_post_meta($booking_id, '_mhmrentiva_payment_type', true);
$deposit_amount   = (float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true);
$remaining_amount = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);
$wc_order_id      = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id($booking_id);
$money_text       = static fn(float $amount): string => wp_strip_all_tags(PriceDisplay::for_cart($amount)['html']);
// Money that moved: recorded gross, recorded currency (no cart tax display).
$minor_html = static fn(int $minor): string => BookingTimeline::recorded_html($minor, $payment_state->currency());
$breakdown  = RvsBreakdown::from_booking($booking_id);

// The payment line under the title (Metin tablosu).
// The card on the dashboard reads the same AccountPaymentSummary.
$payment_line = null;
switch (AccountPaymentSummary::for_booking($booking_id, $booking_status)['kind']) {
	case AccountPaymentSummary::REFUNDED:
		/* translators: %s: refunded amount. */
		$payment_line = sprintf(__('%s refunded', 'mhm-rentiva'), wp_strip_all_tags($minor_html($payment_state->refunded())));
		break;
	case AccountPaymentSummary::DEPOSIT:
		$payment_line = RvsBreakdown::is_closed($booking_id)
			? __('Deposit paid', 'mhm-rentiva')
			/* translators: %s: amount due at pickup. */
			: sprintf(__('Deposit paid, %s due at pickup', 'mhm-rentiva'), $money_text($remaining_amount));
		break;
	case AccountPaymentSummary::PAID:
		$payment_line = __('Paid in full', 'mhm-rentiva');
		break;
	case AccountPaymentSummary::AWAITING:
		// The status tag of a pending_payment booking already says so; the line
		// would repeat it beside the tag. (The dashboard card keeps it: a label there.)
		if ('pending_payment' !== $booking_status) {
			$payment_line = __('Awaiting payment', 'mhm-rentiva');
		}
		break;
}

// "Paid" and "Refunded" rows under the breakdown; the deposit row already
// names a paid deposit.
$paid_rows = BookingSummaryPage::paid_rows($booking_id, $breakdown);

// Pay the balance -- today's condition, unchanged (m21): never while the
// deposit order itself is unpaid (that would open a second order).
$pay_remaining = null;
if ('deposit' === $payment_type && $deposit_amount > 0
	&& $remaining_amount > 0 && ! ( $wc_order_id && ! $money_received ) && in_array($booking_status, array( 'pending', 'confirmed' ), true)
) {
	$pay_remaining = \MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler::is_hybrid_booking($booking_id)
		? array( 'hybrid' => true )
		: array(
			'hybrid' => false,
			'nonce'  => wp_create_nonce('mhmrentiva_pay_remaining_' . $booking_id),
		);
}

// Bank receipt (R13): upload only while no money arrived; a stored receipt's
// status and link always show.
$receipt_id = (int) get_post_meta($booking_id, '_mhmrentiva_receipt_attachment_id', true);
$receipt    = array(
	'can_upload' => AccountController::receipt_upload_allowed($booking_id),
	'has'        => $receipt_id > 0,
	'status'     => BookingMeta::receipt_status_label( (string) get_post_meta($booking_id, '_mhmrentiva_receipt_status', true)),
	'url'        => $receipt_id > 0 ? AccountController::get_receipt_url($booking_id) : '',
);

// Progress.
$steps = array();
$index = 0;
foreach (BookingTimeline::for_booking($booking_id) as $step) {
	++$index;
	$meta = array_filter(array( null !== $step['at'] ? BookingTimeline::moment_text( (int) $step['at']) : '', (string) $step['detail'] ));

	$steps[] = array(
		'label' => $step['label'],
		'meta'  => implode(' · ', $meta),
		'done'  => $step['done'],
		'n'     => $index,
	);
}

$view = array(
	'booking_id'   => $booking_id,
	'number'       => $number,
	'back_url'     => (string) ( $navigation['bookings']['url'] ?? '' ),
	'summary_url'  => BookingSummaryPage::url($booking_id),
	'status'       => RvsStatus::for($booking_status),
	'payment_line' => $payment_line,
	'vehicle'      => array(
		'name'     => $vehicle ? $vehicle->post_title : __('Unknown Vehicle', 'mhm-rentiva'),
		'image'    => (string) $vehicle_image,
		'pickup'   => BookingTimeline::moment_text($planned['pickup']),
		'return'   => BookingTimeline::moment_text($planned['return']),
		'location' => $location ? (string) $location->name : '',
	),
	'steps'        => $steps,
	'cancel'       => CancellationView::for_booking($booking_id, get_current_user_id()),
	'breakdown'    => $breakdown,
	'deposit_paid' => $money_received,
	'paid_rows'    => $paid_rows,
	'pay'          => $pay_remaining,
	'receipt'      => $receipt,
	'uid'          => wp_unique_id('rvs-acc-'),
);

$own_shell  = ! AbstractShortcode::in_rvs_shell();
$root_class = 'rvs-account mhm-rentiva-account-page' . ( $is_integrated ? ' mhm-integrated' : '' );
?>
<?php if ($own_shell) : ?>
<div class="rvs-shell <?php echo esc_attr($root_class); ?>">
<?php else : ?>
<div class="<?php echo esc_attr($root_class); ?>">
<?php endif; ?>
	<?php if (! $is_integrated && ! empty($navigation) && isset($navigation['dashboard']['title'])) : ?>
		<?php echo wp_kses_post(Templates::render('account/navigation', array( 'navigation' => $navigation ), true)); ?>
	<?php endif; ?>
	<?php Templates::render('partials/rvs/account-booking-detail', $view); ?>
</div>
