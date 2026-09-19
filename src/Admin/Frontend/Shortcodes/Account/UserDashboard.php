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
use MHMRentiva\Core\Services\Metrics\MetricCacheManager;



/**
 * Native user dashboard shortcode for the Panel page.
 */
final class UserDashboard {

	/**
	 * Register hooks required by the dashboard shortcode.
	 */
	public static function register(): void
	{
		MetricCacheManager::boot();
		add_action('template_redirect', array( self::class, 'guard_panel_access' ));
		add_action('wp_enqueue_scripts', array( self::class, 'enqueue_assets' ));
		// Before Elementor enqueues page assets by handle (its priority 20).
		add_action('wp_enqueue_scripts', array( self::class, 'register_kit_style' ), 1);
		add_filter('body_class', array( self::class, 'add_body_class' ));
	}

	/**
	 * Shortcode renderer.
	 *
	 * @param array<string, mixed> $atts
	 */
	public static function render(array $atts = array()): string
	{
		unset($atts);

		$type = DashboardContext::resolve();

		if ('guest' === $type) {
			return '';
		}

		$current_user = wp_get_current_user();
		$data         = self::build_template_data($type, (int) $current_user->ID, (string) $current_user->user_email);

		if ('customer' === $type) {
			// Last resort, not the main path. enqueue_assets() already put the kit
			// in <head> for the /panel/ surface and for any singular post whose
			// content carries the shortcode or the block; the Elementor widget
			// declares it through get_style_depends(). What none of those can see
			// -- a theme template, a widget area, a do_shortcode() in PHP, an
			// Elementor template outside the queried post -- still gets the
			// stylesheet here, printed in the footer (late, but not missing).
			// Enqueueing is idempotent.
			\MHMRentiva\Admin\Core\AssetManager::enqueue_kit( 'front' );

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
			// Administrators may preview the customer dashboard page.
			if (current_user_can('manage_options')) {
				return;
			}

			$account_url = function_exists('wc_get_page_permalink')
				? (string) call_user_func('wc_get_page_permalink', 'myaccount')
				: '';
			if ($account_url === '') {
				$account_url = home_url('/hesabim/');
			}
			wp_safe_redirect($account_url);
			exit;
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
		if (self::is_dashboard_surface()) {
			$classes[] = 'rentiva-panel-page';
		}

		return $classes;
	}

	/**
	 * Whether the dashboard surface is being rendered on this request.
	 *
	 * Lite's own surface is the /panel/ page. An extension that renders the
	 * same dashboard somewhere else claims the surface through this filter,
	 * rather than Lite learning where "somewhere else" is. Both the stylesheet
	 * and the body class the responsive rules are scoped to hang off it, so a
	 * surface that cannot claim it renders unstyled.
	 *
	 * The `mhmrentiva_dashboard_surface_active` filter receives Lite's own
	 * answer (whether this is the /panel/ page) and may override it.
	 *
	 * @since 6.1.1
	 */
	public static function is_dashboard_surface(): bool
	{
		return (bool) apply_filters('mhmrentiva_dashboard_surface_active', is_page('panel'));
	}

	/**
	 * Build render data for dashboard template.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_template_data(string $context, int $user_id, string $user_email): array
	{
		$active_tab     = self::resolve_tab();
		$dashboard_url  = self::get_dashboard_url();
		$current_user   = wp_get_current_user();
		$dashboard_data = DashboardDataProvider::build($context, $user_id, $user_email);

		$base_data = array(
			'context'       => $context,
			'active_tab'    => $active_tab,
			'dashboard_url' => $dashboard_url,
			'user'          => $current_user,
		);

		return array_merge($base_data, $dashboard_data);
	}

	/**
	 * Resolve active tab from query string.
	 */
	private static function resolve_tab(): string
	{
		$requested_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash( (string) $_GET['tab'])) : 'overview';
		$context       = DashboardContext::resolve();
		$allowed_tabs  = array_keys(DashboardNavigation::get_items($context));
		if ($allowed_tabs === array()) {
			$allowed_tabs = array( 'overview' );
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
	 * Register the kit's front stylesheet without enqueueing it, so the
	 * Elementor widget can name it in get_style_depends(): Elementor enqueues a
	 * widget's style dependencies by handle alone, and an unregistered handle is
	 * dropped silently. Registering costs nothing on pages that never use it.
	 */
	public static function register_kit_style(): void
	{
		\MHMRentiva\Admin\Core\AssetManager::register_kit( 'front' );
	}

	/**
	 * Whether the queried singular post's own content carries the dashboard,
	 * as the shortcode or as the block.
	 *
	 * Only the queried post: a dashboard placed in a template, a widget area
	 * or a synced pattern is invisible here and falls back to render().
	 */
	private static function queried_post_embeds_dashboard(): bool
	{
		if (! is_singular()) {
			return false;
		}

		$post = get_queried_object();
		if (! $post instanceof \WP_Post) {
			return false;
		}

		return has_shortcode($post->post_content, 'rentiva_user_dashboard')
			|| has_block('mhm-rentiva/user-dashboard', $post);
	}

	/**
	 * Enqueue the scoped stylesheet and the ui-core front kit.
	 */
	public static function enqueue_assets(): void
	{
		if (! self::is_dashboard_surface()) {
			// Not Lite's own surface, but the queried post may still embed the
			// dashboard. Enqueue the kit NOW, on wp_enqueue_scripts, so its
			// <link> lands in <head>; left to render() it would print in the
			// footer and the cards would paint unstyled first.
			if (self::queried_post_embeds_dashboard()) {
				\MHMRentiva\Admin\Core\AssetManager::enqueue_kit( 'front' );
			}
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-user-dashboard',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/user-dashboard.css',
			array( 'mhm-rentiva-css-variables' ),
			MHMRENTIVA_VERSION
		);

		// Kit stat cards and the front page shell (K4: iconless on the front end).
		\MHMRentiva\Admin\Core\AssetManager::enqueue_kit( 'front' );
	}
}
