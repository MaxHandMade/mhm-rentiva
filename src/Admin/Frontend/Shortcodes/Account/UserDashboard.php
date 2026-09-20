<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Native user dashboard shortcode for the Panel page.
 */
final class UserDashboard {

	/**
	 * Register the one hook the retired surface still needs.
	 *
	 * The dashboard this shortcode used to render is gone (2026-09): a
	 * customer's home is the WooCommerce account page. The tag, the block and
	 * the Elementor widget stay registered because sites in the wild have them
	 * embedded -- removing them would blank a page someone published. What they
	 * render now is a pointer, so no stylesheet, no body class, no metric cache.
	 */
	public static function register(): void
	{
		add_action('template_redirect', array( self::class, 'guard_panel_access' ));
	}

	/**
	 * Shortcode renderer: the retirement notice.
	 *
	 * @param array<string, mixed> $atts Unused; the stub takes no attributes.
	 */
	public static function render(array $atts = array()): string
	{
		unset($atts);

		if (! is_user_logged_in()) {
			// A guest has nothing to be told here: the page they landed on is
			// someone else's layout decision, and the login form lives on the
			// account page they will be redirected to anyway.
			return '';
		}

		$account_url = function_exists('wc_get_page_permalink')
			? (string) call_user_func('wc_get_page_permalink', 'myaccount')
			: '';

		$html = sprintf(
			'<div class="mhm-rentiva-retired-dashboard"><p>%1$s</p>%2$s</div>',
			esc_html__('Your account has moved. Everything you had here — bookings, favourites and payments — is now on your account page.', 'mhm-rentiva'),
			'' === $account_url
				? ''
				: sprintf(
					'<p><a class="mhm-rentiva-retired-dashboard__link" href="%1$s">%2$s</a></p>',
					esc_url($account_url),
					esc_html__('Go to my account', 'mhm-rentiva')
				)
		);

		$role = 'customer';

		// Administrators get the sentence that tells them what to DO about it.
		// `! is_admin()` on purpose: the block editor previews this through the
		// REST block renderer and Elementor previews it in a front-end iframe,
		// and those are exactly the two places where the person who can remove
		// the block is looking at it.
		if (! is_admin() && current_user_can('manage_options')) {
			$html .= sprintf(
				'<div class="mhm-rentiva-retired-dashboard__admin"><p>%s</p></div>',
				esc_html__('Rentiva: this customer dashboard is deprecated and will be removed in 7.0. Remove the shortcode, block or widget from this page; customers use the WooCommerce account page instead. Only administrators see this notice.', 'mhm-rentiva')
			);
			$role  = 'admin';
		}

		/**
		 * Filters the retirement notice a retired dashboard surface prints.
		 *
		 * @since 6.2.0
		 *
		 * @param string $html The notice markup.
		 * @param string $role 'customer' or 'admin'.
		 */
		return (string) apply_filters('mhmrentiva_retired_dashboard_notice', $html, $role);
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
}
