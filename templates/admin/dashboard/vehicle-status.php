<?php

/**
 * Dashboard Vehicle Status Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$vehicle_stats = $args['vehicle_stats'] ?? array();
?>

<div class="mhm-dashboard-widget">
	<h3><span class="dashicons dashicons-car"></span> <?php echo esc_html__( 'Vehicle Status', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<div class="vehicle-status-grid">
			<!-- Available Vehicles -->
			<div class="status-item available">
				<div class="status-icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<div class="status-info">
					<div class="status-number"><?php echo esc_html( $vehicle_stats['available'] ?? 0 ); ?></div>
					<div class="status-label"><?php echo esc_html__( 'Available', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<!-- Reserved Vehicles -->
			<div class="status-item reserved">
				<div class="status-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
				<div class="status-info">
					<div class="status-number"><?php echo esc_html( $vehicle_stats['reserved'] ?? 0 ); ?></div>
					<div class="status-label"><?php echo esc_html__( 'Reserved', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<!-- Vehicles Under Maintenance -->
			<div class="status-item maintenance">
				<div class="status-icon"><span class="dashicons dashicons-hammer"></span></div>
				<div class="status-info">
					<div class="status-number"><?php echo esc_html( $vehicle_stats['maintenance'] ?? 0 ); ?></div>
					<div class="status-label"><?php echo esc_html__( 'Maintenance', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

		</div>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=vehicle' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'View All Vehicles', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>