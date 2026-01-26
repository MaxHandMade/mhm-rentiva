<?php

/**
 * Email Template: Booking Cancelled
 *
 * Available variables:
 * - $customer_name
 * - $booking_id
 * - $vehicle_name
 * - $pickup_date
 * - $dropoff_date
 * - $reason
 *
 * @package MHMRentiva
 * @since 4.0.0
 */

if (! defined('ABSPATH')) {
	exit;
}

// Get currency symbol
$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();

// Get booking total (if available)
$total_amount   = get_post_meta($booking_id, '_mhm_total_price', true);
$payment_status = get_post_meta($booking_id, '_mhm_payment_status', true);
?>
<div class="booking-cancelled-email">
	<!-- Greeting -->
	<p style="margin: 0 0 20px 0; font-size: 16px; color: #333; line-height: 1.6;">
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %s: customer name */
				__('Hello %s,', 'mhm-rentiva'),
				'<strong>' . esc_html($customer_name) . '</strong>'
			)
		);
		?>
	</p>

	<p style="margin: 0 0 30px 0; font-size: 16px; color: #333; line-height: 1.6;">
		<?php esc_html_e('Your booking has been successfully cancelled. Below are the details of the cancelled booking:', 'mhm-rentiva'); ?>
	</p>

	<!-- Booking Details Box -->
	<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-left: 4px solid #dc3545; margin-bottom: 30px;">
		<tr>
			<td style="padding: 20px;">
				<h2 style="margin: 0 0 15px 0; font-size: 18px; color: #dc3545; font-weight: 600;">
					<?php esc_html_e('Cancelled Booking Details', 'mhm-rentiva'); ?>
				</h2>

				<table width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px;">
					<tr>
						<td style="padding: 8px 0; color: #666; width: 40%;">
							<strong><?php esc_html_e('Booking ID:', 'mhm-rentiva'); ?></strong>
						</td>
						<td style="padding: 8px 0; color: #333;">
							#<?php echo esc_html($booking_id); ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; color: #666;">
							<strong><?php esc_html_e('Vehicle:', 'mhm-rentiva'); ?></strong>
						</td>
						<td style="padding: 8px 0; color: #333;">
							<?php echo esc_html($vehicle_name); ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; color: #666;">
							<strong><?php esc_html_e('Pickup Date:', 'mhm-rentiva'); ?></strong>
						</td>
						<td style="padding: 8px 0; color: #333;">
							<?php echo esc_html($pickup_date); ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0; color: #666;">
							<strong><?php esc_html_e('Dropoff Date:', 'mhm-rentiva'); ?></strong>
						</td>
						<td style="padding: 8px 0; color: #333;">
							<?php echo esc_html($dropoff_date); ?>
						</td>
					</tr>
					<?php if (! empty($total_amount) && $total_amount > 0) : ?>
						<tr>
							<td style="padding: 8px 0; color: #666;">
								<strong><?php esc_html_e('Total Amount:', 'mhm-rentiva'); ?></strong>
							</td>
							<td style="padding: 8px 0; color: #333;">
								<?php echo esc_html($currency_symbol . number_format((float) $total_amount, 2)); ?>
							</td>
						</tr>
					<?php endif; ?>
					<?php if (! empty($reason)) : ?>
						<tr>
							<td style="padding: 8px 0; color: #666; vertical-align: top;">
								<strong><?php esc_html_e('Cancellation Reason:', 'mhm-rentiva'); ?></strong>
							</td>
							<td style="padding: 8px 0; color: #333;">
								<?php echo esc_html($reason); ?>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<td style="padding: 8px 0; color: #666;">
							<strong><?php esc_html_e('Cancellation Date:', 'mhm-rentiva'); ?></strong>
						</td>
						<td style="padding: 8px 0; color: #333;">
							<?php echo esc_html(current_time('Y-m-d H:i')); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<!-- Refund Notice -->
	<?php if ($payment_status === 'paid') : ?>
		<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 30px;">
			<tr>
				<td style="padding: 20px;">
					<h3 style="margin: 0 0 10px 0; font-size: 16px; color: #856404; font-weight: 600;">
						ℹ️ <?php esc_html_e('Refund Information', 'mhm-rentiva'); ?>
					</h3>
					<p style="margin: 0; font-size: 14px; color: #856404; line-height: 1.6;">
						<?php esc_html_e('Your refund has been initiated and will be processed within 5-7 business days. The refund will be credited to the original payment method you used.', 'mhm-rentiva'); ?>
					</p>
				</td>
			</tr>
		</table>
	<?php endif; ?>

	<!-- Message -->
	<p style="margin: 0 0 20px 0; font-size: 14px; color: #666; line-height: 1.6;">
		<?php esc_html_e('We\'re sorry to see your booking cancelled. If you have any questions or would like to make a new reservation, please don\'t hesitate to contact us.', 'mhm-rentiva'); ?>
	</p>

	<!-- Action Button -->
	<table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
		<tr>
			<td align="center">
				<a href="<?php echo esc_url(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_my_account_url', home_url('/my-account/'))); ?>"
					style="display: inline-block; padding: 14px 35px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 15px;">
					<?php esc_html_e('View My Account', 'mhm-rentiva'); ?>
				</a>
			</td>
		</tr>
	</table>

	<p style="margin: 30px 0 0 0; font-size: 14px; color: #333; line-height: 1.6;">
		<?php esc_html_e('Thank you for choosing our service.', 'mhm-rentiva'); ?>
	</p>
</div>