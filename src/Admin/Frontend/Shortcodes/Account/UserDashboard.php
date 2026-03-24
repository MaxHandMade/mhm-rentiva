<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Core\Dashboard\CustomerDashboard;
use MHMRentiva\Core\Dashboard\DashboardContext;
use MHMRentiva\Core\Dashboard\DashboardDataProvider;
use MHMRentiva\Core\Dashboard\DashboardNavigation;
use MHMRentiva\Core\Dashboard\VendorDashboard;
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;



/**
 * Native user dashboard shortcode for the Panel page.
 */
final class UserDashboard
{
	/**
	 * Register hooks required by the dashboard shortcode.
	 */
	public static function register(): void
	{
		MetricCacheManager::boot();
		\MHMRentiva\Core\Dashboard\AnalyticsController::register();
		add_action('template_redirect', array(self::class, 'guard_panel_access'));
		add_action('wp_enqueue_scripts', array(self::class, 'enqueue_assets'));
		add_filter('body_class', array(self::class, 'add_body_class'));
	}

	/**
	 * Shortcode renderer.
	 *
	 * @param array<string, mixed> $atts
	 */
	public static function render(array $atts = array()): string
	{
		$type = DashboardContext::resolve();

		if ('guest' === $type) {
			return '';
		}

		$current_user = wp_get_current_user();
		$data = self::build_template_data($type, (int) $current_user->ID, (string) $current_user->user_email);

		if ('vendor' === $type) {
			return VendorDashboard::render($data);
		}

		if ('customer' === $type) {
			return CustomerDashboard::render($data);
		}

		return '';
	}

	/**
	 * Redirect unauthenticated users away from the panel page before output starts.
	 */
	public static function guard_panel_access(): void
	{
		if (! is_page('panel')) {
			return;
		}

		if (is_user_logged_in()) {
			// Admins can always access the panel (to test/verify vendor features).
			if (current_user_can('manage_options')) {
				return;
			}

			// /panel/ is vendor-only. Redirect non-vendor customers to WC My Account.
			$context = \MHMRentiva\Core\Dashboard\DashboardContext::resolve();
			if ($context === 'customer') {
				$account_url = function_exists('wc_get_page_permalink')
					? (string) call_user_func('wc_get_page_permalink', 'myaccount')
					: '';
				if ($account_url === '') {
					$account_url = home_url('/hesabim/');
				}
				wp_safe_redirect($account_url);
				exit;
			}
			return;
		}

		$login_url = function_exists('wc_get_page_permalink') ? call_user_func('wc_get_page_permalink', 'myaccount') : wp_login_url();
		if (! is_string($login_url) || $login_url === '') {
			$login_url = wp_login_url();
		}

		wp_safe_redirect($login_url);
		exit;
	}

	/**
	 * Add scoped body class for panel layout overrides.
	 *
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function add_body_class(array $classes): array
	{
		if (is_page('panel')) {
			$classes[] = 'rentiva-panel-page';
		}

		return $classes;
	}

	/**
	 * Build render data for dashboard template.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_template_data(string $context, int $user_id, string $user_email): array
	{
		$active_tab   = self::resolve_tab();
		$dashboard_url = self::get_dashboard_url();
		$current_user = wp_get_current_user();
		$dashboard_data = DashboardDataProvider::build($context, $user_id, $user_email);

		$base_data = array(
			'context'                => $context,
			'active_tab'             => $active_tab,
			'dashboard_url'          => $dashboard_url,
			'user'                   => $current_user,
		);

		return array_merge($base_data, $dashboard_data);
	}

	/**
	 * Resolve active tab from query string.
	 */
	private static function resolve_tab(): string
	{
		$requested_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash((string) $_GET['tab'])) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab state.
		$context = DashboardContext::resolve();
		$allowed_tabs = array_keys(DashboardNavigation::get_items($context));
		if ($allowed_tabs === array()) {
			$allowed_tabs = array('overview');
		}
		if (! in_array($requested_tab, $allowed_tabs, true)) {
			$requested_tab = 'overview';
		}

		return $requested_tab;
	}

	/**
	 * Get base dashboard URL for tab links.
	 */
	private static function get_dashboard_url(): string
	{
		$page_id = get_queried_object_id();
		if ($page_id > 0) {
			$permalink = get_permalink($page_id);
			if (is_string($permalink) && $permalink !== '') {
				return $permalink;
			}
		}

		$panel_page = get_page_by_path('panel');
		if ($panel_page instanceof \WP_Post) {
			$panel_permalink = get_permalink($panel_page);
			if (is_string($panel_permalink) && $panel_permalink !== '') {
				return $panel_permalink;
			}
		}

		return home_url('/panel/');
	}

	/**
	 * Enqueue scoped stylesheet.
	 */
	public static function enqueue_assets(): void
	{
		if (! is_page('panel')) {
			return;
		}

		wp_enqueue_style(
			'flatpickr',
			MHM_RENTIVA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css',
			array(),
			'4.6.13'
		);

		wp_enqueue_style(
			'mhm-rentiva-user-dashboard',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/user-dashboard.css',
			array('flatpickr'),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_script(
			'flatpickr',
			MHM_RENTIVA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js',
			array(),
			'4.6.13',
			true
		);

		wp_enqueue_script(
			'mhm-rentiva-dashboard',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/user-dashboard.js',
			array('flatpickr', 'jquery'),
			MHM_RENTIVA_VERSION,
			true
		);

		wp_localize_script('mhm-rentiva-dashboard', 'mhmRentivaAnalytics', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('mhm_rentiva_vendor_nonce'),
			'i18n'    => array(
				'loading' => __('Loading...', 'mhm-rentiva'),
				'error'   => __('Error fetching analytics data.', 'mhm-rentiva'),
			),
		));
	}
}
