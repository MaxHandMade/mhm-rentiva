<?php
declare(strict_types=1);

namespace MHMRentiva;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.



/**
 * MHM Rentiva Plugin - Main plugin class
 *
 * This class initializes and coordinates all plugin services.
 * Designed in a modular structure where each service takes its own responsibility.
 */
final class Plugin
{

	public const VERSION = MHM_RENTIVA_VERSION;

	private static ?self $instance = null;

	/**
	 * Bootstrap flag (performance optimization - static flag instead of DB query)
	 */
	private static bool $bootstrapped = false;

	/**
	 * Service init guard for current request.
	 */
	private bool $services_initialized = false;

	/**
	 * Initialize plugin
	 */
	public static function bootstrap(): void
	{
		// Singleton - initialize only once (static flag, no unnecessary DB query)
		if (self::$instance !== null || self::$bootstrapped) {
			return;
		}

		// Mark bootstrap flag and create instance
		self::$bootstrapped = true;
		self::$instance     = new self();
	}

	/**
	 * Check if class exists
	 */
	private function is_class_available(string $class): bool
	{
		return class_exists($class);
	}

	/**
	 * Plugin constructor - initialize all services
	 */
	private function __construct()
	{
		// Ensure theme support for thumbnails
		add_action('after_setup_theme', array($this, 'setup_theme_support'));

		// Load text domain
		// Priority 1: Load translations before any output
		add_action('init', array($this, 'load_textdomain'), 1);

		// Register Customer role (also for existing installations)
		// Priority 20: Run after WooCommerce and other plugins that might register customer role
		add_action('init', array(self::class, 'register_customer_role'), 20);

		// Register Vendor role alongside Customer role
		add_action('init', array(self::class, 'register_vendor_role'), 20);

		// Apply license limits
		add_filter('wp_insert_post_data', array($this, 'enforce_limits'), 10, 2);

		// Cache invalidation hooks
		add_action('save_post', array($this, 'invalidate_cache_on_save'));
		add_action('delete_post', array($this, 'invalidate_cache_on_delete'));
		add_action('updated_post_meta', array($this, 'invalidate_cache_on_meta_update'), 10, 4);

		// Delay service graph init until after textdomain is loaded on `init`.
		add_action('init', array($this, 'initialize_services'), 2);
	}

	/**
	 * Initialize plugin service graph once per request.
	 */
	public function initialize_services(): void
	{
		if ($this->services_initialized) {
			return;
		}
		$this->services_initialized = true;

		// Initialize services
		$this->initialize_core_services();
		$this->cleanup_pro_only_schedules();

		// Register currency helper filter hooks
		\MHMRentiva\Admin\Core\CurrencyHelper::register_hooks();

		if (is_admin()) {
			$this->initialize_admin_services();
		}

		// Initialize PostTypes after admin services
		$this->initialize_post_types();

		$this->initialize_remaining_services();
		$this->initialize_additional_services();
		$this->initialize_deposit_services();
		$this->initialize_system_services();

		// Frontend services (also works outside admin)
		$this->initialize_frontend_services();
	}

	/**
	 * Remove stale schedules for Pro-only modules when running in Lite mode.
	 */
	private function cleanup_pro_only_schedules(): void
	{
		if (\MHMRentiva\Admin\Licensing\Mode::canUseVendorPayout()) {
			return;
		}

		wp_clear_scheduled_hook('mhm_rentiva_process_matured_payouts');
	}


	/**
	 * Initialize core services
	 */
	private function initialize_core_services(): void
	{
		// Asset Manager - Central CSS and JS file management
		if ($this->is_class_available('MHMRentiva\Admin\Core\AssetManager')) {
			\MHMRentiva\Admin\Core\AssetManager::init();
		}

		// Governance Enforcement
		if ($this->is_class_available('\MHMRentiva\Admin\Core\Governance')) {
			(new \MHMRentiva\Admin\Core\Governance())->register();
		}

		// WordPress optimizer
		if ($this->is_class_available('\MHMRentiva\Admin\Core\Utilities\WordPressOptimizer')) {
			\MHMRentiva\Admin\Core\Utilities\WordPressOptimizer::register();
		}

		// Styles - Compatible loading with AssetManager
		// Use Styles if AssetManager is not loaded
		if (! $this->is_class_available('MHMRentiva\Admin\Core\AssetManager')) {
			if ($this->is_class_available('MHMRentiva\Admin\Core\Utilities\Styles')) {
				$styles = new \MHMRentiva\Admin\Core\Utilities\Styles(
					MHM_RENTIVA_PLUGIN_DIR,
					MHM_RENTIVA_PLUGIN_URL
				);
				$styles->register();
			}
		}

		// â­ CRITICAL: AutoCancel MUST run in ALL contexts (admin, frontend, cron)
		// Previously was inside is_admin() block which prevented cron from working!
		if ($this->is_class_available('\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel')) {
			\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::register();
		}

		if ($this->is_class_available('\MHMRentiva\Admin\PostTypes\Maintenance\AutoComplete')) {
			\MHMRentiva\Admin\PostTypes\Maintenance\AutoComplete::register();
		}

		// Log Maintenance
		if ($this->is_class_available('\MHMRentiva\Admin\PostTypes\Maintenance\LogRetention')) {
			\MHMRentiva\Admin\PostTypes\Maintenance\LogRetention::register();
		}
		if ($this->is_class_available('\MHMRentiva\Admin\PostTypes\Maintenance\EmailLogRetention')) {
			\MHMRentiva\Admin\PostTypes\Maintenance\EmailLogRetention::register();
		}
		if ($this->is_class_available('\MHMRentiva\Admin\Core\Utilities\LogMaintenanceScheduler')) {
			\MHMRentiva\Admin\Core\Utilities\LogMaintenanceScheduler::init();
		}

		// Privacy and Data Retention
		if ($this->is_class_available('\MHMRentiva\Admin\Privacy\DataRetentionManager')) {
			\MHMRentiva\Admin\Privacy\DataRetentionManager::init();
		}

		// GDPR Manager (registers AJAX handlers for data export, deletion, consent withdrawal)
		if ($this->is_class_available('\MHMRentiva\Admin\Privacy\GDPRManager')) {
			\MHMRentiva\Admin\Privacy\GDPRManager::init();
		}

		// Notification Management
		if ($this->is_class_available('\MHMRentiva\Admin\Notifications\NotificationManager')) {
			\MHMRentiva\Admin\Notifications\NotificationManager::init();
		}
	}

	/**
	 * Initialize post types and taxonomies
	 */
	private function initialize_post_types(): void
	{
		// Vehicle post type
		if ($this->is_class_available('MHMRentiva\Admin\Vehicle\PostType\Vehicle')) {
			\MHMRentiva\Admin\Vehicle\PostType\Vehicle::register();
		}

		// Booking post type
		if ($this->is_class_available('MHMRentiva\Admin\Booking\PostType\Booking')) {
			\MHMRentiva\Admin\Booking\PostType\Booking::register();
		}

		if ($this->is_class_available('\MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory')) {
			\MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::register();
		}

		// Payout workflow storage (Pro only).
		if (
			\MHMRentiva\Admin\Licensing\Mode::canUseVendorPayout() &&
			$this->is_class_available('\MHMRentiva\Admin\PostTypes\Payouts\PostType')
		) {
			\MHMRentiva\Admin\PostTypes\Payouts\PostType::register();
		}

		// Vendor onboarding applications.
		if ($this->is_class_available('\MHMRentiva\Admin\Vendor\PostType\VendorApplication')) {
			\MHMRentiva\Admin\Vendor\PostType\VendorApplication::register();
		}

		// Vendor media isolation — restrict media library to own uploads.
		if ($this->is_class_available('\MHMRentiva\Admin\Vendor\VendorMediaIsolation')) {
			\MHMRentiva\Admin\Vendor\VendorMediaIsolation::register();
		}

		// Vendor ownership enforcement — deny editing/deleting other vendors' vehicles.
		if ($this->is_class_available('\MHMRentiva\Admin\Vendor\VendorOwnershipEnforcer')) {
			\MHMRentiva\Admin\Vendor\VendorOwnershipEnforcer::register();
		}

		// Vendor vehicle review — trigger re-review when vendor edits critical fields.
		if ($this->is_class_available('\MHMRentiva\Admin\Vendor\VendorVehicleReviewManager')) {
			\MHMRentiva\Admin\Vendor\VendorVehicleReviewManager::register();
		}

		// Vendor email notifications — hook into vendor/vehicle lifecycle actions.
		if ($this->is_class_available('\MHMRentiva\Admin\Emails\Notifications\VendorNotifications')) {
			\MHMRentiva\Admin\Emails\Notifications\VendorNotifications::register();
		}
	}

	/**
	 * Initialize admin services
	 */
	private function initialize_admin_services(): void
	{
		// Settings
		if ($this->is_class_available('MHMRentiva\Admin\Settings\Settings')) {
			\MHMRentiva\Admin\Settings\Settings::register();
		}

		// ShortcodeUrlManager is autoloaded when used

		// Shortcode Pages
		if ($this->is_class_available('MHMRentiva\Admin\Settings\ShortcodePages')) {
			\MHMRentiva\Admin\Settings\ShortcodePages::register();
		}

		// Email templates
		if ($this->is_class_available('\MHMRentiva\Admin\Emails\Core\EmailTemplates')) {
			\MHMRentiva\Admin\Emails\Core\EmailTemplates::register();
		}
		if ($this->is_class_available('\MHMRentiva\Admin\Emails\Core\Templates')) {
			\MHMRentiva\Admin\Emails\Core\Templates::register();
		}

		// Admin menu
		if ($this->is_class_available('MHMRentiva\Admin\Utilities\Menu\Menu')) {
			\MHMRentiva\Admin\Utilities\Menu\Menu::register();
		}
		// Admin optimizer
		if ($this->is_class_available('\MHMRentiva\Admin\Utilities\Performance\AdminOptimizer')) {
			\MHMRentiva\Admin\Utilities\Performance\AdminOptimizer::register();
		}

		// Meta boxes

		// Meta boxes
		if (class_exists('\MHMRentiva\Admin\Vehicle\Meta\VehicleMeta')) {
			\MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::register();
		}

		if (class_exists('\MHMRentiva\Admin\Vehicle\Meta\VehicleGallery')) {
			\MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::register();
		}

		if (class_exists('\MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox')) {
			\MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox::register();
		}

		// BookingMeta registration - directly
		\MHMRentiva\Admin\Booking\Meta\BookingMeta::register();

		// Register manual booking meta box
		if ($this->is_class_available('\MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox')) {
			\MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox::register();
		}
		// Register booking edit meta box
		if ($this->is_class_available('\MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox')) {
			\MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox::register();
		}
		if ($this->is_class_available('\MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox')) {
			\MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox::register();
		}

		// List table columns
		if ($this->is_class_available('MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns')) {
			\MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns::register();
		}

		if ($this->is_class_available('MHMRentiva\Admin\Booking\ListTable\BookingColumns')) {
			\MHMRentiva\Admin\Booking\ListTable\BookingColumns::register();
		}

		// Booking admin metaboxes/actions
		if ($this->is_class_available('\MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox')) {
			\MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox::register();
		}
		if ($this->is_class_available('\MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox')) {
			\MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox::register();
		}
		if ($this->is_class_available('\MHMRentiva\Admin\Utilities\Actions\Actions')) {
			\MHMRentiva\Admin\Utilities\Actions\Actions::register();
		}

		// Maintenance (Moved to initialize_core_services for all-context support)

		// Vendor Applications admin page (Pro only)
		if ($this->is_class_available('\MHMRentiva\Admin\Vendor\AdminVendorApplicationsPage')) {
			\MHMRentiva\Admin\Vendor\AdminVendorApplicationsPage::register();
		}

		if ($this->is_class_available('\MHMRentiva\Admin\Vendor\Profile\VendorProfileExtension')) {
			\MHMRentiva\Admin\Vendor\Profile\VendorProfileExtension::register();
		}

		// Setup Wizard
		if ($this->is_class_available('MHMRentiva\Admin\Setup\SetupWizard')) {
			\MHMRentiva\Admin\Setup\SetupWizard::register();
		}
		// REST API Settings AJAX
		add_action('wp_ajax_mhm_create_api_key', array(\MHMRentiva\Admin\REST\Settings\RESTSettings::class, 'ajax_create_api_key'));
		add_action('wp_ajax_mhm_list_api_keys', array(\MHMRentiva\Admin\REST\Settings\RESTSettings::class, 'ajax_list_api_keys'));
		add_action('wp_ajax_mhm_revoke_api_key', array(\MHMRentiva\Admin\REST\Settings\RESTSettings::class, 'ajax_revoke_api_key'));
		add_action('wp_ajax_mhm_delete_api_key', array(\MHMRentiva\Admin\REST\Settings\RESTSettings::class, 'ajax_delete_api_key'));
		add_action('wp_ajax_mhm_list_endpoints', array(\MHMRentiva\Admin\REST\Settings\RESTSettings::class, 'ajax_list_endpoints'));

		// Add Documentation button to standard WP pages
		add_action(
			'all_admin_notices',
			function () {
				$screen = get_current_screen();
				if (! $screen) {
					return;
				}

				$mhm_pages = array(
					'vehicle',
					'vehicle_booking',
					'vehicle_addon',
				);

				if (in_array($screen->post_type, $mhm_pages, true) || 'vehicle_category' === $screen->taxonomy) {
					echo '<style>.mhm-docs-btn-global { float: right; margin-top: 5px; margin-right: 15px; position: relative; z-index: 10; }</style>';
					echo '<div class="mhm-docs-btn-global">';
					\MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button();
					echo '</div>';
				}
			}
		);
	}

	/**
	 * Initialize remaining services
	 */
	private function initialize_remaining_services(): void
	{
		// REST API
		if ($this->is_class_available('\\MHMRentiva\\Admin\\REST\\Availability')) {
			\MHMRentiva\Admin\REST\Availability::register();
		}

		// Logs
		if ($this->is_class_available('\\MHMRentiva\\Admin\\PostTypes\\Logs\\PostType')) {
			\MHMRentiva\Admin\PostTypes\Logs\PostType::register();
		}
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Utilities\\ListTable\\LogColumns')) {
			\MHMRentiva\Admin\Utilities\ListTable\LogColumns::register();
		}
		if ($this->is_class_available('\\MHMRentiva\\Admin\\PostTypes\\Logs\\MetaBox')) {
			\MHMRentiva\Admin\PostTypes\Logs\MetaBox::register();
		}

		// Export
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Utilities\\Export\\Export')) {
			\MHMRentiva\Admin\Utilities\Export\Export::register();
			if (
				\MHMRentiva\Admin\Licensing\Mode::canUseAdvancedReports() &&
				$this->is_class_available('\\MHMRentiva\\Admin\\Utilities\\Export\\ExportStats')
			) {
				\MHMRentiva\Admin\Utilities\Export\ExportStats::register();
			}
		}

		// Booking
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Booking\\Core\\Handler')) {
			\MHMRentiva\Admin\Booking\Core\Handler::register();
		}
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Booking\\Core\\Status')) {
			\MHMRentiva\Admin\Booking\Core\Status::register();
		}
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Booking\\Core\\Hooks')) {
			\MHMRentiva\Admin\Booking\Core\Hooks::register();
		}

		// Reports (Pro only).
		if (
			\MHMRentiva\Admin\Licensing\Mode::canUseAdvancedReports() &&
			$this->is_class_available('\\MHMRentiva\\Admin\\Reports\\Reports')
		) {
			\MHMRentiva\Admin\Reports\Reports::register();
		}

		// Email Logs
		if ($this->is_class_available('MHMRentiva\\\\Admin\\\\Emails\\\\PostTypes\\\\EmailLog')) {
			\MHMRentiva\Admin\Emails\PostTypes\EmailLog::register();
		}
	}

	/**
	 * Initialize Messages, Email, REST API and other services
	 */
	private function initialize_additional_services(): void
	{
		$is_admin = is_admin();

		// Messages System (Pro only).
		if (\MHMRentiva\Admin\Licensing\Mode::canUseMessages()) {
			if (class_exists(Admin\PostTypes\Message\Message::class)) {
				Admin\PostTypes\Message\Message::register();
			}
			if (class_exists(Admin\Messages\Core\Messages::class)) {
				Admin\Messages\Core\Messages::register();
			}
			if ($is_admin && class_exists(Admin\Messages\Admin\MessageListTable::class)) {
				Admin\Messages\Admin\MessageListTable::register();
			}

			if (class_exists(Admin\Messages\REST\Messages::class)) {
				Admin\Messages\REST\Messages::register();
			}
			if (class_exists(Admin\Messages\Notifications\MessageNotifications::class)) {
				Admin\Messages\Notifications\MessageNotifications::register();
			}
		}

		// Email Notifications
		if (class_exists(Admin\Emails\Notifications\BookingNotifications::class)) {
			Admin\Emails\Notifications\BookingNotifications::register();
		}
		if (class_exists(Admin\Emails\Notifications\ReminderScheduler::class)) {
			Admin\Emails\Notifications\ReminderScheduler::register();
		}

		if (class_exists(Admin\Emails\Notifications\RefundNotifications::class)) {
			Admin\Emails\Notifications\RefundNotifications::register();
		}
		if (class_exists(Admin\Emails\Core\Mailer::class)) {
			Admin\Emails\Core\Mailer::register();
		}

		// Email Settings actions
		if (class_exists(Admin\Emails\Settings\EmailTestAction::class)) {
			Admin\Emails\Settings\EmailTestAction::register();
		}
		if (class_exists(Admin\Emails\Settings\EmailTemplateTestAction::class)) {
			Admin\Emails\Settings\EmailTemplateTestAction::register();
		}

		// Admin Email Preview
		if ($is_admin && class_exists(Admin\Emails\Templates\EmailPreview::class)) {
			Admin\Emails\Templates\EmailPreview::register();
		}

		// REST API Error Handler
		if ($this->is_class_available('Admin\REST\ErrorHandler')) {
			Admin\REST\ErrorHandler::register();
		}

		// Debug Helper
		if ($this->is_class_available('Admin\Core\Utilities\DebugHelper')) {
			Admin\Core\Utilities\DebugHelper::register();
		}

		// Vehicle Settings (admin only)
		if ($is_admin) {
			if ($this->is_class_available('MHMRentiva\Admin\Vehicle\Settings\VehicleSettings')) {
				\MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::register();
			}
		}

		// Vehicle Hooks (Normalization)
		if ($this->is_class_available('MHMRentiva\Admin\Vehicle\Hooks\ReviewNormalization')) {
			\MHMRentiva\Admin\Vehicle\Hooks\ReviewNormalization::register();
		}

		// Verified Review Badge - Cache invalidation on booking status changes
		if ($this->is_class_available('MHMRentiva\Admin\Vehicle\Helpers\VerifiedReviewHelper')) {
			\MHMRentiva\Admin\Vehicle\Helpers\VerifiedReviewHelper::register();
		}

		// Direct require to ensure it loads
		require_once MHM_RENTIVA_PLUGIN_DIR . 'src/Admin/Vehicle/Hooks/ReviewEnforcer.php';
		\MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer::register();

		// Transfer Module
		if ($this->is_class_available('MHMRentiva\Admin\Transfer\TransferAdmin')) {
			\MHMRentiva\Admin\Transfer\TransferAdmin::register();

			// Transfer Export/Import Integration
			if ($this->is_class_available('MHMRentiva\Admin\Transfer\TransferExportImport')) {
				\MHMRentiva\Admin\Transfer\TransferExportImport::instance();
			}
		}

		// REST API Fixer
		if ($this->is_class_available('Admin\Core\Utilities\RestApiFixer')) {
			Admin\Core\Utilities\RestApiFixer::register();
		}

		// REST API
		if ($this->is_class_available('Admin\REST\Availability')) {
			Admin\REST\Availability::register();
		}

		// â­ New Account System (WordPress Login)
		if (class_exists(Admin\Frontend\Account\AccountController::class)) {
			Admin\Frontend\Account\AccountController::register();
		}
		if (class_exists(Admin\Frontend\Account\WooCommerceIntegration::class)) {
			Admin\Frontend\Account\WooCommerceIntegration::register();
		}

		// Favorites & Compare (v1.3.3)
		if (class_exists(Admin\Services\FavoritesService::class)) {
			Admin\Services\FavoritesService::register();
		}
		if (class_exists(Admin\Services\CompareService::class)) {
			Admin\Services\CompareService::register();
		}

		// â­ CRITICAL: WooCommerce Bridge - Handles ALL payment transactions (Single Cash Register)
		if (class_exists(Admin\Payment\WooCommerce\WooCommerceBridge::class)) {
			Admin\Payment\WooCommerce\WooCommerceBridge::register();
		} else {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error('WooCommerceBridge class NOT FOUND!');
		}

		// Commission Ledger Bridge
		if (class_exists(Integrations\WooCommerce\CommissionBridge::class)) {
			Integrations\WooCommerce\CommissionBridge::boot();
		}

		// Payment Clients

		// About page
		if ($is_admin && class_exists(Admin\About\About::class)) {
			Admin\About\About::register();
		}

		// Addons System
		if (class_exists(Admin\Addons\AddonPostType::class)) {
			Admin\Addons\AddonPostType::register();
		}
		if (class_exists(Admin\Addons\AddonManager::class)) {
			Admin\Addons\AddonManager::register();
		}
		if ($is_admin && class_exists(Admin\Addons\AddonMenu::class)) {
			Admin\Addons\AddonMenu::register();
		}
		if ($is_admin && class_exists(Admin\Addons\AddonSettings::class)) {
			Admin\Addons\AddonSettings::register();
		}
		if (class_exists(Admin\Booking\Addons\AddonBooking::class)) {
			Admin\Booking\Addons\AddonBooking::register();
		}

		// License system
		if (class_exists(Admin\Licensing\LicenseManager::class)) {
			Admin\Licensing\LicenseManager::instance()->register();
		}
		if (class_exists(Admin\Licensing\Restrictions::class)) {
			Admin\Licensing\Restrictions::register();
		}
		if ($is_admin && class_exists(Admin\Licensing\LicenseAdmin::class)) {
			Admin\Licensing\LicenseAdmin::register();
		}
	}

	/**
	 * Initialize database migration and background processing
	 */
	private function initialize_system_services(): void
	{
		$is_admin = is_admin();

		// Database migration
		add_action('admin_init', array(Admin\Core\Utilities\DatabaseMigrator::class, 'run_migrations'));

		// Taxonomy migration (vehicle_cat â†’ vehicle_category)
		add_action('admin_init', array(Admin\Core\Utilities\TaxonomyMigrator::class, 'migrate_vehicle_cat_to_vehicle_category'), 5);

		// Database cleanup page (admin only)
		if ($is_admin && class_exists('MHMRentiva\\Admin\\Utilities\\Database\\DatabaseCleanupPage')) {
			Admin\Utilities\Database\DatabaseCleanupPage::register();
		}

		// Cron monitor (admin only)
		if ($is_admin && class_exists('MHMRentiva\\Admin\\Utilities\\Cron\\CronMonitorPage')) {
			Admin\Utilities\Cron\CronMonitorPage::register();
		}

		// API Keys Page (admin only)
		if ($is_admin && class_exists('MHMRentiva\\Admin\\Settings\\APIKeysPage')) {
			Admin\Settings\APIKeysPage::register();
		}

		// Uninstall page (admin only)
		if ($is_admin && class_exists('MHMRentiva\\Admin\\Utilities\\Uninstall\\UninstallPage')) {
			Admin\Utilities\Uninstall\UninstallPage::register();
		}

		// Test suite page
		if ($is_admin && class_exists('MHMRentiva\\Admin\\Testing\\TestAdminPage')) {
			Admin\Testing\TestAdminPage::register();
		}

		// Vehicle detail page rewrite rules (SEO-friendly sub-path URLs)
		add_action('init', array($this, 'register_vehicle_rewrite_rules'), 15);

		// Template loading
		add_action('template_redirect', array($this, 'load_vehicle_templates'));

		// REST API
		add_action('rest_api_init', array($this, 'register_rest_api'));

		// Operational Resilience: Health & Integrity
		if (class_exists('MHMRentiva\Api\REST\HealthController')) {
			\MHMRentiva\Api\REST\HealthController::register();
		}
		if (class_exists('MHMRentiva\Core\Financial\Audit\Verification\IntegrityVerificationJob')) {
			\MHMRentiva\Core\Financial\Audit\Verification\IntegrityVerificationJob::register();
		}
		if (
			\MHMRentiva\Admin\Licensing\Mode::canUseVendorPayout() &&
			class_exists('MHMRentiva\Api\REST\PayoutCallbackController')
		) {
			\MHMRentiva\Api\REST\PayoutCallbackController::register();
		}
		if (
			\MHMRentiva\Admin\Licensing\Mode::canUseVendorPayout() &&
			class_exists('MHMRentiva\Core\Financial\PayoutAjaxController')
		) {
			\MHMRentiva\Core\Financial\PayoutAjaxController::register();
		}
		if (
			\MHMRentiva\Admin\Licensing\Mode::canUseVendorPayout() &&
			class_exists('MHMRentiva\Core\Financial\Automation\MaturedPayoutJob')
		) {
			\MHMRentiva\Core\Financial\Automation\MaturedPayoutJob::register();
		}

		// Plugin deactivation hook
		register_deactivation_hook(dirname(__DIR__) . '/mhm-rentiva.php', array(Admin\Licensing\LicenseManager::class, 'deactivatePluginHook'));

		// Shortcode URL cache temizleme
		add_action('save_post', array(Admin\Core\ShortcodeUrlManager::class, 'clear_cache_on_page_update'));
		add_action(
			'delete_post',
			function ($post_id) {
				Admin\Core\ShortcodeUrlManager::clear_cache();
			}
		);

		// Register CLI Commands
		if (defined('WP_CLI') && constant('WP_CLI')) {
			if ($this->is_class_available('MHMRentiva\Admin\CLI\RepairRatingsCommand')) {
				\WP_CLI::add_command('mhm-rentiva repair-ratings', \MHMRentiva\Admin\CLI\RepairRatingsCommand::class);
			}

			// v4.14.x â€” Layout Import Pipeline (Phase 1)
			if ($this->is_class_available('MHMRentiva\Layout\CLI\LayoutImportCommand')) {
				\WP_CLI::add_command('mhm-rentiva layout', \MHMRentiva\Layout\CLI\LayoutImportCommand::class);
			}

			// Auditing & Integrity
			if ($this->is_class_available('MHMRentiva\CLI\ExportAuditCommand')) {
				\WP_CLI::add_command('mhm audit:export', \MHMRentiva\CLI\ExportAuditCommand::class);
			}
			if ($this->is_class_available('MHMRentiva\CLI\IntegrityCheckCommand')) {
				\WP_CLI::add_command('mhm audit:verify', \MHMRentiva\CLI\IntegrityCheckCommand::class);
			}
			if ($this->is_class_available('MHMRentiva\CLI\KeyRevokeCommand')) {
				\WP_CLI::add_command('mhm key:revoke', \MHMRentiva\CLI\KeyRevokeCommand::class);
			}
			if ($this->is_class_available('MHMRentiva\CLI\MaturedPayoutCommand')) {
				\WP_CLI::add_command('mhm payout:execute-matured', \MHMRentiva\CLI\MaturedPayoutCommand::class);
			}
		}
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain(): void
	{
		$domain = 'mhm-rentiva';
		$locale = determine_locale();
		$locale = apply_filters('plugin_locale', $locale, $domain);

		// Force load from local directory first (to avoid global overrides)
		$mofile = dirname(__DIR__) . '/languages/' . $domain . '-' . $locale . '.mo';

		if (file_exists($mofile)) {
			load_textdomain($domain, $mofile);
		}
	}

	/**
	 * Setup theme support
	 */
	public function setup_theme_support(): void
	{
		if (! current_theme_supports('post-thumbnails')) {
			add_theme_support('post-thumbnails');
		}
	}

	/**
	 * Apply license limits
	 */
	public function enforce_limits(array $data, array $postarr): array
	{
		$type = $data['post_type'] ?? '';

		// Skip limit check if:
		// 1. Post ID exists (updating existing post, not creating new)
		// 2. Post is being deleted/trashed
		// 3. Post status is trash or deleted
		$post_id     = $postarr['ID'] ?? 0;
		$post_status = $data['post_status'] ?? '';

		if ($post_id > 0 || in_array($post_status, array('trash', 'delete'), true)) {
			return $data;
		}

		if (class_exists(Admin\Licensing\Mode::class) && class_exists(Admin\Licensing\Restrictions::class)) {
			if (Admin\Licensing\Mode::isLite()) {
				// Only check limits when creating NEW posts (not updating/deleting)
				if ($type === 'vehicle' && Admin\Licensing\Restrictions::vehicleCount() >= Admin\Licensing\Mode::maxVehicles()) {
					wp_die(esc_html__('Rentiva Lite version allows you to add up to 3 vehicles. Activate your license to add more vehicles.', 'mhm-rentiva'), 403);
				}
				if ($type === 'vehicle_booking' && Admin\Licensing\Restrictions::bookingCount() >= Admin\Licensing\Mode::maxBookings()) {
					wp_die(esc_html__('Rentiva Lite version has reached the booking limit. Activate your license to add more bookings.', 'mhm-rentiva'), 403);
				}
			}
		}

		return $data;
	}







	/**
	 * Register SEO-friendly rewrite rules for vehicle detail sub-paths.
	 *
	 * Maps {shortcode-page-slug}/{vehicle-slug}/ to the vehicle-details shortcode page
	 * with the vehicle_slug query var set, so the shortcode can look up the vehicle.
	 * Example: /vehicles/bmw-3-series-320i/ → page_id=X &vehicle_slug=bmw-3-series-320i
	 *
	 * Flush trigger: settings save (mhm_rentiva_vehicle_url_base change) or
	 * manually via Settings → Permalinks when the shortcode page slug changes.
	 */
	public function register_vehicle_rewrite_rules(): void
	{
		// Register vehicle_slug as a public query var so get_query_var() can read it.
		add_filter('query_vars', static function (array $vars): array {
			$vars[] = 'vehicle_slug';
			return $vars;
		});

		// add_rewrite_tag also registers the var, but explicit filter ensures it regardless of timing.
		add_rewrite_tag('%vehicle_slug%', '([^/]+)');

		$page_id = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id('rentiva_vehicle_details');
		if (! $page_id) {
			return;
		}

		$page_path = ltrim((string) parse_url((string) get_permalink($page_id), PHP_URL_PATH), '/');
		$page_path = rtrim($page_path, '/');
		if ('' === $page_path) {
			return;
		}

		$rule_regex = '^' . preg_quote($page_path, '#') . '/([^/]+)/?$';
		$rule_query = 'index.php?page_id=' . $page_id . '&vehicle_slug=$matches[1]';

		add_rewrite_rule($rule_regex, $rule_query, 'top');

		// Auto-flush if our rule is missing from the cached rewrite rules in the DB.
		// This handles the case where flush was triggered before the page was configured.
		$cached_rules = (array) get_option('rewrite_rules', array());
		if (! isset($cached_rules[$rule_regex])) {
			flush_rewrite_rules(false);
		}
	}

	/**
	 * Load vehicle templates
	 */
	public function load_vehicle_templates(): void
	{
		if (! is_singular('vehicle')) {
			return;
		}

		// Redirect vehicle CPT singular pages to the vehicle-details shortcode page
		// using a SEO-friendly sub-path URL: /{shortcode-page-slug}/{vehicle-slug}/
		// This ensures the active theme (classic or FSE/block) renders its full
		// header, navigation, and footer — which template_redirect+include cannot
		// provide for FSE block themes whose navigation lives in block templates.
		$details_page_id = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id('rentiva_vehicle_details');
		if ($details_page_id) {
			$vehicle_post   = get_queried_object();
			$details_base   = untrailingslashit((string) get_permalink($details_page_id));
			$clean_url      = $details_base . '/' . $vehicle_post->post_name . '/';
			wp_safe_redirect($clean_url, 301);
			exit;
		}

		// Fallback: no shortcode page configured — use bundled single-vehicle template.
		$template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/single-vehicle.php';
		if (file_exists($template_path)) {
			include $template_path;
			exit;
		}
	}

	/**
	 * Initialize deposit services
	 */
	private function initialize_deposit_services(): void
	{
		// Register deposit AJAX handlers
		if ($this->is_class_available('MHMRentiva\Admin\Vehicle\Deposit\DepositAjax')) {
			\MHMRentiva\Admin\Vehicle\Deposit\DepositAjax::register();
		}
		if ($this->is_class_available('MHMRentiva\Admin\Booking\Actions\DepositManagementAjax')) {
			\MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::register();
		}
	}

	/**
	 * Initialize frontend services
	 */
	private function initialize_frontend_services(): void
	{
		// â­ Load AbstractShortcode first - Required for other shortcodes
		// Autoloader handles this now

		// â­ Shortcode Service Provider - Manages all shortcodes centrally (v3.0.1)
		if ($this->is_class_available('MHMRentiva\Admin\Core\ShortcodeServiceProvider')) {
			\MHMRentiva\Admin\Core\ShortcodeServiceProvider::register();
		}

		if (
			\MHMRentiva\Admin\Licensing\Mode::canUseVendorPayout() &&
			$this->is_class_available('MHMRentiva\Admin\Frontend\Shortcodes\Account\VendorLedger')
		) {
			\MHMRentiva\Admin\Frontend\Shortcodes\Account\VendorLedger::register();
		}

		// â­ Elementor Integration - Register widgets (v3.0.1)
		$this->initialize_elementor_integration();
	}

	/**
	 * Register REST API
	 */
	public function register_rest_api(): void
	{
		// REST API endpoints are now in Admin\REST namespace
		if ($this->is_class_available('MHMRentiva\Admin\REST\Locations')) {
			\MHMRentiva\Admin\REST\Locations::register();
		}
	}

	/**
	 * Clear cache when post is saved
	 */
	public function invalidate_cache_on_save(int $post_id): void
	{
		$post_type = get_post_type($post_id);

		if ($post_type === 'vehicle') {
			// Clear vehicle caches
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'vehicles',
					"vehicle_{$post_id}",
					'availability_calendar',
				)
			);
			// Invalidate Search Filters Cache
			delete_transient('mhm_rentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhm_rentiva_search_filters_v1');
		} elseif ($post_type === 'vehicle_booking') {
			// Clear booking caches
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'availability',
					'bookings',
				)
			);
		}
	}

	/**
	 * Clear cache when post is deleted
	 */
	public function invalidate_cache_on_delete(int $post_id): void
	{
		$post_type = get_post_type($post_id);

		if ($post_type === 'vehicle') {
			// Clear vehicle caches
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'vehicles',
					"vehicle_{$post_id}",
					'availability_calendar',
				)
			);
			// Invalidate Search Filters Cache
			delete_transient('mhm_rentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhm_rentiva_search_filters_v1');
		} elseif ($post_type === 'vehicle_booking') {
			// Clear booking caches
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'availability',
					'bookings',
				)
			);
		}
	}

	/**
	 * Clear cache when post meta is updated
	 */
	public function invalidate_cache_on_meta_update(int $meta_id, int $post_id, string $meta_key, $meta_value): void
	{
		$post_type = get_post_type($post_id);

		// Vehicle meta changes
		if ($post_type === 'vehicle' && strpos($meta_key, '_mhm_rentiva_') === 0) {
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'vehicles',
					"vehicle_{$post_id}",
					'availability_calendar',
				)
			);
			// Invalidate Search Filters Cache
			delete_transient('mhm_rentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhm_rentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhm_rentiva_search_filters_v1');
		}

		// Booking meta changes
		if ($post_type === 'vehicle_booking' && strpos($meta_key, '_mhm_') === 0) {
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'availability',
					'bookings',
				)
			);
		}
	}

	/**
	 * Initialize Elementor integration
	 */
	private function initialize_elementor_integration(): void
	{
		if (! did_action('elementor/loaded')) {
			return;
		}

		if (! class_exists(\MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration::class)) {
			return;
		}

		// Initialize Elementor widgets
		\MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration::init();
	}

	/**
	 * Plugin activation
	 */
	public static function activate(): void
	{
		// Register post types
		\MHMRentiva\Admin\Vehicle\PostType\Vehicle::register();
		\MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::register();

		// Register Customer role
		self::register_customer_role();

		// Initialize default settings on fresh install (no-op if already initialized)
		\MHMRentiva\Admin\Settings\Services\SettingsService::initialize_defaults_on_activation();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Register Customer role
	 *
	 * âœ… Safe: If customer role already exists (e.g., from WooCommerce),
	 * WordPress add_role() does nothing and returns null (no error).
	 * This ensures compatibility with other plugins.
	 */
	public static function register_customer_role(): void
	{
		// Check if role already exists (e.g., from WooCommerce or another plugin)
		if (get_role('customer')) {
			// Role already exists - no action needed
			// WordPress add_role() safely handles existing roles (returns null, no error)
			return;
		}

		// Get subscriber role capabilities as base
		$subscriber   = get_role('subscriber');
		$capabilities = $subscriber ? $subscriber->capabilities : array('read' => true);

		// Add Customer role with subscriber-like capabilities
		// Note: If role already exists, add_role() safely returns null without error
		$result = add_role(
			'customer',
			__('Customer', 'mhm-rentiva'),
			$capabilities
		);

		// Optional: Log if role creation failed (shouldn't happen if check above works)
		if ($result === null && ! get_role('customer')) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning('Failed to create customer role (may already exist from another plugin)');
		}
	}

	/**
	 * Register the rentiva_vendor WordPress role.
	 * Idempotent — safe to call multiple times.
	 */
	public static function register_vendor_role(): void
	{
		if (get_role('rentiva_vendor')) {
			return;
		}

		add_role(
			'rentiva_vendor',
			__('Rentiva Vendor', 'mhm-rentiva'),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate(): void
	{
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}
