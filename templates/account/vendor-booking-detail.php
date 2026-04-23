<?php
declare(strict_types=1);
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope render variables.

/**
 * Vendor Booking Detail Template
 * Mirrors the vendor notification email (booking-created-vendor) with extras.
 *
 * @var array $data
 */

if (! defined('ABSPATH')) {
	exit;
}

$booking       = $data['booking'] ?? null;
$booking_id    = $booking ? (int) $booking->ID : 0;
$is_integrated = ! empty($data['is_integrated']);

if (! $booking) {
	return;
}

$vehicle_id    = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
$vehicle       = $vehicle_id > 0 ? get_post($vehicle_id) : null;
$vehicle_title = $vehicle ? $vehicle->post_title : __('Unknown Vehicle', 'mhm-rentiva');

$pickup_date  = (string) get_post_meta($booking_id, '_mhm_pickup_date', true);
$dropoff_date = (string) get_post_meta($booking_id, '_mhm_dropoff_date', true);
$pickup_time  = (string) get_post_meta($booking_id, '_mhm_pickup_time', true);
$dropoff_time = (string) get_post_meta($booking_id, '_mhm_dropoff_time', true);

$service_type = (string) get_post_meta($booking_id, '_mhm_service_type', true);
$origin_id    = (int) get_post_meta($booking_id, '_mhm_transfer_origin_id', true);
if ($service_type === '' && $origin_id > 0) {
	$service_type = 'transfer';
}
$transfer_flag = (string) get_post_meta($booking_id, '_mhm_is_transfer', true);
if ($service_type === '' && in_array($transfer_flag, array( '1', 'yes', 'true' ), true)) {
	$service_type = 'transfer';
}
if ($service_type === '') {
	$service_type = 'rental';
}
$is_transfer = ( $service_type === 'transfer' );

$status         = (string) get_post_meta($booking_id, '_mhm_status', true);
$total_price    = (float) get_post_meta($booking_id, '_mhm_total_price', true);
$payment_status = (string) get_post_meta($booking_id, '_mhm_payment_status', true);

$customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo($booking_id);
$customer_name = trim(( $customer_info['first_name'] ?? '' ) . ' ' . ( $customer_info['last_name'] ?? '' ));
if ($customer_name === '') {
	$customer_name = (string) ( $customer_info['name'] ?? '' );
}
$customer_email = (string) ( $customer_info['email'] ?? '' );
$customer_phone = (string) ( $customer_info['phone'] ?? '' );

$origin_location      = null;
$destination_location = null;
$transfer_distance    = (int) get_post_meta($booking_id, '_mhm_transfer_distance_km', true);
$transfer_duration    = (int) get_post_meta($booking_id, '_mhm_transfer_duration_min', true);
$transfer_adults      = (int) get_post_meta($booking_id, '_mhm_transfer_adults', true);
$transfer_children    = (int) get_post_meta($booking_id, '_mhm_transfer_children', true);
$transfer_lug_big     = (int) get_post_meta($booking_id, '_mhm_transfer_luggage_big', true);
$transfer_lug_small   = (int) get_post_meta($booking_id, '_mhm_transfer_luggage_small', true);

if ($is_transfer) {
	$destination_id = (int) get_post_meta($booking_id, '_mhm_transfer_destination_id', true);
	if ($origin_id > 0) {
		$origin_location = \MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_by_id($origin_id);
	}
	if ($destination_id > 0) {
		$destination_location = \MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_by_id($destination_id);
	}
}

$currency_symbol = (string) apply_filters('mhm_rentiva/currency_symbol', '₺');
$display_id      = function_exists('mhm_rentiva_get_display_id') ? mhm_rentiva_get_display_id($booking_id) : (string) $booking_id;

$status_labels  = array(
	'pending'     => __('Pending', 'mhm-rentiva'),
	'confirmed'   => __('Confirmed', 'mhm-rentiva'),
	'in_progress' => __('In Progress', 'mhm-rentiva'),
	'completed'   => __('Completed', 'mhm-rentiva'),
	'cancelled'   => __('Cancelled', 'mhm-rentiva'),
	'refunded'    => __('Refunded', 'mhm-rentiva'),
);
$payment_labels = array(
	'pending'   => __('Payment Pending', 'mhm-rentiva'),
	'paid'      => __('Paid', 'mhm-rentiva'),
	'completed' => __('Completed', 'mhm-rentiva'),
	'failed'    => __('Failed', 'mhm-rentiva'),
	'cancelled' => __('Cancelled', 'mhm-rentiva'),
	'refunded'  => __('Refunded', 'mhm-rentiva'),
);

$format_dt = static function (string $date, string $time = '') {
	if ($date === '') {
		return '-';
	}
	$ts = strtotime($date . ( $time !== '' ? ' ' . $time : '' ));
	if ($ts === false) {
		return esc_html($date);
	}
	$fmt = get_option('date_format');
	if ($time !== '') {
		$fmt .= ' · ' . get_option('time_format');
	}
	return date_i18n($fmt, $ts);
};
?>
<div class="mhm-vendor-booking-detail">
	<div class="mhm-vendor-booking-detail__header">
		<div>
			<h2 class="mhm-vendor-booking-detail__title">
				<span class="mhm-vendor-booking-detail__service-badge is-<?php echo esc_attr($service_type); ?>">
					<?php if ($is_transfer) : ?>
						<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 16v-2l-8.5-5V3.5A1.5 1.5 0 0 0 12 2a1.5 1.5 0 0 0-1.5 1.5V9L2 14v2l8.5-2.5V19L8 20.5V22l4-1 4 1v-1.5L13.5 19v-5.5L22 16z"/></svg>
						<?php esc_html_e('Transfer', 'mhm-rentiva'); ?>
					<?php else : ?>
						<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
						<?php esc_html_e('Car Rental', 'mhm-rentiva'); ?>
					<?php endif; ?>
				</span>
				<?php /* translators: %s: human-readable reservation/booking display ID */ ?>
				<?php printf(esc_html__('Reservation #%s', 'mhm-rentiva'), esc_html( (string) $display_id)); ?>
			</h2>
			<p class="mhm-vendor-booking-detail__subtitle"><?php echo esc_html($vehicle_title); ?></p>
		</div>
		<span class="mhm-vendor-booking-detail__status is-<?php echo esc_attr(sanitize_key($status)); ?>">
			<?php echo esc_html($status_labels[ $status ] ?? ucfirst( (string) $status)); ?>
		</span>
	</div>

	<div class="mhm-vendor-booking-detail__grid">
		<section class="mhm-vendor-booking-detail__card">
			<h3><?php esc_html_e('Customer Information', 'mhm-rentiva'); ?></h3>
			<dl class="mhm-vendor-booking-detail__dl">
				<dt><?php esc_html_e('Name', 'mhm-rentiva'); ?></dt>
				<dd><?php echo esc_html($customer_name !== '' ? $customer_name : '-'); ?></dd>
				<dt><?php esc_html_e('Email', 'mhm-rentiva'); ?></dt>
				<dd>
					<?php if ($customer_email !== '') : ?>
						<a href="mailto:<?php echo esc_attr($customer_email); ?>"><?php echo esc_html($customer_email); ?></a>
						<?php
                    else :
						?>
                        -<?php endif; ?>
				</dd>
				<dt><?php esc_html_e('Phone', 'mhm-rentiva'); ?></dt>
				<dd>
					<?php if ($customer_phone !== '') : ?>
						<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $customer_phone)); ?>"><?php echo esc_html($customer_phone); ?></a>
						<?php
                    else :
						?>
                        -<?php endif; ?>
				</dd>
			</dl>
		</section>

		<section class="mhm-vendor-booking-detail__card">
			<h3><?php esc_html_e('Reservation Details', 'mhm-rentiva'); ?></h3>
			<dl class="mhm-vendor-booking-detail__dl">
				<dt><?php esc_html_e('Reservation No', 'mhm-rentiva'); ?></dt>
				<dd>#<?php echo esc_html( (string) $display_id); ?></dd>
				<dt><?php esc_html_e('Service Type', 'mhm-rentiva'); ?></dt>
				<dd><?php echo $is_transfer ? esc_html__('Transfer', 'mhm-rentiva') : esc_html__('Car Rental', 'mhm-rentiva'); ?></dd>
				<dt><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></dt>
				<dd><?php echo esc_html($vehicle_title); ?></dd>
				<dt><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></dt>
				<dd><?php echo esc_html($format_dt($pickup_date, $pickup_time)); ?></dd>
				<dt><?php echo $is_transfer ? esc_html__('Arrival', 'mhm-rentiva') : esc_html__('Drop-off', 'mhm-rentiva'); ?></dt>
				<dd><?php echo esc_html($format_dt($dropoff_date, $dropoff_time)); ?></dd>
				<dt><?php esc_html_e('Total Amount', 'mhm-rentiva'); ?></dt>
				<dd class="mhm-vendor-booking-detail__amount"><?php echo esc_html($currency_symbol . number_format($total_price, 2)); ?></dd>
				<dt><?php esc_html_e('Payment Status', 'mhm-rentiva'); ?></dt>
				<dd><?php echo esc_html($payment_labels[ $payment_status ] ?? ucfirst( (string) $payment_status)); ?></dd>
			</dl>
		</section>
	</div>

	<?php if ($is_transfer) : ?>
		<section class="mhm-vendor-booking-detail__card mhm-vendor-booking-detail__card--transfer">
			<h3><?php esc_html_e('Transfer Route', 'mhm-rentiva'); ?></h3>

			<div class="mhm-vendor-booking-detail__route">
				<div class="mhm-vendor-booking-detail__route-point">
					<span class="mhm-vendor-booking-detail__route-dot is-origin"></span>
					<div>
						<span class="mhm-vendor-booking-detail__route-label"><?php esc_html_e('From', 'mhm-rentiva'); ?></span>
						<strong>
							<?php echo esc_html($origin_location ? $origin_location->name : __('-', 'mhm-rentiva')); ?>
						</strong>
						<?php if ($origin_location && ! empty($origin_location->city)) : ?>
							<small><?php echo esc_html($origin_location->city); ?></small>
						<?php endif; ?>
					</div>
				</div>
				<div class="mhm-vendor-booking-detail__route-line" aria-hidden="true"></div>
				<div class="mhm-vendor-booking-detail__route-point">
					<span class="mhm-vendor-booking-detail__route-dot is-destination"></span>
					<div>
						<span class="mhm-vendor-booking-detail__route-label"><?php esc_html_e('To', 'mhm-rentiva'); ?></span>
						<strong>
							<?php echo esc_html($destination_location ? $destination_location->name : __('-', 'mhm-rentiva')); ?>
						</strong>
						<?php if ($destination_location && ! empty($destination_location->city)) : ?>
							<small><?php echo esc_html($destination_location->city); ?></small>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<dl class="mhm-vendor-booking-detail__dl mhm-vendor-booking-detail__dl--transfer-meta">
				<?php if ($transfer_distance > 0) : ?>
					<dt><?php esc_html_e('Distance', 'mhm-rentiva'); ?></dt>
					<dd><?php echo esc_html(sprintf(/* translators: %d km */ __('%d km', 'mhm-rentiva'), $transfer_distance)); ?></dd>
				<?php endif; ?>
				<?php if ($transfer_duration > 0) : ?>
					<dt><?php esc_html_e('Duration', 'mhm-rentiva'); ?></dt>
					<dd><?php echo esc_html(sprintf(/* translators: %d minutes */ __('%d min', 'mhm-rentiva'), $transfer_duration)); ?></dd>
				<?php endif; ?>
				<?php if ($transfer_adults > 0 || $transfer_children > 0) : ?>
					<dt><?php esc_html_e('Passengers', 'mhm-rentiva'); ?></dt>
					<dd>
						<?php
						$parts = array();
						if ($transfer_adults > 0) {
							$parts[] = sprintf(/* translators: %d adults */ _n('%d adult', '%d adults', $transfer_adults, 'mhm-rentiva'), $transfer_adults);
						}
						if ($transfer_children > 0) {
							$parts[] = sprintf(/* translators: %d children */ _n('%d child', '%d children', $transfer_children, 'mhm-rentiva'), $transfer_children);
						}
						echo esc_html(implode(', ', $parts));
						?>
					</dd>
				<?php endif; ?>
				<?php if ($transfer_lug_big > 0 || $transfer_lug_small > 0) : ?>
					<dt><?php esc_html_e('Luggage', 'mhm-rentiva'); ?></dt>
					<dd>
						<?php
						$lug = array();
						if ($transfer_lug_big > 0) {
							$lug[] = sprintf(/* translators: %d large bags */ __('%d large', 'mhm-rentiva'), $transfer_lug_big);
						}
						if ($transfer_lug_small > 0) {
							$lug[] = sprintf(/* translators: %d small bags */ __('%d small', 'mhm-rentiva'), $transfer_lug_small);
						}
						echo esc_html(implode(', ', $lug));
						?>
					</dd>
				<?php endif; ?>
			</dl>
		</section>
	<?php endif; ?>
</div>
