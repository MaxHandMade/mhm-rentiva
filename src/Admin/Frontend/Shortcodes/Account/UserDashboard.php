<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Retirement stub for the `[rentiva_user_dashboard]` shortcode.
 *
 * This class used to render the customer dashboard on the Panel page. That
 * dashboard was retired in 6.2.0 -- a customer's home is the WooCommerce
 * account page -- and its whole pipeline was deleted. What is left renders a
 * pointer to My Account and keeps non-administrators off /panel/. The tag, the
 * block and the Elementor widget stay registered because sites in the wild have
 * them embedded.
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
		//
		// `! is_admin()` is a guard, not a reach. An earlier version of this
		// comment claimed Elementor previews the stub in a front-end iframe and
		// that the editing administrator therefore sees this paragraph.
		// Measured in the browser 2026-09-20: that is false. Elementor's editor
		// is itself a /wp-admin/ request, and it renders the widget while
		// assembling that page, baking the HTML into the document's `htmlCache`
		// field -- so is_admin() is true and this paragraph is absent from the
		// editor canvas. The same admin, on the same widget instance, does get
		// it on the front end. Both were captured.
		//
		// Keep the guard anyway, and precisely because of that cache: a notice
		// rendered during a wp-admin request is stored in htmlCache, and a
		// cache later served to a visitor would show an administrator-only
		// sentence to a customer. Admin-conditional output must not be baked
		// into cached markup.
		//
		// The block editor is the other case and behaves differently: its
		// preview goes through the REST block renderer, which is not
		// WP_ADMIN, so is_admin() is false and the notice does show there.
		// The Elementor user is told by the widget's own surfaces instead --
		// UserDashboardWidget::get_title() returns "User Dashboard
		// (deprecated)" and the panel carries a raw_html control saying so.
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
	 * Send everyone but an administrator away from the panel page, before
	 * output starts.
	 *
	 * Three outcomes, not two: an administrator stays (they may still want to
	 * look at the page they are about to clean up), a logged-in non-administrator
	 * is redirected to My Account, and a guest is redirected to the account page
	 * too -- that is where the login form lives -- falling back to
	 * wp_login_url() when WooCommerce is not available to answer.
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
