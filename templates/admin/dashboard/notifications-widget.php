<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Notifications Widget Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$notifications = $args['notifications'] ?? array();
?>

<div class="mhm-dashboard-widget">
	<h3><?php echo esc_html__( 'System Notifications', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<?php if ( ! empty( $notifications ) ) : ?>
			<ul class="notification-list">
				<?php foreach ( $notifications as $notification ) : ?>
					<li class="notification-item <?php echo esc_attr( $notification['type'] ); ?>">
						<div class="notification-icon">
							<span class="dashicons <?php echo esc_attr( $notification['icon'] ); ?>"></span>
						</div>
						<div class="notification-content">
							<div class="notification-title"><?php echo esc_html( $notification['title'] ); ?></div>
							<div class="notification-message"><?php echo esc_html( $notification['message'] ); ?></div>
							<div class="notification-time"><?php echo esc_html( $notification['time'] ); ?></div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="no-data"><?php echo esc_html__( 'No new notifications.', 'mhm-rentiva' ); ?></p>
		<?php endif; ?>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-settings' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'View Settings', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>