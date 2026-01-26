<?php

/**
 * Dashboard Pending Payments Template
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/** @var array $args */
$payments        = $args['payments'] ?? array();
$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
?>

<div class="mhm-dashboard-widget">
	<h3><?php echo esc_html__('Pending Payments', 'mhm-rentiva'); ?></h3>
	<div class="widget-content">
		<?php if (! empty($payments)) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__('Booking', 'mhm-rentiva'); ?></th>
						<th><?php echo esc_html__('Customer', 'mhm-rentiva'); ?></th>
						<th><?php echo esc_html__('Amount', 'mhm-rentiva'); ?></th>
						<th><?php echo esc_html__('Due Date', 'mhm-rentiva'); ?></th>
						<th><?php echo esc_html__('Status', 'mhm-rentiva'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($payments as $payment) :
						$status_class = 'status-' . esc_attr($payment['status'] ?? 'unpaid');
						$row_class    = ($payment['is_overdue'] ?? false) ? 'overdue' : '';
					?>
						<tr class="<?php echo esc_attr($row_class); ?>">
							<td><strong>#<?php echo esc_html($payment['booking_id']); ?></strong></td>
							<td><?php echo esc_html($payment['customer_name']); ?></td>
							<td><?php echo esc_html(number_format((float) ($payment['amount'] ?? 0), 2)); ?> <?php echo esc_html($currency_symbol); ?></td>
							<td><?php echo esc_html($payment['deadline'] ?? '—'); ?></td>
							<td><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($payment['status_label'] ?? __('Unpaid', 'mhm-rentiva')); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="no-data"><?php echo esc_html__('No pending payments found.', 'mhm-rentiva'); ?></p>
		<?php endif; ?>

		<div class="widget-footer">
			<a href="<?php echo esc_url(admin_url('edit.php?post_type=vehicle_booking')); ?>" class="button button-secondary"><?php echo esc_html__('All Pending Payments', 'mhm-rentiva'); ?></a>
		</div>
	</div>
</div>