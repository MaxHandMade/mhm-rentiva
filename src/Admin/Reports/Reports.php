<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Reports;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reports orchestrator coordinates bounded aggregate/reporting queries.



// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Public/legacy hook names kept stable for compatibility.

use MHMRentiva\Admin\Reports\BusinessLogic\BookingReport;
use MHMRentiva\Admin\Reports\BusinessLogic\CustomerReport;
use MHMRentiva\Admin\Reports\BusinessLogic\RevenueReport;
use MHMRentiva\Admin\Vehicle\Reports\VehicleReport;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Reports\Repository\ReportRepository;
use MHMRentiva\Admin\Core\Utilities\Templates;



final class Reports
{
	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	/**
	 * Get currency symbol
	 */
	public static function get_currency_symbol(): string
	{
		return \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
	}

	public static function register(): void
	{
		// Add dashboard widgets
		add_action('wp_dashboard_setup', array(self::class, 'add_dashboard_widgets'));

		// AJAX handlers
		add_action('wp_ajax_mhm_rentiva_reports_data', array(self::class, 'ajax_get_data'));

		// Admin scripts
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_scripts'));

		// Cache clearing
		add_action('wp_ajax_mhm_rentiva_clear_reports_cache', array(self::class, 'ajax_clear_cache'));
	}

	public static function add_dashboard_widgets(): void
	{
		// Stats widget — always available (basic stats)
		wp_add_dashboard_widget(
			'mhm_rentiva_stats',
			esc_html__('MHM Rentiva Statistics', 'mhm-rentiva'),
			array(self::class, 'render_stats_widget')
		);

		// Revenue chart and upcoming ops — Pro only (advanced reports feature)
		if ( Mode::canUseAdvancedReports() ) {
			wp_add_dashboard_widget(
				'mhm_rentiva_revenue_chart',
				esc_html__('Revenue Chart', 'mhm-rentiva'),
				array(self::class, 'render_revenue_widget')
			);

			wp_add_dashboard_widget(
				'mhm_rentiva_upcoming_ops',
				esc_html__('Upcoming Operations', 'mhm-rentiva'),
				array(self::class, 'render_upcoming_ops_widget')
			);
		}
	}

	public static function render_stats_widget(): void
	{
		$stats           = static::get_dashboard_stats();
		$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
		$revenue_display = function_exists( 'wc_price' )
			? wp_strip_all_tags( wc_price( $stats['monthly_revenue_raw'] ?? 0 ) )
			: $stats['monthly_revenue'] . ' ' . $currency_symbol;

		$items = array(
			array(
				'icon'  => 'dashicons-calendar-alt',
				'value' => $stats['total_bookings'],
				'label' => __( 'Total Bookings', 'mhm-rentiva' ),
				'color' => '#2563eb',
				'bg'    => '#eff6ff',
			),
			array(
				'icon'  => 'dashicons-money-alt',
				'value' => $revenue_display,
				'label' => __( 'This Month Revenue', 'mhm-rentiva' ),
				'color' => '#059669',
				'bg'    => '#ecfdf5',
			),
			array(
				'icon'  => 'dashicons-car',
				'value' => $stats['active_bookings'],
				'label' => __( 'Active Reservations', 'mhm-rentiva' ),
				'color' => '#d97706',
				'bg'    => '#fffbeb',
			),
			array(
				'icon'  => 'dashicons-chart-pie',
				'value' => $stats['occupancy_rate'] . '%',
				'label' => __( 'Occupancy Rate', 'mhm-rentiva' ),
				'color' => '#7c3aed',
				'bg'    => '#f5f3ff',
			),
		);
		?>
		<style>
			.mhm-stats-widget { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
			.mhm-stats-widget__card { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 10px; border: 1px solid #f3f4f6; transition: box-shadow 0.15s; }
			.mhm-stats-widget__card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
			.mhm-stats-widget__icon { flex-shrink: 0; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
			.mhm-stats-widget__icon .dashicons { font-size: 20px; width: 20px; height: 20px; }
			.mhm-stats-widget__info { min-width: 0; }
			.mhm-stats-widget__value { font-size: 18px; font-weight: 700; line-height: 1.2; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.mhm-stats-widget__label { font-size: 11px; color: #6b7280; margin-top: 2px; }
			.mhm-stats-widget__footer { grid-column: 1 / -1; text-align: center; padding-top: 10px; border-top: 1px solid #e5e7eb; margin-top: 4px; }
			.mhm-stats-widget__footer a { font-size: 13px; text-decoration: none; color: #2563eb; font-weight: 500; }
			.mhm-stats-widget__footer a:hover { text-decoration: underline; }
		</style>
		<div class="mhm-stats-widget">
			<?php foreach ( $items as $item ) : ?>
				<div class="mhm-stats-widget__card">
					<div class="mhm-stats-widget__icon" style="background:<?php echo esc_attr( $item['bg'] ); ?>;">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" style="color:<?php echo esc_attr( $item['color'] ); ?>;"></span>
					</div>
					<div class="mhm-stats-widget__info">
						<div class="mhm-stats-widget__value"><?php echo esc_html( $item['value'] ); ?></div>
						<div class="mhm-stats-widget__label"><?php echo esc_html( $item['label'] ); ?></div>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="mhm-stats-widget__footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-dashboard' ) ); ?>">
					<?php esc_html_e( 'View Full Dashboard', 'mhm-rentiva' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}

	public static function render_revenue_widget(): void
	{
		$start_date = gmdate('Y-m-d', strtotime('-30 days'));
		$end_date   = gmdate('Y-m-d');

		Charts::render_revenue_chart($start_date, $end_date);
	}

	public static function get_dashboard_stats(): array
	{
		// Central cache management
		$stats = false;
		if (class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
			$stats = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache('dashboard_stats');
		}

		if ($stats === false) {
			global $wpdb;

			// Total bookings
			$total_bookings = ReportRepository::get_total_bookings_count();

			// This month revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
			$current_month_start = wp_date( 'Y-m-01' );
			$current_month_end   = wp_date( 'Y-m-t' );
			$monthly_revenue     = ReportRepository::get_monthly_revenue_amount(
				$current_month_start,
				wp_date( 'Y-m-d', strtotime( $current_month_end . ' +1 day' ) )
			);

			// Active bookings
			$active_bookings = ReportRepository::get_active_bookings_count();

			// Occupancy rate (simple calculation)
			$total_vehicles = ReportRepository::get_total_vehicles_count();

			$occupancy_rate = 0;
			if ($total_vehicles > 0 && $active_bookings > 0) {
				$occupancy_rate = min(100, round(($active_bookings / $total_vehicles) * 100));
			}

			$stats = array(
				'total_bookings'      => number_format($total_bookings),
				'monthly_revenue'     => number_format($monthly_revenue, 0, ',', '.'),
				'monthly_revenue_raw' => $monthly_revenue,
				'active_bookings'     => number_format($active_bookings),
				'occupancy_rate'      => $occupancy_rate,
			);

			// Central cache management
			if (class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
				\MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache('dashboard_stats', '', $stats);
			}
		}

		return $stats;
	}

	public static function ajax_get_data(): void
	{
		if (! check_ajax_referer('mhm_reports_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Unauthorized access', 'mhm-rentiva')));
		}

		$type       = isset($_POST['type']) ? sanitize_key(wp_unslash((string) $_POST['type'])) : '';
		$start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash((string) $_POST['start_date'])) : gmdate('Y-m-d', strtotime('-30 days'));
		$end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash((string) $_POST['end_date'])) : gmdate('Y-m-d');

		// License check
		if (! Mode::featureEnabled(Mode::FEATURE_REPORTS_ADV)) {
			$max_days  = Mode::reportsMaxRangeDays();
			$date_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);

			if ($date_diff > $max_days) {
				wp_send_json_error(__('Maximum 30 days of data can be displayed in Lite version.', 'mhm-rentiva'));
				return;
			}
		}

		$data = array();

		try {
			switch ($type) {
				case 'revenue':
					$data = RevenueReport::get_data($start_date, $end_date);
					break;
				case 'bookings':
					$data = BookingReport::get_data($start_date, $end_date);
					break;
				case 'vehicles':
					$data = VehicleReport::get_data($start_date, $end_date);
					break;
				case 'customers':
					$data = CustomerReport::get_data($start_date, $end_date);
					break;
				default:
					wp_send_json_error(__('Invalid report type', 'mhm-rentiva'));
					return;
			}

			wp_send_json_success($data);
		} catch (\Exception $e) {
			wp_send_json_error($e->getMessage());
		}
	}

	/**
	 * Clear reports cache
	 */
	public static function ajax_clear_cache(): void
	{
		if (! check_ajax_referer('mhm_reports_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Unauthorized access', 'mhm-rentiva')));
		}

		// Cache clearing
		$cache_keys = array(
			'mhm_rentiva_reports_revenue',
			'mhm_rentiva_reports_bookings',
			'mhm_rentiva_reports_customers',
			'mhm_rentiva_reports_vehicles',
			'mhm_rentiva_dashboard_stats',
		);

		foreach ($cache_keys as $key) {
			delete_transient($key);
		}

		wp_send_json_success(esc_html__('Cache cleared successfully', 'mhm-rentiva'));
	}

	/**
	 * Clear reports cache - Internal function
	 */
	private static function clear_reports_cache(): void
	{
		// Cache clearing
		$cache_keys = array(
			'mhm_revenue_report_',
			'mhm_booking_report_',
			'mhm_customer_report_',
			'mhm_vehicle_report_',
			'mhm_rentiva_dashboard_stats',
		);

		// Clear all cache keys
		global $wpdb;
		foreach ($cache_keys as $key_prefix) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_' . $key_prefix . '%'
				)
			);
		}
	}

	public static function enqueue_scripts(string $hook): void
	{
		// Load only on reports page and dashboard
		if (strpos($hook, 'mhm-rentiva-reports') === false && $hook !== 'index.php') {
			return;
		}

		// Load core JavaScript files using AssetManager
		if (class_exists('MHMRentiva\\Admin\\Core\\AssetManager')) {
			\MHMRentiva\Admin\Core\AssetManager::enqueue_core_js();
		}

		// Load core CSS files in correct order
		wp_enqueue_style(
			'mhm-css-variables',
			plugins_url('assets/css/core/css-variables.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
			array(),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-core-css',
			plugins_url('assets/css/core/core.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
			array('mhm-css-variables'),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-animations',
			plugins_url('assets/css/core/animations.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
			array('mhm-css-variables'),
			MHM_RENTIVA_VERSION
		);

		// Load statistics cards CSS
		wp_enqueue_style(
			'mhm-stats-cards',
			plugins_url('assets/css/components/stats-cards.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
			array('mhm-core-css'),
			MHM_RENTIVA_VERSION
		);

		// Load admin reports CSS
		wp_enqueue_style(
			'mhm-admin-reports',
			plugins_url('assets/css/admin/admin-reports.css', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
			array('mhm-core-css'),
			(MHM_RENTIVA_VERSION) . '.4' // Add version for cache busting
		);

		// Reports JavaScript
		wp_enqueue_script(
			'mhm-admin-reports',
			plugins_url('assets/js/admin/reports.js', dirname(__DIR__, 3) . '/mhm-rentiva.php'),
			array('jquery'),
			MHM_RENTIVA_VERSION,
			true
		);

		// AJAX nonce for reports
		wp_localize_script('mhm-admin-reports', 'mhm_reports_nonce', array('nonce' => wp_create_nonce('mhm_reports_nonce')));

		Charts::enqueue_scripts();
	}

	/**
	 * Renders the main reports page
	 */
	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		// Pro feature check
		$is_pro = Mode::featureEnabled(Mode::FEATURE_REPORTS_ADV);

		echo '<div class="wrap mhm-rentiva-reports-wrap">';

		$this->render_admin_header(
			(string) get_admin_page_title(),
			array(
				array(
					'type' => 'documentation',
					'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
				),
			)
		);

		// Pro feature notices and Developer Mode banner
		\MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice('reports');

		// Statistics cards - at the top of page
		static::render_stats_cards();

		// Filters (read-only querystring values).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request    = wp_unslash($_GET ?? []);
		$start_date = isset($request['start_date']) ? sanitize_text_field((string) $request['start_date']) : gmdate('Y-m-d', strtotime('-30 days'));
		$end_date   = isset($request['end_date']) ? sanitize_text_field((string) $request['end_date']) : gmdate('Y-m-d');

		// Date validation
		if (! strtotime($start_date) || ! strtotime($end_date)) {
			$start_date = gmdate('Y-m-d', strtotime('-30 days'));
			$end_date   = gmdate('Y-m-d');
		}

		// Date sorting check
		if (strtotime($start_date) > strtotime($end_date)) {
			$temp       = $start_date;
			$start_date = $end_date;
			$end_date   = $temp;
		}

		// Cache clearing check - Only if date parameters exist
		if (isset($request['start_date']) || isset($request['end_date'])) {
			self::clear_reports_cache();
		}

		// Debug: Date filtering check
		if (defined('WP_DEBUG') && WP_DEBUG) {

			// Check available dates in database (using prepared statement for security)
			global $wpdb;
			$available_dates = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(post_date) as date, COUNT(*) as count 
                 FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND post_status = %s 
                 GROUP BY DATE(post_date) 
                 ORDER BY date DESC 
                 LIMIT 10",
					'vehicle_booking',
					'publish'
				)
			);
		}

		echo '<div class="mhm-rentiva-reports-filters">';
		echo '<form method="get" action="" id="reports-filter-form">';
		echo '<input type="hidden" name="page" value="mhm-rentiva-reports">';

		// Preserve current tab
		if (isset($request['tab'])) {
			echo '<input type="hidden" name="tab" value="' . esc_attr(sanitize_key((string) $request['tab'])) . '">';
		}

		echo '<div class="filter-row">';
		echo '<label for="start_date">' . esc_html__('Start Date:', 'mhm-rentiva') . '</label>';
		echo '<input type="date" id="start_date" name="start_date" value="' . esc_attr($start_date) . '" required>';

		echo '<label for="end_date">' . esc_html__('End Date:', 'mhm-rentiva') . '</label>';
		echo '<input type="date" id="end_date" name="end_date" value="' . esc_attr($end_date) . '" required>';

		echo '<button type="submit" class="button button-primary" id="filter-button">' . esc_html__('Filter', 'mhm-rentiva') . '</button>';
		echo '<button type="button" class="button" id="reset-filter">' . esc_html__('Reset', 'mhm-rentiva') . '</button>';
		echo '</div>';

		echo '</form>';
		echo '</div>';

		// Base report tabs (can be extended via filter hook)
		$tabs = array(
			'overview'  => esc_html__('Overview', 'mhm-rentiva'),
			'revenue'   => esc_html__('Revenue Report', 'mhm-rentiva'),
			'bookings'  => esc_html__('Booking Report', 'mhm-rentiva'),
			'vehicles'  => esc_html__('Vehicle Report', 'mhm-rentiva'),
			'customers' => esc_html__('Customer Report', 'mhm-rentiva'),
		);

		/**
		 * Filter: Allow addons and third-party plugins to add custom report tabs
		 *
		 * @param array<string, string> $tabs Array of tab_key => tab_label pairs
		 * @return array Modified tabs array
		 *
		 * @example
		 * add_filter('mhm_rentiva_report_tabs', function($tabs) {
		 *     $tabs['custom-report'] = __('Custom Report', 'my-plugin');
		 *     return $tabs;
		 * });
		 */
		$tabs = apply_filters('mhm_rentiva_report_tabs', $tabs);

		$current_tab = isset($request['tab']) ? sanitize_key((string) $request['tab']) : 'overview';

		echo '<div class="nav-tab-wrapper">';
		foreach ($tabs as $tab => $label) {
			$active = $current_tab === $tab ? ' nav-tab-active' : '';
			$url    = add_query_arg(
				array(
					'tab'        => $tab,
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			);
			echo '<a href="' . esc_url($url) . '" class="nav-tab' . esc_attr($active) . '">' . esc_html($label) . '</a>';
		}
		echo '</div>';

		// Tab content
		echo '<div class="tab-content">';

		// Check if custom tab rendering is handled via action hook
		$custom_tab_handled = false;

		/**
		 * Action: Allow addons to render custom report tabs
		 *
		 * @param string $current_tab Current tab key
		 * @param string $start_date  Start date filter
		 * @param string $end_date    End date filter
		 * @param bool   $handled     Reference to indicate if tab was handled
		 *
		 * @example
		 * add_action('mhm_rentiva_render_report_tab', function($tab, $start_date, $end_date, &$handled) {
		 *     if ($tab === 'custom-report') {
		 *         echo '<h2>Custom Report</h2>';
		 *         // Render custom report...
		 *         $handled = true;
		 *     }
		 * }, 10, 4);
		 */
		do_action_ref_array('mhm_rentiva_render_report_tab', array(&$current_tab, &$start_date, &$end_date, &$custom_tab_handled));

		// If custom tab was handled, skip default rendering
		if (! $custom_tab_handled) {
			switch ($current_tab) {
				case 'overview':
					self::render_overview_tab($start_date, $end_date);
					break;
				case 'revenue':
					self::render_revenue_tab($start_date, $end_date);
					break;
				case 'bookings':
					self::render_bookings_tab($start_date, $end_date);
					break;
				case 'vehicles':
					self::render_vehicles_tab($start_date, $end_date);
					break;
				case 'customers':
					self::render_customers_tab($start_date, $end_date);
					break;
				default:
					// Default case for unknown tabs
					echo '<p>' . esc_html__('Report for this section is not yet implemented.', 'mhm-rentiva') . '</p>';
					break;
			}
		}

		echo '</div>';

		echo '</div>';
	}

	private static function render_overview_tab(string $start_date, string $end_date): void
	{
		// Get data - Real data based on date range
		$revenue_data            = RevenueReport::get_data($start_date, $end_date);
		$booking_data            = BookingReport::get_data($start_date, $end_date);
		$customer_data           = CustomerReport::get_data($start_date, $end_date);
		$vehicle_data            = VehicleReport::get_data($start_date, $end_date);
		$vehicle_categories_data = ReportRepository::get_vehicle_category_performance($start_date, $end_date);

		// Use Repository for customer data
		$real_customers = ReportRepository::get_customer_spending_data($start_date, $end_date);

		Templates::render(
			'admin/reports/overview',
			array(
				'start_date'              => $start_date,
				'end_date'                => $end_date,
				'revenue_data'            => $revenue_data,
				'booking_data'            => $booking_data,
				'customer_data'           => $customer_data,
				'vehicle_data'            => $vehicle_data,
				'vehicle_categories_data' => $vehicle_categories_data,
				'real_customers'          => $real_customers,
			)
		);
	}





	private static function render_revenue_tab(string $start_date, string $end_date): void
	{
		$data = RevenueReport::get_data($start_date, $end_date);

		Templates::render(
			'admin/reports/revenue',
			array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'data'       => $data,
			)
		);
	}

	private static function render_bookings_tab(string $start_date, string $end_date): void
	{
		$data = BookingReport::get_data($start_date, $end_date);

		Templates::render(
			'admin/reports/bookings',
			array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'data'       => $data,
			)
		);
	}

	private static function render_vehicles_tab(string $start_date, string $end_date): void
	{
		$data                    = VehicleReport::get_data($start_date, $end_date);
		$vehicle_categories_data = ReportRepository::get_vehicle_category_performance($start_date, $end_date);

		Templates::render(
			'admin/reports/vehicles',
			array(
				'start_date'              => $start_date,
				'end_date'                => $end_date,
				'data'                    => $data,
				'vehicle_categories_data' => $vehicle_categories_data,
			)
		);
	}

	private static function render_customers_tab(string $start_date, string $end_date): void
	{
		$data = CustomerReport::get_data($start_date, $end_date);

		// Use Repository for customer data
		$real_customers = ReportRepository::get_customer_spending_data($start_date, $end_date);

		// Customer segments
		$customer_segments = array(
			'new'       => 0,
			'returning' => 0,
			'active'    => 0,
			'total'     => 0,
		);

		if (! empty($real_customers)) {
			$customer_segments['total']     = count($real_customers);
			$customer_segments['returning'] = count(
				array_filter(
					$real_customers,
					function ($customer) {
						return $customer->booking_count > 1;
					}
				)
			);
			$customer_segments['new']       = $customer_segments['total'] - $customer_segments['returning'];
			$customer_segments['active']    = $customer_segments['total'];
		}

		Templates::render(
			'admin/reports/customers',
			array(
				'start_date'        => $start_date,
				'end_date'          => $end_date,
				'customer_data'     => $data,
				'real_customers'    => $real_customers,
				'customer_segments' => $customer_segments,
			)
		);
	}

	/**
	 * Render statistics cards
	 */
	private static function render_stats_cards(): void
	{
		$stats = self::get_dashboard_stats();

		Templates::render(
			'admin/reports/stats-cards',
			array(
				'stats'           => $stats,
				'currency_symbol' => self::get_currency_symbol(),
			)
		);
	}

	/**
	 * Render Upcoming Operations Widget for WP Dashboard
	 */
	public static function render_upcoming_ops_widget(): void
	{
		$operations = \MHMRentiva\Admin\Reports\Repository\ReportRepository::get_upcoming_operations(5);

		if (! empty($operations)) {
			echo '<div class="mhm-upcoming-ops-widget">';
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Type', 'mhm-rentiva') . '</th>';
			echo '<th>' . esc_html__('Time', 'mhm-rentiva') . '</th>';
			echo '<th>' . esc_html__('Detail', 'mhm-rentiva') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ($operations as $op) {
				$icon     = ($op['type'] === 'transfer') ? 'dashicons-airplane' : 'dashicons-car';
				$date_str = ! empty($op['start_time'])
					? $op['start_date'] . ' ' . $op['start_time']
					: $op['start_date'];
				$date_time = strtotime($date_str);

				$formatted_date = date_i18n('d M', $date_time);
				$formatted_time = ! empty($op['start_time']) ? esc_html($op['start_time']) : wp_date('H:i', $date_time);

				$customer         = esc_html($op['customer_name']);
				$vehicle_or_route = ($op['type'] === 'transfer')
					? esc_html($op['origin'] ?? '') . ' &rarr; ' . esc_html($op['destination'] ?? '')
					: esc_html($op['vehicle_title'] ?? '');

				$booking_id  = (int) ($op['id'] ?? 0);
				$display_id  = $booking_id ? '#' . mhm_rentiva_get_display_id($booking_id) : '';
				$booking_url = $booking_id ? esc_url(admin_url('post.php?post=' . $booking_id . '&action=edit')) : '';

				echo '<tr>';
				echo '<td style="text-align:center;"><span class="dashicons ' . esc_attr($icon) . '"></span></td>';
				echo '<td>' . esc_html($formatted_date) . '<br><small>' . esc_html($formatted_time) . '</small></td>';
				echo '<td>';
				if ($booking_url) {
					echo '<a href="' . esc_url($booking_url) . '" style="text-decoration:none;">';
				}
				echo '<strong>' . wp_kses_post((string) $vehicle_or_route) . '</strong>';
				echo '<br><small>' . esc_html((string) $customer) . ' ' . esc_html($display_id) . '</small>';
				if ($booking_url) {
					echo '</a>';
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';

			// Footer link
			echo '<div style="margin-top:10px; text-align:right;">';
			echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-dashboard')) . '">' . esc_html__('View Full Dashboard', 'mhm-rentiva') . '</a>';
			echo '</div>';
			echo '</div>';
		} else {
			echo '<p>' . esc_html__('No upcoming operations.', 'mhm-rentiva') . '</p>';
		}
	}
}
