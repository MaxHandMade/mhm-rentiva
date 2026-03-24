<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Main Template
 *
 * @package MHMRentiva
 * @version 1.1.0
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/** @var array $args Template arguments */
$stats = $args['stats'] ?? array();
?>

<div class="wrap mhm-rentiva-dashboard">
	<?php echo $args['header_html'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>


	<div class="mhm-dashboard-content">
		<?php
		// Fixed: Statistics Cards (Not draggable as per user request)
		\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/stats-cards', array('args' => array('metrics' => $stats['metrics'] ?? array())));

		// Draggable Widgets Container
		echo '<div id="mhm-dashboard-widgets" class="mhm-sortable-container">';

		$default_order = array(
			'quick-actions',
			'upcoming-operations',
			'transfer-widget',
			'recent-bookings',
			'pending-payments',
			'revenue-chart',
		);

		$order = ! empty($args['widget_order']) ? $args['widget_order'] : $default_order;

		foreach ($order as $widget_slug) {
			echo '<div class="mhm-dashboard-widget-wrapper" data-widget="' . esc_attr($widget_slug) . '">';
			// Drag handle
			echo '<div class="mhm-widget-drag-handle"><span class="dashicons dashicons-move"></span></div>';

			switch ($widget_slug) {
				case 'transfer-widget':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/transfer-widget', array('args' => array('transfer_stats' => $stats['transfer_stats'] ?? array())));
					break;
				case 'quick-actions':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/quick-actions', array('args' => array('pending_messages' => (int) ( $stats['message_stats']['pending'] ?? 0 ))));
					break;
				case 'customer-stats':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/customer-stats', array('args' => array('customer_stats' => $stats['customer_stats'] ?? array())));
					break;
				case 'vehicle-status':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/vehicle-status', array('args' => array('vehicle_stats' => $stats['vehicle_stats'] ?? array())));
					break;
				case 'messages-widget':
					\MHMRentiva\Admin\Core\Utilities\Templates::load(
						'admin/dashboard/messages-widget',
						array(
							'args' => array(
								'message_stats'   => $stats['message_stats'] ?? array(),
								'recent_messages' => $stats['recent_messages'] ?? array(),
							),
						)
					);
					break;
				case 'recent-bookings':
					\MHMRentiva\Admin\Core\Utilities\Templates::load(
						'admin/dashboard/recent-bookings',
						array(
							'args' => array(
								'bookings'      => $stats['recent_bookings'] ?? array(),
								'booking_stats' => $stats['booking_stats']   ?? array(),
							),
						)
					);
					break;
				case 'upcoming-operations':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/upcoming-operations');
					break;
				case 'revenue-chart':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/revenue-chart', array('args' => array('revenue_data' => $stats['revenue_data'] ?? array())));
					break;
				case 'notifications-widget':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/notifications-widget', array('args' => array('notifications' => $stats['notifications'] ?? array())));
					break;
				case 'deposit-stats':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/deposit-stats', array('args' => array('deposit_stats' => $stats['deposit_stats'] ?? array())));
					break;
				case 'pending-payments':
					\MHMRentiva\Admin\Core\Utilities\Templates::load('admin/dashboard/pending-payments', array('args' => array('payments' => $stats['pending_payments'] ?? array())));
					break;
			}
			echo '</div>';
		}

		echo '</div>'; // End Sortable container
		?>
	</div>
</div>
