<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Booking Detail Template
 *
 * @var WP_Post $booking
 * @var array $booking_data
 * @var WP_Post $vehicle
 * @var array $navigation
 */

if (! defined('ABSPATH')) {
	exit;
}



// Get booking data
$booking    = $data['booking'] ?? null;
$booking_id = $booking ? $booking->ID : 0;
$navigation = $data['navigation'] ?? array();
$is_integrated = $data['is_integrated'] ?? false;

if (! $booking) {
	return;
}

$vehicle_id = get_post_meta($booking_id, '_mhm_vehicle_id', true);
$vehicle    = get_post($vehicle_id);

$pickup_date      = get_post_meta($booking_id, '_mhm_pickup_date', true);
$dropoff_date     = get_post_meta($booking_id, '_mhm_dropoff_date', true);
$pickup_time      = get_post_meta($booking_id, '_mhm_start_time', true);
$dropoff_time     = get_post_meta($booking_id, '_mhm_end_time', true);
$total_price      = get_post_meta($booking_id, '_mhm_total_price', true);
$status           = get_post_meta($booking_id, '_mhm_status', true);
$payment_type     = get_post_meta($booking_id, '_mhm_payment_type', true);
$deposit_amount   = get_post_meta($booking_id, '_mhm_deposit_amount', true);
$remaining_amount = get_post_meta($booking_id, '_mhm_remaining_amount', true);
$selected_addons  = get_post_meta($booking_id, '_mhm_selected_addons', true);

// Vehicle image
$vehicle_image = get_the_post_thumbnail_url($vehicle_id, 'medium');
if (! $vehicle_image) {
	$vehicle_image = MHM_RENTIVA_PLUGIN_URL . 'assets/images/no-image.png';
}

// Currency
$currency_symbol = apply_filters('mhm_rentiva/currency_symbol', '');

// Date format
$formatted_pickup_date  = date_i18n('j F Y', strtotime($pickup_date));
$formatted_dropoff_date = date_i18n('j F Y', strtotime($dropoff_date));

// Status text
$status_text = array(
	'pending'     => __('Pending', 'mhm-rentiva'),
	'confirmed'   => __('Confirmed', 'mhm-rentiva'),
	'in_progress' => __('In Progress', 'mhm-rentiva'),
	'completed'   => __('Completed', 'mhm-rentiva'),
	'cancelled'   => __('Cancelled', 'mhm-rentiva'),
);

$status_display = $status_text[$status] ?? ucfirst($status);
$status_class   = 'status-' . $status;

$is_integrated = empty($navigation);
$wrapper_class = 'mhm-rentiva-account-page';
if ($is_integrated) {
	$wrapper_class .= ' mhm-integrated';
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">

	<!-- Account Navigation -->
	<?php if (! empty($navigation)) : ?>
		<?php echo wp_kses_post(\MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', array('navigation' => $navigation), true)); ?>
	<?php endif; ?>

	<div class="mhm-account-content">

		<!-- Breadcrumb -->
		<div class="rv-breadcrumb">
			<a href="<?php echo esc_url($navigation['dashboard']['url']); ?>"><?php esc_html_e('Dashboard', 'mhm-rentiva'); ?></a>
			<span>/</span>
			<a href="<?php echo esc_url($navigation['bookings']['url']); ?>"><?php esc_html_e('My Bookings', 'mhm-rentiva'); ?></a>
			<span>/</span>
			<span><?php esc_html_e('Booking Details', 'mhm-rentiva'); ?></span>
		</div>

		<!-- Header -->
		<div class="rv-page-header">
			<h1><?php esc_html_e('Booking Confirmation', 'mhm-rentiva'); ?></h1>
			<p><?php esc_html_e('Your reservation has been successfully completed. Please find your booking details below.', 'mhm-rentiva'); ?></p>
		</div>

		<!-- Booking Details -->
		<div class="rv-booking-details">
			<h2><?php esc_html_e('Reservation Details', 'mhm-rentiva'); ?></h2>

			<div class="rv-details-grid">
				<!-- Booking Reference -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Booking Reference', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value">#<?php echo esc_html($booking_id); ?></div>
				</div>

				<!-- Vehicle -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value">
						<div class="rv-vehicle-info">
							<img src="<?php echo esc_url($vehicle_image); ?>" alt="<?php echo esc_attr($vehicle->post_title); ?>" class="rv-vehicle-thumb">
							<span><?php echo esc_html($vehicle->post_title); ?></span>
						</div>
					</div>
				</div>

				<!-- Pickup Date & Time -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Pickup Date & Time', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value"><?php echo esc_html($formatted_pickup_date . ', ' . $pickup_time); ?></div>
				</div>

				<!-- Return Date & Time -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Return Date & Time', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value"><?php echo esc_html($formatted_dropoff_date . ', ' . $dropoff_time); ?></div>
				</div>

				<!-- Status -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Status', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value">
						<span class="rv-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_display); ?></span>
					</div>
				</div>

				<!-- Total Cost -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Total Cost', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value rv-price"><?php echo esc_html(number_format($total_price, 2, ',', '.') . ' ' . $currency_symbol); ?></div>
				</div>

				<?php if ($payment_type === 'deposit' && $deposit_amount > 0) : ?>
					<!-- Deposit Amount -->
					<div class="rv-detail-row">
						<div class="rv-detail-label"><?php esc_html_e('Deposit Paid', 'mhm-rentiva'); ?></div>
						<div class="rv-detail-value rv-price"><?php echo esc_html(number_format($deposit_amount, 2, ',', '.') . ' ' . $currency_symbol); ?></div>
					</div>

					<!-- Remaining Amount -->
					<div class="rv-detail-row">
						<div class="rv-detail-label"><?php esc_html_e('Remaining Amount', 'mhm-rentiva'); ?></div>
						<div class="rv-detail-value rv-price"><?php echo esc_html(number_format($remaining_amount, 2, ',', '.') . ' ' . $currency_symbol); ?></div>
					</div>
				<?php endif; ?>

				<?php if (! empty($selected_addons)) : ?>
					<!-- Selected Add-ons -->
					<div class="rv-detail-row">
						<div class="rv-detail-label"><?php esc_html_e('Additional Services', 'mhm-rentiva'); ?></div>
						<div class="rv-detail-value">
							<ul class="rv-addons-list">
								<?php
								foreach ($selected_addons as $addon_id) :
									$addon = get_post($addon_id);
									if ($addon) :
								?>
										<li><?php echo esc_html($addon->post_title); ?></li>
								<?php
									endif;
								endforeach;
								?>
							</ul>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Next Steps -->
		<div class="rv-next-steps">
			<h2><?php esc_html_e('Next Steps', 'mhm-rentiva'); ?></h2>
			<p><?php esc_html_e('Please arrive at the pickup location 15 minutes prior to your scheduled pickup time. Remember to bring your driver\'s license and the credit card used for the reservation.', 'mhm-rentiva'); ?></p>
		</div>

		<!-- Actions -->
		<div class="rv-booking-actions">
			<a href="<?php echo esc_url($navigation['bookings']['url']); ?>" class="rv-btn rv-btn-secondary">
				<?php esc_html_e('Back to Bookings', 'mhm-rentiva'); ?>
			</a>

			<?php
			// Check if user can cancel this booking
			$can_cancel        = \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::user_can_cancel($booking_id);
			$cancellation_info = \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::get_cancellation_info($booking_id);

			if ($can_cancel) :
			?>
				<button type="button" id="cancel-booking-btn" class="rv-btn rv-btn-danger" data-booking-id="<?php echo esc_attr($booking_id); ?>">
					<?php esc_html_e('Cancel Booking', 'mhm-rentiva'); ?>
				</button>
			<?php endif; ?>


		</div>

		<?php if ($can_cancel) : ?>
			<!-- Cancellation Info -->
			<div class="rv-cancellation-info" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
				<p style="margin: 0; font-size: 14px; color: #856404;">
					<?php echo esc_html($cancellation_info['message']); ?>
				</p>
				<?php
				$refund_policy = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_text_refund_policy', '');
				if (! empty($refund_policy)) :
				?>
					<p style="margin: 10px 0 0 0; font-size: 13px; color: #856404; font-style: italic;">
						<?php echo esc_html($refund_policy); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div><!-- .mhm-account-content -->
</div>

<!-- Cancel Booking Modal -->
<div id="cancel-booking-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
	<div style="background-color: #fefefe; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
			<h2 style="margin: 0; color: #dc3545;"><?php esc_html_e('Cancel Booking', 'mhm-rentiva'); ?></h2>
			<button id="close-modal" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #999;">&times;</button>
		</div>

		<p style="margin-bottom: 20px; color: #666;">
			<?php esc_html_e('Are you sure you want to cancel this booking? This action cannot be undone.', 'mhm-rentiva'); ?>
		</p>

		<div style="margin-bottom: 20px;">
			<label for="cancellation-reason" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
				<?php esc_html_e('Reason for cancellation (optional):', 'mhm-rentiva'); ?>
			</label>
			<textarea id="cancellation-reason" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;" placeholder="<?php esc_attr_e('Please tell us why you\'re cancelling...', 'mhm-rentiva'); ?>"></textarea>
		</div>

		<div id="cancel-status-message" style="margin-bottom: 15px; padding: 10px; border-radius: 4px; display: none;"></div>

		<div style="display: flex; gap: 10px; justify-content: flex-end;">
			<button id="cancel-modal-close" class="rv-btn rv-btn-secondary"><?php esc_html_e('Keep Booking', 'mhm-rentiva'); ?></button>
			<button id="confirm-cancellation" class="rv-btn rv-btn-danger"><?php esc_html_e('Yes, Cancel Booking', 'mhm-rentiva'); ?></button>
		</div>
	</div>
</div>



<style>
	.rv-btn-danger {
		background-color: #dc3545;
		color: #ffffff;
		border: none;
		padding: 12px 24px;
		border-radius: 4px;
		cursor: pointer;
		font-size: 15px;
		font-weight: 600;
		transition: background-color 0.2s;
	}

	.rv-btn-danger:hover {
		background-color: #c82333;
	}

	.rv-btn-danger:disabled {
		background-color: #6c757d;
		cursor: not-allowed;
	}

	.rv-success {
		background-color: #d4edda;
		border-left: 4px solid #28a745;
		color: #155724;
	}

	.rv-error {
		background-color: #f8d7da;
		border-left: 4px solid #dc3545;
		color: #721c24;
	}
</style>