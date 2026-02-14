<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Messages Widget Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$message_stats   = $args['message_stats'] ?? array();
$recent_messages = $args['recent_messages'] ?? array();
?>

<div class="mhm-dashboard-widget">
	<h3><span class="dashicons dashicons-format-chat"></span> <?php echo esc_html__( 'Messages', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<!-- Message statistics -->
		<div class="message-stats-grid">
			<!-- Pending Messages -->
			<div class="stat-item pending">
				<div class="stat-icon"><span class="dashicons dashicons-clock"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $message_stats['pending'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Pending', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<!-- Answered Messages -->
			<div class="stat-item answered">
				<div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $message_stats['answered'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Answered', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<!-- Total Messages -->
			<div class="stat-item total">
				<div class="stat-icon"><span class="dashicons dashicons-email-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $message_stats['total'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Total', 'mhm-rentiva' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Recent messages list -->
		<?php if ( ! empty( $recent_messages ) ) : ?>
			<div class="recent-messages">
				<h4><?php echo esc_html__( 'Recent Messages', 'mhm-rentiva' ); ?></h4>
				<ul class="message-list">
					<?php
					foreach ( $recent_messages as $message ) :
						$status_class = 'status-' . esc_attr( $message['status'] );
						?>
						<li class="message-item <?php echo esc_attr( $status_class ); ?>">
							<div class="message-header">
								<span class="customer-name"><?php echo esc_html( $message['customer_name'] ); ?></span>
								<span class="message-date"><?php echo esc_html( $message['date'] ); ?></span>
							</div>
							<div class="message-preview"><?php echo esc_html( wp_trim_words( $message['content'], 15 ) ); ?></div>
							<div class="message-status">
								<span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $message['status_label'] ); ?></span>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php else : ?>
			<p class="no-data"><?php echo esc_html__( 'No messages found yet.', 'mhm-rentiva' ); ?></p>
		<?php endif; ?>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mhm_message' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'View All Messages', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>