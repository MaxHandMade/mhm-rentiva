<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Customers Page Class.
 *
 * @package MHMRentiva\Admin\Customers
 */


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.





/**
 * Handles the display and processing of the customers management page.
 */
final class CustomersPage
{
	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	/**
	 * Safe sanitize text field that handles null values.
	 *
	 * @param mixed $value Input value.
	 * @return string Sanitized string.
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if (null === $value || '' === $value) {
			return '';
		}
		return sanitize_text_field((string) $value);
	}

	/**
	 * Register actions and hooks.
	 */
	public static function register(): void
	{
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_assets'));

		// Register AJAX actions.
		add_action('wp_ajax_mhm_rentiva_get_customer_stats', array(self::class, 'ajax_get_customer_stats'));
		add_action('wp_ajax_mhm_rentiva_get_customers_data', array(self::class, 'ajax_get_customers_data'));
		add_action('wp_ajax_mhm_rentiva_bulk_action_customers', array(self::class, 'ajax_bulk_action_customers'));
		add_action('wp_ajax_mhm_rentiva_get_customer_details', array(self::class, 'ajax_get_customer_details'));
		add_action('wp_ajax_mhm_rentiva_export_customers', array(self::class, 'ajax_export_customers'));

		// Create database indexes
		add_action('admin_init', array(self::class, 'maybe_create_database_indexes'));

		// Register new customer page hooks
		AddCustomerPage::register();
	}

	/**
	 * Render the customers page.
	 */
	public function render(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		// Check action parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Viewing page action, no data processing.
		$action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));
		if ('' !== $action) {
			switch ($action) {
				case 'add-customer':
					AddCustomerPage::render();
					return;
				case 'view':
					$this->render_customer_view();
					return;
				case 'edit':
					$this->render_customer_edit();
					return;
			}
		}

		// Assets already loaded via hook

		echo '<div class="wrap mhm-rentiva-wrap customers-page">';

		$this->render_admin_header(
			(string) get_admin_page_title(),
			array(
				array(
					'text'   => esc_html__('Add New Customer', 'mhm-rentiva'),
					'url'    => admin_url('admin.php?page=mhm-rentiva-customers&action=add-customer'),
					'class'  => 'button button-primary',
					'icon'   => 'dashicons-plus-alt',
				),
				array(
					'type' => 'documentation',
					'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
				),
			)
		);

		// Customer statistics cards
		static::render_customer_stats();

		// Display Developer Mode banner and limit notices — after KPI cards
		\MHMRentiva\Admin\Core\ProFeatureNotice::displayDeveloperModeAndLimits(
			'customers',
			array(
				__('Unlimited Customers', 'mhm-rentiva'),
				__('Advanced Customer Management', 'mhm-rentiva'),
			)
		);

		// Monthly booking calendar
		static::render_customer_calendar();

		// WordPress standard edit.php style.
		echo '<div class="wrap">';

		// Customer list table.
		$customers_table = new \MHMRentiva\Admin\Utilities\ListTable\CustomersListTable();
		$customers_table->prepare_items();

		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="mhm-rentiva-customers">';
		wp_nonce_field('mhm_rentiva_customers_bulk_action', 'mhm_rentiva_customers_nonce');

		$customers_table->display();

		echo '</form>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render customer monthly booking calendar - same structure as Tools page
	 */
	private static function render_customer_calendar(): void
	{
		// Get month and year from URL parameters, otherwise use current month/year.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
		$current_month = isset($_GET['month']) ? (int) sanitize_text_field(wp_unslash($_GET['month'])) : (int) gmdate('n');
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
		$current_year  = isset($_GET['year']) ? (int) sanitize_text_field(wp_unslash($_GET['year'])) : (int) gmdate('Y');

		// Check invalid values.
		if ($current_month < 1 || $current_month > 12) {
			$current_month = (int) gmdate('n');
		}
		if ($current_year < 2020 || $current_year > 2035) {
			$current_year = (int) gmdate('Y');
		}

		// Locale-aware month name via WordPress i18n.
		$current_month_name = date_i18n( 'F', mktime( 0, 0, 0, $current_month, 1, $current_year ) );
		$days_in_month      = (int) gmdate('t', mktime(0, 0, 0, $current_month, 1, $current_year));
		$today              = gmdate('j');

		// Get customer registration dates
		$customer_registration_days = self::get_customer_registration_days($current_month, $current_year);

?>
		<div class="calendar-container customers-page">
			<div class="calendar-header">
				<button id="prevMonth">&lt;</button>
				<h2 id="monthYear"><?php echo esc_html($current_month_name . ' ' . $current_year); ?> - <?php esc_html_e('Customer Registrations', 'mhm-rentiva'); ?></h2>
				<button id="nextMonth">&gt;</button>
			</div>
			<div class="calendar-days">
				<div class="day-name"><?php esc_html_e('Mon', 'mhm-rentiva'); ?></div>
				<div class="day-name"><?php esc_html_e('Tue', 'mhm-rentiva'); ?></div>
				<div class="day-name"><?php esc_html_e('Wed', 'mhm-rentiva'); ?></div>
				<div class="day-name"><?php esc_html_e('Thu', 'mhm-rentiva'); ?></div>
				<div class="day-name"><?php esc_html_e('Fri', 'mhm-rentiva'); ?></div>
				<div class="day-name"><?php esc_html_e('Sat', 'mhm-rentiva'); ?></div>
				<div class="day-name"><?php esc_html_e('Sun', 'mhm-rentiva'); ?></div>
			</div>
			<div id="calendarDays" class="calendar-grid">
				<?php
				// Find which day of the week the first day of the month is.
				$first_day_of_month = (int) gmdate('w', mktime(0, 0, 0, $current_month, 1, $current_year));
				// Adjust so Monday = 1, Sunday = 0.
				$first_day_of_month = (0 === $first_day_of_month) ? 6 : $first_day_of_month - 1;

				// Last days of previous month
				$prev_month         = $current_month == 1 ? 12 : $current_month - 1;
				$prev_year          = $current_month == 1 ? $current_year - 1 : $current_year;
				$prev_month_date    = new \DateTimeImmutable(
					sprintf('%04d-%02d-01', (int) $prev_year, (int) $prev_month),
					new \DateTimeZone('UTC')
				);
				$prev_days_in_month = (int) $prev_month_date->format('t');

				for ($i = $first_day_of_month; $i > 0; $i--) {
					$day = $prev_days_in_month - $i + 1;
					echo '<div class="prev-date">' . esc_html((string) $day) . '</div>';
				}

				// Days of this month.
				for ($day = 1; $day <= $days_in_month; $day++) {
					$is_today                  = ($day === (int) $today && $current_month === (int) gmdate('n') && $current_year === (int) gmdate('Y'));
					$has_customer_registration = isset($customer_registration_days[$day]);

					$classes = array();
					if ($is_today) {
						$classes[] = 'today';
					}
					if ($has_customer_registration) {
						$classes[] = 'customer-registered';
					}

					echo '<div class="' . esc_attr(implode(' ', $classes)) . '" data-day="' . esc_attr((string) $day) . '">';
					echo esc_html((string) $day);
					if ($has_customer_registration) {
						$customer_info  = $customer_registration_days[$day];
						$customer_name  = $customer_info['name'] ?? 'Unknown';
						$customer_email = $customer_info['email'] ?? '';
						echo '<span class="customer-icon" title="' . esc_attr($customer_name . ' - ' . $customer_email) . '">👤</span>';
					}
					echo '</div>';
				}

				// First days of next month.
				$last_day_of_month = (int) gmdate('w', mktime(0, 0, 0, $current_month, (int) $days_in_month, $current_year));
				$last_day_of_month = (0 === $last_day_of_month) ? 6 : $last_day_of_month - 1;
				$next_days         = 6 - $last_day_of_month;

				for ($j = 1; $j <= $next_days; $j++) {
					echo '<div class="next-date">' . esc_html((string) $j) . '</div>';
				}
				?>
			</div>
		</div>
	<?php
	}

	/**
	 * Get customer data for calendar
	 */
	private static function get_calendar_customers(): array
	{
		// Get customer data (example - adjust according to your actual data source)
		$customers = array();

		// Get customer data from WordPress users
		$users = get_users(
			array(
				'role'    => 'customer',
				'number'  => 20, // First 20 customers
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);

		foreach ($users as $user) {
			$customers[] = array(
				'id'    => $user->ID,
				'name'  => $user->display_name ?: $user->user_login,
				'email' => $user->user_email,
			);
		}

		// Add sample data if no customers
		if (empty($customers)) {
			$customers = array(
				array(
					'id'    => 1,
					'name'  => __('Sample Customer 1', 'mhm-rentiva'),
					'email' => 'ornek1@example.com',
				),
				array(
					'id'    => 2,
					'name'  => __('Sample Customer 2', 'mhm-rentiva'),
					'email' => 'ornek2@example.com',
				),
			);
		}

		return $customers;
	}

	/**
	 * Load CSS and JS files directly (from render method)
	 *
	 * @return void
	 */
	private static function enqueue_assets_direct(): void
	{
		// Load core CSS and JS using AssetManager
		if (class_exists('MHMRentiva\\Admin\\Core\\AssetManager')) {
			\MHMRentiva\Admin\Core\AssetManager::enqueue_core_css();
			\MHMRentiva\Admin\Core\AssetManager::enqueue_core_js();
			\MHMRentiva\Admin\Core\AssetManager::enqueue_stats_cards();
			\MHMRentiva\Admin\Core\AssetManager::enqueue_calendars();
		}
		// Load core CSS files in correct order
		wp_enqueue_style(
			'mhm-css-variables',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css',
			array(),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-core-css',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/core.css',
			array('mhm-css-variables'),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-animations',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/animations.css',
			array('mhm-css-variables'),
			\MHM_RENTIVA_VERSION
		);

		// Component CSS files
		wp_enqueue_style(
			'mhm-stats-cards',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
			array('mhm-core-css'),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-calendars',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/calendars.css',
			array('mhm-core-css'),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-rentiva-customers',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/customers.css',
			array(),
			\MHM_RENTIVA_VERSION
		);

		// JavaScript file
		wp_enqueue_script(
			'mhm-rentiva-customers',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers.js',
			array('jquery'),
			\MHM_RENTIVA_VERSION,
			true
		);

		// Calendar JavaScript file
		wp_enqueue_script(
			'mhm-customers-calendar',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers-calendar.js',
			array(),
			\MHM_RENTIVA_VERSION,
			true
		);

		// Localization
		wp_localize_script(
			'mhm-customers-calendar',
			'mhmCustomersCalendar',
			array(
				'locale'                => str_replace( '_', '-', get_locale() ),
				'strings'               => array(
					'selectedDate' => __('Selected date', 'mhm-rentiva'),
				),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only month/year filters for admin screen rendering.
				'customerRegistrations' => self::get_customer_registration_days((int) (sanitize_text_field(wp_unslash($_GET['month'] ?? gmdate('n')))), (int) (sanitize_text_field(wp_unslash($_GET['year'] ?? gmdate('Y'))))),
			)
		);

		// AJAX için gerekli değişkenler
		wp_localize_script(
			'mhm-rentiva-customers',
			'mhm_rentiva_customers',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('mhm_rentiva_customers_nonce'),
				'strings'  => array(
					'loading'        => __('Loading...', 'mhm-rentiva'),
					'error'          => __('An error occurred.', 'mhm-rentiva'),
					'success'        => __('Operation successful.', 'mhm-rentiva'),
					'confirm_delete' => __('Are you sure you want to delete this customer?', 'mhm-rentiva'),
					'no_customers'   => __('No customers found.', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * Load CSS and JS files (via hook)
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public static function enqueue_assets(string $hook): void
	{
		// Load only on customers page
		if (strpos($hook, 'mhm-rentiva-customers') === false) {
			return;
		}

		// CSS files - same structure as Tools page
		wp_enqueue_style(
			'mhm-stats-cards',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
			array(),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-simple-calendars',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/simple-calendars.css',
			array(),
			\MHM_RENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-rentiva-customers',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/customers.css',
			array(),
			\MHM_RENTIVA_VERSION
		);

		// JavaScript file
		wp_enqueue_script(
			'mhm-rentiva-customers',
			\MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers.js',
			array('jquery'),
			\MHM_RENTIVA_VERSION,
			true
		);

		// Calendar JavaScript file
		wp_enqueue_script(
			'mhm-customers-calendar',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/customers-calendar.js',
			array(),
			MHM_RENTIVA_VERSION,
			true
		);

		// Localization
		wp_localize_script(
			'mhm-customers-calendar',
			'mhmCustomersCalendar',
			array(
				'locale'                => str_replace( '_', '-', get_locale() ),
				'strings'               => array(
					'selectedDate' => __('Selected date', 'mhm-rentiva'),
				),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only month/year filters for admin screen rendering.
				'customerRegistrations' => self::get_customer_registration_days(
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only month filter for admin calendar rendering.
					isset($_GET['month']) ? absint(sanitize_text_field(wp_unslash((string) $_GET['month']))) : (int) gmdate('n'),
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only year filter for admin calendar rendering.
					isset($_GET['year']) ? absint(sanitize_text_field(wp_unslash((string) $_GET['year']))) : (int) gmdate('Y')
				),
			)
		);

		// AJAX için gerekli değişkenler
		wp_localize_script(
			'mhm-rentiva-customers',
			'mhm_rentiva_customers',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('mhm_rentiva_customers_nonce'),
				'strings'  => array(
					'loading'        => __('Loading...', 'mhm-rentiva'),
					'error'          => __('An error occurred.', 'mhm-rentiva'),
					'success'        => __('Operation successful.', 'mhm-rentiva'),
					'confirm_delete' => __('Are you sure you want to delete this customer?', 'mhm-rentiva'),
					'no_customers'   => __('No customers found.', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * Render customer statistics cards
	 *
	 * @return void
	 */
	private static function render_customer_stats(): void
	{
		$stats = self::get_customer_stats();

	?>
		<div class="mhm-stats-cards">
			<div class="stats-grid">
				<!-- Total Customers -->
				<div class="stat-card stat-card-total">
					<div class="stat-icon">
						<span class="dashicons dashicons-groups"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html((string) $stats['total']); ?></div>
						<div class="stat-label"><?php esc_html_e('TOTAL CUSTOMERS', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php echo esc_html((string) $stats['total']); ?> <?php esc_html_e('Registered', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- Active Customers -->
				<div class="stat-card stat-card-active">
					<div class="stat-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html((string) $stats['active']); ?></div>
						<div class="stat-label"><?php esc_html_e('ACTIVE CUSTOMERS', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php echo esc_html((string) $stats['active']); ?> <?php esc_html_e('Active', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- New Customers -->
				<div class="stat-card stat-card-new">
					<div class="stat-icon">
						<span class="dashicons dashicons-plus-alt"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html((string) $stats['new']); ?></div>
						<div class="stat-label"><?php esc_html_e('NEW THIS MONTH', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php echo esc_html((string) $stats['new']); ?> <?php esc_html_e('New', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- Monthly Average Customers -->
				<div class="stat-card stat-card-average">
					<div class="stat-icon">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html((string) $stats['average']); ?></div>
						<div class="stat-label"><?php esc_html_e('MONTHLY AVERAGE CUSTOMERS', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php echo esc_html($stats['average_trend']); ?> <?php esc_html_e('vs last month', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php
	}


	/**
	 * Get customer registration dates for specific month
	 *
	 * @param int $month Month (1-12)
	 * @param int $year Year
	 * @return array Customer registration dates [day => customer_info]
	 */
	private static function get_customer_registration_days(int $month, int $year): array
	{
		global $wpdb;

		$start_date = sprintf('%04d-%02d-01', $year, $month);
		$end_date   = sprintf('%04d-%02d-%02d', $year, $month, gmdate('t', mktime(0, 0, 0, $month, 1, $year)));

		$query = $wpdb->prepare(
			"
            SELECT 
                DAY(u.user_registered) as day,
                u.display_name as customer_name,
                u.user_email as customer_email
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1
                AND u.user_registered >= %s
                AND u.user_registered <= %s
            GROUP BY u.ID, u.user_email, u.display_name, u.user_registered
            ORDER BY day ASC
        ",
			$start_date,
			$end_date . ' 23:59:59'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is correctly prepared above.
		$results = $wpdb->get_results($query);

		$registration_days = array();
		foreach ($results as $result) {
			$day            = (int) $result->day;
			$customer_name  = $result->customer_name ?: 'Unknown';
			$customer_email = $result->customer_email;

			// Store as array if multiple customers on same day
			if (! isset($registration_days[$day])) {
				$registration_days[$day] = array();
			}

			$registration_days[$day][] = array(
				'name'  => $customer_name,
				'email' => $customer_email,
			);
		}

		return $registration_days;
	}

	/**
	 * Get booking days for specific month (optimized)
	 *
	 * @param int $month Month (1-12)
	 * @param int $year Year
	 * @return array Booking days
	 */
	private static function get_booking_days(int $month, int $year): array
	{
		return CustomersOptimizer::get_booking_days_optimized($month, $year);
	}

	/**
	 * Get customer statistics (optimized)
	 *
	 * @return array
	 */
	private static function get_customer_stats(): array
	{
		return CustomersOptimizer::get_customer_stats_optimized();
	}

	/**
	 * AJAX: Get customer statistics
	 */
	public static function ajax_get_customer_stats(): void
	{
		if (! check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('No permission.', 'mhm-rentiva')));
		}

		$stats = self::get_customer_stats();
		wp_send_json_success($stats);
	}

	/**
	 * AJAX: Get customer data
	 */
	public static function ajax_get_customers_data(): void
	{
		if (! check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('No permission.', 'mhm-rentiva')));
		}

		// Return empty data for now
		wp_send_json_success(array());
	}

	/**
	 * AJAX: Bulk action process
	 */
	public static function ajax_bulk_action_customers(): void
	{
		if (! check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('No permission.', 'mhm-rentiva')));
		}

		$action       = sanitize_text_field(wp_unslash($_POST['bulk_action'] ?? ''));
		$customer_ids = array_map('intval', (array) (wp_unslash($_POST['customer_ids'] ?? array())));

		if (empty($action) || empty($customer_ids)) {
			wp_send_json_error(esc_html__('Invalid parameters.', 'mhm-rentiva'));
		}

		// Return success message for now
		wp_send_json_success(
			array(
				/* translators: %d placeholder. */
				'message' => sprintf(esc_html__('%d customers processed.', 'mhm-rentiva'), count($customer_ids)),
			)
		);
	}

	/**
	 * AJAX: Get customer details (optimized)
	 */
	public static function ajax_get_customer_details(): void
	{
		if (! check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('No permission.', 'mhm-rentiva')));
		}

		$customer_id = intval(wp_unslash($_POST['customer_id'] ?? 0));

		if (empty($customer_id)) {
			wp_send_json_error(esc_html__('Customer ID required.', 'mhm-rentiva'));
		}

		$customer_data = CustomersOptimizer::get_customer_details_optimized($customer_id);

		if (! $customer_data) {
			wp_send_json_error(esc_html__('Customer not found.', 'mhm-rentiva'));
		}

		wp_send_json_success($customer_data);
	}

	/**
	 * AJAX: Export customers
	 */
	public static function ajax_export_customers(): void
	{
		if (! check_ajax_referer('mhm_rentiva_customers_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('No permission.', 'mhm-rentiva')));
		}

		// Success message.
		wp_send_json_success(
			array(
				'message' => esc_html__('Export process started.', 'mhm-rentiva'),
			)
		);
	}

	/**
	 * Render customer view page
	 *
	 * @return void
	 */
	private function render_customer_view(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only customer detail view in admin UI.
		if (! isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
			wp_die(esc_html__('Invalid customer ID.', 'mhm-rentiva'));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only customer detail view in admin UI.
		$customer_id = intval(wp_unslash($_GET['customer_id'] ?? 0));
		$customer    = get_user_by('id', $customer_id);

		if (! $customer) {
			wp_die(esc_html__('Customer not found.', 'mhm-rentiva'));
		}

		$buttons = array(
			array(
				'text'   => esc_html__('Customers List', 'mhm-rentiva'),
				'url'    => admin_url('admin.php?page=mhm-rentiva-customers'),
				'class'  => 'button',
			),
		);

		echo '<div class="wrap mhm-customer-view-wrap">';
		$this->render_admin_header(esc_html__('Customer Details', 'mhm-rentiva'), $buttons);

		echo '<div class="customer-details">';
		echo '<table class="form-table">';
		echo '<tbody>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__('Customer Name', 'mhm-rentiva') . '</th>';
		echo '<td><strong>' . esc_html($customer->display_name) . '</strong></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__('Email', 'mhm-rentiva') . '</th>';
		echo '<td><a href="mailto:' . esc_attr($customer->user_email) . '">' . esc_html($customer->user_email) . '</a></td>';
		echo '</tr>';

		$phone = get_user_meta($customer_id, 'mhm_rentiva_phone', true);
		if ($phone) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html__('Phone', 'mhm-rentiva') . '</th>';
			echo '<td>' . esc_html($phone) . '</td>';
			echo '</tr>';
		}

		$address = get_user_meta($customer_id, 'mhm_rentiva_address', true);
		if ($address) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html__('Address', 'mhm-rentiva') . '</th>';
			echo '<td>' . esc_html($address) . '</td>';
			echo '</tr>';
		}

		echo '<tr>';
		echo '<th scope="row">' . esc_html__('Registration Date', 'mhm-rentiva') . '</th>';
		echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($customer->user_registered))) . '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

		// Booking statistics
		$booking_stats = self::get_customer_booking_stats($customer_id);
		if ($booking_stats) {
			echo '<h2>' . esc_html__('Booking Statistics', 'mhm-rentiva') . '</h2>';
			echo '<table class="form-table">';
			echo '<tbody>';

			echo '<tr>';
			echo '<th scope="row">' . esc_html__('Total Bookings', 'mhm-rentiva') . '</th>';
			echo '<td>' . esc_html($booking_stats['booking_count']) . '</td>';
			echo '</tr>';

			echo '<tr>';
			echo '<th scope="row">' . esc_html__('Total Spending', 'mhm-rentiva') . '</th>';
			echo '<td>' . esc_html($booking_stats['total_spent']) . ' ' . esc_html($booking_stats['currency']) . '</td>';
			echo '</tr>';

			echo '<tr>';
			echo '<th scope="row">' . esc_html__('Last Booking', 'mhm-rentiva') . '</th>';
			echo '<td>' . esc_html($booking_stats['last_booking']) . '</td>';
			echo '</tr>';

			echo '<tr>';
			echo '<th scope="row">' . esc_html__('First Booking', 'mhm-rentiva') . '</th>';
			echo '<td>' . esc_html($booking_stats['first_booking']) . '</td>';
			echo '</tr>';

			echo '</tbody>';
			echo '</table>';
		}

		echo '</div>';

		echo '<p class="submit">';
		echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=edit&customer_id=' . $customer_id)) . '" class="button button-primary">' . esc_html__('Edit', 'mhm-rentiva') . '</a>';
		echo ' <a href="' . esc_url(admin_url('edit.php?post_type=vehicle_booking&customer_email=' . $customer->user_email)) . '" class="button">' . esc_html__('View Bookings', 'mhm-rentiva') . '</a>';
		echo ' <a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers')) . '" class="button">' . esc_html__('Go Back', 'mhm-rentiva') . '</a>';
		echo '</p>';

		echo '</div>';
	}

	/**
	 * Get customer booking statistics (optimized)
	 *
	 * @param int $customer_id
	 * @return array|null
	 */
	private static function get_customer_booking_stats(int $customer_id): ?array
	{
		$customer_data = CustomersOptimizer::get_customer_details_optimized($customer_id);

		if (! $customer_data) {
			return null;
		}

		return array(
			'booking_count' => $customer_data['booking_count'],
			'total_spent'   => $customer_data['total_spent'],
			'last_booking'  => $customer_data['last_booking'],
			'first_booking' => $customer_data['first_booking'],
			'currency'      => $customer_data['currency'],
		);
	}

	/**
	 * Render customer edit page
	 *
	 * @return void
	 */
	private function render_customer_edit(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID from URL for edit view only.
		if (! isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
			wp_die(esc_html__('Invalid customer ID.', 'mhm-rentiva'));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID from URL for edit view only.
		$customer_id = intval(wp_unslash($_GET['customer_id'] ?? 0));
		$customer    = get_user_by('id', $customer_id);

		if (! $customer) {
			wp_die(esc_html__('Customer not found.', 'mhm-rentiva'));
		}

		// Form processing.
		if (isset($_POST['submit']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_rentiva_edit_customer_nonce'] ?? '')), 'mhm_rentiva_edit_customer')) {
			$customer_name    = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash((string) $_POST['customer_name'])) : '';
			$customer_email   = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
			$customer_phone   = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash((string) $_POST['customer_phone'])) : '';
			$customer_address = sanitize_textarea_field(wp_unslash($_POST['customer_address'] ?? ''));

			if (empty($customer_name) || empty($customer_email)) {
				echo '<div class="notice notice-error"><p>' . esc_html__('Customer name and email fields are required.', 'mhm-rentiva') . '</p></div>';
			} else {
				// Update user information
				wp_update_user(
					array(
						'ID'           => $customer_id,
						'display_name' => $customer_name,
						'user_email'   => $customer_email,
						'first_name'   => $customer_name,
					)
				);

				// Update meta information
				update_user_meta($customer_id, 'mhm_rentiva_phone', $customer_phone);
				update_user_meta($customer_id, 'mhm_rentiva_address', $customer_address);

				// Clear cache
				\MHMRentiva\Admin\Customers\CustomersOptimizer::clear_cache($customer_id);

				echo '<div class="notice notice-success"><p>' . esc_html__('Customer information updated successfully.', 'mhm-rentiva') . '</p></div>';

				// Get updated information
				$customer = get_user_by('id', $customer_id);
			}
		}

		$buttons = array(
			array(
				'text'   => esc_html__('View', 'mhm-rentiva'),
				'url'    => admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id),
				'class'  => 'button',
			),
		);

		echo '<div class="wrap mhm-customer-edit-wrap">';
		$this->render_admin_header(esc_html__('Edit Customer', 'mhm-rentiva'), $buttons);

		echo '<form method="post" action="">';
		wp_nonce_field('mhm_rentiva_edit_customer', 'mhm_rentiva_edit_customer_nonce');

		echo '<table class="form-table">';
		echo '<tbody>';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_name">' . esc_html__('Customer Name', 'mhm-rentiva') . '</label></th>';
		echo '<td><input name="customer_name" type="text" id="customer_name" value="' . esc_attr($customer->display_name) . '" class="regular-text" required /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_email">' . esc_html__('Email', 'mhm-rentiva') . '</label></th>';
		echo '<td><input name="customer_email" type="email" id="customer_email" value="' . esc_attr($customer->user_email) . '" class="regular-text" required /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_phone">' . esc_html__('Phone', 'mhm-rentiva') . '</label></th>';
		echo '<td><input name="customer_phone" type="tel" id="customer_phone" value="' . esc_attr(get_user_meta($customer_id, 'mhm_rentiva_phone', true)) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="customer_address">' . esc_html__('Address', 'mhm-rentiva') . '</label></th>';
		echo '<td><textarea name="customer_address" id="customer_address" rows="3" cols="50" class="large-text">' . esc_textarea(get_user_meta($customer_id, 'mhm_rentiva_address', true)) . '</textarea></td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

		echo '<p class="submit">';
		echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr__('Update', 'mhm-rentiva') . '">';
		echo ' <a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id)) . '" class="button">' . esc_html__('Cancel', 'mhm-rentiva') . '</a>';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Create database indexes (runs once)
	 *
	 * @return void
	 */
	public static function maybe_create_database_indexes(): void
	{
		// Only for admin users and runs once
		if (! current_user_can('manage_options') || get_option('mhm_rentiva_customers_indexes_created')) {
			return;
		}

		// Create indexes
		$success = \MHMRentiva\Admin\Customers\CustomersOptimizer::create_database_indexes();

		if ($success) {
			// Mark that indexes have been created
			update_option('mhm_rentiva_customers_indexes_created', true);
		}
	}
}
