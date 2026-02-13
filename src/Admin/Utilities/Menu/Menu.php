<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Menu;

if (! defined('ABSPATH')) {
	exit;
}

final class Menu
{
	public static function register(): void
	{
		add_action('admin_menu', array(self::class, 'add_menu'), 5); // Priority 5 to run earliest

		// Parent menu fix - Keep menu open when editing Vehicle and Booking
		add_filter('parent_file', array(self::class, 'fix_parent_file'));
		add_filter('submenu_file', array(self::class, 'fix_submenu_file'));

		// Register page hooks (Internal registration logic, without add_submenu_page)
		\MHMRentiva\Admin\Customers\CustomersPage::register();
		\MHMRentiva\Admin\Utilities\Dashboard\DashboardPage::register();
	}

	public static function add_menu(): void
	{
		add_menu_page(
			__('MHM Rentiva', 'mhm-rentiva'),
			__('MHM Rentiva', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva',
			array(new \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage(), 'render'),
			'dashicons-car',
			6
		);

		// 1. Dashboard
		add_submenu_page(
			'mhm-rentiva',
			__('Dashboard', 'mhm-rentiva'),
			__('Dashboard', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-dashboard',
			array(new \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage(), 'render')
		);

		// 2. Vehicles Group
		add_submenu_page(
			'mhm-rentiva',
			__('Vehicles', 'mhm-rentiva'),
			__('Vehicles', 'mhm-rentiva'),
			'manage_options',
			'edit.php?post_type=vehicle'
		);

		add_submenu_page(
			'mhm-rentiva',
			__('Vehicle Categories', 'mhm-rentiva'),
			__('Vehicle Categories', 'mhm-rentiva'),
			'manage_options',
			'edit-tags.php?taxonomy=vehicle_category&post_type=vehicle'
		);

		add_submenu_page(
			'mhm-rentiva',
			__('Vehicle Settings', 'mhm-rentiva'),
			__('Vehicle Settings', 'mhm-rentiva'),
			'manage_options',
			'vehicle-settings',
			array(new \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings(), 'render_settings_page')
		);

		// 3. Bookings
		add_submenu_page(
			'mhm-rentiva',
			__('Bookings', 'mhm-rentiva'),
			__('Bookings', 'mhm-rentiva'),
			'manage_options',
			'edit.php?post_type=vehicle_booking'
		);

		// 4. Transfer Group (Previously in TransferAdmin)
		add_submenu_page(
			'mhm-rentiva',
			__('Transfer Locations', 'mhm-rentiva'),
			__('Transfer Locations', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-transfer-locations',
			array(new \MHMRentiva\Admin\Transfer\TransferAdmin(), 'render_locations_page')
		);

		add_submenu_page(
			'mhm-rentiva',
			__('Transfer Routes', 'mhm-rentiva'),
			__('Transfer Routes', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-transfer-routes',
			array(new \MHMRentiva\Admin\Transfer\TransferAdmin(), 'render_routes_page')
		);

		// 5. Additional Services (Addons)
		add_submenu_page(
			'mhm-rentiva',
			__('Additional Services', 'mhm-rentiva'),
			__('Additional Services', 'mhm-rentiva'),
			'manage_options',
			'edit.php?post_type=vehicle_addon'
		);

		// 6. Customers
		add_submenu_page(
			'mhm-rentiva',
			__('Customers', 'mhm-rentiva'),
			__('Customers', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-customers',
			array(new \MHMRentiva\Admin\Customers\CustomersPage(), 'render')
		);

		// 7. Reports (Pro feature)
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_REPORTS_ADV)) {
			add_submenu_page(
				'mhm-rentiva',
				__('Reports', 'mhm-rentiva'),
				__('Reports', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-reports',
				array(new \MHMRentiva\Admin\Reports\Reports(), 'render_page')
			);
		}

		// 8. Messages (Pro feature)
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)) {
			add_submenu_page(
				'mhm-rentiva',
				__('Messages', 'mhm-rentiva'),
				__('Messages', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-messages',
				array(new \MHMRentiva\Admin\Messages\Core\Messages(), 'render_messages_page')
			);
		}

		// 9. Export (Pro feature)
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_EXPORT)) {
			add_submenu_page(
				'mhm-rentiva',
				__('Export', 'mhm-rentiva'),
				__('Export', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-export',
				array(new \MHMRentiva\Admin\Utilities\Export\Export(), 'render_export_page')
			);
		}

		// 10. Settings
		add_submenu_page(
			'mhm-rentiva',
			__('Settings', 'mhm-rentiva'),
			__('Settings', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-settings',
			array(self::class, 'render_settings_page')
		);

		// 11. Shortcode Pages (Administrative/Frontend Utility)
		if (class_exists(\MHMRentiva\Admin\Settings\ShortcodePages::class)) {
			add_submenu_page(
				'mhm-rentiva',
				__('Shortcode Pages', 'mhm-rentiva'),
				__('Shortcode Pages', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-shortcode-pages',
				array(\MHMRentiva\Admin\Settings\ShortcodePages::register(), 'render_page')
			);
		}

		// 12. Setup Wizard
		if (class_exists(\MHMRentiva\Admin\Setup\SetupWizard::class)) {
			add_submenu_page(
				'mhm-rentiva',
				__('Setup Wizard', 'mhm-rentiva'),
				__('Setup Wizard', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-setup',
				array(new \MHMRentiva\Admin\Setup\SetupWizard(), 'render_page')
			);
		}

		// 13. About
		if (class_exists(\MHMRentiva\Admin\About\About::class)) {
			add_submenu_page(
				'mhm-rentiva',
				__('About', 'mhm-rentiva'),
				__('About', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-about',
				array(new \MHMRentiva\Admin\About\About(), 'render_page')
			);
		}

		// 14. License (Requested at the very bottom)
		add_submenu_page(
			'mhm-rentiva',
			__('License Management', 'mhm-rentiva'),
			__('License', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-license',
			array(new \MHMRentiva\Admin\Licensing\LicenseAdmin(), 'render_page')
		);

		// Remove WordPress's automatically created "MHM Rentiva" submenu
		remove_submenu_page('mhm-rentiva', 'mhm-rentiva');
	}

	public static function slug(): string
	{
		return 'mhm-rentiva';
	}

	/**
	 * Fix parent menu - For Vehicle and Booking post types
	 */
	public static function fix_parent_file($parent_file)
	{
		global $current_screen;

		if (! $current_screen || ! $parent_file) {
			return $parent_file;
		}

		// When in Vehicle post type editor
		if ($current_screen->post_type === 'vehicle') {
			return 'mhm-rentiva';
		}

		// When in Vehicle addon post type editor
		if ($current_screen->post_type === 'vehicle_addon') {
			return 'mhm-rentiva';
		}

		// When in Booking post type editor
		if ($current_screen->post_type === 'vehicle_booking') {
			return 'mhm-rentiva';
		}

		return $parent_file;
	}

	/**
	 * Fix submenu - Mark active page
	 */
	public static function fix_submenu_file($submenu_file)
	{
		global $current_screen, $pagenow;

		if (! $current_screen || $submenu_file === null) {
			return $submenu_file;
		}

		// When in Vehicle post type editor, make "Vehicles" active
		if ($current_screen->post_type === 'vehicle' && ($pagenow === 'post.php' || $pagenow === 'post-new.php')) {
			return 'edit.php?post_type=vehicle';
		}

		// When in Vehicle addon editor, make "Additional Services" active
		if ($current_screen->post_type === 'vehicle_addon' && ($pagenow === 'post.php' || $pagenow === 'post-new.php')) {
			return 'edit.php?post_type=vehicle_addon';
		}

		// When in Booking editor, make "Bookings" active
		if ($current_screen->post_type === 'vehicle_booking' && ($pagenow === 'post.php' || $pagenow === 'post-new.php')) {
			return 'edit.php?post_type=vehicle_booking';
		}

		return $submenu_file;
	}

	/**
	 * Render main settings page
	 *
	 * This function has been moved to SettingsPage class - safe refactoring
	 *
	 * @return void
	 */
	public static function render_settings_page(): void
	{
		// Use new Settings system
		\MHMRentiva\Admin\Settings\Settings::render_settings_page();
	}
}
