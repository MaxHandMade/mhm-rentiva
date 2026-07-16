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
			6
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

		/*
		 * 4. Transfer Group (Previously in TransferAdmin)
		 *
		 * TransferAdmin is a Pro seam, so both screens are guarded — without it the
		 * submenus would fatal on `new TransferAdmin()`. There is no
		 * Mode::canUseTransfer(), so class_exists() is the gate.
		 *
		 * The gap escalated here (a Lite site that has the locations table and reads
		 * it, but has no CRUD UI to populate it → permanently empty rental location
		 * pickers) was RESOLVED by the owner on 2026-07-16: Lite has NO location
		 * search — location belongs to Transfer (Pro). Splitting the Locations
		 * screen into core was explicitly rejected. So this guard is now exactly
		 * right: no TransferAdmin, no Locations screen, and no location affordance
		 * anywhere in core either (LocationProvider + REST\Locations moved to Pro in
		 * Task 5a-2; every core call site is guarded). Lite no longer creates the
		 * table. See carveout/faz1-exit-decisions.md §"Task 5a escalation".
		 */
		if (class_exists('\MHMRentiva\Admin\Transfer\TransferAdmin')) {
			add_submenu_page(
				'mhm-rentiva',
				__('Locations', 'mhm-rentiva'),
				__('Locations', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-transfer-locations',
				array( new \MHMRentiva\Admin\Transfer\TransferAdmin(), 'render_locations_page' )
			);

			add_submenu_page(
				'mhm-rentiva',
				__('Transfer Routes', 'mhm-rentiva'),
				__('Transfer Routes', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-transfer-routes',
				array( new \MHMRentiva\Admin\Transfer\TransferAdmin(), 'render_routes_page' )
			);
		}

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
			array( new \MHMRentiva\Admin\Customers\CustomersPage(), 'render' )
		);

		// 7. Reports (Pro feature)
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::canUseAdvancedReports()) {
			add_submenu_page(
				'mhm-rentiva',
				__('Reports', 'mhm-rentiva'),
				__('Reports', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-reports',
				array( new \MHMRentiva\Admin\Reports\Reports(), 'render_page' )
			);
		}

		// 8. Messages (Pro feature)
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::canUseMessages()) {
			add_submenu_page(
				'mhm-rentiva',
				__('Messages', 'mhm-rentiva'),
				__('Messages', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-messages',
				array( new \MHMRentiva\Admin\Messages\Core\Messages(), 'render_messages_page' )
			);
		}

		// 9. Export (Pro feature)
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::canUseExport()) {
			add_submenu_page(
				'mhm-rentiva',
				__('Export', 'mhm-rentiva'),
				__('Export', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-export',
				array( new \MHMRentiva\Admin\Utilities\Export\Export(), 'render_export_page' )
			);
		}

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

		// 14. License (Requested at the very bottom)
		// LicenseAdmin is a Pro seam: the Lite (wp.org) build has no licence to
		// manage, and `new LicenseAdmin()` would fatal without it.
		if (class_exists('\MHMRentiva\Admin\Licensing\LicenseAdmin')) {
			add_submenu_page(
				'mhm-rentiva',
				__('License Management', 'mhm-rentiva'),
				__('License', 'mhm-rentiva'),
				'manage_options',
				'mhm-rentiva-license',
				array( new \MHMRentiva\Admin\Licensing\LicenseAdmin(), 'render_page' )
			);
		}

		// Remove WordPress's automatically created "MHM Rentiva" submenu
		remove_submenu_page('mhm-rentiva', 'mhm-rentiva');
	}

	/**
	 * Vendor (Bayi) menus — registered at priority 15 so they always appear at the bottom.
	 */
	public static function add_bayi_menus(): void
	{
		// 1. Vendor Management (Pro — Vendor Marketplace)
		if (class_exists(\MHMRentiva\Admin\Vendor\AdminVendorApplicationsPage::class)
			&& \MHMRentiva\Admin\Licensing\Mode::canUseVendorMarketplace()
		) {
			\MHMRentiva\Admin\Vendor\AdminVendorApplicationsPage::add_submenu();
		}

		// 2. Vendor Reports (Pro — Vendor Marketplace)
		if (class_exists(\MHMRentiva\Admin\VendorReport\Admin\VendorReportsAdminPage::class)
			&& \MHMRentiva\Admin\Licensing\Mode::canUseVendorMarketplace()
		) {
			\MHMRentiva\Admin\VendorReport\Admin\VendorReportsAdminPage::add_submenu();
		}

		// 3. Payout Requests (Pro — Vendor Marketplace / Payout)
		if (class_exists(\MHMRentiva\Admin\PostTypes\Payouts\PayoutAdminPage::class)
			&& \MHMRentiva\Admin\Licensing\Mode::canUseVendorPayout()
		) {
			// phpcs:disable WordPress.WP.Capabilities.Unknown -- mhm_rentiva_approve_payout is a custom governance capability registered via DatabaseMigrator::register_governance_capabilities().
			add_submenu_page(
				'mhm-rentiva',
				__('Payout Requests', 'mhm-rentiva'),
				__('Payout Requests', 'mhm-rentiva'),
				'mhm_rentiva_approve_payout',
				'mhm-rentiva-payouts',
				array( \MHMRentiva\Admin\PostTypes\Payouts\PayoutAdminPage::class, 'render' )
			);
			// phpcs:enable
		}
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
