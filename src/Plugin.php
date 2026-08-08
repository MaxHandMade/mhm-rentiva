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
final class Plugin {


	public const VERSION = MHMRENTIVA_VERSION;

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
	private function is_class_available(string $class_name): bool
	{
		return class_exists($class_name);
	}

	/**
	 * Plugin constructor - initialize all services
	 */
	private function __construct()
	{
		// Ensure theme support for thumbnails
		add_action('after_setup_theme', array( $this, 'setup_theme_support' ));

		// Load text domain
		// Priority 1: Load translations before any output
		add_action('init', array( $this, 'load_textdomain' ), 1);

		// Register Customer role (also for existing installations)
		// Priority 20: Run after WooCommerce and other plugins that might register customer role
		add_action('init', array( self::class, 'register_customer_role' ), 20);

		// Cache invalidation hooks
		add_action('save_post', array( $this, 'invalidate_cache_on_save' ));
		add_action('delete_post', array( $this, 'invalidate_cache_on_delete' ));
		add_action('updated_post_meta', array( $this, 'invalidate_cache_on_meta_update' ), 10, 4);

		// Delay service graph init until after textdomain is loaded on `init`.
		add_action('init', array( $this, 'initialize_services' ), 2);
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
					MHMRENTIVA_PLUGIN_DIR,
					MHMRENTIVA_PLUGIN_URL
				);
				$styles->register();
			}
		}

		// ⭐ CRITICAL: AutoCancel MUST run in ALL contexts (admin, frontend, cron)
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
		// LogMaintenanceScheduler retired: its
		// `mhmrentiva_daily_log_cleanup` cron purged the exact same
		// mhmrentiva_app_log posts as LogRetention's own
		// `mhmrentiva_log_purge_event` cron above, gated by the same two
		// settings -- duplicated work, not a distinct feature. LogRetention is
		// kept: it deletes through wp_delete_post() (correct hook/cache
		// lifecycle, bounded per run via the mhmrentiva_log_purge_limit
		// filter), where the retired path ran one unbounded raw multi-table
		// DELETE. Self-heals any install that already has the retired cron
		// scheduled -- most WordPress updates never fire a deactivation hook,
		// so an active-clear here (not just on deactivate) is what actually
		// reaches them.
		if (wp_next_scheduled('mhmrentiva_daily_log_cleanup')) {
			wp_clear_scheduled_hook('mhmrentiva_daily_log_cleanup');
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

		$this->register_vehicle_meta();
	}

	/**
	 * Post meta registration, on `init`, for every request.
	 *
	 * 🔴 THIS USED TO LIVE BEHIND `is_admin()`. VehicleMeta::register() and
	 * VehicleGallery::register() are called from initialize_admin_services(),
	 * which only runs when is_admin() is true, and each hooked its own
	 * register_meta_fields() onto `init` from in there. So on a REST request --
	 * where is_admin() is false -- none of these fields were registered at all.
	 *
	 * That made `'show_in_rest' => true` a claim the plugin did not honour: the
	 * fields were absent from the REST API, and, worse, absent from
	 * sanitize_meta(), so a REST or front-end write stored whatever it was given.
	 * A test proved it before this was fixed -- update_post_meta() with
	 * 'rocket_powered' came back 'rocket_powered'.
	 *
	 * register_post_meta() is a DECLARATION. It describes the shape of the data
	 * and must be identical on every request, exactly like register_post_type().
	 * Who may read or write is decided by auth_callback and by capability checks
	 * at the point of use -- never by whether registration happened to run.
	 *
	 * WHAT THIS DOES NOT DO, measured rather than assumed: it exposes nothing new
	 * over REST. The `mhmrentiva_vehicle` post type does not declare
	 * `show_in_rest` at all, so no REST route exists for it and the eleven
	 * `'show_in_rest' => true` meta flags remain unreachable through the core
	 * controller. Every one of those keys is protected (leading underscore) and
	 * none declares an auth_callback, so WordPress's default for protected meta
	 * -- `__return_false` -- already denies REST writes.
	 *
	 * The whole effect is therefore a hardening: sanitize_meta() now runs on
	 * front-end and REST writes as well as admin ones. Nothing was relying on the
	 * gap -- no front-end code writes any of these keys.
	 *
	 * Left deliberately unchanged: those eleven `show_in_rest` flags are still
	 * inert, now for an honest reason rather than an accidental one. Putting the
	 * post type into REST is a real surface change and belongs to whoever decides
	 * the API, not to this fix.
	 */
	private function register_vehicle_meta(): void
	{
		if ($this->is_class_available('\MHMRentiva\Admin\Vehicle\Meta\VehicleMeta')) {
			add_action('init', array( \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::class, 'register_meta_fields' ));
		}

		if ($this->is_class_available('\MHMRentiva\Admin\Vehicle\Meta\VehicleGallery')) {
			add_action('init', array( \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::class, 'register_meta_fields' ));
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

		// Setup Wizard
		if ($this->is_class_available('MHMRentiva\Admin\Setup\SetupWizard')) {
			\MHMRentiva\Admin\Setup\SetupWizard::register();
		}
		// REST API Settings AJAX handlers are registered by APIKeysPage::register()
		// below (single source of truth). The dispatcher there verifies nonce +
		// capability and routes each `wp_ajax_mhmrentiva_{action}` hook.

		// Add Documentation button to standard WP list screens that don't ship
		// their own custom header.
		//
		// `vehicle_addon` is intentionally excluded: its AddonMenu screen renders
		// a custom `render_admin_header()` that already exposes a Documentation
		// button (plus the Add New CTA). Keeping it in this list produced two
		// Documentation buttons on the Additional Services page — one from the
		// header, one floated right by this hook.
		add_action(
			'all_admin_notices',
			function () {
				$screen = get_current_screen();
				if (! $screen) {
					return;
				}

				$mhmrentiva_pages = array(
					'mhmrentiva_vehicle',
					'mhmrentiva_booking',
				);

				if (in_array($screen->post_type, $mhmrentiva_pages, true) || 'mhmrentiva_vehicle_category' === $screen->taxonomy) {
					echo '<div class="mhm-docs-btn-global">';
					\MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button();
					echo '</div>';
				}
			}
		);

		// Enqueue the positioning CSS for the floated Documentation button above.
		// Must run on admin_enqueue_scripts (not all_admin_notices/admin_head) —
		// the style queue is already printed by the time either of those fire.
		add_action(
			'admin_enqueue_scripts',
			function () {
				$screen = get_current_screen();
				if (! $screen) {
					return;
				}

				$mhmrentiva_pages = array(
					'mhmrentiva_vehicle',
					'mhmrentiva_booking',
				);

				if (in_array($screen->post_type, $mhmrentiva_pages, true) || 'mhmrentiva_vehicle_category' === $screen->taxonomy) {
					wp_enqueue_style(
						'mhm-rentiva-hide-wp-chrome',
						MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/hide-wp-chrome.css',
						array(),
						MHMRENTIVA_VERSION
					);
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
		if ($this->is_class_available('\\MHMRentiva\\Admin\\REST\\BlockedDates')) {
			\MHMRentiva\Admin\REST\BlockedDates::register();
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

		// Booking
		// Handler::register() was removed: its
		// two hooks (admin_post_mhmrentiva_booking / admin_post_nopriv_*) had
		// zero shipped nonce producer and zero consumer in either repo. Handler
		// itself survives -- get_cancellation_policy()/get_payment_deadline()
		// are called live from WooCommerceBridge.
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Booking\\Core\\Status')) {
			\MHMRentiva\Admin\Booking\Core\Status::register();
		}
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Booking\\Core\\Hooks')) {
			\MHMRentiva\Admin\Booking\Core\Hooks::register();
		}

		// Customers REST endpoints — must be in the context-agnostic init path.
		// rest_api_init fires before admin_menu, so registering via Menu→CustomersPage
		// is too late. REST routes are needed on both admin and REST API requests.
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Customers\\REST\\CustomersRestController')) {
			add_action('rest_api_init', [ '\\MHMRentiva\\Admin\\Customers\\REST\\CustomersRestController', 'register_routes' ]);
		}

		// ShortcodePages REST endpoints — must be context-agnostic (same reason as
		// Customers above: rest_api_init fires on REST requests where is_admin() is
		// false, so initialize_admin_services() is skipped entirely).
		if ($this->is_class_available('\\MHMRentiva\\Admin\\Settings\\ShortcodePages\\REST\\ShortcodePagesController')) {
			\MHMRentiva\Admin\Settings\ShortcodePages\REST\ShortcodePagesController::register();
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
		require_once MHMRENTIVA_PLUGIN_DIR . 'src/Admin/Vehicle/Hooks/ReviewEnforcer.php';
		\MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer::register();

		// ⭐ New Account System (WordPress Login)
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

		// ⭐ CRITICAL: WooCommerce Bridge - Handles ALL payment transactions (Single Cash Register)
		if (class_exists(Admin\Payment\WooCommerce\WooCommerceBridge::class)) {
			Admin\Payment\WooCommerce\WooCommerceBridge::register();
		} else {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error('WooCommerceBridge class NOT FOUND!');
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
	}

	/**
	 * Initialize database migration and background processing
	 */
	private function initialize_system_services(): void
	{
		$is_admin = is_admin();

		// Database migration. LOAD-BEARING: this is the only unconditional
		// retry path for a version-gated migration that failed to finish --
		// mhm-rentiva.php's own plugins_loaded lane stamps
		// mhmrentiva_plugin_version after calling run_migrations() exactly
		// once per code-version change, regardless of whether the migration
		// itself finished, so it never calls run_migrations() again on its
		// own. As long as `mhmrentiva_db_version` has not reached
		// DatabaseMigrator::CURRENT_VERSION, THIS hook is what gives the
		// migration another attempt on the next request. Do not gate it or
		// remove it without keeping some other unconditional retry path --
		// see DatabaseMigrator::INDEX_CLEANUP_MAX_ATTEMPTS for why that retry
		// is itself bounded rather than infinite.
		// Use the action-specific void adapter: run_migrations() returns whether
		// the schema is complete, while WordPress action callbacks return nothing.
		add_action('admin_init', array( Admin\Core\Utilities\DatabaseMigrator::class, 'run_migrations_from_hook' ), 10, 0);

		// Taxonomy migration (vehicle_cat → vehicle_category)
		add_action('admin_init', array( Admin\Core\Utilities\TaxonomyMigrator::class, 'migrate_vehicle_cat_to_vehicle_category' ), 5);

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

		// UninstallPage::register() was removed:
		// its two wp_ajax_* handlers had zero rendering surface and zero
		// consumer in either repo. The real uninstall path (Uninstaller::
		// uninstall_direct(), called from uninstall.php) is untouched.

		// Vehicle detail page rewrite rules (SEO-friendly sub-path URLs)
		add_action('init', array( $this, 'register_vehicle_rewrite_rules' ), 15);

		// Template loading
		add_action('template_redirect', array( $this, 'load_vehicle_templates' ));

		// REST API
		add_action('rest_api_init', array( $this, 'register_rest_api' ));

		// Shortcode URL cache temizleme
		add_action('save_post', array( Admin\Core\ShortcodeUrlManager::class, 'clear_cache_on_page_update' ));
		add_action(
			'delete_post',
			function ($post_id) {
				unset($post_id);
				Admin\Core\ShortcodeUrlManager::clear_cache();
			}
		);

		// Register CLI Commands
		if (defined('WP_CLI') && constant('WP_CLI')) {
			if ($this->is_class_available('MHMRentiva\Admin\CLI\RepairRatingsCommand')) {
				\WP_CLI::add_command('mhm-rentiva repair-ratings', \MHMRentiva\Admin\CLI\RepairRatingsCommand::class);
			}

			// v4.14.x — Layout Import Pipeline (Phase 1)
			if ($this->is_class_available('MHMRentiva\Layout\CLI\LayoutImportCommand')) {
				\WP_CLI::add_command('mhm-rentiva layout', \MHMRentiva\Layout\CLI\LayoutImportCommand::class);
			}
		}
	}

	/**
	 * Load plugin text domain.
	 *
	 * The locale comes straight from determine_locale(), which is the mechanism
	 * WordPress still ships: it applies core's own `pre_determine_locale` and
	 * `determine_locale` filters internally, so a translation plugin overriding
	 * the locale is honoured here without this plugin firing any hook of its own.
	 *
	 * A `plugin_locale` filter used to be applied on top of that, mirroring what
	 * core's former plugin textdomain loader did at the time. WordPress 7.0
	 * removed the filter -- core neither owns nor fires the name any more --
	 * which left this plugin as the only party firing an unprefixed global hook
	 * name. Do not reintroduce it: PluginTextdomainLocaleTest fails if it comes
	 * back.
	 */
	public function load_textdomain(): void
	{
		$domain = 'mhm-rentiva';
		$locale = determine_locale();

		// Force load from the shipped local directory first. Plugin Check
		// discourages the automatic plugin textdomain loader for
		// WordPress.org-hosted plugins; this explicit load remains necessary
		// because it honours determine_locale() immediately on WordPress 7.0+.
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
	 * Register SEO-friendly rewrite rules for vehicle detail sub-paths.
	 *
	 * Maps {shortcode-page-slug}/{vehicle-slug}/ to the vehicle-details shortcode page
	 * with the vehicle_slug query var set, so the shortcode can look up the vehicle.
	 * Example: /vehicles/bmw-3-series-320i/ → page_id=X &vehicle_slug=bmw-3-series-320i
	 *
	 * Flush trigger: settings save (mhmrentiva_vehicle_url_base change) or
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

		$page_path = ltrim( (string) wp_parse_url( (string) get_permalink($page_id), PHP_URL_PATH), '/');
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
		if (! isset($cached_rules[ $rule_regex ])) {
			flush_rewrite_rules(false);
		}
	}

	/**
	 * Load vehicle templates
	 */
	public function load_vehicle_templates(): void
	{
		if (! is_singular('mhmrentiva_vehicle')) {
			return;
		}

		// Redirect vehicle CPT singular pages to the vehicle-details shortcode page
		// using a SEO-friendly sub-path URL: /{shortcode-page-slug}/{vehicle-slug}/
		// This ensures the active theme (classic or FSE/block) renders its full
		// header, navigation, and footer — which template_redirect+include cannot
		// provide for FSE block themes whose navigation lives in block templates.
		$details_page_id = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id('rentiva_vehicle_details');
		if ($details_page_id) {
			$vehicle_post = get_queried_object();
			$details_base = untrailingslashit( (string) get_permalink($details_page_id));
			$clean_url    = $details_base . '/' . $vehicle_post->post_name . '/';
			wp_safe_redirect($clean_url, 301);
			exit;
		}

		// Fallback: no shortcode page configured — use bundled single-vehicle template.
		$template_path = MHMRENTIVA_PLUGIN_PATH . 'templates/single-vehicle.php';
		if (file_exists($template_path)) {
			// Template styles (were an inline <style> in the template). Enqueued before
			// the include so get_header()'s wp_head prints the stylesheet.
			wp_enqueue_style(
				'mhm-rentiva-single-vehicle',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/single-vehicle.css',
				array(),
				MHMRENTIVA_VERSION
			);
			include $template_path;
			exit;
		}
	}

	/**
	 * Initialize deposit services
	 */
	private function initialize_deposit_services(): void
	{
		// DepositAjax::register() was removed:
		// wp_ajax_mhmrentiva_calculate_deposit had zero shipped nonce producer
		// and zero consumer in either repo; the whole class was dead.
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
	}

	/**
	 * Register REST API
	 */
	public function register_rest_api(): void
	{
		if ($this->is_class_available('MHMRentiva\Admin\Utilities\Dashboard\DashboardPage')) {
			\MHMRentiva\Admin\Utilities\Dashboard\DashboardPage::register_rest_routes();
		}

		if ($this->is_class_available('MHMRentiva\Admin\About\REST\AboutController')) {
			\MHMRentiva\Admin\About\REST\AboutController::register_route();
		}
	}

	/**
	 * Clear cache when post is saved
	 */
	public function invalidate_cache_on_save(int $post_id): void
	{
		$post_type = get_post_type($post_id);

		if ($post_type === 'mhmrentiva_vehicle') {
			// Clear vehicle caches
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'vehicles',
					"vehicle_{$post_id}",
					'availability_calendar',
				)
			);
			// Invalidate Search Filters Cache
			delete_transient('mhmrentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhmrentiva_search_filters_v1');
		} elseif ($post_type === 'mhmrentiva_booking') {
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

		if ($post_type === 'mhmrentiva_vehicle') {
			// Clear vehicle caches
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'vehicles',
					"vehicle_{$post_id}",
					'availability_calendar',
				)
			);
			// Invalidate Search Filters Cache
			delete_transient('mhmrentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhmrentiva_search_filters_v1');
		} elseif ($post_type === 'mhmrentiva_booking') {
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
		unset($meta_id, $meta_value);

		$post_type = get_post_type($post_id);

		// Vehicle meta changes
		if ($post_type === 'mhmrentiva_vehicle' && strpos($meta_key, '_mhmrentiva_') === 0) {
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(
				array(
					'vehicles',
					"vehicle_{$post_id}",
					'availability_calendar',
				)
			);
			// Invalidate Search Filters Cache
			delete_transient('mhmrentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhmrentiva_search_filters_v1');
			// Invalidate Search Filters Cache
			delete_transient('mhmrentiva_search_filters_v1');
		}

		// Booking meta changes
		if ($post_type === 'mhmrentiva_booking' && strpos($meta_key, '_mhmrentiva_') === 0) {
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
	 * Safe: If the customer role already exists (for example from WooCommerce),
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
		$capabilities = $subscriber ? $subscriber->capabilities : array( 'read' => true );

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
	 * Plugin deactivation
	 */
	public static function deactivate(): void
	{
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}
