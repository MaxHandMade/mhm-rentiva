<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard metrics intentionally execute bounded aggregate/admin queries.



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

		// The 3 reserved drag-and-drop/cache-clear AJAX wrappers formerly
		// registered here (wp_ajax_mhmrentiva_clear_dashboard_cache /
		// _save_dashboard_order / _reset_dashboard_layout) were removed:
		// zero shipped nonce producer and zero consumer anywhere -- the
		// React dashboard payload
		// hardcodes 'widget_order' => array() and never reads back what those
		// two would have written. clear_dashboard_cache() below survives: the
		// 6 hook registrations directly beneath this comment all call it live.

		add_action('save_post_mhmrentiva_booking', array( self::class, 'clear_cache_on_booking_change' ));
		add_action('delete_post', array( self::class, 'clear_cache_on_booking_delete' ));
		add_action('save_post_mhmrentiva_vehicle', array( self::class, 'clear_cache_on_vehicle_change' ));
		add_action('save_post_mhmrentiva_message', array( self::class, 'clear_cache_on_message_change' ));
		add_action('mhmrentiva_booking_status_changed', array( self::class, 'clear_dashboard_cache' ));
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
	/**
	 * Shortcuts contributed by add-ons, resolved and made safe to render.
	 *
	 * The default is an EMPTY array on purpose. Lite ships seven shortcuts of
	 * its own, hardcoded in the React component, and it must not know that any
	 * other kind exists: naming a paid destination here -- even to hide it --
	 * would put tier awareness in the free plugin, which is exactly what the
	 * WordPress.org carve-out forbids. Whatever comes back from this filter is
	 * appended after Lite's own, in the order contributed.
	 *
	 * Everything is scrubbed rather than trusted, because a filter takes input
	 * from any plugin on the site and the result is handed to the browser:
	 *
	 *   - href goes through esc_url_raw() restricted to http/https, so a
	 *     `javascript:` shortcut cannot be smuggled into an anchor;
	 *   - icon must look exactly like a dashicon token, or it is replaced --
	 *     it is interpolated into a class attribute, and an unconstrained
	 *     string there is a styling injection;
	 *   - label is sanitised text (React escapes it too, but the payload is
	 *     also readable by anything else that reads the localized data);
	 *   - an entry missing a label or a usable href is dropped rather than
	 *     rendered as an empty box.
	 *
	 * @return list<array{label:string,href:string,icon:string}>
	 */
	public static function get_extra_quick_actions(): array
	{
		$contributed = apply_filters( 'mhmrentiva_dashboard_quick_actions', array() );

		if ( ! is_array( $contributed ) ) {
			return array();
		}

		$clean = array();

		foreach ( $contributed as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
			$href  = isset( $item['href'] ) ? esc_url_raw( (string) $item['href'], array( 'http', 'https' ) ) : '';
			$icon  = isset( $item['icon'] ) ? (string) $item['icon'] : '';

			if ( '' === $label || '' === $href ) {
				continue;
			}

			if ( 1 !== preg_match( '/^dashicons-[a-z0-9-]+$/', $icon ) ) {
				$icon = 'dashicons-admin-generic';
			}

			$clean[] = array(
				'label' => $label,
				'href'  => $href,
				'icon'  => $icon,
			);
		}

		return $clean;
	}

	private static function format_upcoming_items( array $items ): array
	{
		return array_map(
			function ( array $op ): array {
				$op['display_id']   = mhmrentiva_get_display_id( (int) ( $op['id'] ?? 0 ) );
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

	// ajax_save_dashboard_order() and ajax_reset_dashboard_layout() were
	// removed with their wp_ajax_* registrations above.

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
			MHMRENTIVA_PLUGIN_URL . 'build/admin/dashboard.css',
			array(),
			\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'build/admin/dashboard.css' )
		);

		$bookings_result = DashboardService::get_recent_bookings_paginated( 1, 5 );

		// Same first page the REST route would return, so the widget paints from
		// localized data instead of spinning on load -- the pattern recent_bookings
		// above already uses. Page 1, 5 rows, 7-day horizon: the arguments
		// rest_get_upcoming() passes, kept identical on purpose so the first page
		// and every page after it come from one definition.
		$upcoming_result = \MHMRentiva\Admin\Reports\Repository\ReportRepository::get_upcoming_operations_paginated( 1, 5, 7 );

		$data = array(
			'metrics'                     => DashboardService::get_dashboard_metrics(),
			'revenue_data'                => DashboardService::get_revenue_data(),
			'recent_bookings'             => $bookings_result['items'],
			'recent_bookings_total_pages' => $bookings_result['total_pages'],
			'upcoming'                    => self::format_upcoming_items( $upcoming_result['items'] ),
			'upcoming_total_pages'        => (int) $upcoming_result['total_pages'],
			'quick_actions_extra'         => self::get_extra_quick_actions(),
			'metric_deltas'               => DashboardService::get_metric_deltas(),
			'status_breakdown'            => DashboardService::get_status_breakdown(),
			'payments_summary'            => DashboardService::get_payments_summary(),
			'widget_order'                => array(),
			'currency'                    => CurrencyHelper::get_currency_symbol(),
			'admin_url'                   => admin_url(),
		);

		// Generic extension point for add-ons that augment dashboard data.
		$data = apply_filters( 'mhmrentiva_dashboard_localize', $data );

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
		if (get_post_type($post_id) === 'mhmrentiva_booking') {
			self::clear_dashboard_cache();
		}
	}
	public static function clear_cache_on_booking_delete(int $post_id): void
	{
		if (get_post_type($post_id) === 'mhmrentiva_booking') {
			self::clear_dashboard_cache();
		}
	}
	public static function clear_cache_on_vehicle_change(int $post_id): void
	{
		if (get_post_type($post_id) === 'mhmrentiva_vehicle') {
			self::clear_dashboard_cache();
		}
	}
	public static function clear_cache_on_message_change(int $post_id): void
	{
		if (get_post_type($post_id) === 'mhmrentiva_message') {
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
		$watched_keys = array( '_mhmrentiva_status', '_mhmrentiva_payment_status', '_mhmrentiva_total_price' );
		if ( in_array( $meta_key, $watched_keys, true ) && get_post_type( $post_id ) === 'mhmrentiva_booking' ) {
			$cleared = true;
			self::clear_dashboard_cache();
		}
	}

	public static function clear_dashboard_cache(): void
	{
		global $wpdb;
		// Spellings must match what DashboardService and CacheManager actually
		// write. They did not: the writers were renamed to carry the plugin's full
		// prefix and this list kept the old names, so a booking change deleted
		// nothing and the recent-bookings widget stayed stale for up to twelve
		// hours. A cleanup that silently stops matching raises no error anywhere,
		// which is why the names are listed beside their writers here.
		$cache_keys = array(
			// DashboardService::get_recent_bookings() -- 12 hour TTL.
			'mhmrentiva_dashboard_recent_bookings_v4',
			// DashboardService::get_recent_messages() -- per user.
			'mhmrentiva_recent_messages_',
			// CacheManager::CACHE_KEYS entries touched by dashboard widgets.
			'mhmrentiva_dashboard_stats',
			'mhmrentiva_revenue_report_',
			'mhmrentiva_booking_report_',
			'mhmrentiva_customer_report_',
			'mhmrentiva_vehicle_report_',
			'mhmrentiva_vlist_',
			// prefix-rename:ignore-start
			// The add-on's reports cache. Kept deliberately: the add-on writes
			// this family and the dashboard shows figures derived from the same
			// data, so clearing one without the other shows two different numbers
			// on one screen.
			//
			// It USED to be a second, distinct key. The 6.0.0 rename sends both
			// `mhm_rentiva_revenue_report_` and the add-on's `mhm_revenue_report_`
			// to the same new name, so the entry above already covers it and a
			// literal duplicate here would only have documented the collapse.
			//
			// The pre-rename spellings stay listed because a site that has not run
			// the 6.0.0 migration still holds transients under them, and a cache
			// this code believes it cleared but did not is a stale number on the
			// dashboard with no way for the user to flush it.
			'mhm_rentiva_revenue_report_',
			'mhm_revenue_report_',
			'mhm_rentiva_dashboard_recent_bookings_v4',
			'mhm_rentiva_recent_messages_',
			'mhm_rentiva_dashboard_stats',
			'mhm_rentiva_booking_report_',
			'mhm_rentiva_customer_report_',
			'mhm_rentiva_vehicle_report_',
			'mhm_rentiva_vlist_',
			// prefix-rename:ignore-end
		);
		foreach ($cache_keys as $key_prefix) {
			$prefix_like = $wpdb->esc_like('_transient_' . $key_prefix) . '%';
			$wpdb->query($wpdb->prepare("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", $prefix_like));

			$timeout_like = $wpdb->esc_like('_transient_timeout_' . $key_prefix) . '%';
			$wpdb->query($wpdb->prepare("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", $timeout_like));
		}
	}

	// ajax_clear_dashboard_cache() was removed with its wp_ajax_* registration
	// above. clear_dashboard_cache() above survives -- it is called
	// live by the 6 hook registrations in register().
}
