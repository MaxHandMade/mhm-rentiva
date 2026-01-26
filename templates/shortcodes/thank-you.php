<?php

/**
 * Thank You Page Template
 *
 * @var WP_Post $booking
 * @var WP_Post $vehicle
 * @var string $customer_email
 * @var string $customer_name
 * @var string $booking_reference
 */

if (! defined('ABSPATH')) {
	exit;
}



// Check for error
if (! empty($error)) {
	echo '<div class="rv-thank-you-error"><p>' . esc_html($error) . '</p></div>';
	return;
}

// Date format
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

// Payment method text
$payment_method_text = array(
	'woocommerce'   => esc_html__('WooCommerce', 'mhm-rentiva'),
	'credit_card'   => esc_html__('Credit Card', 'mhm-rentiva'),
	'bank_transfer' => esc_html__('Bank Transfer', 'mhm-rentiva'),
	'cash'          => esc_html__('Cash', 'mhm-rentiva'),
);

$payment_method_display = ! empty($payment_method) ? ($payment_method_text[$payment_method] ?? ucfirst($payment_method)) : esc_html__('WooCommerce', 'mhm-rentiva');

// Payment status text
$payment_status_text = array(
	'pending'   => esc_html__('Payment Pending', 'mhm-rentiva'),
	'completed' => esc_html__('Payment Completed', 'mhm-rentiva'),
	'failed'    => esc_html__('Payment Failed', 'mhm-rentiva'),
	'cancelled' => esc_html__('Cancelled', 'mhm-rentiva'),
	'refunded'  => esc_html__('Refunded', 'mhm-rentiva'),
);

$payment_status_display = ! empty($payment_status) ? ($payment_status_text[$payment_status] ?? ucfirst($payment_status)) : '';

// URLs
$account_url      = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();
$booking_form_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
$confirmation_url = \MHMRentiva\Admin\Frontend\Shortcodes\BookingConfirmation::get_confirmation_url($booking_id ?? 0);
?>

<div class="rv-thank-you">
	<!-- Header -->
	<div class="rv-thank-you-header">
		<div class="rv-success-icon">
			<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<circle cx="12" cy="12" r="10" fill="#10B981" stroke="#10B981" stroke-width="2" />
				<path d="M9 12l2 2 4-4" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
			</svg>
		</div>
		<h1><?php esc_html_e('Thank You!', 'mhm-rentiva'); ?></h1>
		<p class="rv-thank-you-message">
			<?php
			if (! empty($customer_name)) {
				printf(
					/* translators: %s: customer name */
					esc_html__('Dear %s, your reservation has been successfully received!', 'mhm-rentiva'),
					esc_html($customer_name)
				);
			} else {
				esc_html_e('Your reservation has been successfully received!', 'mhm-rentiva');
			}
			?>
		</p>
		<p class="rv-thank-you-submessage">
			<?php esc_html_e('We have sent a confirmation email to your email address. Please check your inbox.', 'mhm-rentiva'); ?>
		</p>
	</div>

	<!-- Quick Summary Card -->
	<div class="rv-quick-summary">
		<div class="rv-summary-card">
			<div class="rv-summary-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M9 12l2 2 4-4" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
					<circle cx="12" cy="12" r="10" stroke="#10B981" stroke-width="2" />
				</svg>
			</div>
			<div class="rv-summary-content">
				<div class="rv-summary-label"><?php esc_html_e('Booking Reference', 'mhm-rentiva'); ?></div>
				<div class="rv-summary-value"><?php echo esc_html($booking_reference ?? 'BK-' . str_pad((string) ($booking_id ?? 0), 6, '0', STR_PAD_LEFT)); ?></div>
			</div>
		</div>

		<div class="rv-summary-card">
			<div class="rv-summary-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
					<path d="M17 21v-8H7v8" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
					<path d="M7 3v5h5" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</div>
			<div class="rv-summary-content">
				<div class="rv-summary-label"><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></div>
				<div class="rv-summary-value"><?php echo esc_html($vehicle->post_title ?? ''); ?></div>
			</div>
		</div>

		<div class="rv-summary-card">
			<div class="rv-summary-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="12" cy="12" r="10" stroke="#F59E0B" stroke-width="2" />
					<path d="M12 6v6l4 2" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" />
				</svg>
			</div>
			<div class="rv-summary-content">
				<div class="rv-summary-label"><?php esc_html_e('Pickup Date', 'mhm-rentiva'); ?></div>
				<div class="rv-summary-value"><?php echo esc_html($formatted_pickup_date . ' ' . ($pickup_time ?? '')); ?></div>
			</div>
		</div>

		<div class="rv-summary-card">
			<div class="rv-summary-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="#8B5CF6" />
					<path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="#8B5CF6" />
				</svg>
			</div>
			<div class="rv-summary-content">
				<div class="rv-summary-label"><?php esc_html_e('Total Amount', 'mhm-rentiva'); ?></div>
				<div class="rv-summary-value rv-price"><?php echo esc_html(number_format($total_price ?? 0, 2, ',', '.') . ' ' . ($currency_symbol ?? '$')); ?></div>
			</div>
		</div>
	</div>

	<!-- Action Buttons -->
	<div class="rv-thank-you-actions">
		<a href="<?php echo esc_url($confirmation_url); ?>" class="rv-btn rv-btn-primary">
			<?php esc_html_e('View Full Booking Details', 'mhm-rentiva'); ?>
		</a>
		<?php if (! empty($woocommerce_order) && is_a($woocommerce_order, 'WC_Order')) : ?>
			<a href="<?php echo esc_url($woocommerce_order->get_view_order_url()); ?>" class="rv-btn rv-btn-secondary">
				<?php esc_html_e('View Order', 'mhm-rentiva'); ?>
			</a>
		<?php endif; ?>
		<?php if (! empty($account_url)) : ?>
			<a href="<?php echo esc_url($account_url); ?>" class="rv-btn rv-btn-outline">
				<?php esc_html_e('My Account', 'mhm-rentiva'); ?>
			</a>
		<?php endif; ?>
		<a href="<?php echo esc_url($booking_form_url ?: home_url()); ?>" class="rv-btn rv-btn-outline">
			<?php esc_html_e('Book Another Vehicle', 'mhm-rentiva'); ?>
		</a>
	</div>

	<!-- Next Steps -->
	<div class="rv-next-steps">
		<h2><?php esc_html_e('What Happens Next?', 'mhm-rentiva'); ?></h2>
		<div class="rv-steps-list">
			<div class="rv-step-item">
				<div class="rv-step-number">1</div>
				<div class="rv-step-content">
					<h3><?php esc_html_e('Confirmation Email', 'mhm-rentiva'); ?></h3>
					<p><?php esc_html_e('You will receive a confirmation email with all booking details shortly.', 'mhm-rentiva'); ?></p>
				</div>
			</div>
			<div class="rv-step-item">
				<div class="rv-step-number">2</div>
				<div class="rv-step-content">
					<h3><?php esc_html_e('Payment Processing', 'mhm-rentiva'); ?></h3>
					<p><?php esc_html_e('Your payment is being processed. You will receive a payment confirmation once completed.', 'mhm-rentiva'); ?></p>
				</div>
			</div>
			<div class="rv-step-item">
				<div class="rv-step-number">3</div>
				<div class="rv-step-content">
					<h3><?php esc_html_e('Pickup Reminder', 'mhm-rentiva'); ?></h3>
					<p><?php esc_html_e('We will send you a reminder email before your pickup date.', 'mhm-rentiva'); ?></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Support Information -->
	<div class="rv-support-info">
		<h3><?php esc_html_e('Need Help?', 'mhm-rentiva'); ?></h3>
		<p>
			<?php
			/* translators: %s: admin email link */
			echo wp_kses_post(
				sprintf(
					/* translators: %s: admin email address and account link */
					__('If you have any questions or need assistance, please contact us at %s or visit your account page.', 'mhm-rentiva'),
					'<a href="mailto:' . esc_attr(get_option('admin_email')) . '">' . esc_html(get_option('admin_email')) . '</a>'
				)
			);
			?>
		</p>
	</div>
</div>