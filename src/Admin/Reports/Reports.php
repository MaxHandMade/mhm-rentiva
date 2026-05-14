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



final class Reports {

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
		add_action('wp_dashboard_setup', array( self::class, 'add_dashboard_widgets' ));
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));
		add_action('rest_api_init', array( \MHMRentiva\Admin\Reports\REST\ReportsRestController::class, 'register_routes' ));
	}

	public static function add_dashboard_widgets(): void
	{
		// Stats widget — always available (basic stats)
		wp_add_dashboard_widget(
			'mhm_rentiva_stats',
			esc_html__('MHM Rentiva Statistics', 'mhm-rentiva'),
			array( self::class, 'render_stats_widget' )
		);

		// Upcoming operations — Pro only (advanced reports feature)
		if ( Mode::canUseAdvancedReports() ) {
			wp_add_dashboard_widget(
				'mhm_rentiva_upcoming_ops',
				esc_html__('Upcoming Operations', 'mhm-rentiva'),
				array( self::class, 'render_upcoming_ops_widget' )
			);
		}
	}

	public static function render_stats_widget(): void
	{
		$stats           = self::get_dashboard_stats();
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
				$occupancy_rate = min(100, round(( $active_bookings / $total_vehicles ) * 100));
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
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Unauthorized access', 'mhm-rentiva') ));
		}

		$type       = isset($_POST['type']) ? sanitize_key(wp_unslash( (string) $_POST['type'])) : '';
		$start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash( (string) $_POST['start_date'])) : gmdate('Y-m-d', strtotime('-30 days'));
		$end_date   = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash( (string) $_POST['end_date'])) : gmdate('Y-m-d');

		// License check
		if (! Mode::canUseAdvancedReports()) {
			$max_days  = Mode::reportsMaxRangeDays();
			$date_diff = ( strtotime($end_date) - strtotime($start_date) ) / ( 60 * 60 * 24 );

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
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => __('Unauthorized access', 'mhm-rentiva') ));
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

	public static function enqueue_scripts( string $hook ): void
	{
		if ( strpos( $hook, 'mhm-rentiva-reports' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'chart-js',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/vendor/chart.min.js',
			array(),
			'3.9.1',
			true
		);

		\MHMRentiva\Admin\Core\AssetManager::enqueue_react_page( 'reports', array( 'chart-js' ) );

		wp_enqueue_style(
			'mhm-rentiva-reports',
			MHM_RENTIVA_PLUGIN_URL . 'build/admin/reports.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		$stats    = self::get_dashboard_stats();
		$currency = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();

		wp_localize_script(
			'mhm-rentiva-react-reports',
			'mhmRentivaReports',
			array(
				'statsCards'   => array(
					'total_bookings'  => $stats['total_bookings'],
					'monthly_revenue' => $stats['monthly_revenue'],
					'active_bookings' => $stats['active_bookings'],
					'occupancy_rate'  => (string) $stats['occupancy_rate'],
				),
				'defaultStart' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
				'defaultEnd'   => gmdate( 'Y-m-d' ),
				'currency'     => $currency,
			)
		);
	}

	/**
	 * Renders the main reports page
	 */
	public function render_page(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
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
		\MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice( 'reports' );
		echo '<div id="mhm-reports-root"></div>';
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

		$status_labels = array(
			'confirmed' => __( 'Confirmed', 'mhm-rentiva' ),
			'pending'   => __( 'Pending', 'mhm-rentiva' ),
			'active'    => __( 'Active', 'mhm-rentiva' ),
		);
		?>
		<style>
			.mhm-upcoming-ops-widget__list { margin: 0; padding: 0; list-style: none; }
			.mhm-upcoming-ops-widget__item { display: block; padding: 10px 12px; margin-bottom: 6px; border-radius: 10px; background: #f9fafb; border: 1px solid #f3f4f6; transition: background 0.15s, border-color 0.15s; text-decoration: none; color: inherit; }
			.mhm-upcoming-ops-widget__item:hover { background: #eff6ff; border-color: #bfdbfe; }
			.mhm-upcoming-ops-widget__header { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
			.mhm-upcoming-ops-widget__icon { flex-shrink: 0; width: 28px; height: 28px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; }
			.mhm-upcoming-ops-widget__icon .dashicons { font-size: 16px; width: 16px; height: 16px; }
			.mhm-upcoming-ops-widget__id { font-size: 13px; color: #1f2937; font-weight: 600; }
			.mhm-upcoming-ops-widget__status { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; margin-left: auto; }
			.mhm-upcoming-ops-widget__status--confirmed { background: #ecfdf5; color: #059669; }
			.mhm-upcoming-ops-widget__status--pending { background: #fef3c7; color: #d97706; }
			.mhm-upcoming-ops-widget__status--active { background: #eff6ff; color: #2563eb; }
			.mhm-upcoming-ops-widget__body { padding-left: 36px; }
			.mhm-upcoming-ops-widget__primary { font-size: 13px; color: #1f2937; line-height: 1.3; }
			.mhm-upcoming-ops-widget__dates { font-size: 12px; color: #6b7280; margin-top: 2px; }
			.mhm-upcoming-ops-widget__customer { font-size: 11px; color: #9ca3af; margin-top: 2px; }
			.mhm-upcoming-ops-widget__empty { text-align: center; padding: 20px 10px; color: #9ca3af; font-size: 13px; }
			.mhm-upcoming-ops-widget__footer { margin-top: 14px; padding-top: 12px; border-top: 1px solid #e5e7eb; text-align: center; }
			.mhm-upcoming-ops-widget__footer a { font-size: 13px; text-decoration: none; color: #2563eb; font-weight: 500; }
			.mhm-upcoming-ops-widget__footer a:hover { text-decoration: underline; }
		</style>
		<div class="mhm-upcoming-ops-widget">
			<?php if ( ! empty( $operations ) ) : ?>
				<ul class="mhm-upcoming-ops-widget__list">
					<?php
                    foreach ( $operations as $op ) :
						$is_transfer = ( $op['type'] === 'transfer' );
						$icon        = $is_transfer ? 'dashicons-airplane' : 'dashicons-car';

						$start_dt    = ! empty( $op['start_time'] ) ? $op['start_date'] . ' ' . $op['start_time'] : $op['start_date'];
						$start_stamp = strtotime( $start_dt );
						$start_fmt   = date_i18n( 'd M', $start_stamp )
							. ( ! empty( $op['start_time'] ) ? ' ' . $op['start_time'] : '' );

						$end_fmt = '';
						if ( ! $is_transfer && ! empty( $op['end_date'] ) ) {
							$end_stamp = strtotime( $op['end_date'] );
							$end_fmt   = date_i18n( 'd M', $end_stamp );
						}

						$customer    = $op['customer_name'];
						$booking_id  = (int) ( $op['id'] ?? 0 );
						$display_id  = $booking_id ? '#' . mhm_rentiva_get_display_id( $booking_id ) : '';
						$booking_url = $booking_id ? admin_url( 'post.php?post=' . $booking_id . '&action=edit' ) : '';
						$tag         = $booking_url ? 'a' : 'div';

						$status        = (string) ( $op['status'] ?? '' );
						$status_label  = $status_labels[ $status ] ?? $status;
						$status_class  = $status ? 'mhm-upcoming-ops-widget__status--' . $status : '';

						// Build primary text (vehicle + plate for rental; route for transfer)
						if ( $is_transfer ) {
							$primary = trim( ( $op['origin'] ?? '' ) . ' → ' . ( $op['destination'] ?? '' ) );
						} else {
							$primary = (string) ( $op['vehicle_title'] ?? '' );
							if ( ! empty( $op['vehicle_plate'] ) ) {
								$primary .= ' · ' . $op['vehicle_plate'];
							}
						}
						?>
						<li>
							<<?php echo esc_attr( $tag ); ?> class="mhm-upcoming-ops-widget__item"<?php echo $booking_url ? ' href="' . esc_url( $booking_url ) . '"' : ''; ?>>
								<div class="mhm-upcoming-ops-widget__header">
									<span class="mhm-upcoming-ops-widget__icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></span>
									<span class="mhm-upcoming-ops-widget__id"><?php echo esc_html( $display_id ); ?></span>
									<?php if ( $status_label ) : ?>
										<span class="mhm-upcoming-ops-widget__status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
									<?php endif; ?>
								</div>
								<div class="mhm-upcoming-ops-widget__body">
									<div class="mhm-upcoming-ops-widget__primary"><?php echo esc_html( $primary ); ?></div>
									<?php if ( $is_transfer ) : ?>
										<div class="mhm-upcoming-ops-widget__dates"><?php echo esc_html( $start_fmt ); ?></div>
									<?php else : ?>
										<div class="mhm-upcoming-ops-widget__dates">
											<?php echo esc_html( $start_fmt ); ?>
											<?php if ( $end_fmt ) : ?> → <?php echo esc_html( $end_fmt ); ?><?php endif; ?>
										</div>
									<?php endif; ?>
									<?php if ( $customer ) : ?>
										<div class="mhm-upcoming-ops-widget__customer"><?php echo esc_html( $customer ); ?></div>
									<?php endif; ?>
								</div>
							</<?php echo esc_attr( $tag ); ?>>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="mhm-upcoming-ops-widget__empty">
					<?php esc_html_e( 'No upcoming operations.', 'mhm-rentiva' ); ?>
				</div>
			<?php endif; ?>
			<div class="mhm-upcoming-ops-widget__footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-dashboard' ) ); ?>">
					<?php esc_html_e( 'View Full Dashboard', 'mhm-rentiva' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}
}
