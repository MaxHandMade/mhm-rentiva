<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Core\Utilities\UXHelper;



/**
 * License Admin
 *
 * @method void render_admin_header(string $title, array $buttons = array(), bool $echo = true, string $subtitle = '')
 * @method void show_admin_notice(string $message, string $type = 'info', bool $dismissible = true)
 */
final class LicenseAdmin {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	/**
	 * Safe sanitize text field that handles null values
	 *
	 * @param mixed $value Value to sanitize
	 * @return string Sanitized value
	 */
	public static function sanitize_text_field_safe($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}

	public static function register(): void
	{
		add_action('admin_post_mhm_rentiva_activate_license', array( self::class, 'handle_activation' ));
		add_action('admin_post_mhm_rentiva_deactivate_license', array( self::class, 'handle_deactivation' ));
		add_action('admin_post_mhm_rentiva_toggle_dev_mode', array( self::class, 'handle_toggle_dev_mode' ));
		// v4.32.0+ — Manage Subscription button → Polar customer portal.
		add_action('admin_post_mhm_rentiva_manage_subscription', array( self::class, 'handle_manage_subscription' ));
		add_action('admin_notices', array( self::class, 'admin_notices' ));
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_styles' ));
	}



	public static function enqueue_styles(string $hook): void
	{
		if (strpos($hook, 'mhm-rentiva-license') === false) {
			return;
		}

		// v4.32.0+ — filemtime cache-busting so state-driven button emphasis
		// styles refresh as soon as the CSS is updated, even mid-version.
		$css_path = MHM_RENTIVA_PLUGIN_DIR . 'assets/css/admin/license-admin.css';
		if (! file_exists($css_path)) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-license-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/license-admin.css',
			array(),
			MHM_RENTIVA_VERSION . '.' . filemtime($css_path)
		);
	}



	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'mhm-rentiva'));
		}

		$license      = LicenseManager::instance();
		$throttle_key = 'mhm_rentiva_license_visit_throttle';

		// v4.31.2+ — Manual "Re-validate Now" trigger. Bypasses the 5-minute
		// throttle so an admin who just had a license revoked / re-issued on
		// the license-server side can force an immediate re-check without
		// waiting for the throttle TTL.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked on the next line.
		$revalidate_requested = isset($_GET['mhm_revalidate']);
		if (
			$revalidate_requested
			&& isset($_GET['_wpnonce'])
			&& wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mhm_rentiva_revalidate')
		) {
			delete_transient($throttle_key);
			$current = $license->get();
			if (! empty($current['key']) && ! empty($current['activation_id'])) {
				$license->validate(true);
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'mhm-rentiva-license',
						'license' => 'revalidated',
					),
					admin_url('admin.php')
				)
			);
			exit;
		}

		// v4.31.0+ — Force a fresh server check when the admin opens this
		// page so a deactivation initiated from the license-server side is
		// reflected immediately instead of waiting for the 6-hourly cron.
		// Throttled by a 5-minute transient so reloads on the same page do
		// not hammer the license server.
		if (false === get_transient($throttle_key)) {
			$current = $license->get();
			if (! empty($current['key']) && ! empty($current['activation_id'])) {
				// Silent: errors surface through admin_notices on the next render.
				$license->validate(true);
			}
			set_transient($throttle_key, time(), 5 * MINUTE_IN_SECONDS);
		}

		$license_data     = $license->get();
		$disable_dev_mode = get_option('mhm_rentiva_disable_dev_mode', false);
		$is_dev_mode      = $license->isDevelopmentEnvironment() && ! $disable_dev_mode;
		$is_active        = $license->isActive();

		echo '<div class="wrap mhm-rentiva-wrap">';

		$this->render_admin_header(
			(string) get_admin_page_title(),
			array(
				array(
					'type' => 'documentation',
					'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
				),
			)
		);

		// Developer mode warning - only show if no real license is active
		$disable_dev_mode = get_option('mhm_rentiva_disable_dev_mode', false);
		$has_real_license = ! empty($license_data['key']) &&
			( $license_data['status'] ?? '' ) === 'active' &&
			! empty($license_data['activation_id']);

		// Only show developer mode warning if no real license is active
		if ($is_dev_mode && ! $disable_dev_mode && ! $has_real_license) {
			echo '<div class="notice notice-warning"><p>';
			if ( defined( 'MHM_RENTIVA_DEV_PRO' ) && MHM_RENTIVA_DEV_PRO ) {
				echo '<strong>' . esc_html__( '🔧 Developer Mode Active', 'mhm-rentiva' ) . '</strong> &mdash; ';
				echo esc_html__( 'Pro features can be tested (token check is skipped).', 'mhm-rentiva' );
			} else {
				echo '<strong>' . esc_html__( '🔧 Developer Mode', 'mhm-rentiva' ) . '</strong> &mdash; ';
				echo esc_html(
					sprintf(
						/* translators: %s — PHP define snippet to add to wp-config.php */
						__( 'Pro features cannot be tested. Add %s to wp-config.php.', 'mhm-rentiva' ),
						"define('MHM_RENTIVA_DEV_PRO', true);"
					)
				);
			}
			echo '</p></div>';

			// Option to disable developer mode
			echo '<div class="notice notice-info inline">';
			echo '<form id="disable_dev_mode_form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			echo '<input type="hidden" name="action" value="mhm_rentiva_toggle_dev_mode">';
			wp_nonce_field('mhm_rentiva_toggle_dev_mode', 'mhm_rentiva_toggle_dev_mode_nonce');
			echo '<p>';
			echo '<label><input type="checkbox" name="disable_dev_mode" value="1" onchange="this.form.submit();"> ';
			echo esc_html__('Disable automatic developer mode (force real license validation)', 'mhm-rentiva');
			echo '</label>';
			echo '</p>';
			echo '</form>';
			echo '</div>';
		} elseif ($is_dev_mode && $disable_dev_mode) {
			echo '<div class="notice notice-info"><p>';
			echo '<strong>' . esc_html__('ℹ️ Developer Mode Disabled', 'mhm-rentiva') . '</strong><br>';
			echo esc_html__('Automatic developer mode is disabled. Real license validation is required.', 'mhm-rentiva');
			echo '</p>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			echo '<input type="hidden" name="action" value="mhm_rentiva_toggle_dev_mode">';
			wp_nonce_field('mhm_rentiva_toggle_dev_mode', 'mhm_rentiva_toggle_dev_mode_nonce');
			echo '<p><button type="submit" class="button">' . esc_html__('Re-enable developer mode', 'mhm-rentiva') . '</button></p>';
			echo '</form>';
			echo '</div>';
		}

		// License status
		echo '<h2>' . esc_html__('License Status', 'mhm-rentiva') . '</h2>';

		if ($is_active) {
			echo '<div class="notice notice-success inline">';
			echo '<p><strong>' . esc_html__('✅ Pro License Active', 'mhm-rentiva') . '</strong></p>';
			echo '</div>';

			// Show license key and expiry date in a nice format
			if (! empty($license_data['key'])) {
				echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 15px 0;">';
				echo '<table class="form-table" style="margin: 0;">';
				echo '<tr>';
				echo '<th scope="row" style="width: 150px; padding: 8px 0;">' . esc_html__('License Key:', 'mhm-rentiva') . '</th>';
				echo '<td style="padding: 8px 0;"><code style="background: #fff; padding: 6px 10px; border: 1px solid #ccc; border-radius: 3px; font-size: 14px; font-weight: 600;">' . esc_html($license_data['key']) . '</code></td>';
				echo '</tr>';

				// Show expiry date if available
				if (isset($license_data['expires_at']) && ! empty($license_data['expires_at'])) {
					$expires_timestamp = is_numeric($license_data['expires_at']) ? (int) $license_data['expires_at'] : strtotime($license_data['expires_at']);
					$expires_date      = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expires_timestamp);
					$is_expired        = $expires_timestamp < time();
					$expires_class     = $is_expired ? 'color: #d63638;' : 'color: #00a32a;';

					// Calculate days remaining
					$current_time   = time();
					$days_remaining = $is_expired ? 0 : (int) floor(( $expires_timestamp - $current_time ) / DAY_IN_SECONDS);

					echo '<tr>';
					echo '<th scope="row" style="width: 150px; padding: 8px 0;">' . esc_html__('Expires At:', 'mhm-rentiva') . '</th>';
					echo '<td style="padding: 8px 0;">';
					echo '<span style="' . esc_attr($expires_class) . ' font-weight: 600;">' . esc_html($expires_date) . '</span>';
					if ($is_expired) {
						echo ' <span style="color: #d63638;">(' . esc_html__('Expired', 'mhm-rentiva') . ')</span>';
					} else {
						echo ' <span style="color: #666; font-size: 13px;">(';
						if ($days_remaining === 0) {
							echo esc_html__('Expires today', 'mhm-rentiva');
						} elseif ($days_remaining === 1) {
							echo esc_html__('1 day remaining', 'mhm-rentiva');
						} else {
							/* translators: %d: number of days remaining */
							echo esc_html(sprintf(__('%d days remaining', 'mhm-rentiva'), $days_remaining));
						}
						echo ')</span>';
					}
					echo '</td>';
					echo '</tr>';
				} elseif (isset($license_data['expires']) && ! empty($license_data['expires'])) {
					// Fallback for old format
					$expires_timestamp = is_numeric($license_data['expires']) ? (int) $license_data['expires'] : strtotime($license_data['expires']);
					$expires_date      = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expires_timestamp);
					$is_expired        = $expires_timestamp < time();
					$expires_class     = $is_expired ? 'color: #d63638;' : 'color: #00a32a;';

					// Calculate days remaining
					$current_time   = time();
					$days_remaining = $is_expired ? 0 : (int) floor(( $expires_timestamp - $current_time ) / DAY_IN_SECONDS);

					echo '<tr>';
					echo '<th scope="row" style="width: 150px; padding: 8px 0;">' . esc_html__('Expires At:', 'mhm-rentiva') . '</th>';
					echo '<td style="padding: 8px 0;">';
					echo '<span style="' . esc_attr($expires_class) . ' font-weight: 600;">' . esc_html($expires_date) . '</span>';
					if ($is_expired) {
						echo ' <span style="color: #d63638;">(' . esc_html__('Expired', 'mhm-rentiva') . ')</span>';
					} else {
						echo ' <span style="color: #666; font-size: 13px;">(';
						if ($days_remaining === 0) {
							echo esc_html__('Expires today', 'mhm-rentiva');
						} elseif ($days_remaining === 1) {
							echo esc_html__('1 day remaining', 'mhm-rentiva');
						} else {
							/* translators: %d: number of days remaining */
							echo esc_html(sprintf(__('%d days remaining', 'mhm-rentiva'), $days_remaining));
						}
						echo ')</span>';
					}
					echo '</td>';
					echo '</tr>';
				}

				echo '</table>';
				echo '</div>';
			}

			$this->render_active_features();
		} else {

			echo '<div class="notice notice-warning inline">';
			echo '<p><strong>' . esc_html__('⚠️ Lite Version Active', 'mhm-rentiva') . '</strong> &mdash; ';
			echo esc_html__('Enter your license key below to unlock unlimited access and Pro features.', 'mhm-rentiva');
			echo '</p>';
			echo '</div>';
		}

		// License activation form - only show if no active license
		if (! $is_active || $is_dev_mode) {
			echo '<h2>' . esc_html__('License Activation', 'mhm-rentiva') . '</h2>';

			// CTA to open the product/purchase page in a new tab.
			echo '<div class="mhm-license-purchase-cta" style="margin: 10px 0 20px;">';
			echo '<p class="description" style="margin: 0 0 8px;">';
			echo esc_html__('Don\'t have a license yet? Get one from our store, then paste the key below.', 'mhm-rentiva');
			echo '</p>';
			echo '<a class="button button-secondary" href="' . esc_url(UXHelper::get_product_url()) . '" target="_blank" rel="noopener noreferrer">';
			echo '<span class="dashicons dashicons-cart" style="margin-top: 4px;"></span> ';
			echo esc_html__('Get a License', 'mhm-rentiva');
			echo ' <span class="dashicons dashicons-external" style="margin-top: 4px;"></span>';
			echo '</a>';
			echo '</div>';

			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			wp_nonce_field('mhm_rentiva_license_action', 'mhm_rentiva_license_nonce');
			echo '<input type="hidden" name="action" value="mhm_rentiva_activate_license">';

			echo '<table class="form-table">';
			echo '<tr>';
			echo '<th scope="row"><label for="license_key">' . esc_html__('License Key', 'mhm-rentiva') . '</label></th>';
			echo '<td>';
			echo '<input type="text" id="license_key" name="license_key" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" required>';
			echo '<p class="description">' . esc_html__('Paste your Pro license key and click the Activate button.', 'mhm-rentiva') . '</p>';
			echo '</td>';
			echo '</tr>';
			echo '</table>';

			submit_button(__('Activate License', 'mhm-rentiva'), 'primary', 'submit', false);
			echo '</form>';
		} else {
			// License is active, show info message
			echo '<h2>' . esc_html__('License Activation', 'mhm-rentiva') . '</h2>';
			echo '<div class="notice notice-info inline">';
			echo '<p>' . esc_html__('Your license is active. To activate a different license key, please deactivate the current license first using the "Deactivate License" button below.', 'mhm-rentiva') . '</p>';
			echo '</div>';
		}

		// License deactivation - only show if license is active
		if ($is_active) {
			echo '<h2>' . esc_html__('License Management', 'mhm-rentiva') . '</h2>';

			if ($is_dev_mode) {
				echo '<div class="notice notice-info inline">';
				echo '<p>' . esc_html__('You are running in developer mode. You can deactivate to test real license.', 'mhm-rentiva') . '</p>';
				echo '</div>';
			}

			echo '<p>' . esc_html__('If you want to deactivate your license, click the button below. This will disable Pro features.', 'mhm-rentiva') . '</p>';

			// v4.32.0+ — "Manage Subscription" button. Opens the Polar customer
			// portal in a new tab so the admin can cancel auto-renewal, update
			// their payment method, switch plans, or resubscribe — all without
			// leaving WP admin. The button gains a state-driven emphasis class
			// (`...-warning` ≤ 30 days, `...-urgent` ≤ 7 days) so customers see
			// renewal urgency at a glance.
			$days_remaining = self::compute_days_remaining($license_data);
			$emphasis_class = self::compute_emphasis_class($days_remaining);

			$manage_url = wp_nonce_url(
				add_query_arg(
					'action',
					'mhm_rentiva_manage_subscription',
					admin_url('admin-post.php')
				),
				'mhm_rentiva_manage_subscription'
			);

			printf(
				'<a href="%1$s" target="_blank" rel="noopener" class="button button-primary mhm-rentiva-manage-subscription %2$s" style="margin-right:10px;">%3$s</a>',
				esc_url($manage_url),
				esc_attr($emphasis_class),
				esc_html__('Manage Subscription', 'mhm-rentiva')
			);

			// v4.31.2+ — "Re-validate Now" button: lets the customer admin
			// force an immediate license check without waiting for the 5-minute
			// throttle or the 6-hour cron. Useful when the licence-server
			// admin just revoked or re-issued an activation.
			$revalidate_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'           => 'mhm-rentiva-license',
						'mhm_revalidate' => '1',
					),
					admin_url('admin.php')
				),
				'mhm_rentiva_revalidate'
			);
			echo '<a href="' . esc_url($revalidate_url) . '" class="button" style="margin-right:10px;">' . esc_html__('Re-validate Now', 'mhm-rentiva') . '</a>';

			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;" onsubmit="return confirm(\'' . esc_js(__('Are you sure you want to deactivate the license?', 'mhm-rentiva')) . '\')">';
			wp_nonce_field('mhm_rentiva_license_action', 'mhm_rentiva_license_nonce');
			echo '<input type="hidden" name="action" value="mhm_rentiva_deactivate_license">';

			submit_button(__('Deactivate License', 'mhm-rentiva'), 'secondary', 'submit', false);
			echo '</form>';
		}

		// Lite vs Pro comparison
		self::render_feature_comparison();

		echo '</div>';
	}

	/**
	 * Render the dynamic "Active Pro features" line on the License page.
	 *
	 * Introduced in v4.33.0 — replaces the static "All Pro features active"
	 * string with a list derived from the actual feature-token gates. If no
	 * features are granted (license active but token empty), shows a warning
	 * notice with a "Re-validate Now" CTA.
	 */
	public function render_active_features(): void {
		$active_features = array();

		if ( Mode::canUseVendorMarketplace() ) {
			$active_features[] = __( 'Vendor & Payout', 'mhm-rentiva' );
		}
		if ( Mode::canUseAdvancedReports() ) {
			$active_features[] = __( 'Advanced Reports', 'mhm-rentiva' );
		}
		if ( Mode::canUseMessages() ) {
			$active_features[] = __( 'Messages', 'mhm-rentiva' );
		}
		if ( Mode::canUseExport() ) {
			$active_features[] = __( 'Expanded Export', 'mhm-rentiva' );
		}

		if ( ! empty( $active_features ) ) {
			// Each $active_features entry is __( literal, 'mhm-rentiva' ) — no
			// user input ever enters this array. If you later add a filter or
			// dynamic source, escape inside the implode and remove this ignore.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safety guaranteed by hardcoded __() literals; see comment above.
			echo '<p>' . esc_html__( 'Active Pro features:', 'mhm-rentiva' ) . ' ' . implode( ', ', $active_features ) . '</p>';
		} else {
			echo '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'License active but no feature tokens loaded yet. Click "Re-validate Now" to refresh.', 'mhm-rentiva' )
				. '</p></div>';
		}
	}

	public static function handle_activation(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission for this operation.', 'mhm-rentiva'));
		}

		if (
			! isset($_POST['mhm_rentiva_license_nonce']) ||
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_rentiva_license_nonce'] ?? '')), 'mhm_rentiva_license_action')
		) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		$license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
		if (empty($license_key)) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'license' => 'error',
						'message' => 'empty_key',
					),
					wp_get_referer()
				)
			);
			exit;
		}

		// Use LicenseManager for real API integration
		$license = LicenseManager::instance();
		$result  = $license->activate($license_key);

		if (is_wp_error($result)) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'license' => 'error',
						'message' => $result->get_error_code(),
					),
					wp_get_referer()
				)
			);
		} else {
			wp_safe_redirect(add_query_arg(array( 'license' => 'activated' ), wp_get_referer()));
		}

		exit;
	}

	public static function handle_deactivation(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission for this operation.', 'mhm-rentiva'));
		}

		if (
			! isset($_POST['mhm_rentiva_license_nonce']) ||
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_rentiva_license_nonce'] ?? '')), 'mhm_rentiva_license_action')
		) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		$license = LicenseManager::instance();

		// Deactivate license on server first (this will also clear local data)
		$result = $license->deactivate();

		// If deactivation fails on server, still clear local data
		if (is_wp_error($result)) {
			$license->clearLicense();
		}

		wp_safe_redirect(add_query_arg(array( 'license' => 'deactivated' ), wp_get_referer()));
		exit;
	}

	public static function handle_toggle_dev_mode(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission for this operation.', 'mhm-rentiva'));
		}

		if (
			! isset($_POST['mhm_rentiva_toggle_dev_mode_nonce']) ||
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_rentiva_toggle_dev_mode_nonce'] ?? '')), 'mhm_rentiva_toggle_dev_mode')
		) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		$current_value = get_option('mhm_rentiva_disable_dev_mode', false);
		update_option('mhm_rentiva_disable_dev_mode', ! $current_value);

		wp_safe_redirect(add_query_arg(array( 'license' => 'dev_mode_toggled' ), wp_get_referer()));
		exit;
	}

	/**
	 * Handle the "Manage Subscription" button click (v4.32.0+).
	 *
	 * Mints a Polar customer-portal session via {@see LicenseManager::createCustomerPortalSession()}
	 * and redirects the admin there. On any failure (license not active,
	 * server 4xx, tampered response, network error) we fall back to the
	 * License page with `?license=manage_unavailable&reason=<sanitized code>`,
	 * which {@see admin_notices()} renders as a customer-friendly warning.
	 */
	public static function handle_manage_subscription(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'mhm-rentiva'), '', array( 'response' => 403 ));
		}

		check_admin_referer('mhm_rentiva_manage_subscription');

		$license_admin_url = admin_url('admin.php?page=mhm-rentiva-license');
		$session           = LicenseManager::instance()->createCustomerPortalSession($license_admin_url);

		if (empty($session['success'])) {
			$reason = isset($session['error_code']) ? sanitize_key( (string) $session['error_code']) : 'unknown_error';
			wp_safe_redirect(
				add_query_arg(
					array(
						'license' => 'manage_unavailable',
						'reason'  => $reason,
					),
					$license_admin_url
				)
			);
			exit;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External Polar URL by design (customer portal lives on polar.sh).
		wp_redirect( (string) $session['customer_portal_url']);
		exit;
	}

	public static function admin_notices(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query-flag check for admin notices.
		if (! isset($_GET['license'])) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notices display only, no form processing.
		$message = isset($_GET['license']) ? sanitize_text_field(wp_unslash($_GET['license'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notices display only, no form processing.
		$error_message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : '';

		switch ($message) {
			case 'activated':
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html__('✅ License successfully activated!', 'mhm-rentiva') . '</p>';
				echo '</div>';
				break;

			case 'deactivated':
				echo '<div class="notice notice-info is-dismissible">';
				echo '<p>' . esc_html__('ℹ️ License deactivated.', 'mhm-rentiva') . '</p>';
				echo '</div>';
				break;

			case 'revalidated':
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html__('🔄 License re-validated against the licence server. Pro state is now in sync.', 'mhm-rentiva') . '</p>';
				echo '</div>';
				break;

			case 'dev_mode_toggled':
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html__('✅ Developer mode setting updated.', 'mhm-rentiva') . '</p>';
				echo '</div>';
				break;

			case 'error':
				// v4.30.2+ — Defensive: when stale URL state leaves $_GET[message]
				// unset (e.g. browser back/forward, bookmark, copy-paste of a
				// truncated URL), the original `default` match arm rendered
				// "License activation failed: " with an empty trailing %s.
				// Skip the notice entirely when there is no actual error code.
				if ('' === $error_message) {
					break;
				}

				$error_text = match ($error_message) {
					'empty_key' => __('License key cannot be empty.', 'mhm-rentiva'),
					'invalid_key' => __('Invalid license key.', 'mhm-rentiva'),
					'invalid' => __('Invalid license key.', 'mhm-rentiva'),
					'expired' => __('License has expired.', 'mhm-rentiva'),
					'expired_license' => __('License has expired.', 'mhm-rentiva'),
					'inactive_license' => __('License is not active.', 'mhm-rentiva'),
					'already_activated' => __('License is already activated on another site. Maximum 1 activation allowed.', 'mhm-rentiva'),
					'site_already_activated' => __('This site already has an active license key. Please deactivate the existing license before activating a new one.', 'mhm-rentiva'),
					'license_connection' => __('Could not connect to license server. Please check your internet connection and try again.', 'mhm-rentiva'),
					'license_http' => __('License server error. Please try again later.', 'mhm-rentiva'),
					'missing_parameters' => __('Missing required parameters.', 'mhm-rentiva'),
					// v4.30.2+ — server v1.9.0+ reverse-validation error codes.
					'site_unreachable' => __('License server could not reach your site for verification. A firewall or CDN may be blocking inbound HTTP. Please contact support.', 'mhm-rentiva'),
					'site_verification_failed' => __('Site verification failed. Please try again or contact support.', 'mhm-rentiva'),
					'tampered_response' => __('License server response could not be verified (tampered or out-of-sync). Please contact support.', 'mhm-rentiva'),
					// v4.30.2+ — server v1.8.0+ / v1.9.3+ product binding error codes.
					'product_mismatch' => __('This license key was issued for a different product and cannot be activated here.', 'mhm-rentiva'),
					'product_slug_required' => __('Your plugin version is outdated or the request is malformed. Please update to the latest plugin release and try again.', 'mhm-rentiva'),
					// v4.30.2+ — Generic fallback for unknown/future codes; the
					// raw code is exposed via the wrapper's data-error-code
					// attribute (see below) for support, NOT inline text.
					default => __('License activation failed. Please try again.', 'mhm-rentiva'),
				};

				printf(
					'<div class="notice notice-error is-dismissible" data-error-code="%s"><p>%s</p></div>',
					esc_attr($error_message),
					esc_html($error_text)
				);
				break;

			case 'limit_exceeded':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notices display only, no form processing.
				$type      = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '';
				$limit_msg = match ($type) {
					/* translators: %d: max vehicles allowed in Lite */
					'vehicle' => sprintf(__('Vehicle limit reached (Max %d).', 'mhm-rentiva'), \MHMRentiva\Admin\Licensing\Mode::maxVehicles()),
					'booking' => __('Booking limit reached (Max 50).', 'mhm-rentiva'),
					'route' => __('Transfer route limit reached.', 'mhm-rentiva'),
					'addon' => __('Addon limit reached.', 'mhm-rentiva'),
					default => __('License limit exceeded.', 'mhm-rentiva'),
				};
				echo '<div class="notice notice-warning is-dismissible">';
				echo '<p><strong>' . esc_html__('⚠️ Pro Upgrade Required', 'mhm-rentiva') . '</strong></p>';
				echo '<p>' . esc_html($limit_msg) . ' ' . esc_html__('Please activate your license to unlock unlimited access.', 'mhm-rentiva') . '</p>';
				echo '</div>';
				break;

			case 'pro_feature':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notices display only, no form processing.
				$feature_name = isset($_GET['feature']) ? sanitize_text_field(wp_unslash($_GET['feature'])) : '';
				echo '<div class="notice notice-warning is-dismissible">';
				echo '<p><strong>' . esc_html__('💎 Pro Feature Locked', 'mhm-rentiva') . '</strong></p>';
				if (! empty($feature_name)) {
					/* translators: %s: feature name */
					echo '<p>' . esc_html(sprintf(__('%s is available in Pro version.', 'mhm-rentiva'), $feature_name)) . '</p>';
				} else {
					echo '<p>' . esc_html__('This feature is available in Pro version.', 'mhm-rentiva') . '</p>';
				}
				echo '<p>' . esc_html__('Enter your license key below to unlock this feature.', 'mhm-rentiva') . '</p>';
				echo '</div>';
				break;

			case 'manage_unavailable':
				// v4.32.0+ — Customer clicked "Manage Subscription" but the
				// portal session could not be minted (license inactive,
				// server 4xx, tampered response, network error). Render a
				// friendly warning with a short reason label.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notices display only, no form processing.
				$reason       = isset($_GET['reason']) ? sanitize_key( (string) wp_unslash($_GET['reason'])) : '';
				$reason_label = self::get_manage_unavailable_label($reason);
				echo '<div class="notice notice-warning is-dismissible">';
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: short reason label like "service unavailable" */
							__('ℹ️ Subscription management is not available right now (%s). Please try again later or contact support@wpalemi.com.', 'mhm-rentiva'),
							$reason_label
						)
					)
				);
				echo '</div>';
				break;
		}
	}

	/**
	 * Map a `manage_unavailable` reason code to a short, customer-friendly
	 * label that fits inside the parenthesis of the warning notice (v4.32.0+).
	 *
	 * Unknown codes (future server releases, typos in the URL) collapse to
	 * "unknown error" so we never leak raw technical strings.
	 */
	private static function get_manage_unavailable_label(string $reason): string
	{
		$labels = array(
			'license_not_subscription' => __('legacy license', 'mhm-rentiva'),
			'polar_api_unavailable'    => __('service unavailable', 'mhm-rentiva'),
			'license_not_active'       => __('license inactive', 'mhm-rentiva'),
			'site_not_activated'       => __('site not activated', 'mhm-rentiva'),
			'license_not_found'        => __('license not found', 'mhm-rentiva'),
			// Network / HTTP transport errors collapsed to a single user-facing label.
			// Triggered when request() returns WP_Error (DNS/timeout/connection-refused),
			// when the server returns a non-2xx without a JSON body, OR — importantly —
			// when an older mhm-license-server (< v1.11.0) returns a `rest_no_route` 404
			// for the new /licenses/customer-portal-session endpoint.
			'license_http'             => __('service unavailable', 'mhm-rentiva'),
			'license_connection'       => __('service unavailable', 'mhm-rentiva'),
			'http_error'               => __('service unavailable', 'mhm-rentiva'),
		);
		return $labels[ $reason ] ?? __('unknown error', 'mhm-rentiva');
	}

	private static function validate_license(string $license_key): array
	{
		// This method is no longer used - LicenseManager handles API calls directly
		// Kept for backward compatibility but should not be called
		return array(
			'success' => false,
			'message' => 'invalid',
		);
	}

	/**
	 * Compute days remaining until license expiry (v4.32.0+).
	 *
	 * Reads `expires_at` (preferred, v4.30.0+ canonical) with a fallback to the
	 * legacy `expires` key. Accepts unix timestamp ints or any strtotime()-able
	 * string. Returns `null` when no expiry information is available so the
	 * caller can render a default (non-emphasised) UI.
	 *
	 * @param array<string,mixed> $license_data License option payload from
	 *                                          {@see LicenseManager::get()}.
	 * @return int|null Whole days remaining (>= 0) or null when undetermined.
	 */
	public static function compute_days_remaining(array $license_data): ?int
	{
		$expires_raw = $license_data['expires_at'] ?? $license_data['expires'] ?? '';
		if (empty($expires_raw)) {
			return null;
		}

		$ts = is_numeric($expires_raw) ? (int) $expires_raw : strtotime( (string) $expires_raw);
		if ($ts === false) {
			return null;
		}

		$diff = $ts - time();
		if ($diff <= 0) {
			return 0;
		}

		return (int) ceil($diff / DAY_IN_SECONDS);
	}

	/**
	 * Map days-remaining to the CSS emphasis class for the "Manage
	 * Subscription" button (v4.32.0+).
	 *
	 * Mapping:
	 * - null           → '' (no expiry data — default styling)
	 * - >= 31 days     → '' (plenty of runway)
	 * - 8..30 days     → 'mhm-rentiva-license-warning' (yellow)
	 * - 0..7 days      → 'mhm-rentiva-license-urgent' (amber + glow)
	 */
	public static function compute_emphasis_class(?int $days_remaining): string
	{
		if ($days_remaining === null) {
			return '';
		}
		if ($days_remaining <= 7) {
			return 'mhm-rentiva-license-urgent';
		}
		if ($days_remaining <= 30) {
			return 'mhm-rentiva-license-warning';
		}
		return '';
	}

	private static function render_feature_comparison(): void
	{
		$license_url = admin_url('admin.php?page=mhm-rentiva-license');
		$is_pro      = \MHMRentiva\Admin\Licensing\Mode::isPro();

		echo '<h2>' . esc_html__('Lite vs Pro', 'mhm-rentiva') . '</h2>';
		echo '<div class="mhm-comparison-wrap">';

		// --- LITE COLUMN ---
		echo '<div class="mhm-plan-lite">';
		echo '<div class="mhm-plan-header">';
		echo '<h3 class="mhm-plan-title">' . esc_html__('Lite', 'mhm-rentiva') . '</h3>';
		echo '<p class="mhm-plan-subtitle">' . esc_html__('Free — Get started', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<ul class="mhm-plan-features">';

		$max_vehicles  = (int) apply_filters('mhm_rentiva_lite_max_vehicles', 5);
		$max_bookings  = (int) apply_filters('mhm_rentiva_lite_max_bookings', 50);
		$max_customers = (int) apply_filters('mhm_rentiva_lite_max_customers', 10);
		$max_addons    = (int) apply_filters('mhm_rentiva_lite_max_addons', 4);
		$max_routes    = (int) apply_filters('mhm_rentiva_lite_max_transfer_routes', 3);
		$max_gallery   = (int) apply_filters('mhm_rentiva_lite_max_gallery_images', 5);

		$lite_rows = array(
			array(
				'label' => __('Maximum Vehicles', 'mhm-rentiva'),
				/* translators: %d: maximum number of vehicles allowed in Lite tier */
				'value' => sprintf(__('%d vehicles', 'mhm-rentiva'), $max_vehicles),
			),
			array(
				'label' => __('Maximum Bookings', 'mhm-rentiva'),
				/* translators: %d: maximum number of bookings allowed in Lite tier */
				'value' => sprintf(__('%d bookings', 'mhm-rentiva'), $max_bookings),
			),
			array(
				'label' => __('Maximum Customers', 'mhm-rentiva'),
				/* translators: %d: maximum number of customers allowed in Lite tier */
				'value' => sprintf(__('%d customers', 'mhm-rentiva'), $max_customers),
			),
			array(
				'label' => __('Maximum Addons', 'mhm-rentiva'),
				/* translators: %d: maximum number of add-on services allowed in Lite tier */
				'value' => sprintf(__('%d services', 'mhm-rentiva'), $max_addons),
			),
			array(
				'label' => __('VIP Transfer Routes', 'mhm-rentiva'),
				/* translators: %d: maximum number of transfer routes allowed in Lite tier */
				'value' => sprintf(__('%d routes', 'mhm-rentiva'), $max_routes),
			),
			array(
				'label' => __('Gallery Images', 'mhm-rentiva'),
				/* translators: %d: maximum number of gallery images per vehicle in Lite tier */
				'value' => sprintf(__('%d / vehicle', 'mhm-rentiva'), $max_gallery),
			),
			array(
				'label' => __('Export Formats', 'mhm-rentiva'),
				'value' => 'CSV',
			),
			array(
				'label' => __('Report Date Range', 'mhm-rentiva'),
				/* translators: %d: maximum report date range in days for Lite tier */
				'value' => sprintf(__('%d days', 'mhm-rentiva'), (int) apply_filters('mhm_rentiva_lite_reports_max_days', 30)),
			),
			array(
				'label' => __('Payment Gateways', 'mhm-rentiva'),
				'value' => 'WooCommerce',
			),
			array(
				'label'       => __('Advanced Reports', 'mhm-rentiva'),
				'unavailable' => true,
			),
			array(
				'label'       => __('Messaging System', 'mhm-rentiva'),
				'unavailable' => true,
			),
			array(
				'label'       => __('Vendor & Payout', 'mhm-rentiva'),
				'unavailable' => true,
			),
			array(
				'label' => __('REST API', 'mhm-rentiva'),
				'value' => __('Limited', 'mhm-rentiva'),
			),
		);

		foreach ($lite_rows as $row) {
			echo '<li>';
			echo '<span class="mhm-plan-feature-label">' . esc_html($row['label']) . '</span>';
			if (! empty($row['unavailable'])) {
				echo '<span class="mhm-feature-unavailable">&#10007; ' . esc_html__('Not available', 'mhm-rentiva') . '</span>';
			} else {
				echo '<span class="mhm-plan-value">' . esc_html($row['value']) . '</span>';
			}
			echo '</li>';
		}

		echo '</ul>';

		if ($is_pro) {
			echo '<span class="mhm-plan-cta">' . esc_html__('Your previous plan', 'mhm-rentiva') . '</span>';
		} else {
			echo '<span class="mhm-plan-cta">' . esc_html__('Current Plan', 'mhm-rentiva') . '</span>';
		}
		echo '</div>';

		// --- PRO COLUMN ---
		echo '<div class="mhm-plan-pro">';
		echo '<span class="mhm-plan-badge">' . esc_html__('RECOMMENDED', 'mhm-rentiva') . '</span>';
		echo '<div class="mhm-plan-header">';
		echo '<h3 class="mhm-plan-title">' . esc_html__('Pro', 'mhm-rentiva') . '</h3>';
		echo '<p class="mhm-plan-subtitle">' . esc_html__('Unlimited everything', 'mhm-rentiva') . '</p>';
		echo '</div>';

		echo '<ul class="mhm-plan-features">';

		$pro_rows = array(
			array(
				'label' => __('Maximum Vehicles', 'mhm-rentiva'),
				'value' => __('Unlimited', 'mhm-rentiva'),
			),
			array(
				'label' => __('Maximum Bookings', 'mhm-rentiva'),
				'value' => __('Unlimited', 'mhm-rentiva'),
			),
			array(
				'label' => __('Maximum Customers', 'mhm-rentiva'),
				'value' => __('Unlimited', 'mhm-rentiva'),
			),
			array(
				'label' => __('Maximum Addons', 'mhm-rentiva'),
				'value' => __('Unlimited', 'mhm-rentiva'),
			),
			array(
				'label' => __('VIP Transfer Routes', 'mhm-rentiva'),
				'value' => __('Unlimited', 'mhm-rentiva'),
			),
			array(
				'label' => __('Gallery Images', 'mhm-rentiva'),
				'value' => __('Unlimited', 'mhm-rentiva'),
			),
			array(
				'label' => __('Export Formats', 'mhm-rentiva'),
				'value' => 'CSV + JSON',
			),
			array(
				'label' => __('Report Date Range', 'mhm-rentiva'),
				'value' => __('Unlimited', 'mhm-rentiva'),
			),
			array(
				'label' => __('Payment Gateways', 'mhm-rentiva'),
				'value' => 'WooCommerce',
			),
			array(
				'label'     => __('Advanced Reports', 'mhm-rentiva'),
				'available' => true,
			),
			array(
				'label'     => __('Messaging System', 'mhm-rentiva'),
				'available' => true,
			),
			array(
				'label'     => __('Vendor & Payout', 'mhm-rentiva'),
				'available' => true,
			),
			array(
				'label' => __('REST API', 'mhm-rentiva'),
				'value' => __('Full Access', 'mhm-rentiva'),
			),
		);

		foreach ($pro_rows as $row) {
			echo '<li>';
			echo '<span class="mhm-plan-feature-label">' . esc_html($row['label']) . '</span>';
			if (! empty($row['available'])) {
				echo '<span class="mhm-feature-available">&#10003; ' . esc_html__('Included', 'mhm-rentiva') . '</span>';
			} else {
				echo '<span class="mhm-plan-value">' . esc_html($row['value']) . '</span>';
			}
			echo '</li>';
		}

		echo '</ul>';

		if ($is_pro) {
			echo '<span class="mhm-plan-cta" style="background:#00a32a;color:#fff;display:block;text-align:center;padding:12px;border-radius:6px;font-weight:700;">' . esc_html__('✅ Active Plan', 'mhm-rentiva') . '</span>';
		} else {
			echo '<a href="' . esc_url($license_url) . '" class="mhm-plan-cta">' . esc_html__('Enter License Key →', 'mhm-rentiva') . '</a>';
		}
		echo '</div>';

		echo '</div>'; // .mhm-comparison-wrap
	}
}
