<?php

declare(strict_types=1);

namespace MHMRentiva;

if (! defined('ABSPATH')) {
	exit;
}

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

		// Register Customer role (also for existing installations)
		// Priority 20: Run after WooCommerce and other plugins that might register customer role
		add_action('init', array(self::class, 'register_customer_role'), 20);

		// Apply license limits
		add_filter('wp_insert_post_data', array($this, 'enforce_limits'), 10, 2);

		// Cache invalidation hooks
		add_action('save_post', array($this, 'invalidate_cache_on_save'));
		add_action('delete_post', array($this, 'invalidate_cache_on_delete'));
		add_action('updated_post_meta', array($this, 'invalidate_cache_on_meta_update'), 10, 4);

		// Initialize services
		$this->initialize_core_services();

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
	 * Initialize core services
	 */
	private function initialize_core_services(): void
	{
		// Asset Manager - Central CSS and JS file management
		if ($this->is_class_available('MHMRentiva\Admin\Core\AssetManager')) {
			\MHMRentiva\Admin\Core\AssetManager::init();
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

		// ⭐ CRITICAL: AutoCancel MUST run in ALL contexts (admin, frontend, cron)
		// Previously was inside is_admin() block which prevented cron from working!
		if ($this->is_class_available('\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel')) {
			\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::register();
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
		if ($this->is_class_available('\MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox')) {
			\MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox::register();
		}
		if ($this->is_class_available('\MHMRentiva\Admin\Utilities\Actions\Actions')) {
			\MHMRentiva\Admin\Utilities\Actions\Actions::register();
		}

		// Maintenance (Moved to initialize_core_services for all-context support)

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
			\MHMRentiva\Admin\Utilities\Export\ExportStats::register();
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

		// Reports
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Reports\\Reports')) {
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
		// Messages System
		if (class_exists(Admin\PostTypes\Message\Message::class)) {
			Admin\PostTypes\Message\Message::register();
		}
		if (class_exists(Admin\Messages\Core\Messages::class)) {
			Admin\Messages\Core\Messages::register();
		}
		if (class_exists(Admin\Messages\Admin\MessageListTable::class)) {
			Admin\Messages\Admin\MessageListTable::register();
		}

		if (class_exists(Admin\Messages\REST\Messages::class)) {
			Admin\Messages\REST\Messages::register();
		}
		if (class_exists(Admin\Messages\Notifications\MessageNotifications::class)) {
			Admin\Messages\Notifications\MessageNotifications::register();
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
		if (is_admin() && class_exists(Admin\Emails\Templates\EmailPreview::class)) {
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
		if (is_admin()) {
			if ($this->is_class_available('MHMRentiva\Admin\Vehicle\Settings\VehicleSettings')) {
				\MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::register();
			}
		}

		// Transfer Module
		if ($this->is_class_available('MHMRentiva\Admin\Transfer\TransferAdmin')) {
			\MHMRentiva\Admin\Transfer\TransferAdmin::register();
		}

		// REST API Fixer
		if ($this->is_class_available('Admin\Core\Utilities\RestApiFixer')) {
			Admin\Core\Utilities\RestApiFixer::register();
		}

		// REST API
		if ($this->is_class_available('Admin\REST\Availability')) {
			Admin\REST\Availability::register();
		}

		// ☠️ DEAD CODE REMOVED: Portal API (552 lines) - deprecated in v4.0.0, not used anywhere
		// My Account system uses direct AJAX (admin-ajax.php), not REST API

		// ⭐ New Account System (WordPress Login)
		if (class_exists(Admin\Frontend\Account\AccountController::class)) {
			Admin\Frontend\Account\AccountController::register();
		}
		if (class_exists(Admin\Frontend\Account\WooCommerceIntegration::class)) {
			Admin\Frontend\Account\WooCommerceIntegration::register();
		}
		// ⭐ CRITICAL: WooCommerce Bridge - Handles ALL payment transactions (Single Cash Register)
		if (class_exists(Admin\Payment\WooCommerce\WooCommerceBridge::class)) {
			Admin\Payment\WooCommerce\WooCommerceBridge::register();
		} else {
			error_log('MHM Rentiva: WooCommerceBridge class NOT FOUND!');
		}

		// Payment Clients

		// About page
		if (class_exists(Admin\About\About::class)) {
			Admin\About\About::register();
		}

		// Addons System
		if (class_exists(Admin\Addons\AddonPostType::class)) {
			Admin\Addons\AddonPostType::register();
		}
		if (class_exists(Admin\Addons\AddonManager::class)) {
			Admin\Addons\AddonManager::register();
		}
		if (class_exists(Admin\Addons\AddonMenu::class)) {
			Admin\Addons\AddonMenu::register();
		}
		if (class_exists(Admin\Addons\AddonSettings::class)) {
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
		if (is_admin() && class_exists(Admin\Licensing\LicenseAdmin::class)) {
			Admin\Licensing\LicenseAdmin::register();
		}
	}

	/**
	 * Initialize database migration and background processing
	 */
	private function initialize_system_services(): void
	{
		// Database migration
		add_action('admin_init', array(Admin\Core\Utilities\DatabaseMigrator::class, 'run_migrations'));

		// Taxonomy migration (vehicle_cat → vehicle_category)
		add_action('admin_init', array(Admin\Core\Utilities\TaxonomyMigrator::class, 'migrate_vehicle_cat_to_vehicle_category'), 5);

		// Database cleanup page (admin only)
		if (is_admin() && class_exists('MHMRentiva\\Admin\\Utilities\\Database\\DatabaseCleanupPage')) {
			Admin\Utilities\Database\DatabaseCleanupPage::register();
		}

		// Cron monitor (admin only)
		if (is_admin() && class_exists('MHMRentiva\\Admin\\Utilities\\Cron\\CronMonitorPage')) {
			Admin\Utilities\Cron\CronMonitorPage::register();
		}

		// API Keys Page (admin only)
		if (is_admin() && class_exists('MHMRentiva\\Admin\\Settings\\APIKeysPage')) {
			Admin\Settings\APIKeysPage::register();
		}

		// Uninstall page (admin only)
		if (is_admin() && class_exists('MHMRentiva\\Admin\\Utilities\\Uninstall\\UninstallPage')) {
			Admin\Utilities\Uninstall\UninstallPage::register();
		}

		// Test suite page (development only)
		if (defined('WP_DEBUG') && WP_DEBUG && is_admin() && class_exists('MHMRentiva\\Admin\\Testing\\TestAdminPage')) {
			Admin\Testing\TestAdminPage::register();
		}

		// Template loading
		add_action('template_redirect', array($this, 'load_vehicle_templates'));

		// REST API
		add_action('rest_api_init', array($this, 'register_rest_api'));

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
	 * Load vehicle templates
	 */
	public function load_vehicle_templates(): void
	{
		// Vehicle control with query string
		if (isset($_GET['vehicle']) && ! empty($_GET['vehicle'])) {
			$vehicle_slug = mhm_rentiva_sanitize_text_field_safe(wp_unslash($_GET['vehicle']));
			$vehicle_post = get_page_by_path($vehicle_slug, OBJECT, 'vehicle');

			if ($vehicle_post && 'publish' === $vehicle_post->post_status) {
				global $post;
				$post = $vehicle_post;
				setup_postdata($post);

				$template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/single-vehicle.php';
				if (file_exists($template_path)) {
					include $template_path;
					exit;
				}
			}
		}

		// Normal single vehicle template
		if (is_singular('vehicle')) {
			$template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/single-vehicle.php';
			if (file_exists($template_path)) {
				include $template_path;
				exit;
			}
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
		// ⭐ Load AbstractShortcode first - Required for other shortcodes
		// Autoloader handles this now

		// ⭐ Shortcode Service Provider - Manages all shortcodes centrally (v3.0.1)
		if ($this->is_class_available('MHMRentiva\Admin\Core\ShortcodeServiceProvider')) {
			\MHMRentiva\Admin\Core\ShortcodeServiceProvider::register();
		}

		// ⭐ Elementor Integration - Register widgets (v3.0.1)
		$this->initialize_elementor_integration();

		// ⭐ Gutenberg Integration - Register blocks (v3.0.1)
		$this->initialize_gutenberg_integration();
	}

	/**
	 * Register REST API
	 */
	public function register_rest_api(): void
	{
		// REST API endpoints are now in Admin\REST namespace
		// Availability and Portal endpoints are automatically registered
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
		// Initialize Elementor widgets
		\MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration::init();
	}

	/**
	 * Initialize Gutenberg integration
	 */
	private function initialize_gutenberg_integration(): void
	{
		// Initialize Gutenberg integration
		\MHMRentiva\Admin\Frontend\Blocks\Gutenberg\GutenbergIntegration::init();
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

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Register Customer role
	 *
	 * ✅ Safe: If customer role already exists (e.g., from WooCommerce),
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
			error_log('MHM Rentiva: Failed to create customer role (may already exist from another plugin)');
		}
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
