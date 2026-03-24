<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized Pro Feature Notice helper
 * Displays Pro feature warnings and Developer Mode notices on admin pages
 */
final class ProFeatureNotice {



	/**
	 * Display Developer Mode banner
	 *
	 * @param array $features Optional list of Pro features to highlight
	 */
	public static function displayDeveloperModeBanner( array $features = array() ): void {
		$license      = LicenseManager::instance();
		$license_data = $license->get();

		// BUG FIX 2: Don't show if Pro license is active (real license, not developer mode)
		// Check if there's a real license key (not just developer mode)
		$has_real_license = ! empty( $license_data['key'] ) &&
			( $license_data['status'] ?? '' ) === 'active' &&
			! empty( $license_data['activation_id'] );

		if ( $has_real_license ) {
			return; // Real license active, don't show developer mode banner
		}

		// Also check if developer mode is manually disabled
		$disable_dev_mode = get_option( 'mhm_rentiva_disable_dev_mode', false );
		if ( $disable_dev_mode ) {
			return; // Developer mode disabled, don't show banner
		}

		if ( ! $license->isDevelopmentEnvironment() ) {
			return;
		}

		echo '<div class="mhm-dev-mode-banner notice notice-info inline">';
		echo '<div class="mhm-dev-banner-content" style="background: #2271b1; color: #fff; border-left: 4px solid #135e96; padding: 12px; border-radius: 4px;">';
		echo '<p style="margin: 0; font-size: 14px;">';
		echo '<strong style="font-size: 15px;">🚀 ' . esc_html__( 'Developer Mode Active', 'mhm-rentiva' ) . '</strong><br>';
		echo '<span style="opacity: 0.95;">' . esc_html__( 'All Pro features including VIP Transfers, Messaging System, Advanced Analytics, and unlimited management are enabled for development.', 'mhm-rentiva' ) . '</span>';
		echo '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Display Pro feature notice for Lite users
	 *
	 * @param string      $page_name Page name identifier
	 * @param array       $pro_features List of Pro features available on this page
	 * @param string|null $custom_message Custom message (optional)
	 */
	public static function displayProFeatureNotice( string $page_name, array $pro_features = array(), ?string $custom_message = null ): void {
		if ( Mode::isPro() ) {
			return;
		}

		$license_url = admin_url( 'admin.php?page=mhm-rentiva-license' );

		if ( $custom_message ) {
			$message = $custom_message;
		} elseif ( ! empty( $pro_features ) ) {
			$feature_list = implode( ', ', array_slice( $pro_features, 0, -1 ) ) . ' ' . __( 'and', 'mhm-rentiva' ) . ' ' . end( $pro_features );
			$message      = sprintf(
				/* translators: 1: feature list, 2: is/are, 3: license URL. */
				__( 'You are using Rentiva Lite. %1$s %2$s available in Pro version. <a href="%3$s">Enter your license key</a> to enable.', 'mhm-rentiva' ),
				$feature_list,
				count( $pro_features ) === 1 ? __( 'is', 'mhm-rentiva' ) : __( 'are', 'mhm-rentiva' ),
				esc_url( $license_url )
			);
		} else {
			$message = sprintf(
				/* translators: %s: license URL */
				__( 'You are using Rentiva Lite. This feature is available in Pro version. <a href="%s">Enter your license key</a> to enable.', 'mhm-rentiva' ),
				esc_url( $license_url )
			);
		}

		echo '<div class="notice notice-warning mhm-pro-feature-notice inline">';
		echo '<p>' . wp_kses_post( $message ) . '</p>';
		echo '</div>';
	}

	/**
	 * Display Pro feature badge on UI elements
	 *
	 * @param string $feature_name Feature name
	 * @param bool   $is_enabled Whether feature is currently enabled
	 */
	public static function displayProBadge( string $feature_name = '', bool $is_enabled = false ): void {
		if ( $is_enabled ) {
			return;
		}

		/* translators: %s: feature name */
		$badge_text = $feature_name ? sprintf( __( '%s (Pro)', 'mhm-rentiva' ), $feature_name ) : __( 'Pro', 'mhm-rentiva' );

		echo '<span class="mhm-pro-badge" style="display: inline-block; background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 6px; vertical-align: middle;">';
		echo esc_html( $badge_text );
		echo '</span>';
	}

	/**
	 * Get Pro feature list for specific page
	 *
	 * @param string $page_name Page identifier
	 * @return array List of Pro features
	 */
	public static function getProFeaturesForPage( string $page_name ): array {
		$features = array(
			'export'   => array(
				__( 'JSON Export Format', 'mhm-rentiva' ),
				__( 'Unlimited Date Range', 'mhm-rentiva' ),
				__( 'Unlimited Export Rows', 'mhm-rentiva' ),
			),
			'reports'  => array(
				__( 'Advanced Reports', 'mhm-rentiva' ),
				__( 'Unlimited Date Range', 'mhm-rentiva' ),
				__( 'Unlimited Report Rows', 'mhm-rentiva' ),
				__( 'PDF Report Export', 'mhm-rentiva' ),
			),
			'messages' => array(
				__( 'Messaging System', 'mhm-rentiva' ),
				__( 'Message Threads', 'mhm-rentiva' ),
				__( 'Email Notifications', 'mhm-rentiva' ),
				__( 'Unlimited Messages', 'mhm-rentiva' ),
			),
			'settings' => array(
				__( 'JSON Export Format', 'mhm-rentiva' ),
				__( 'Advanced Reports', 'mhm-rentiva' ),
				__( 'Unlimited Date Range', 'mhm-rentiva' ),
				__( 'Unlimited Report Rows', 'mhm-rentiva' ),
				__( 'Messaging System', 'mhm-rentiva' ),
				__( 'Unlimited Vehicles/Bookings/Customers', 'mhm-rentiva' ),
			),
			'payments' => array(
				// Payment features (Offline and WooCommerce) are available in both Lite and Pro versions
			),
		);

		return $features[ $page_name ] ?? array();
	}

	/**
	 * Display comprehensive Pro feature notice for a page
	 *
	 * @param string     $page_name Page identifier
	 * @param array|null $custom_features Custom feature list (optional)
	 */
	public static function displayPageProNotice( string $page_name, ?array $custom_features = null ): void {
		// If Pro license is active (not developer mode), don't show any notices
		if ( Mode::isPro() && ! self::isDeveloperMode() ) {
			return;
		}

		// Developer mode banner (only if developer mode is active)
		$features = $custom_features ?? self::getProFeaturesForPage( $page_name );
		self::displayDeveloperModeBanner( $features );

		// Pro feature notice for Lite users (only if not Pro)
		if ( ! empty( $features ) ) {
			self::displayProFeatureNotice( $page_name, $features );
		}
	}

	/**
	 * Check if current environment is development mode
	 *
	 * @return bool True if developer mode
	 */
	public static function isDeveloperMode(): bool {
		$license = LicenseManager::instance();
		return $license->isDevelopmentEnvironment();
	}

	/**
	 * Check if Pro features are enabled
	 *
	 * @return bool True if Pro
	 */
	public static function isPro(): bool {
		return Mode::isPro();
	}

	/**
	 * Display limit usage notice for Lite users
	 *
	 * @param string $type Limit type: 'vehicles', 'bookings', or 'customers'
	 */
	public static function displayLimitNotice( string $type ): void {
		if ( Mode::isPro() ) {
			return;
		}

		$limits      = \MHMRentiva\Admin\Licensing\Restrictions::check_limits();
		$license_url = admin_url( 'admin.php?page=mhm-rentiva-license' );

		if ( $type === 'vehicles' && isset( $limits['vehicles'] ) ) {
			$current    = $limits['vehicles']['current'];
			$max        = $limits['vehicles']['max'];
			$percentage = $max > 0 ? round( ( $current / $max ) * 100 ) : 0;
			$exceeded   = $limits['vehicles']['exceeded'];

			$notice_class = $exceeded ? 'notice-error' : ( $percentage >= 80 ? 'notice-warning' : 'notice-info' );
			$icon         = $exceeded ? '⚠️' : ( $percentage >= 80 ? '⚠️' : 'ℹ️' );

			echo '<div class="notice ' . esc_attr( $notice_class ) . ' mhm-limit-notice">';
			echo '<p style="margin: 0; font-size: 14px;">';
			echo '<strong>' . esc_html( $icon ) . ' ' . esc_html__( 'Rentiva Lite Limit', 'mhm-rentiva' ) . ':</strong> ';
			/* translators: 1: current count, 2: max limit, 3: percentage */
			echo esc_html( sprintf( __( 'You have used %1$d out of %2$d vehicles (%3$d%%).', 'mhm-rentiva' ), $current, $max, $percentage ) );
			if ( $exceeded ) {
				echo ' <strong>' . esc_html__( 'Limit reached!', 'mhm-rentiva' ) . '</strong> ';
			}

			printf(
				/* translators: Dynamic value. */
				wp_kses_post( __( '<a href="%s">Enter your license key</a> to upgrade to Pro for unlimited vehicles.', 'mhm-rentiva' ) ),
				esc_url( $license_url )
			);
			echo '</p>';
			echo '</div>';
		} elseif ( $type === 'bookings' && isset( $limits['bookings'] ) ) {
			$current    = $limits['bookings']['current'];
			$max        = $limits['bookings']['max'];
			$percentage = $max > 0 ? round( ( $current / $max ) * 100 ) : 0;
			$exceeded   = $limits['bookings']['exceeded'];

			$notice_class = $exceeded ? 'notice-error' : ( $percentage >= 80 ? 'notice-warning' : 'notice-info' );
			$icon         = $exceeded ? '⚠️' : ( $percentage >= 80 ? '⚠️' : 'ℹ️' );

			echo '<div class="notice ' . esc_attr( $notice_class ) . ' mhm-limit-notice">';
			echo '<p style="margin: 0; font-size: 14px;">';
			echo '<strong>' . esc_html( $icon ) . ' ' . esc_html__( 'Rentiva Lite Limit', 'mhm-rentiva' ) . ':</strong> ';
			/* translators: 1: current count, 2: max limit, 3: percentage */
			echo esc_html( sprintf( __( 'You have used %1$d out of %2$d bookings (%3$d%%).', 'mhm-rentiva' ), $current, $max, $percentage ) );
			if ( $exceeded ) {
				echo ' <strong>' . esc_html__( 'Limit reached!', 'mhm-rentiva' ) . '</strong> ';
			}

			printf(
				/* translators: Dynamic value. */
				wp_kses_post( __( '<a href="%s">Enter your license key</a> to upgrade to Pro for unlimited bookings.', 'mhm-rentiva' ) ),
				esc_url( $license_url )
			);
			echo '</p>';
			echo '</div>';
		} elseif ( $type === 'customers' ) {
			$current    = \MHMRentiva\Admin\Licensing\Restrictions::customerCount();
			$max        = Mode::maxCustomers();
			$percentage = $max > 0 ? round( ( $current / $max ) * 100 ) : 0;
			$exceeded   = $current >= $max;

			$notice_class = $exceeded ? 'notice-error' : ( $percentage >= 80 ? 'notice-warning' : 'notice-info' );
			$icon         = $exceeded ? '⚠️' : ( $percentage >= 80 ? '⚠️' : 'ℹ️' );

			echo '<div class="notice ' . esc_attr( $notice_class ) . ' mhm-limit-notice">';
			echo '<p style="margin: 0; font-size: 14px;">';
			echo '<strong>' . esc_html( $icon ) . ' ' . esc_html__( 'Rentiva Lite Limit', 'mhm-rentiva' ) . ':</strong> ';
			/* translators: 1: current count, 2: max limit, 3: percentage */
			echo esc_html( sprintf( __( 'You have used %1$d out of %2$d customers (%3$d%%).', 'mhm-rentiva' ), $current, $max, $percentage ) );
			if ( $exceeded ) {
				echo ' <strong>' . esc_html__( 'Limit reached!', 'mhm-rentiva' ) . '</strong> ';
			}

			printf(
				/* translators: Dynamic value. */
				wp_kses_post( __( '<a href="%s">Enter your license key</a> to upgrade to Pro for unlimited customers.', 'mhm-rentiva' ) ),
				esc_url( $license_url )
			);
			echo '</p>';
			echo '</div>';
		}
	}

	/**
	 * Display Developer Mode banner and limit notices together
	 *
	 * @param string $limit_type Limit type: 'vehicles', 'bookings', or 'customers'
	 * @param array  $features Optional list of Pro features to highlight
	 */
	public static function displayDeveloperModeAndLimits( string $limit_type = '', array $features = array() ): void {
		// If Pro license is active (not developer mode), don't show any notices
		if ( Mode::isPro() && ! self::isDeveloperMode() ) {
			return;
		}

		// Developer Mode banner (only if developer mode is active)
		self::displayDeveloperModeBanner( $features );

		// Limit notice for Lite users (only if not Pro)
		if ( ! empty( $limit_type ) && Mode::isLite() ) {
			self::displayLimitNotice( $limit_type );
		}
	}
}
