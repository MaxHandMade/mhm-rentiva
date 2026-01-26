<?php

/**
 * Dashboard Quick Actions Template
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}
?>

<div class="mhm-quick-actions">
	<h2><?php echo esc_html__('Quick Actions', 'mhm-rentiva'); ?></h2>
	<div class="quick-actions-grid">
		<a href="<?php echo esc_url(admin_url('post-new.php?post_type=vehicle')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-plus-alt"></span>
			<span class="action-title"><?php echo esc_html__('Add Vehicle', 'mhm-rentiva'); ?></span>
		</a>

		<!-- New Booking -->
		<a href="<?php echo esc_url(admin_url('post-new.php?post_type=vehicle_booking')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-calendar-alt"></span>
			<span class="action-title"><?php echo esc_html__('New Booking', 'mhm-rentiva'); ?></span>
		</a>

		<!-- Add Customer -->
		<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=add-customer')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-admin-users"></span>
			<span class="action-title"><?php echo esc_html__('Add Customer', 'mhm-rentiva'); ?></span>
		</a>

		<!-- Add Transfer Route -->
		<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-routes')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-randomize"></span>
			<span class="action-title"><?php echo esc_html__('Add Route', 'mhm-rentiva'); ?></span>
		</a>

		<!-- Reports -->
		<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-reports')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-chart-bar"></span>
			<span class="action-title"><?php echo esc_html__('Reports', 'mhm-rentiva'); ?></span>
		</a>

		<!-- Settings -->
		<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-admin-settings"></span>
			<span class="action-title"><?php echo esc_html__('Settings', 'mhm-rentiva'); ?></span>
		</a>

		<!-- Email Templates -->
		<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=email-templates')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-email-alt"></span>
			<span class="action-title"><?php echo esc_html__('Email Tabs', 'mhm-rentiva'); ?></span>
		</a>

		<!-- Additional Services -->
		<a href="<?php echo esc_url(admin_url('post-new.php?post_type=vehicle_addon')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-admin-tools"></span>
			<span class="action-title"><?php echo esc_html__('Add-on Svc', 'mhm-rentiva'); ?></span>
		</a>

		<!-- Messages -->
		<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-messages')); ?>" class="quick-action-card">
			<span class="dashicons dashicons-format-chat"></span>
			<span class="action-title"><?php echo esc_html__('Messages', 'mhm-rentiva'); ?></span>
		</a>
	</div>
</div>