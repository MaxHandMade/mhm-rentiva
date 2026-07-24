<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Menu;

if (! defined('ABSPATH')) {
	exit;
}

final class Menu {

	public static function register(): void
	{
		add_action('admin_menu', array( self::class, 'add_menu' ), 5);   // Priority 5: main menu items
		add_action('admin_menu', array( self::class, 'add_bayi_menus' ), 15); // Priority 15: vendor menus at the bottom

		// Parent menu fix - Keep menu open when editing Vehicle and Booking
		add_filter('parent_file', array( self::class, 'fix_parent_file' ));
		add_filter('submenu_file', array( self::class, 'fix_submenu_file' ));

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
			array( new \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage(), 'render' ),
			'dashicons-car',
			58
		);

		// 1. Dashboard
		add_submenu_page(
			'mhm-rentiva',
			__('Dashboard', 'mhm-rentiva'),
			__('Dashboard', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-dashboard',
			array( new \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage(), 'render' )
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
			array( new \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings(), 'render_settings_page' )
		);

		// 3. Bookings
		add_submenu_page(
			'mhm-rentiva',
			__('Bookings', 'mhm-rentiva'),
			__('Bookings', 'mhm-rentiva'),
			'manage_options',
			'edit.php?post_type=vehicle_booking'
		);

		// 5. Additional Services (Addons)
		add_submenu_page(
			'mhm-rentiva',
			__('Additional Services', 'mhm-rentiva'),
			__('Additional Services', 'mhm-rentiva'),
			'manage_options',
			'edit.php?post_type=vehicle_addon'
		);

		// 6. Customers — gated on `edit_users`, the capability that matches the data
		// this screen shows (customer PII plus booking/spend history), rather than the
		// generic manage_options. Same capability as the /customers REST routes that
		// feed it, so the menu, the page and the API agree.
		add_submenu_page(
			'mhm-rentiva',
			__('Customers', 'mhm-rentiva'),
			__('Customers', 'mhm-rentiva'),
			'edit_users',
			'mhm-rentiva-customers',
			array( new \MHMRentiva\Admin\Customers\CustomersPage(), 'render' )
		);

		// 10. Settings
		add_submenu_page(
			'mhm-rentiva',
			__('Settings', 'mhm-rentiva'),
			__('Settings', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-settings',
			array( self::class, 'render_settings_page' )
		);

		// 11. Shortcode Pages (Administrative/Frontend Utility)
		if (class_exists(\MHMRentiva\Admin\Settings\ShortcodePages::class)) {
			add_submenu_page(
				'mhm-rentiva',
				__('Shortcode Pages', 'mhm-rentiva'),
				__('Shortcode Pages', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-shortcode-pages',
				array( \MHMRentiva\Admin\Settings\ShortcodePages::register(), 'render_page' )
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
				array( new \MHMRentiva\Admin\Setup\SetupWizard(), 'render_page' )
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
				array( new \MHMRentiva\Admin\About\About(), 'render_page' )
			);
		}

		// Remove WordPress's automatically created "MHM Rentiva" submenu
		remove_submenu_page('mhm-rentiva', 'mhm-rentiva');
	}

	/**
	 * Vendor (Bayi) menus — registered at priority 15 so they always appear at the
	 * bottom. Lite itself has no vendor submenus to add here (all three carved to
	 * the add-on's MenuExtensions::add_pro_bayi_menu_items(), Task A7 seam inversion) —
	 * the hook stays registered so the priority slot is reserved and the add-on's own
	 * later-priority admin_menu callback appends after whatever Lite would have
	 * rendered here.
	 */
	public static function add_bayi_menus(): void
	{
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
		if ($current_screen->post_type === 'vehicle' && ( $pagenow === 'post.php' || $pagenow === 'post-new.php' )) {
			return 'edit.php?post_type=vehicle';
		}

		// When in Vehicle addon editor, make "Additional Services" active
		if ($current_screen->post_type === 'vehicle_addon' && ( $pagenow === 'post.php' || $pagenow === 'post-new.php' )) {
			return 'edit.php?post_type=vehicle_addon';
		}

		// When in Booking editor, make "Bookings" active
		if ($current_screen->post_type === 'vehicle_booking' && ( $pagenow === 'post.php' || $pagenow === 'post-new.php' )) {
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
