<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard metrics intentionally execute bounded aggregate/admin queries.



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

	/**
	 * Register WordPress hooks and actions
	 */
	public static function register(): void
	{
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));
		add_action( 'rest_api_init', array( self::class, 'register_rest_routes' ) );

		// Reserved: Faz 2 drag-and-drop / cache-clear UI.
		add_action('wp_ajax_mhm_rentiva_clear_dashboard_cache', array( self::class, 'ajax_clear_dashboard_cache' ));
		// Reserved: Faz 2 drag-and-drop / cache-clear UI.
		add_action('wp_ajax_mhm_rentiva_save_dashboard_order', array( self::class, 'ajax_save_dashboard_order' ));
		// Reserved: Faz 2 drag-and-drop / cache-clear UI.
		add_action('wp_ajax_mhm_rentiva_reset_dashboard_layout', array( self::class, 'ajax_reset_dashboard_layout' ));

		add_action('save_post_vehicle_booking', array( self::class, 'clear_cache_on_booking_change' ));
		add_action('delete_post', array( self::class, 'clear_cache_on_booking_delete' ));
		add_action('save_post_vehicle', array( self::class, 'clear_cache_on_vehicle_change' ));
		add_action('save_post_mhm_message', array( self::class, 'clear_cache_on_message_change' ));
		add_action('mhm_rentiva_booking_status_changed', array( self::class, 'clear_dashboard_cache' ));
		add_action('updated_post_meta', array( self::class, 'clear_cache_on_meta_change' ), 10, 4);
		add_action('added_post_meta', array( self::class, 'clear_cache_on_meta_change' ), 10, 4);
	}

	/**
	 * Register REST API routes for the dashboard.
	 */
	public static function register_rest_routes(): void
	{
		register_rest_route(
			'mhm-rentiva/v1',
			'/dashboard/upcoming',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_get_upcoming' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'page' => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/dashboard/recent-bookings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_get_recent_bookings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'page' => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);
	}

	/**
	 * REST callback: return a page of upcoming operations.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 */
	public static function rest_get_upcoming( \WP_REST_Request $request ): \WP_REST_Response
	{
		$page   = (int) $request->get_param( 'page' );
		$result = \MHMRentiva\Admin\Reports\Repository\ReportRepository::get_upcoming_operations_paginated( $page, 5, 7 );
		return new \WP_REST_Response(
			array(
				'items'       => self::format_upcoming_items( $result['items'] ),
				'total'       => (int) $result['total'],
				'total_pages' => (int) $result['total_pages'],
				'page'        => $page,
			)
		);
	}

	/**
	 * REST callback: paginated recent bookings.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 */
	public static function rest_get_recent_bookings( \WP_REST_Request $request ): \WP_REST_Response
	{
		$page   = (int) $request->get_param( 'page' );
		$result = DashboardService::get_recent_bookings_paginated( $page, 5 );
		return new \WP_REST_Response(
			array(
				'items'       => $result['items'],
				'total'       => $result['total'],
				'total_pages' => $result['total_pages'],
				'page'        => $page,
			)
		);
	}

	/**
	 * Format upcoming operation items for the REST response.
	 *
	 * @param array $items Raw items from the repository.
	 * @return array Formatted items.
	 */
	private static function format_upcoming_items( array $items ): array
	{
		return array_map(
			function ( array $op ): array {
				$op['display_id']   = mhm_rentiva_get_display_id( (int) ( $op['id'] ?? 0 ) );
				$op['status_label'] = \MHMRentiva\Admin\Booking\Core\Status::get_label( $op['status'] ?? '' );
				return $op;
			},
			$items
		);
	}

	/**
	 * Render dashboard page — React mount point.
	 */
	public function render(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div id="mhm-rentiva-dashboard"></div>';
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

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
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
	 * Load dashboard scripts and styles — React build.
	 */
	public static function enqueue_scripts( string $hook ): void
	{
		$is_dashboard = (
			str_contains( $hook, 'mhm-rentiva-dashboard' ) ||
			$hook === 'toplevel_page_mhm-rentiva'
		);

		if ( ! $is_dashboard ) {
			return;
		}

		AssetManager::enqueue_react_page( 'dashboard', array() );

		wp_enqueue_style(
			'mhm-rentiva-dashboard',
			MHM_RENTIVA_PLUGIN_URL . 'build/admin/dashboard.css',
			array(),
			\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'build/admin/dashboard.css' )
		);

		$bookings_result = DashboardService::get_recent_bookings_paginated( 1, 5 );

		$data = array(
			'metrics'                     => DashboardService::get_dashboard_metrics(),
			'revenue_data'                => DashboardService::get_revenue_data(),
			'recent_bookings'             => $bookings_result['items'],
			'recent_bookings_total_pages' => $bookings_result['total_pages'],
			'metric_deltas'               => DashboardService::get_metric_deltas(),
			'status_breakdown'            => DashboardService::get_status_breakdown(),
			'payments_summary'            => DashboardService::get_payments_summary(),
			'widget_order'                => array(),
			'currency'                    => CurrencyHelper::get_currency_symbol(),
			'admin_url'                   => admin_url(),
			// Registration gates for add-on quick actions, so the dashboard does
			// not link to pages an inactive add-on would expose. Keys match the
			// `cap` tags in QuickActions.jsx; same gates as the admin menus (Menu.php).
			// Lite ships no keys at all -- a subscriber (the add-on) supplies transfer/
			// reports/vendors/messages/export; QuickActions.jsx already reads
			// `caps[a.cap]`, and a missing JS object key is falsy, so an absent
			// key behaves identically to an explicit `false`.
			'caps'                        => apply_filters( 'mhm_rentiva_dashboard_features', array() ),
		);

		// Seam inversion (Task A5b): Lite ships no transfer data at all -- a
		// subscriber (the add-on's DashboardExtensions) adds `transfer_stats` /
		// `recent_transfers` / `recent_transfers_total_pages` back only when
		// the add-on is active. The React app already guards its
		// TransferWidget render on `transfer_stats` being truthy.
		$data = apply_filters( 'mhm_rentiva_dashboard_localize', $data );

		wp_localize_script(
			'mhm-rentiva-react-dashboard',
			'mhmRentivaDashboard',
			$data
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
