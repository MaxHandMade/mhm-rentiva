<?php

/**
 * Booking Confirmation Template
 *
 * @var WP_Post $booking
 * @var array $booking_data
 * @var WP_Post $vehicle
 * @var string $customer_email
 * @var string $customer_name
 */

if (! defined('ABSPATH')) {
	exit;
}



// Use data from prepare_template_data (already prepared by BookingConfirmation class)
// All booking data is already available in the template variables

// Date format - check if dates exist
$formatted_pickup_date  = ! empty($pickup_date) ? date_i18n('j F Y', strtotime($pickup_date)) : '';
$formatted_dropoff_date = ! empty($dropoff_date) ? date_i18n('j F Y', strtotime($dropoff_date)) : '';

// Status text
$status_text = array(
	'pending'     => esc_html__('Pending', 'mhm-rentiva'),
	'confirmed'   => esc_html__('Confirmed', 'mhm-rentiva'),
	'in_progress' => esc_html__('In Progress', 'mhm-rentiva'),
	'completed'   => esc_html__('Completed', 'mhm-rentiva'),
	'cancelled'   => esc_html__('Cancelled', 'mhm-rentiva'),
);

$status_display = ! empty($status) ? ($status_text[$status] ?? ucfirst($status)) : '';
$status_class   = ! empty($status) ? 'status-' . $status : '';

// Payment gateway information - use data from prepare_template_data
// $payment_gateway is already available from template data

// Payment gateway names
$gateway_names = array(
	'stripe'        => esc_html__('Credit Card (Stripe)', 'mhm-rentiva'),
	'paypal'        => esc_html__('PayPal', 'mhm-rentiva'),
	'paytr'         => esc_html__('PayTR', 'mhm-rentiva'),
	'offline'       => esc_html__('Bank EFT/Transfer', 'mhm-rentiva'),
	'bank_transfer' => esc_html__('Bank EFT/Transfer', 'mhm-rentiva'),
);

// Payment method text
$payment_method_text = array(
	'credit_card'   => esc_html__('Credit Card', 'mhm-rentiva'),
	'bank_transfer' => esc_html__('Bank Transfer', 'mhm-rentiva'),
	'cash'          => esc_html__('Cash', 'mhm-rentiva'),
);

// Payment method display (gateway first, then method)
if (! empty($payment_gateway) && isset($gateway_names[$payment_gateway])) {
	$payment_method_display = $gateway_names[$payment_gateway];
} else {
	$payment_method_display = ! empty($payment_method) ? ($payment_method_text[$payment_method] ?? ucfirst($payment_method)) : '';
}

// Payment status text
$payment_status_text = array(
	'pending'   => esc_html__('Payment Pending', 'mhm-rentiva'),
	'completed' => esc_html__('Completed', 'mhm-rentiva'),
	'failed'    => esc_html__('Failed', 'mhm-rentiva'),
	'cancelled' => esc_html__('Cancelled', 'mhm-rentiva'),
	'refunded'  => esc_html__('Refunded', 'mhm-rentiva'),
);

$payment_status_display = ! empty($payment_status) ? ($payment_status_text[$payment_status] ?? ucfirst($payment_status)) : '';

// Bank account details - Legacy offline support removed.
// Future: If manual bank transfer instructions are needed, they should be added via WooCommerce BACS settings.

// URLs
$account_url      = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();
$booking_form_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
?>

<div class="rv-booking-confirmation">
	<!-- Header -->
	<div class="rv-confirmation-header">
		<div class="rv-success-icon">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<circle cx="12" cy="12" r="10" fill="#10B981" stroke="#10B981" stroke-width="2" />
				<path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
			</svg>
		</div>
		<h1 class="rv-confirmation-title"><?php esc_html_e('Booking Confirmation', 'mhm-rentiva'); ?></h1>
		<p class="rv-confirmation-message">
			<?php esc_html_e('Your booking has been successfully completed! Your details are below.', 'mhm-rentiva'); ?>
		</p>
	</div>

	<!-- Booking Details -->
	<div class="rv-booking-details">
		<h2><?php esc_html_e('Booking Details', 'mhm-rentiva'); ?></h2>

		<div class="rv-details-grid">
			<!-- Booking Reference -->
			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Booking Reference', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value booking-ref">#<?php echo esc_html($booking_id ?? ''); ?></div>
			</div>

			<!-- Vehicle -->
			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value">
					<div class="rv-vehicle-info">
						<img src="<?php echo esc_url($vehicle_image ?? ''); ?>" alt="<?php echo esc_attr($vehicle->post_title ?? ''); ?>" class="rv-vehicle-thumb">
						<span><?php echo esc_html($vehicle->post_title ?? ''); ?></span>
					</div>
				</div>
			</div>

			<!-- Pickup Date & Time -->
			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Pickup Date & Time', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value"><?php echo esc_html($formatted_pickup_date . ', ' . ($pickup_time ?? '')); ?></div>
			</div>

			<!-- Return Date & Time -->
			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Return Date & Time', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value"><?php echo esc_html($formatted_dropoff_date . ', ' . ($dropoff_time ?? '')); ?></div>
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
				<div class="rv-detail-value rv-price total-cost"><?php echo esc_html(number_format($total_price ?? 0, 2, ',', '.') . ' ' . ($currency_symbol ?? '$')); ?></div>
			</div>

			<?php if ((! empty($payment_type) && $payment_type === 'deposit') && (! empty($deposit_amount) && $deposit_amount > 0)) : ?>
				<!-- Deposit Amount -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Deposit Paid', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value rv-price deposit-paid"><?php echo esc_html(number_format($deposit_amount ?? 0, 2, ',', '.') . ' ' . ($currency_symbol ?? '$')); ?></div>
				</div>

				<!-- Remaining Amount -->
				<div class="rv-detail-row">
					<div class="rv-detail-label"><?php esc_html_e('Remaining Amount', 'mhm-rentiva'); ?></div>
					<div class="rv-detail-value rv-price remaining-amount"><?php echo esc_html(number_format($remaining_amount, 2, ',', '.') . ' ' . $currency_symbol); ?></div>
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
									<li>
										<span class="addon-check">✓</span>
										<?php echo esc_html($addon->post_title); ?>
									</li>
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

	<!-- Payment Information -->
	<div class="rv-payment-info">
		<h2><?php esc_html_e('Payment Information', 'mhm-rentiva'); ?></h2>

		<div class="rv-details-grid">
			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Payment Method', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value"><?php echo esc_html($payment_method_display); ?></div>
			</div>

			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Payment Status', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value">
					<span class="rv-payment-status status-<?php echo esc_attr($payment_status ?? ''); ?>">
						<?php echo esc_html($payment_status_display); ?>
					</span>
				</div>
			</div>
		</div>

		<?php
		// Payment Deadline Warning (for non-paid statuses)
		if ($payment_status !== 'completed' && $payment_status !== 'paid') :
			$payment_deadline = get_post_meta($booking_id, '_mhm_payment_deadline', true);
			if ($payment_deadline) :
		?>
				<div class="rv-payment-info-box" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
					<div class="rv-payment-warning">
						<div class="rv-warning-icon" style="float: left; margin-right: 10px;">⚠️</div>
						<div class="rv-warning-text">
							<strong><?php esc_html_e('Important:', 'mhm-rentiva'); ?></strong>
							<?php
							printf(
								/* translators: %s placeholder. */
								esc_html__('You must complete your payment by %s. If payment is not made within this period, your booking will be automatically cancelled.', 'mhm-rentiva'),
								esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment_deadline)))
							);
							?>
						</div>
					</div>
				</div>
		<?php
			endif;
		endif;
		?>
	</div>

	<!-- Customer Information -->
	<div class="rv-customer-info">
		<h2><?php esc_html_e('Customer Information', 'mhm-rentiva'); ?></h2>

		<div class="rv-details-grid">
			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Full Name', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value"><?php echo esc_html($customer_name ?? ''); ?></div>
			</div>

			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Email', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value"><?php echo esc_html($customer_email ?? ''); ?></div>
			</div>

			<div class="rv-detail-row">
				<div class="rv-detail-label"><?php esc_html_e('Phone', 'mhm-rentiva'); ?></div>
				<div class="rv-detail-value"><?php echo esc_html($customer_phone ?? ''); ?></div>
			</div>
		</div>
	</div>

	<!-- Account Information -->
	<div class="rv-account-info">
		<h2><?php esc_html_e('Account Information', 'mhm-rentiva'); ?></h2>
		<div class="rv-info-box">
			<div class="rv-info-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#3B82F6" stroke-width="2" stroke-linejoin="round" />
					<path d="M2 17L12 22L22 17" stroke="#3B82F6" stroke-width="2" stroke-linejoin="round" />
					<path d="M2 12L12 17L22 12" stroke="#3B82F6" stroke-width="2" stroke-linejoin="round" />
				</svg>
			</div>
			<div class="rv-info-content">
				<h3><?php esc_html_e('Your WordPress Account Has Been Created', 'mhm-rentiva'); ?></h3>
				<p><?php esc_html_e('A WordPress account has been automatically created for your booking. Your login details have been sent to your email address.', 'mhm-rentiva'); ?></p>
				<ul>
					<li><strong><?php esc_html_e('Username:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($customer_email ?? ''); ?></li>
					<li><strong><?php esc_html_e('Email:', 'mhm-rentiva'); ?></strong> <?php echo esc_html($customer_email ?? ''); ?></li>
					<li><strong><?php esc_html_e('Password:', 'mhm-rentiva'); ?></strong> <?php esc_html_e('Sent to your email address', 'mhm-rentiva'); ?></li>
				</ul>
			</div>
		</div>
	</div>

	<!-- Next Steps -->
	<div class="rv-next-steps">
		<h2><?php esc_html_e('Next Steps', 'mhm-rentiva'); ?></h2>
		<div class="rv-steps-list">
			<div class="rv-step">
				<div class="rv-step-number">1</div>
				<div class="rv-step-content">
					<h4><?php esc_html_e('Check Your Email', 'mhm-rentiva'); ?></h4>
					<p><?php esc_html_e('Your WordPress account login details have been sent to your email address.', 'mhm-rentiva'); ?></p>
				</div>
			</div>
			<div class="rv-step">
				<div class="rv-step-number">2</div>
				<div class="rv-step-content">
					<h4><?php esc_html_e('Log In to Your Account', 'mhm-rentiva'); ?></h4>
					<p><?php esc_html_e('You can log in to your account using the details in the email.', 'mhm-rentiva'); ?></p>
				</div>
			</div>
			<div class="rv-step">
				<div class="rv-step-number">3</div>
				<div class="rv-step-content">
					<h4><?php esc_html_e('Track Your Booking', 'mhm-rentiva'); ?></h4>
					<p><?php esc_html_e('You can view and manage your bookings from your account.', 'mhm-rentiva'); ?></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Actions -->
	<div class="rv-confirmation-actions">
		<a href="<?php echo esc_url($booking_form_url); ?>" class="rv-btn rv-btn-secondary">
			<?php esc_html_e('New Booking', 'mhm-rentiva'); ?>
		</a>
		<a href="<?php echo esc_url($account_url); ?>" class="rv-btn rv-btn-primary">
			<?php esc_html_e('Go to My Account', 'mhm-rentiva'); ?>
		</a>
	</div>
</div>