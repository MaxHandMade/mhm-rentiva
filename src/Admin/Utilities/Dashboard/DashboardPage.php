<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard metrics intentionally execute bounded aggregate/admin queries.



use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\CurrencyHelper;



/**
 * Dashboard page class
 *
 * Manages the high-level dashboard orchestration and AJAX interactions.
 * Rendering is handled by templates, and data logic by DashboardService.
 *
 * @since 4.6.3
 */
final class DashboardPage {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	/**
	 * Register WordPress hooks and actions
	 */
	public static function register(): void
	{
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));

		add_action('wp_ajax_mhm_refresh_dashboard_data', array( self::class, 'ajax_refresh_dashboard_data' ));
		add_action('wp_ajax_mhm_clear_dashboard_cache', array( self::class, 'ajax_clear_dashboard_cache' ));
		add_action('wp_ajax_mhm_save_dashboard_order', array( self::class, 'ajax_save_dashboard_order' ));
		add_action('wp_ajax_mhm_reset_dashboard_layout', array( self::class, 'ajax_reset_dashboard_layout' ));
		add_action('wp_ajax_mhm_upcoming_operations_page', array( self::class, 'ajax_upcoming_operations_page' ));

		add_action('save_post_vehicle_booking', array( self::class, 'clear_cache_on_booking_change' ));
		add_action('delete_post', array( self::class, 'clear_cache_on_booking_delete' ));
		add_action('save_post_vehicle', array( self::class, 'clear_cache_on_vehicle_change' ));
		add_action('save_post_mhm_message', array( self::class, 'clear_cache_on_message_change' ));
		add_action('mhm_rentiva_booking_status_changed', array( self::class, 'clear_dashboard_cache' ));
		add_action('updated_post_meta', array( self::class, 'clear_cache_on_meta_change' ), 10, 4);
		add_action('added_post_meta', array( self::class, 'clear_cache_on_meta_change' ), 10, 4);
	}

	/**
	 * Render dashboard page using the new template system
	 */
	public function render(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		// Fetch all stats from service
		$stats = DashboardService::get_comprehensive_stats();

		// Get saved widget order
		$user_id = get_current_user_id();
		$order   = get_user_meta($user_id, 'mhm_dashboard_widget_order', true);

		// Prepare header buttons
		$buttons = array(
			array(
				'type' => 'documentation',
				'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
			),
			array(
				'type' => 'reset',
				'url'  => '#',
				'id'   => 'mhm-reset-dashboard',
			),
		);

		// Centralized header rendering (echo=false to capture output)
		$admin_header = $this->render_admin_header( (string) get_admin_page_title(), $buttons, false);

		// Capture Developer Mode Banner (if active)
		ob_start();
		$this->render_developer_mode_banner();
		$dev_banner = ob_get_clean();

		// Combine Header + Banner
		$header_html = $admin_header . $dev_banner;

		// Pass to main index template
		Templates::load(
			'admin/dashboard/index',
			array(
				'args' => array(
					'stats'        => $stats,
					'widget_order' => $order ?: array(),
					'header_html'  => $header_html,
				),
			)
		);
	}

	/**
	 * Refresh dashboard data via AJAX
	 */
	public static function ajax_refresh_dashboard_data(): void
	{
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if (! wp_verify_nonce($nonce, 'mhm_dashboard_nonce')) {
			wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
			return;
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
			return;
		}

		try {
			$stats = DashboardService::get_comprehensive_stats();

			// Add extra fields expected by frontend
			$stats['timestamp'] = current_time('mysql');

			wp_send_json_success($stats);
		} catch (\Exception $e) {
			wp_send_json_error(__('Error occurred while fetching data: ', 'mhm-rentiva') . $e->getMessage());
		}
	}

	/**
	 * Save dashboard widget order via AJAX
	 */
	public static function ajax_save_dashboard_order(): void
	{
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if (! wp_verify_nonce($nonce, 'mhm_dashboard_nonce')) {
			wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
			return;
		}

		$order = isset($_POST['order']) ? array_map('sanitize_key', $_POST['order']) : array();
		if (empty($order)) {
			wp_send_json_error(__('Invalid order data', 'mhm-rentiva'));
			return;
		}

		update_user_meta(get_current_user_id(), 'mhm_dashboard_widget_order', $order);
		wp_send_json_success(__('Order saved successfully', 'mhm-rentiva'));
	}

	/**
	 * Reset dashboard layout via AJAX
	 */
	public static function ajax_reset_dashboard_layout(): void
	{
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if (! wp_verify_nonce($nonce, 'mhm_dashboard_nonce')) {
			wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
			return;
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
			return;
		}

		delete_user_meta(get_current_user_id(), 'mhm_dashboard_widget_order');
		wp_send_json_success(__('Dashboard layout reset successfully', 'mhm-rentiva'));
	}

	/**
	 * Load dashboard scripts and styles
	 */
	public static function enqueue_scripts(string $hook): void
	{
		// Target both the top-level page (toplevel_page_mhm-rentiva) and the submenu (mhm-rentiva_page_mhm-rentiva-dashboard)
		if (strpos($hook, 'mhm-rentiva') === false) {
			return;
		}

		// If explicit dashboard slug is not present, check generic top level
		// But wait, other pages like settings also have 'mhm-rentiva' in hook.
		// We only want to load DASHBOARD assets on dashboard.

		$is_dashboard = (
			strpos($hook, 'mhm-rentiva-dashboard') !== false ||
			$hook === 'toplevel_page_mhm-rentiva'
		);

		if (! $is_dashboard) {
			return;
		}

		if (class_exists(AssetManager::class)) {
			AssetManager::enqueue_core_js();
		}

		wp_enqueue_style('mhm-css-variables', MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css', array(), MHM_RENTIVA_VERSION);
		wp_enqueue_style('mhm-core-css', MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/core.css', array( 'mhm-css-variables' ), MHM_RENTIVA_VERSION);
		wp_enqueue_style('mhm-animations', MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/animations.css', array( 'mhm-css-variables' ), MHM_RENTIVA_VERSION);
		wp_enqueue_style('mhm-stats-cards', MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css', array( 'mhm-core-css' ), MHM_RENTIVA_VERSION);
		wp_enqueue_style('mhm-dashboard', MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/dashboard.css', array( 'mhm-stats-cards' ), MHM_RENTIVA_VERSION);
		wp_enqueue_style('mhm-dashboard-tooltips', MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/dashboard-tooltips.css', array( 'mhm-dashboard' ), MHM_RENTIVA_VERSION);

		wp_enqueue_script('chart-js', MHM_RENTIVA_PLUGIN_URL . 'assets/js/vendor/chart.min.js', array(), '3.9.1', true);
		wp_enqueue_script('mhm-dashboard', MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/dashboard.js', array( 'jquery', 'jquery-ui-sortable', 'chart-js' ), MHM_RENTIVA_VERSION, true);

		$currency_symbol = CurrencyHelper::get_currency_symbol();
		$revenue_data    = DashboardService::get_revenue_data();

		wp_localize_script(
			'mhm-dashboard',
			'mhm_dashboard_vars',
			array(
				'ajax_url'     => admin_url('admin-ajax.php'),
				'nonce'        => wp_create_nonce('mhm_dashboard_nonce'),
				'revenue_data' => $revenue_data,
				'currency'     => $currency_symbol,
			)
		);
	}

	/**
	 * Cache Clearing Integration
	 */
	public static function clear_cache_on_booking_change(int $post_id): void
	{
		if (get_post_type($post_id) === 'vehicle_booking') {
			self::clear_dashboard_cache();
		}
	}
	public static function clear_cache_on_booking_delete(int $post_id): void
	{
		if (get_post_type($post_id) === 'vehicle_booking') {
			self::clear_dashboard_cache();
		}
	}
	public static function clear_cache_on_vehicle_change(int $post_id): void
	{
		if (get_post_type($post_id) === 'vehicle') {
			self::clear_dashboard_cache();
		}
	}
	public static function clear_cache_on_message_change(int $post_id): void
	{
		if (get_post_type($post_id) === 'mhm_message') {
			self::clear_dashboard_cache();
		}
	}

	/**
	 * Clear cache when booking-related meta changes (status, payment, etc.).
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $post_id   Post ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function clear_cache_on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ): void {
		static $cleared = false;
		if ( $cleared ) {
			return;
		}
		$watched_keys = array( '_mhm_status', '_mhm_payment_status', '_mhm_total_price' );
		if ( in_array( $meta_key, $watched_keys, true ) && get_post_type( $post_id ) === 'vehicle_booking' ) {
			$cleared = true;
			self::clear_dashboard_cache();
		}
	}

	public static function clear_dashboard_cache(): void
	{
		global $wpdb;
		$cache_keys = array(
			'mhm_dashboard_stats',
			'mhm_dashboard_recent_bookings',
			'mhm_revenue_data',
			'mhm_vehicle_stats',
			'mhm_customer_stats',
			'mhm_message_stats',
			'mhm_recent_messages',
			'mhm_deposit_stats',
			'mhm_pending_payments',
			// WP Dashboard widget caches (CacheManager keys)
			'mhm_rentiva_dashboard_stats',
			// Revenue report caches
			'mhm_revenue_report_',
			'mhm_rentiva_reports_revenue',
			'mhm_rentiva_reports_bookings',
		);
		foreach ($cache_keys as $key_prefix) {
			$prefix_like = $wpdb->esc_like('_transient_' . $key_prefix) . '%';
			$wpdb->query($wpdb->prepare("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", $prefix_like)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$timeout_like = $wpdb->esc_like('_transient_timeout_' . $key_prefix) . '%';
			$wpdb->query($wpdb->prepare("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", $timeout_like)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Return a page of upcoming operations as rendered HTML rows.
	 */
	public static function ajax_upcoming_operations_page(): void
	{
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mhm_dashboard_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', 'mhm-rentiva' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'mhm-rentiva' ) );
			return;
		}

		$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = 5;
		$result   = \MHMRentiva\Admin\Reports\Repository\ReportRepository::get_upcoming_operations_paginated( $page, $per_page, 7 );

		ob_start();
		foreach ( $result['items'] as $op ) {
			$icon      = ( 'transfer' === $op['type'] ) ? 'dashicons-airplane' : 'dashicons-car';
			$date_str  = ! empty( $op['start_time'] ) ? $op['start_date'] . ' ' . $op['start_time'] : $op['start_date'];
			$date_time = strtotime( $date_str );

			$formatted_date = date_i18n( 'd M Y', $date_time );
			$formatted_time = ! empty( $op['start_time'] ) ? esc_html( $op['start_time'] ) : wp_date( 'H:i', $date_time );

			$today    = strtotime( wp_date( 'Y-m-d' ) );
			$tomorrow = strtotime( wp_date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) ) );
			$op_day   = strtotime( wp_date( 'Y-m-d', $date_time ) );

			if ( $op_day === $today ) {
				$day_label = '<strong>' . esc_html__( 'Today', 'mhm-rentiva' ) . '</strong>';
			} elseif ( $op_day === $tomorrow ) {
				$day_label = '<strong>' . esc_html__( 'Tomorrow', 'mhm-rentiva' ) . '</strong>';
			} else {
				$day_label = esc_html( $formatted_date );
			}

			if ( 'transfer' === $op['type'] && ( ! empty( $op['origin'] ) || ! empty( $op['destination'] ) ) ) {
				$route = esc_html( $op['origin'] ?? '-' ) . ' &rarr; ' . esc_html( $op['destination'] ?? '-' );
			} elseif ( 'transfer' === $op['type'] ) {
				$route = '<em class="op-route-unknown">' . esc_html__( 'Transfer', 'mhm-rentiva' ) . '</em>';
			} elseif ( ! empty( $op['vehicle_location'] ) ) {
				$route = '<span class="dashicons dashicons-location op-location-icon"></span> ' . esc_html( $op['vehicle_location'] );
			} else {
				$route = '-';
			}

			$booking_id    = (int) ( $op['id'] ?? 0 );
			$booking_url   = $booking_id ? esc_url( admin_url( 'post.php?post=' . $booking_id . '&action=edit' ) ) : '';
			$vehicle_label = esc_html( $op['vehicle_title'] ?? __( 'VIP Transfer', 'mhm-rentiva' ) );
			if ( ! empty( $op['vehicle_plate'] ) ) {
				$vehicle_label .= ' <small class="op-vehicle-plate">(' . esc_html( $op['vehicle_plate'] ) . ')</small>';
			}

			$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label( $op['status'] );
			$status_class = 'status-' . esc_attr( $op['status'] );

			$countdown_html = '';
			if ( 'confirmed' === $op['status'] ) {
				$diff = $date_time - current_time( 'timestamp' );
				if ( $diff > 0 ) {
					$days    = (int) floor( $diff / DAY_IN_SECONDS );
					$hours   = (int) floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
					$minutes = (int) floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

					if ( $days >= 3 ) {
						$cd_class = 'countdown-green';
						$cd_text  = sprintf( __( '%1$dd %2$dh', 'mhm-rentiva' ), $days, $hours );
					} elseif ( $diff >= DAY_IN_SECONDS ) {
						$cd_class = 'countdown-orange';
						$cd_text  = sprintf( __( '%1$dd %2$dh', 'mhm-rentiva' ), $days, $hours );
					} elseif ( $diff >= HOUR_IN_SECONDS ) {
						$cd_class = 'countdown-red';
						$cd_text  = sprintf( __( '%1$dh %2$dm', 'mhm-rentiva' ), $hours, $minutes );
					} else {
						$cd_class = 'countdown-red';
						$cd_text  = $minutes > 0 ? sprintf( __( '%dm', 'mhm-rentiva' ), $minutes ) : esc_html__( 'Almost there!', 'mhm-rentiva' );
					}

					$countdown_html = '<span class="op-countdown ' . esc_attr( $cd_class ) . '">' . esc_html( $cd_text ) . '</span>';
				}
			}
			?>
			<tr>
				<td class="op-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?> op-type-icon"></span></td>
				<td>
					<?php if ( $booking_url ) : ?>
						<a href="<?php echo esc_url( $booking_url ); ?>" class="op-booking-link">#<?php echo esc_html( $booking_id ); ?></a>
					<?php else : ?>
						-
					<?php endif; ?>
				</td>
				<td><?php echo wp_kses_post( $day_label ); ?><br><small class="op-time-sub"><?php echo esc_html( $formatted_time ); ?></small></td>
				<td><?php echo wp_kses_post( $countdown_html ); ?></td>
				<td><?php echo esc_html( $op['customer_name'] ?: '-' ); ?></td>
				<td><?php echo esc_html( $op['customer_phone'] ?? '-' ); ?></td>
				<td><?php echo wp_kses_post( $vehicle_label ); ?></td>
				<td><?php echo wp_kses_post( $route ); ?></td>
				<td><span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
			</tr>
			<?php
		}
		$rows_html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'        => $rows_html,
				'total'       => $result['total'],
				'total_pages' => $result['total_pages'],
				'page'        => $page,
			)
		);
	}

	public static function ajax_clear_dashboard_cache(): void
	{
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if (! wp_verify_nonce($nonce, 'mhm_clear_cache')) {
			wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
			return;
		}
		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
			return;
		}
		self::clear_dashboard_cache();
		wp_send_json_success(__('Cache cleared successfully', 'mhm-rentiva'));
	}
}
