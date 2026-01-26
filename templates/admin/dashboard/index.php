<?php

/**
 * Dashboard Main Template
 *
 * @package MHMRentiva
 * @version 1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args Template arguments */
$stats = $args['stats'] ?? array();
?>

<div class="wrap mhm-rentiva-dashboard">
	<div class="mhm-dashboard-header">
		<h1 class="wp-heading-inline"><?php echo esc_html__( 'Control Panel', 'mhm-rentiva' ); ?></h1>
		<div class="header-actions">
			<?php \MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button(); ?>
			<button id="mhm-reset-dashboard" class="button button-secondary">
				<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Reset Layout', 'mhm-rentiva' ); ?>
			</button>
		</div>
	</div>
	<hr class="wp-header-end">

	<div class="mhm-dashboard-content">
		<?php
		// Fixed: Statistics Cards (Not draggable as per user request)
		\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/stats-cards', array( 'metrics' => $stats['metrics'] ?? array() ) );

		// Draggable Widgets Container
		echo '<div id="mhm-dashboard-widgets" class="mhm-sortable-container">';

		$default_order = array(
			'quick-actions',
			'upcoming-operations',
			'transfer-widget',
			'messages-widget',
			'recent-bookings',
			'pending-payments',
			'customer-stats',
			'vehicle-status',
			'revenue-chart',
			'deposit-stats',
			'notifications-widget',
		);

		$order = ! empty( $args['widget_order'] ) ? $args['widget_order'] : $default_order;

		foreach ( $order as $widget_slug ) {
			echo '<div class="mhm-dashboard-widget-wrapper" data-widget="' . esc_attr( $widget_slug ) . '">';
			// Drag handle
			echo '<div class="mhm-widget-drag-handle"><span class="dashicons dashicons-move"></span></div>';

			switch ( $widget_slug ) {
				case 'transfer-widget':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/transfer-widget', array( 'transfer_stats' => $stats['transfer_stats'] ?? array() ) );
					break;
				case 'quick-actions':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/quick-actions' );
					break;
				case 'customer-stats':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/customer-stats', array( 'customer_stats' => $stats['customer_stats'] ?? array() ) );
					break;
				case 'vehicle-status':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/vehicle-status', array( 'vehicle_stats' => $stats['vehicle_stats'] ?? array() ) );
					break;
				case 'messages-widget':
					\MHMRentiva\Admin\Core\Utilities\Templates::load(
						'admin/dashboard/messages-widget',
						array(
							'message_stats'   => $stats['message_stats'] ?? array(),
							'recent_messages' => $stats['recent_messages'] ?? array(),
						)
					);
					break;
				case 'recent-bookings':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/recent-bookings', array( 'bookings' => $stats['recent_bookings'] ?? array() ) );
					break;
				case 'upcoming-operations':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/upcoming-operations' );
					break;
				case 'revenue-chart':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/revenue-chart', array( 'revenue_data' => $stats['revenue_data'] ?? array() ) );
					break;
				case 'notifications-widget':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/notifications-widget', array( 'notifications' => $stats['notifications'] ?? array() ) );
					break;
				case 'deposit-stats':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/deposit-stats', array( 'deposit_stats' => $stats['deposit_stats'] ?? array() ) );
					break;
				case 'pending-payments':
					\MHMRentiva\Admin\Core\Utilities\Templates::load( 'admin/dashboard/pending-payments', array( 'payments' => $stats['pending_payments'] ?? array() ) );
					break;
			}
			echo '</div>';
		}

		echo '</div>'; // End Sortable container
		?>
	</div>
</div>
