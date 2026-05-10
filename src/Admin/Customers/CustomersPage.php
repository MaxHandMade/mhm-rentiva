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
final class CustomersPage {

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
		return sanitize_text_field( (string) $value);
	}

	/**
	 * Register actions and hooks.
	 */
	public static function register(): void
	{
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( \MHMRentiva\Admin\Customers\REST\CustomersRestController::class, 'register_routes' ) );
		add_action( 'admin_post_mhm_rentiva_export_customers', array( \MHMRentiva\Admin\Customers\Export\CustomerExporter::class, 'handle' ) );
		add_action( 'admin_init', array( self::class, 'maybe_create_database_indexes' ) );
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

		echo '<div class="wrap mhm-rentiva-wrap customers-page">';

		$this->render_admin_header(
			(string) get_admin_page_title(),
			array(
				array(
					'type' => 'documentation',
					'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
				),
			)
		);

		\MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice( 'customers' );

		echo '<div id="mhm-customers-root"></div>';
		echo '</div>';
	}


	/**
	 * Load CSS and JS files (via hook)
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void
	{
		if ( strpos( $hook, 'mhm-rentiva-customers' ) === false ) {
			return;
		}

		\MHMRentiva\Admin\Core\AssetManager::enqueue_react_page( 'customers' );

		wp_enqueue_style(
			'mhm-rentiva-customers',
			MHM_RENTIVA_PLUGIN_URL . 'build/admin/customers.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		$stats    = CustomersOptimizer::get_customer_stats_optimized();
		$currency = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();

		wp_localize_script(
			'mhm-rentiva-react-customers',
			'mhmRentivaCustomers',
			array(
				'stats'            => array(
					'total'          => $stats['total']   ?? 0,
					'active'         => $stats['active']  ?? 0,
					'new_this_month' => $stats['new']     ?? 0,
					'monthly_avg'    => $stats['average'] ?? 0,
				),
				'currency'         => $currency,
				'admin_url'        => admin_url(),
				'export_nonce'     => wp_create_nonce( 'mhm_rentiva_export_customers' ),
				'add_customer_url' => admin_url( 'admin.php?page=mhm-rentiva-customers&action=add-customer' ),
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
				'text'  => esc_html__('Customers List', 'mhm-rentiva'),
				'url'   => admin_url('admin.php?page=mhm-rentiva-customers'),
				'class' => 'button',
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
			$customer_name    = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash( (string) $_POST['customer_name'])) : '';
			$customer_email   = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
			$customer_phone   = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash( (string) $_POST['customer_phone'])) : '';
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
				'text'  => esc_html__('View', 'mhm-rentiva'),
				'url'   => admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id),
				'class' => 'button',
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
