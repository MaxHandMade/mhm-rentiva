<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Licensing\UpgradeFunnelTelemetry;
use MHMRentiva\Admin\Core\Utilities\UXHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * License Admin
 *
 * @method void render_admin_header(string $title, array $buttons = array(), bool $echo = true, string $subtitle = '')
 * @method void show_admin_notice(string $message, string $type = 'info', bool $dismissible = true)
 */
final class LicenseAdmin
{
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
		return sanitize_text_field((string) $value);
	}

	public static function register(): void
	{
		add_action('admin_post_mhm_rentiva_activate_license', array(self::class, 'handle_activation'));
		add_action('admin_post_mhm_rentiva_deactivate_license', array(self::class, 'handle_deactivation'));
		add_action('admin_post_mhm_rentiva_toggle_dev_mode', array(self::class, 'handle_toggle_dev_mode'));
		add_action('admin_post_mhm_rentiva_track_upgrade_cta', array(self::class, 'handle_track_upgrade_cta'));
		add_action('admin_notices', array(self::class, 'admin_notices'));
	}



	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'mhm-rentiva'));
		}

		$license          = LicenseManager::instance();
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

		echo '<p class="description">' . esc_html__('Enter your license key to enable Pro features (unlimited vehicles/bookings, export, advanced reports).', 'mhm-rentiva') . '</p>';
		/* translators: %s: feature name. */
		$vendor_payout_pro_only = sprintf( __( '%s (Pro Only)', 'mhm-rentiva' ), __( 'Vendor & Payout', 'mhm-rentiva' ) );
		echo '<p class="description"><strong>' . esc_html( $vendor_payout_pro_only ) . '</strong></p>';

		// Developer mode warning - only show if no real license is active
		$disable_dev_mode = get_option('mhm_rentiva_disable_dev_mode', false);
		$has_real_license = ! empty($license_data['key']) &&
			($license_data['status'] ?? '') === 'active' &&
			! empty($license_data['activation_id']);

		// Only show developer mode warning if no real license is active
		if ($is_dev_mode && ! $disable_dev_mode && ! $has_real_license) {
			echo '<div class="notice notice-warning"><p>';
			echo '<strong>' . esc_html__('🚀 Developer Mode Active', 'mhm-rentiva') . '</strong><br>';
			echo esc_html__('Automatic developer mode active (development environment detected). All Pro features enabled.', 'mhm-rentiva');
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
					$days_remaining = $is_expired ? 0 : (int) floor(($expires_timestamp - $current_time) / DAY_IN_SECONDS);

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
					$days_remaining = $is_expired ? 0 : (int) floor(($expires_timestamp - $current_time) / DAY_IN_SECONDS);

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

			echo '<p>' . esc_html__('All Pro features active: Unlimited vehicles/bookings, export, advanced reports, Vendor & Payout.', 'mhm-rentiva') . '</p>';
		} else {
			$variant = UpgradeFunnelTelemetry::resolve_variant_for_current_user();
			do_action('mhm_rentiva_track_upgrade_funnel_event', 'license_page_view_lite', $variant);

			echo '<div class="notice notice-warning inline">';
			echo '<p><strong>' . esc_html__('⚠️ Lite Version', 'mhm-rentiva') . '</strong></p>';
			echo '</div>';
			echo '<p>' . esc_html__('You are currently using the Lite version. A license key is required for Pro features.', 'mhm-rentiva') . '</p>';
			echo '<p><strong>' . esc_html__('Lite Version Limits:', 'mhm-rentiva') . '</strong></p>';
			echo '<ul>';
			echo '<li>' . esc_html__('Maximum 3 vehicles can be added', 'mhm-rentiva') . '</li>';
			echo '<li>' . esc_html__('Maximum 50 bookings can be made', 'mhm-rentiva') . '</li>';
			echo '<li>' . esc_html__('Maximum 3 customers can be added', 'mhm-rentiva') . '</li>';
			echo '<li>' . esc_html__('WooCommerce integration available', 'mhm-rentiva') . '</li>';
			echo '<li>' . esc_html__('CSV export available', 'mhm-rentiva') . '</li>';
			echo '<li>' . esc_html__('Report range limited to 30 days', 'mhm-rentiva') . '</li>';
			echo '</ul>';

			$tracked_upgrade_url = UpgradeFunnelTelemetry::build_tracked_cta_url(
				'upgrade_cta_click_license_page',
				admin_url('admin.php?page=mhm-rentiva-license')
			);
			echo '<p><a href="' . esc_url($tracked_upgrade_url) . '" class="button button-primary">' . esc_html__('Upgrade to Pro', 'mhm-rentiva') . '</a></p>';
		}

		// License activation form - only show if no active license
		if (! $is_active || $is_dev_mode) {
			echo '<h2>' . esc_html__('License Activation', 'mhm-rentiva') . '</h2>';

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

			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Are you sure you want to deactivate the license?', 'mhm-rentiva')) . '\')">';
			wp_nonce_field('mhm_rentiva_license_action', 'mhm_rentiva_license_nonce');
			echo '<input type="hidden" name="action" value="mhm_rentiva_deactivate_license">';

			submit_button(__('Deactivate License', 'mhm-rentiva'), 'secondary', 'submit', false);
			echo '</form>';
		}

		// Lite vs Pro comparison
		self::render_feature_comparison();

		echo '</div>';
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
			wp_safe_redirect(add_query_arg(array('license' => 'activated'), wp_get_referer()));
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

		wp_safe_redirect(add_query_arg(array('license' => 'deactivated'), wp_get_referer()));
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

		wp_safe_redirect(add_query_arg(array('license' => 'dev_mode_toggled'), wp_get_referer()));
		exit;
	}

	public static function handle_track_upgrade_cta(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is validated inside process_upgrade_cta_tracking().
		$request = (array) $_GET;
		self::process_upgrade_cta_tracking($request);

		$redirect = self::resolve_tracking_redirect($request);
		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * Deterministic test seam for security and tracking behavior.
	 *
	 * @param array<string,mixed> $request Request payload.
	 */
	public static function process_upgrade_cta_tracking_for_tests(array $request): void
	{
		self::process_upgrade_cta_tracking($request);
	}

	/**
	 * @param array<string,mixed> $request Request payload.
	 */
	private static function process_upgrade_cta_tracking(array $request): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		$nonce_raw = isset($request['_wpnonce']) ? (string) $request['_wpnonce'] : '';
		$nonce = sanitize_text_field(wp_unslash($nonce_raw));
		if ('' === $nonce || ! wp_verify_nonce($nonce, UpgradeFunnelTelemetry::get_tracking_nonce_action())) {
			return;
		}

		$event_raw = isset($request['event']) ? (string) $request['event'] : '';
		$event = sanitize_key(wp_unslash($event_raw));
		if ('' === $event || ! UpgradeFunnelTelemetry::is_allowed_event($event)) {
			return;
		}

		$variant = UpgradeFunnelTelemetry::resolve_variant_for_current_user();
		do_action('mhm_rentiva_track_upgrade_funnel_event', $event, $variant);
	}

	/**
	 * @param array<string,mixed> $request Request payload.
	 */
	private static function resolve_tracking_redirect(array $request): string
	{
		$default = admin_url('admin.php?page=mhm-rentiva-license');
		$redirect_raw = isset($request['redirect_to']) ? (string) $request['redirect_to'] : '';
		if ('' === $redirect_raw) {
			return $default;
		}

		$redirect = esc_url_raw(wp_unslash($redirect_raw));

		return wp_validate_redirect($redirect, $default);
	}

	public static function admin_notices(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query-flag check for admin notices.
		if (! isset($_GET['license'])) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notices display only, no form processing.
		$message       = isset($_GET['license']) ? sanitize_text_field(wp_unslash($_GET['license'])) : '';
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

			case 'dev_mode_toggled':
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html__('✅ Developer mode setting updated.', 'mhm-rentiva') . '</p>';
				echo '</div>';
				break;

			case 'error':
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
					/* translators: %s: error message */
					default => sprintf(esc_html__('License activation failed: %s', 'mhm-rentiva'), esc_html($error_message)),
				};
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p>' . esc_html($error_text) . '</p>';
				echo '</div>';
				break;

			case 'limit_exceeded':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notices display only, no form processing.
				$type        = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '';
				$limit_msg   = match ($type) {
					'vehicle' => __('Vehicle limit reached (Max 3).', 'mhm-rentiva'),
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
		}
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

	private static function render_feature_comparison(): void
	{
		echo '<h2>' . esc_html__('Lite vs Pro Comparison', 'mhm-rentiva') . '</h2>';

		// Use centralized comparison table from Mode class
		Mode::render_comparison_table(true); // compact mode for License page
	}
}


